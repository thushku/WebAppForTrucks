# Phone API, version 1

For the phone-app session (B4A crew app and owner app, decision D3). Everything
here is tested in `webapp/tests/Feature/PhoneApiTest.php`; when in doubt, that
file shows the exact requests.

Base URL: `https://<server>/api/v1`. All requests and answers are JSON (photos are
`multipart/form-data`). Times are ISO 8601 with offset (`2026-10-25T09:00:00+05:30`);
the server answers in UTC.

## 1. Headers

| Header | When | Value |
|---|---|---|
| `Accept` | always | `application/json` |
| `Authorization` | after sign-in | `Bearer <token>` |
| `X-Company` | after sign-in, when the login has more than one company | the company `id` from the sign-in answer |

Errors are JSON: `{"message": "…", "code": "…"}` and, for form errors (422),
`{"message": "…", "errors": {"field": ["…"]}}`. Show `message` to the user as it is.
`401` means the token is gone (signed out elsewhere, expired): go to the sign-in screen.
`409` with `code: company_required`: show the company switcher.

## 2. Signing in

### Crew: activation code (no SMS)

The office opens the person in the web app and presses **Activate app**; it shows an
8-digit code and a QR code `fleet-activate:<mobile>:<code>` (valid 72 hours, once).

`POST /auth/activate`

```json
{ "mobile": "+919847011111", "code": "48213907",
  "device_id": "android-<random made on first run>", "platform": "android",
  "model": "SM-A105", "app_version": "1.0.0" }
```

The mobile may be typed locally (`98470 11111`); the server tries India and UAE.
Answer:

```json
{ "token": "12|Xk…", "expires_at": "2027-01-23T09:00:00+00:00",
  "account": { "name": "Ravi Kumar", "mobile": "+919847011111", "locale": "en", "has_pin": false },
  "companies": [ { "id": "019a…", "name": "Sharma Roadlines", "country": "IN", "crew": true, "person_type": "driver" } ],
  "company": "019a…" }
```

A wrong, used or expired code: `422 code: bad_code`. Eight wrong codes for a number
in an hour: `429`.

### Owner and office: code by SMS / WhatsApp (D21)

1. `POST /auth/otp` `{ "mobile": "9847099999", "channel": "sms" }` → always
   `{ "ok": true, "expires_in": 300 }` (the same answer for unknown numbers).
2. `POST /auth/otp/verify` `{ "mobile": "…", "code": "123456", "device_id": "…", "platform": "ios" }`
   → the same answer as activation (`crew` is false for office people).

### After sign-in

| Call | What |
|---|---|
| `GET /companies` | the company list again (switcher) |
| `POST /auth/pin` `{ "pin": "4826", "current_pin": "…" }` | set the app PIN (4–6 digits; `current_pin` only when one is set). Easy PINs (1234, 0000…) are refused |
| `POST /auth/refresh` | a new token; the old one stops at once. Call it when `expires_at` is less than 30 days away |
| `POST /auth/logout` | the token stops; the device is marked signed out |
| `POST /device` `{ "push_token": "…", "app_version": "1.0.1" }` | after sign-in and whenever Firebase gives a new token |
| `GET /me` | name, company, permission codes |
| `GET /ping` (no token) | server time, for the clock check before sign-in |

## 3. Sync (read)

`GET /crew/sync?since=<server_time of the last sync>` — leave `since` out for a full
sync (first start, after sign-in, once a day). Keep `server_time` from the answer for
the next `since`.

| Key | What |
|---|---|
| `person` | `id`, `name`, `type`, `can_record` (false: hide the entry buttons) |
| `company` | `id`, `name`, `country`, `currency`, `timezone` |
| `settings` | `dual_approval_enabled`, `single_driver_approver` (manager / none), `helpers_record_events`, `driver_change_photo`, `fuel_photos_required`, `app_lock_minutes`, `stop_prompt_minutes`, `freight_visible_to_crew`, `max_continuous_driving_minutes`, `max_daily_driving_minutes` |
| `duty` | `{ "on": true, "since": "…" }` |
| `driving` | who holds the wheel now: `{ "trip", "person_id", "since" }` or null |
| `trips[]` | sent, accepted, running, and ended in the last 15 days. Each: `uuid`, `number`, `status`, `date`, `my_role`, `accepted`, `rejected`, `vehicle`, `truck` (reg no, tank, odometer), `from`, `to`, `planned_km`, `instructions`, `budget`, `crew[]`, `stops[]` (`id`, `seq`, `kind`, `place`, `code`, `lat`, `lng`, `arrived_at`, `departed_at`), `loads[]` (`id`, `lr_number`, consignor and consignee with mobile, `pickup_stop_id`, `drop_stop_id`, goods, `seal_no`, `payment_type`, `freight` — null unless the owner allows it or the load is "to pay", `ewaybills[]`) |
| `events[]` | entries on those trips (all crew members'), changed since `since`: `uuid`, `type`, `amount`, `status`, `second_by`, `second_reject_reason`, `office_note` (the office's query or reject reason), `photos` (slots received), `photos_expected` |
| `waiting_for_me[]` | payments without receipt by the other driver, waiting for my yes or no |
| `papers[]` | the truck's papers and my own (licence): `name`, `number`, `expires_on`, `state` (valid / due / expired / lifelong), `files[]` |
| `lists` | full sync only: `event_types[]` (`code`, `label`, `approval`, `photos`, `amount`), `payment_modes[]` (`code`, `label`, `own_pocket`), `commands[]` |

