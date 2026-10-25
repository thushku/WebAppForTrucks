# Sync design for the phone apps

The crew app must never lose an entry and never send one twice, on a cheap phone, in
the ghats, at 2 am. The owner app reads more than it writes and needs less of this, but
uses the same building blocks (shared code in `phoneapp/shared/`).

## 1. The phone's database (SQLite)

| Table | Holds | Notes |
|---|---|---|
| `meta` | key / value: `server_url`, `last_sync_server_time`, `last_full_sync_at`, `boot_count`, `app_version` | |
| `session` | `token` (encrypted with the Android Keystore), `expires_at`, `account_name`, `mobile`, `has_pin`, current `company_uuid` | one row |
| `companies` | `uuid`, `name`, `country`, `currency`, `timezone`, `person_type` | from the sign-in answer and `GET /companies` |
| `lists` | `kind` (event_types, payment_modes, commands), `json` | full sync only |
| `trips` | `uuid`, `company_uuid`, `number`, `status`, `json` (the whole trip from sync) | replaced on each sync |
| `server_events` | `uuid`, `trip_uuid`, `status`, `json` | statuses and office notes from sync |
| `outbox` | `id` (auto), `company_uuid`, `kind` (event / command / edit / cancel), `uuid`, `payload` (JSON as it will be sent), `made_uptime_ms`, `made_boot`, `state` (waiting / sending / done / refused / needs_fix), `attempts`, `next_try_at`, `last_error`, `created_at` | the one queue for everything that changes data |
| `photos` | `id`, `company_uuid`, `entry_uuid`, `slot`, `file_path`, `bytes`, `taken_at`, `state` (waiting / done / refused), `attempts`, `next_try_at` | the file stays on the phone until the server answers |
| `gps` | `id`, `company_uuid`, `t`, `lat`, `lng`, `acc`, `spd`, `hdg`, `mock`, `batch_uuid` (null until put in a batch) | |
| `papers` | `id`, `name`, `number`, `expires_on`, `state`, `files` (JSON with local file paths) | for checkposts without signal |

Screens read from these tables only. A screen shows the server's status for an entry
when `server_events` has it, else "on phone ⟳".

## 2. Making something

1. The tap builds the payload with a new **UUID v4**, the phone time (`event_at`), the
   uptime counter (`SystemClock.elapsedRealtime()` → `uptime_ms`), the last GPS fix
   (`lat`, `lng`, `accuracy_m`, `gps_time`, `mock`), `device_id` and `app_version`.
2. It is written to `outbox` (and its photos to `photos`) **in one SQLite transaction**,
   then the screen says "Saved on phone ✓". Nothing else is needed for the entry to be safe.
3. The sync worker is woken.

## 3. Sending

One worker, one item at a time, in this order:

1. `outbox` items with `state = waiting` whose `next_try_at` has passed, **oldest first**,
   per company (each request carries that company's `X-Company`). Commands and entries
   share the queue, so `trip.start` always goes before the fuel entered after it.
   Entries go in batches of up to 100 (`POST /crew/events`), commands up to 50
   (`POST /crew/commands`); add `sent_uptime_ms` to each batch.
2. Photos whose entry is `done` (answered `saved` or `duplicate`), one per request.
3. GPS: take up to 500 unsent points, give them a `batch_uuid` **before** the first try,
   and resend the same batch with the same UUID until it is answered.
4. Then `GET /crew/sync?since=<last_sync_server_time>`.

### Answers

| Answer | What the phone does |
|---|---|
| entry `saved` / `duplicate`, command `200` or `replayed: true`, photo `201` / `200` | item `done` |
| entry `rejected` (with `errors`) | `needs_fix`: show it in **Waiting to send** with the errors; the person edits it, which makes a new payload under the same UUID |
| command `422` / `409` | `refused`: show the message once; it never goes again (a new tap makes a new command) |
| `401` | the token is gone: go to sign-in, **keep the queue**; send it after the same person signs in again |
| `403 company_forbidden` | the person left that company: show it, keep the items, ask the office |
| `409 company_required` | send `X-Company` (a bug: every request must carry it) |
| `423 read_only` | the company's plan has ended: stop sending, keep everything, show the message once; check `GET /me` hourly and at start |
| `429`, `5xx`, time-out, no network | try again later: 30 s, 1 min, 2 min, 5 min, then every 15 min (±20 % so phones do not all retry together) |

An item is never deleted before it is `done` or `refused`. `done` rows are cleaned after
7 days; photo files after the trip is settled (sync shows `settlement`).

## 4. Time

- The server rebuilds an entry's true time from `uptime_ms` and `sent_uptime_ms`, which
  the user cannot change, and flags a phone clock more than 5 minutes off.
- After a **restart** the uptime counter starts again. Keep `made_boot` (a boot counter
  increased when the app sees uptime smaller than the last one it stored, or from
  `Settings.Global.BOOT_COUNT`). For an item made before the current boot, leave
  `uptime_ms` out of the payload and send `gps_time` instead.
- Show times in the company's time zone (`companies.timezone`), not the phone's.

## 5. GPS

- A **location foreground service** with a permanent notification ("Trip … · Tracking
  ON"), started by **Duty on** or **Start trip** while a screen is open (Android 14+
  requires this), stopped by **Duty off** or **End trip**.
- Every 30–60 s while moving, every 5–10 min when parked. Skip fixes worse than 100 m.
  Mark mock locations (`mock: true`); the server flags them.
- Uploads happen inside the location service or as short jobs — never a `dataSync`
  service (Android 15 limits it to 6 hours a day).
- After a reboot, show a notification "Tap to resume trip tracking" (no "all the time"
  location permission in version 1).

## 6. Pulling

- `GET /crew/sync` after every send round, every 5 minutes while the app is open, and
  when a push notification says something changed.
- A full sync (no `since`) after sign-in, after a company switch and once a day; it
  replaces `trips`, `lists` and `papers`.
- The server's status wins for an entry, except while the phone still has an edit or
  cancel for it in `outbox` (show "change on phone ⟳").
- Paper files are downloaded once and kept, so they open without signal.

## 7. Sign-in, PIN, locking

- The token lives encrypted in the Keystore. Call `POST /auth/refresh` when it expires
  within 30 days; the old token stops at once, so store the new one before anything else.
- The PIN is checked on the phone (keep a salted hash) and set on the server too
  (`POST /auth/pin`), because the server checks the incoming driver's PIN on a driver
  change. Fingerprint/face only unlocks the stored PIN.
- Lock after `settings.app_lock_minutes` in the background; clear screens and in-memory
  data on lock. GPS keeps running while locked.
- **One person per phone** in version 1: entries belong to the person signed in. A
  co-driver records on their own phone; a driver change on this phone carries the
  incoming person's PIN.

## 8. Several companies

A crew member who works for two owners has one sign-in and one phone. Everything in
the phone's tables carries `company_uuid`; Home shows the current company with a
switcher; a notification opens its company. The queue sends each item with its own
company's header, whichever company is on screen.

## 9. Forward compatibility

Ignore fields you do not know; never fail on a new `status` or `type` — show it as
"Other". New screens use new endpoints; version 1's answers keep their fields.
