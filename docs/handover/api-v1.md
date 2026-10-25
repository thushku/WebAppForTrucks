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
`423` with `code: read_only`: the company's plan has ended; nothing is lost (see §8).

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
| `GET /me` | name, company (with `status`, `read_only`, `trial_ends_at`), permission codes, and `announcement` (a line from the provider, or null — show it as a banner) |
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
| `trips[]` | sent, accepted, running, and ended in the last 15 days (settled or not). Each: `uuid`, `number`, `status`, `date`, `my_role`, `accepted`, `rejected`, `vehicle`, `truck` (reg no, tank, odometer), `from`, `to`, `planned_km`, `instructions`, `budget`, `crew[]`, `stops[]` (`id`, `seq`, `kind`, `place`, `code`, `lat`, `lng`, `arrived_at`, `departed_at`), `loads[]` (`id`, `lr_number`, consignor and consignee with mobile, `pickup_stop_id`, `drop_stop_id`, goods, `seal_no`, `payment_type`, `freight` — null unless the owner allows it or the load is "to pay", `ewaybills[]`), `settlement` (mine, once the office has settled the trip: `advances`, `collected`, `spent`, `balance` — positive: I return it, negative: the company pays me — `mode`, `mode_label`, `bata`, `settled_at`; null before) |
| `events[]` | entries on those trips (all crew members'), changed since `since`: `uuid`, `type`, `amount`, `status`, `second_by`, `second_reject_reason`, `office_note` (the office's query or reject reason), `photos` (slots received), `photos_expected` |
| `waiting_for_me[]` | payments without receipt by the other driver, waiting for my yes or no |
| `papers[]` | the truck's papers and my own (licence): `name`, `number`, `expires_on`, `state` (valid / due / expired / lifelong), `files[]` |
| `lists` | full sync only: `event_types[]` (`code`, `label`, `approval`, `photos`, `amount`), `payment_modes[]` (`code`, `label`, `own_pocket`), `commands[]` |

A paper's file: `GET /crew/papers/{id}/files/{file_id}` (image or PDF, for showing at a
checkpost).

**My Khata** (the Money tab): `GET /crew/money?from=&to=` (default: the last 90 days) →
`currency`, `holding` (the company's trip cash I carry), `owed_to_me` (bata, salary
not yet paid), `net` (positive: I give this back; negative: the company pays me),
`opening`, `closing`, `lines[]` (`date`, `what`, `reference`, `bucket` — trip_cash /
earnings, `given` — money handed to me, `credited` — what counts for me: spent for the
company, earned, returned, `balance`, `cancelled`), and `payslips[]` of approved pay runs
(`period`, `from`, `to`, `basis`, `units`, `base`, `additions`, `deductions`, `lines[]`, `net`,
`status` pending / paid, `paid_amount`, `paid_on`, `paid_mode`). From the books, so it moves
when the office approves entries, settles a trip, approves a pay run or pays. Office logins
get `403 not_crew`.

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

## 7. Owner app: approvals

For office people with the permission `event.verify` (owner, manager).

`GET /approvals?status=waiting_check` (or `waiting_2nd`, `queried`, `disputed`; leave it out
for all four):

```json
{ "counts": { "waiting_check": 5, "waiting_2nd": 1 },
  "events": [ { "uuid": "…", "trip": { "uuid": "…", "number": "261025-ACC-COK-BOM-01" }, "by": "Ravi Kumar",
                "type": "fuel", "label": "Fuel", "amount": 9500, "payment_mode": "cash", "odometer": 100300,
                "data": { "litres": 100, "rate": 95 }, "at": "…", "lat": 10.1, "lng": 76.3, "status": "waiting_check",
                "question": null, "second_reject_reason": null,
                "flags": [ { "code": "cash_limit", "label": "Cash to one payee in a day above the limit" } ],
                "photos": [ { "slot": "receipt", "url": "https://…/api/v1/approvals/<uuid>/photos/12", "same_as_before": false } ],
                "photos_expected": 3, "actions": ["approve", "query", "reject"] } ] }
```

`POST /approvals/{uuid}` `{ "decision": "approve" | "query" | "reject", "note": "…" }` — a
note is required to ask or reject; the crew see it (`office_note` in their sync). Only the
decisions in `actions` are allowed. On a one-driver trip, `approve` on a `waiting_2nd`
entry is the manager's second approval and check together. Photo URLs need the same
`Authorization` and `X-Company` headers.

The crew answer a question by editing the entry (`PATCH /crew/events/{uuid}`, the `note`
is their answer); it goes back to `waiting_check`. Once the trip is settled, the crew's
entries are locked (422).

## 8. Offline rules for the app

1. Save every tap locally first (with its UUID, phone time, uptime, GPS), then send.
2. Send in order: commands and entries in one queue, oldest first; photos after their
   entry was answered `saved`/`duplicate`; GPS in its own queue.