A paper's file: `GET /crew/papers/{id}/files/{file_id}` (image or PDF, for showing at a
checkpost).

## 4. Entries (push)

Every entry is made on the phone with a **UUID** and kept in the phone's queue until the
server answers `saved` or `duplicate`. Sending the same entry again is always safe.

`POST /crew/events`

```json
{ "sent_uptime_ms": 86400123,
  "events": [
    { "uuid": "5f0c…", "trip": "<trip uuid>", "type": "fuel",
      "amount": 19000, "payment_mode": "cash", "odometer": 100400,
      "event_at": "2026-10-25T14:32:10+05:30", "recorded_at": "2026-10-25T14:33:02+05:30",
      "uptime_ms": 86000456, "gps_time": "2026-10-25T09:02:09Z",
      "lat": 10.1076, "lng": 76.3516, "accuracy_m": 12, "mock": false,
      "device_id": "android-…", "app_version": "1.0.0",
      "photos": ["odometer", "pump_meter", "receipt"],
      "data": { "litres": 200, "rate": 95, "full_tank": true, "pump": "HP Aluva" },
      "note": "" } ] }
```

- `event_at`: the phone clock at the moment of the event. `uptime_ms`: the phone's
  uptime counter then (Android `SystemClock.elapsedRealtime()`); `sent_uptime_ms`: the
  same counter when the batch is sent. The server rebuilds the true time from these
  (the user cannot change uptime) and flags a phone clock more than 5 minutes off.
  After a restart, old entries have no usable uptime: send `gps_time` if you have it.
- `photos`: the slots the phone **will** send for this entry (so the office sees
  "1 of 3 photos" while they are still uploading).
- Up to 100 entries per batch, oldest first. The answer has one result per entry:

```json
{ "results": [ { "uuid": "5f0c…", "result": "saved", "id": 41, "status": "waiting_check", "flags": [] } ] }
```

`result`: `saved`, `duplicate` (already had it: drop it from the queue) or `rejected`
(with `errors`: show them, keep the entry for the user to fix — never resend unchanged).

### Types sent as entries

| `type` | Needs | `data` fields |
|---|---|---|
| `fuel` | amount, payment_mode | `litres`*, `rate`, `full_tank`, `adblue_litres`, `pump`, `vat_amount`, `supplier_trn` |
| `toll` | amount, payment_mode | `plaza` |
| `weighbridge` | — | `gross_kg`*, `tare_kg`, `net_kg`, `slip_no` |
| `fine_receipt` | amount, payment_mode | `authority`* (police / rto / checkpost / municipality / other), `receipt_no`*, `reason` |
| `no_receipt` | amount, payment_mode | `authority`*, `reason`*, `place` — goes to the other driver (D5) |
| `loading`, `parking`, `food`, `other_expense` | amount, payment_mode | `what` (loading: `labour_count`, `paid_to`) |
| `repair` | amount, payment_mode | `what`*, `workshop` — also opens a truck complaint |
| `money_received` | amount, payment_mode | `from`* (office / party / other), `reference` |
| `rest_start`, `rest_end`, `note` | — | — (rests are taken out of driving hours) |
| `incident` | — | `kind`* (breakdown / accident / theft / police_case / other), `description`* |

\* required. Payment modes: `cash`, `upi` (own phone), `fuel_card`, `fastag`, `credit`,
`party_paid`, `company_paid`; only cash and UPI count as the crew member's own money in
settlement.

### Status of an entry

`info` (recorded, nothing to approve) · `waiting_2nd` (waiting for the other driver,
or the manager on a one-driver trip) · `waiting_check` (the office checks it) ·
`approved` · `queried` (the office asks; the person edits it) · `rejected` · `disputed`
(the second driver said no) · `cancelled`.

Small tolls may come back `approved` at once (owner's rule).

### Change or cancel one's own entry

Until the office has checked it (status info, waiting_2nd, waiting_check, queried,
disputed):

- `PATCH /crew/events/{uuid}` `{ "amount": 195, "payment_mode": "…", "odometer": …, "note": "…", "data": {…} }`
  — the old values are kept for the office. A payment without receipt goes back to the
  second driver.
- `POST /crew/events/{uuid}/cancel` `{ "reason": "Entered twice" }`

### Photos

`POST /crew/events/{uuid}/photos` (multipart): `slot`, `photo` (JPG/PNG/WebP, ≤ 8 MB —
shrink to ~1600 px first to save data), `taken_at`.

Slots: `odometer`, `pump_meter`, `receipt`, `fuel_gauge`, `slip`, `pod`, `damage`,
`location`, `loaded`, `other`. Answer `201 saved`, or `200 duplicate` (same photo again)
or `200 replaced` (a new photo for the same slot). A photo used on another entry before
is accepted but flagged for the office.

## 5. GPS

`POST /crew/gps`

```json
{ "batch": "<uuid made for this batch>",
  "points": [ { "t": "2026-10-25T09:00:30Z", "lat": 9.98, "lng": 76.30, "acc": 12, "spd": 48.5, "hdg": 270, "mock": false } ] }
```

Up to 500 points, any order. The same `batch` again is stored once (`replayed: true`).
Answer `{ "kept": 498, "dropped": 2, "last_at": "…" }` — points in the future, older than
7 days, at 0,0 or a jump faster than 200 km/h are dropped. Send every 30–60 s while
moving, every 5–10 min when parked, only while duty is on or a trip runs.

## 6. Commands

The taps that change a trip. Each has a UUID; the first answer is kept, and the same
UUID again gets the same answer (`replayed: true`) without doing anything twice. A
refused command is kept as refused: show the message; to try again make a new command.

`POST /crew/commands`

```json
{ "sent_uptime_ms": 86400123,
  "commands": [ { "uuid": "…", "name": "trip.start", "trip": "<trip uuid>",
                  "event": { "event_at": "…", "uptime_ms": 86000456, "lat": 9.93, "lng": 76.26,
                             "odometer": 100250, "photos": ["odometer", "fuel_gauge"],
                             "data": { "fuel_gauge": "1/2" } } } ] }
```

Answer per command: `{ "uuid", "name", "status": 200 | 422 | 409, "message"?, … }`.
Commands marked "event" also save a road entry under the command's UUID — send its
photos to `/crew/events/<command uuid>/photos`.

| `name` | Who | Fields | Does |
|---|---|---|---|
| `trip.accept` | crew | `trip` | the trip is accepted when its driver says yes |
| `trip.reject` | crew | `trip`, `reason` | back to the office to re-plan |
| `trip.start` | driver, co-driver | `trip`, event (`odometer`; `data.fuel_gauge`, `data.fuel_litres`, `data.checklist`) | trip running; the starter holds the wheel |
| `trip.end` | driver, co-driver | `trip`, `early`, event (`odometer`) | trip ended; loads still on board are delivered |
| `stop.reached` / `stop.left` | recorders | `trip`, event `data.stop_id` | arrival / departure time of the stop (first report counts) |
| `load.loaded` | recorders | `trip`, event `data.consignment_id`, `packages`, `weight_mt`, `seal_no` | load in transit |
| `load.delivered` | recorders | `trip`, event `data.consignment_id`, `stop_id`, `packages`, `weight_mt`, `short_packages`, `damaged_packages`, `remarks`, `receiver_name`*, `receiver_mobile`, `otp_verified`, `unloading_started_at`, `unloading_ended_at`, `partial`; photo slot `pod` | e-POD; the POD photo marks the POD received |
| `driver.change` | driver, co-driver | `trip`, `pin` (the incoming person's PIN, when they are not the one holding this phone), event `data.incoming_person_id`, `odometer` (photo `odometer` when the owner requires it) | closes the last driving shift, opens the new one |
| `event.second_approve` | drivers | `event_uuid` | the other driver agrees with a payment without receipt |
| `event.second_reject` | drivers | `event_uuid`, `reason` | disputed |
| `duty.start` / `duty.stop` | crew | `at`, `uptime_ms` | location sharing on / off |
| `complaint.raise` | crew | `trip`, `title`, `description`, `priority` (low / normal / high / urgent), `odometer` | a truck complaint for the workshop |

"Recorders" = drivers, and helpers when the owner allows it (`settings.helpers_record_events`).

## 7. Offline rules for the app

1. Save every tap locally first (with its UUID, phone time, uptime, GPS), then send.
2. Send in order: commands and entries in one queue, oldest first; photos after their
   entry was answered `saved`/`duplicate`; GPS in its own queue.
3. On `saved`, `duplicate`, `replayed` or a 4xx answer: the item is done (show 4xx
   messages). On no network, 5xx or 429: keep it and retry with back-off.
4. After a push, run a sync with `since` to pick up statuses and office notes.