3. On `saved`, `duplicate`, `replayed` or a 4xx answer: the item is done (show 4xx
   messages). On no network, 5xx or 429: keep it and retry with back-off.
   **Except `423` with `code: read_only`** (the company's plan has ended): keep the
   whole queue, show the message once, and stop sending until `GET /me` says
   `read_only: false` (check at app start and every hour). Reads still work.
4. After a push, run a sync with `since` to pick up statuses and office notes.

## 9. Owner app: signing the web in by QR

The web sign-in page shows a QR code (decision P7). The owner app scans it and sends the
whole scanned text as `code` (a URL ending in `/qr/<40 characters>`, or just the 40
characters).

- `POST /web-login/inspect` `{ "code": "…" }` → `{ "browser": "Chrome on Windows", "ip": "…", "created_at": "…" }`
  — show "Sign in on Chrome on Windows?" with Yes / No.
- `POST /web-login/approve` `{ "code": "…" }` → `{ "ok": true, "message": "The browser is signing in to Sharma Roadlines." }`
- Both answer `422` with `code`: `expired` (ask to refresh the page), `used`, `unknown`.
- The browser signs in to the company in `X-Company`. Works while the company is read-only.

## 10. Owner app: dashboard, map, trips, trucks, papers, bills, ledgers

For office people (owner, manager, accountant, workshop, viewer). Every call needs the
permission shown; without it the answer is `403` — hide that tile or tab (the codes are in
`GET /me` → `permissions`). Amounts are numbers in the company's currency, dates
`YYYY-MM-DD`, times ISO 8601. Lists of trips and bills come 30 at a time: `?page=2` while
`more` is true.

| Call | Permission | Answer |
|---|---|---|
| `GET /owner/dashboard` | `dashboard.view` | `company` (`name`, `currency`, `read_only`), `counts` (`trucks`: total, on_trip, idle, workshop; `trips`: running, planned, to_settle; `approvals`: waiting_check, waiting_2nd, disputed, queried), `profit` (this month: `income`, `expense`, `profit`, `last` — the same days of last month — and `change` in %; **null** without money permissions), `spending_today[]` (`label`, `amount`), `queues` (`pod_pending`, `ready_to_bill`, `not_submitted`, `overdue`, `overdue_amount` — null without invoice/LR permissions), `alerts[]` (`level`: critical / serious / warning, `text`), `announcement` |
| `GET /owner/map` | `map.view` | `positions[]`: `trip_uuid`, `trip` (number), `vehicle`, `driver`, `mobile`, `from`, `to`, `lat`, `lng` (null: no point yet), `speed`, `at`, `minutes` (since the last point), `quiet` (no point yet, or longer than the company allows) |
| `GET /owner/trips?status=&q=&page=` | `trip.view` | `trips[]` (`uuid`, `number`, `date`, `status`, `status_label`, `load_type`, `vehicle`, `hired`, `from`, `to`, `driver`, `started_at`, `ended_at`), `page`, `more`. `status`: planned, running, ended, settled, closed, cancelled; leave it out for all but drafts. `q`: trip number or vehicle |
| `GET /owner/trips/{uuid}` | `trip.view` | the list fields plus `instructions`, `planned_km`, `start_odometer`, `end_odometer`, `stops[]` (`seq`, `place`, `kind`, `arrived_at`, `departed_at`), `loads[]` (`lr_number`, `status`, `billing_party`, `consignor`, `consignee`, `packages`, `weight_mt`, `freight` — null without `trip.freight_view`), `crew[]` (`name`, `mobile`, `role`, `answer`: waiting / accepted / rejected), `entries` (`count`, `to_check`, `spent`), `profit` (income, expense, profit from the books, or null), `route_url` |
| `GET /owner/trips/{uuid}/route` | `map.view` | `points[]` as `[lat, lng, "time", speed]` (at most 1,500, thinned, the last one kept), `total` (points before thinning), `events[]` (`label`, `lat`, `lng`, `amount` as formatted text, `at`, `status`) — draw the track and the entries on it |
| `GET /owner/trucks` | `truck.view` | `trucks[]`: `id`, `reg_no`, `type`, `status`, `status_label`, `papers_expired`, `next_expiry` (`type`, `on`, `days_left`) |
| `GET /owner/papers?days=30` | `document.view` | papers expired or expiring within `days` (1–365), soonest first: `id`, `type`, `type_label`, `number`, `owner_type` (truck / person / party / company), `owner` (reg no or name), `expires_on`, `days_left` (negative: expired), `state`, `files[]` (`id`, `name`, `mime`, `url`). ID papers (Aadhaar, PAN…) only with `people.id_documents` |
| `GET /owner/papers/{id}/files/{file}` | `document.view` | the image or PDF. Opening an ID paper is logged |
| `GET /owner/invoices?tab=open&q=&page=` | `invoice.view` | `invoices[]`: `id`, `number`, `type`, `type_label`, `status`, `party`, `party_mobile`, `issue_date`, `due_date`, `overdue_days`, `currency`, `total`, `balance_due`, `share_url` (the client's secure page), `pdf_url`. `tab`: open, overdue, paid, all |
| `GET /owner/invoices/{id}` | `invoice.view` | the row plus `subtotal`, `tax`, `tax_reverse_charge`, `paid`, `lines[]` (`description`, `quantity`, `rate`, `tax`, `amount`) |
| `POST /owner/invoices/{id}/shared` `{ "channel": "whatsapp" \| "link", "recipient": "98470…" }` | `invoice.send` | `status` (the bill becomes `sent`), `text` (ready to share), `whatsapp_url` (`https://wa.me/<number>?text=…`, null without a number). Open `whatsapp_url`, or share `text` with Android's share sheet, then call this so the office sees it was sent |
| `GET /owner/ledgers?kind=` | `ledger.view` | `ledgers[]` with a balance today: `id`, `name`, `kind` (party, person, bank, cash, general, tax), `group`, `nature`, `balance` (positive: a debit — they owe us; negative: we owe them) |
| `GET /owner/ledgers/{id}?from=&to=` | `ledger.view` | `opening`, `debit`, `credit`, `closing`, `lines[]` (`date`, `number`, `reference`, `narration`, `debit`, `credit`, `balance`, `bucket`, `cancelled`) for the period (default: the last 90 days); for a crew member also `buckets` (trip cash, earnings, loan) |

Not in version 1 (use the web app for now): making an LR, a bill or a receipt from the
phone, hire challans, uploading a paper by camera, creating a trip. They come in API v2
with the same rules as the web (finalised bills are never edited; every money write is
one transaction).
