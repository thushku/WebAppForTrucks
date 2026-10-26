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
not yet paid), `advance` (an advance against my pay, taken back from a payslip — M27), `net` (positive: I give this back; negative: the company pays me),
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
| `GET /owner/ledgers/{id}?from=&to=` | `ledger.view` | `opening`, `debit`, `credit`, `closing`, `lines[]` (`date`, `number`, `reference`, `narration`, `debit`, `credit`, `balance`, `bucket`, `cancelled`) for the period (default: the last 90 days); for a crew member also `buckets` (`trip_cash`, `earnings`, `loan`, `net`) |

A person limited to one branch (decision P17) gets only that branch's trips, trucks and bills in these lists, and
`404` for another branch's trip or bill.

Making things from the phone is §12. Still web only: hire challans, credit and debit notes, journals and payments,
settlement.

## 11. Notifications (both apps)

The server tells people things (M20, decision P15): a notice goes to the person's **inbox**
(the same on the web bell and in both apps) and, when the phone has sent its Firebase token
with `POST /device`, as a **push**. Notices are per company: the inbox lists the company in
`X-Company`. A push carries the company too, so the app can switch to it before opening.

| Call | Answer |
|---|---|
| `GET /notifications?before=&limit=30` | `unread` (the badge), `items[]` newest first: `id`, `kind`, `title`, `body`, `data` (what to open, below), `times` (how often a collapsed notice was updated), `read`, `at`; `more` — then ask again with `before` = the last `id` |
| `POST /notifications/read` `{ "ids": [12, 13] }` or `{ "all": true }` | `unread` after marking. Mark a notice read when the person opens it from the list or from the push |
| `GET /me` | also `unread_notifications` |
| `GET /notifications/settings` | `groups[]` (`key`, `name`, `about`, `push`: whether this group reaches the phone), `quiet_from`, `quiet_to` ("22:00"), `digest` (the morning summary, M32: `daily` / `weekly` / `off`; owners `daily` until they choose) and `digests` (value → words) |
| `PUT /notifications/settings` `{ "push": ["trips", "incidents"], "quiet_from": "22:00", "quiet_to": "06:00", "digest": "weekly" }` | the same, saved. Groups left out of `push` stay in the inbox only; in quiet hours only high-priority notices are pushed; `digest` left out stays as it was. Per company |

Every item also has `priority` (`high`, `medium`, `low`): show high ones in red at the top of the unread ones. The
push carries it in `data.priority`; a low one is sent with Android priority `normal` (it does not wake the phone).

**The push message** (FCM HTTP v1): `notification.title` / `notification.body` as in the
inbox, Android `priority: high`, channel id `fleet` (create it in the app; the name "Trips and
approvals"), iOS badge = the unread count. `data` (all strings): `notification` (the inbox id),
`kind`, `company` (the company's id as in `/companies`) and what to open:

| `kind` | Who gets it | `data` → open |
|---|---|---|
| `trip.sent` | the trip's crew | `type: trip`, `trip` (uuid) → the trip; run `GET /crew/sync` first |
| `trip.cancelled` | the trip's crew | `type: trip`, `trip` → the trip list (sync removes it) |
| `entry.second` | the other driver | `type: entry`, `entry`, `trip` → the entry with Approve / Reject (`event.second_approve` / `event.second_reject`) |
| `entry.queried`, `entry.rejected` | who recorded the entry | `type: entry`, `entry`, `trip` → the entry with the office's note; a queried entry can be changed (`PATCH /crew/events/{uuid}`) |
| `pay.paid` | the person paid | `type: money` → My Khata (`GET /crew/money`) |
| `approvals.waiting` | everyone with `event.verify` | `type: approvals` → the approvals list (`GET /approvals`). One notice keeps the count; pushed again only after half an hour |
| `road.incident` | everyone with `trip.view` | `type: trip`, `trip` → the trip |
| `papers.due` | everyone with `document.manage` | `type: papers` → papers (`GET /owner/papers`) |
| `digest.ready` (M32, low) | each person who chose the morning summary (07:00 company time; weekly on Mondays) — also by email | the summary's headline ("Sunday 25 Oct: 2 trips ended · ₹50,000.00 received") → the dashboard (`GET /owner/dashboard`) |
| `test` | one person (`php artisan fleet:push <mobile>`) | `type: test` |

Unknown kinds may come later: show them in the list and open the list. After any push, a crew
app runs `GET /crew/sync` (the push says *that* something changed; sync says *what*).

## 12. Owner app: making things (added in M23; crew cash in M27; masters in M29; quotations and payments in M31)

The same rules and permissions as the web: a trip is planned with its load and sent to the crew, a bill is made from
LRs and issued, money received is set against the oldest bills, a paper is photographed onto a truck or a person.

**Every write carries `Idempotency-Key: <uuid>`** — a new UUID made by the app for each tap (keep it with the queued
request). Sent again with the same key (no answer came, the network dropped), the server answers what it answered the
first time with `"replayed": true` and does not make a second trip, bill or receipt. A refused request (`422`) is not
kept: correct it and send it again with the same key. Without the header: `428`. A key used for another action: `409`.
Answers to a write are `201`.

| Call | Permission | Body → answer |
|---|---|---|
| `GET /owner/masters` | any of `trip.create`, `invoice.create`, `payment.record`, `document.manage`, `trip.settle` | the lists for the forms: `places[]` (`id`, `name`, `short_code`), `clients[]` (`id`, `name`, `short_code`, `mobile`), `trucks[]` (`id`, `reg_no`, `type`, `status`, `busy_on` — the trip number it is on), `crew[]` (`id`, `name`, `type`, `mobile`), `materials[]` (`code`, `name`), `branches[]` (`id`, `name`), `branch_required` (true for a person limited to a branch), `bank_accounts[]` (`id`, `label`), `freight_bases`, `payment_types`, `receipt_modes`, `paper_types` (`truck`, `person`: code → name), and for crew cash (M27) `expense_categories[]` (`code`, `name`), `cash_directions`, `cash_modes`, `cash_purposes` |
| `POST /owner/trips` | `trip.create` (`trip.assign` for `send`) | `trip_date` (default today), `from_place_id`, `to_place_id`, `truck_id`, `driver_id`, `co_driver_id`, `helper_ids[]`, `planned_km`, `branch_id`, `instructions`, `trip_kind` (`loaded` / `empty`, default from `load`), `load` {`billing_party_id`, `consignor_party_id`, `consignee_party_id`, `material`, `packages`, `actual_weight_mt`, `freight_basis`, `rate`, `payment_type`, `gst_payable_by`, `lr_number` (from a printed LR book), `loading_charge`, …}, `send` (true: send to the crew now), `override_reason` (papers expired: why it must go anyway) → `trip` {`uuid`, `number`, `status`, `date`, `from`, `to`, `vehicle`, `branch_id`, `loads[]` (`id`, `lr_number`, `freight`)}. With `send`, a refusal (truck busy, papers) makes nothing at all |
| `POST /owner/trips/{uuid}/send` | `trip.assign` | `override_reason` → `trip` |
| `GET /owner/unbilled?party=` | `invoice.create` | `loads[]` waiting to be billed (To-be-billed LRs): `id`, `lr_number`, `lr_date`, `status`, `party_id`, `party`, `trip_uuid`, `trip`, `route`, `freight` |
| `POST /owner/invoices` | `invoice.create` (`invoice.finalise` for `finalise`) | `party_id`, `loads[]` (ids from `unbilled`), `finalise` → `invoice` as in §10's list (`number` and `share_url` once issued) |
| `POST /owner/invoices/{id}/finalise` | `invoice.finalise` | → `invoice` with its number |
| `POST /owner/receipts` | `payment.record` | `party_id`, `receipt_date`, `amount`, `mode` (`receipt_modes`), `reference` (all but cash), `cheque_date` (cheque), `bank_account_id`, `tds_amount`, `tds_section`, `deduction_amount`, `deduction_note`, `narration` → `receipt` {`id`, `number`, `date`, `amount`, `applied[]` (`invoice`, `amount`) — oldest bills first —, `share_url`} |
| `POST /owner/papers` (multipart) | `document.manage` | `owner_type` (`truck` / `person`), `owner_id`, `document_type` (`paper_types`), `number`, `authority`, `issued_on`, `valid_from`, `expires_on`, `amount`, `notes`, `files[]` (1–6 photos or PDFs, 8 MB each; photos are shrunk on the server) → `paper` {`id`, `type`, `type_label`, `number`, `expires_on`, `files`} |
| `GET /owner/crew/{person_id}/khata` | `trip.settle` | a crew member's khata in short (M27): `person` {`id`, `name`, `type`, `mobile`}, `ledger_id` (for `GET /owner/ledgers/{id}`; null until something is posted), `trip_cash`, `earnings`, `advance`, `net` — as in the ledger, positive is the company's money with them: trip cash they carry, an advance against pay; a negative `earnings` is what the company owes them —, `words` ("Ravi Kumar will give you ₹3,000.00"), `trips[]` their cash can go with (`id`, `number`, `date`, `status`: sent and not settled) |
| `POST /owner/crew/{person_id}/cash` | `trip.settle` | quick cash (M27): `direction` (`received` — given to them / `spent` — spent by them for the company), `amount`, `remark` (what for, required), `on` (date, not after today), `trip_id` (one of `trips`), `mode` (`cash_modes`, for `received`), `purpose` (`advance` — against pay, or `dues` — paying what we owe them; for `received` without a trip), `category` (`expense_categories`, required for `spent`), `reference` (required for UPI and bank), `bank_account_id` → `voucher` {`id`, `number`, `date`, `amount`, `narration`}. Given for a trip it is that trip's advance (counted when the trip is settled); spent, it is owed to them with their pay, tagged with the trip and truck when given |

A person limited to a branch sees and uses only that branch's trucks, crew and trips here (decision P17).

**Masters (added in M29):** clients, places, trucks and crew — a card list (`?q=` search, `?status=active` (default) /
`inactive` / `all`, `?page=`; `next_page` is null on the last page), open one, **add** (`POST`, once per
`Idempotency-Key` like every add above), **edit** (`PUT`: only the fields sent change — send just what was edited;
sending it again is harmless) and **deactivate** (never delete). The choices for the forms are in `GET /owner/masters`
→ `forms` (`truck_types`, `body_types`, `fuel_types`, `ownerships`, `truck_statuses`, `person_types`, `pay_bases`,
`place_types`, `regions`). Every change is in the audit log, as from the web; the rest of each record is on the web's
full form.

| Call | Permission | Body → answer |
|---|---|---|
| `GET /owner/clients` | `party.view` | `clients[]`: `id`, `name`, `short_code`, `mobile`, `email`, `city`, `state`, `tax_id` (the default GSTIN / TRN), `active`, `due` (billed, not paid) |
| `GET /owner/clients/{id}` | `party.view` | `client`: the card and `legal_name`, `address_line1`, `address_line2`, `state_code`, `postal_code`, `credit_days`, `notes`, `tax_ids[]` (`number`, `default`), `roles` |
| `POST /owner/clients` | `party.manage` | `name` (required), `legal_name`, `short_code` (made from the name when left out), `mobile`, `email`, `address_line1`, `address_line2`, `city`, `state_code`, `postal_code`, `credit_days`, `notes`, `tax_id` (GSTIN / TRN, checked; its PAN and state are taken from it) → `client` |
| `PUT /owner/clients/{id}` | `party.manage` | any of those but `tax_id` (tax numbers are changed on the web) → `client` |
| `POST /owner/clients/{id}/active` | `party.manage` | `active` (true / false) → `client` |
| `GET /owner/places` | any of `trip.view`, `trip.create`, `settings.masters` | `places[]`: `id`, `name`, `short_code`, `type`, `type_label`, `address`, `city`, `state_code`, `postal_code`, `lat`, `lng`, `active` |
| `POST /owner/places` | `settings.masters` | `name` (required), `short_code` (made when left out), `type` (`place_types`; a place with an address is a customer site by default, else a city), `address`, `city`, `state_code`, `postal_code`, `lat` and `lng` (both or neither) → `place` |
| `PUT /owner/places/{id}` · `POST …/active` | `settings.masters` | the fields sent · `active` → `place` |
| `GET /owner/trucks/{id}` | `truck.view` | `truck`: `id`, `status`, `status_label`, `reg_no`, `rc_owner_name`, `truck_type`, `body_type`, `vehicle_class`, `make`, `model`, `manufacture_year`, `registration_state`, `registration_date`, `fuel_type`, `ownership`, `owner_party_id`, `payload_mt`, `odometer_km`, `odometer_locked` (typed once; trips move it after), `branch_id`, `notes` (the list is `GET /owner/trucks`, §10) |
| `POST /owner/trucks` | `truck.manage` | `reg_no` and `rc_owner_name` (required; the registration is checked against the fleet), and any of the fields above; the plan's truck limit holds (`422` with the message) → `truck` |
| `PUT /owner/trucks/{id}` | `truck.manage` | the fields sent (`odometer_km` only while none is recorded) → `truck` |
| `POST /owner/trucks/{id}/status` | `truck.manage` | `status`: `idle` (free) / `workshop` / `breakdown` / `out_of_service` / `sold` — refused while it is on a trip → `truck` |
| `GET /owner/crew?type=` | `people.view` | `crew[]`: `id`, `name`, `type`, `type_label`, `mobile`, `status`, `licence_no`, `licence_expiry`, `licence_state`, `truck`, `app_linked`, `papers` (`state` green / amber / red, `expired`, `due`, `missing`; with `document.view`) — `status=working` (default) / `left` / `all` |
| `GET /owner/crew/{id}` | `people.view` | `person`: the card and `alt_mobile`, `employee_code`, `licence_class`, `badge_no`, `default_truck_id`, `branch_id`, `address`, `emergency_name`, `emergency_mobile`, `blood_group`, `notes`, `joining_date`, `leaving_date`, and the pay (`pay_basis`, `monthly_salary`, `per_trip_rate`, `per_km_rate`) for those who manage people or see payroll |
| `POST /owner/crew` | `people.manage` | `name` and `type` (required), and any of the fields above (`mobile` is unique: it is how the crew app signs in) → `person` |
| `PUT /owner/crew/{id}` | `people.manage` | the fields sent. The mobile of someone using the crew app is changed on the web (that ends the old login); the licence comes from the licence paper when one is on file → `person` |
| `POST /owner/crew/{id}/active` | `people.manage` | `active` false (left, `leaving_date` default today; refused while on a trip that has not ended) / true (working again) → `person` |

A person limited to a branch adds trucks and crew only in that branch.

**Quotations and payments (added in M31):**

| Call | Permission | Body → answer |
|---|---|---|
| `GET /owner/quotations?tab=&q=` | `invoice.view` | `tab` `open` (default: drafts and sent) / `accepted` / `closed` (declined, cancelled) / `all`; `quotations[]`: `id`, `number` (null for a draft), `label`, `status` (`draft` / `issued` / `expired` / `accepted` / `declined` / `cancelled`), `status_label`, `party` {`id`, `name`}, `quote_date`, `valid_until`, `subject`, `routes`; `next_page` |
| `GET /owner/quotations/{id}` | `invoice.view` | `quotation`: the card and `terms`, `notes`, `decision_note`, `answered_by_name` (answered on the client's link), `viewed_at`, `issued_at`, `lines[]` (`from`, `to` {`id`, `name`, `short_code`}, `material`, `truck_type`, `basis`, `rate`, `min_weight_mt`, `detention_free_hours`, `detention_rate_per_day`, `loading_charge`, `unloading_charge`, `note`), `share_url` and `whatsapp_text` (once sent: the client's link and the words to send — open WhatsApp to `party_mobile` with them) |
| `POST /owner/quotations` | `invoice.create` | `party_id`, `quote_date` (default today), `valid_until` (default 15 days on), `subject`, `terms` (the usual terms when left out), `notes`, `lines[]` (each: `from_place_id`, `to_place_id`, `rate` required, and the fields above; `basis` `trip` by default), `issue` (true: send it now — a refusal makes nothing) → `quotation` |
| `PUT /owner/quotations/{id}` | `invoice.create` | a draft saved again as a whole (its routes become those sent); a sent quotation is never changed — copy it → `quotation` |
| `POST /owner/quotations/{id}/issue` | `invoice.create` | → `quotation` with its `QT/` number, `share_url`, `whatsapp_text` |
| `POST /owner/quotations/{id}/decide` | `invoice.create` | `decision` `accepted` / `declined` / `cancelled` (cancelled needs `note`, why), `note` (how it was accepted: "PO 4512") → `quotation` |
| `POST /owner/quotations/{id}/copy` | `invoice.create` | once per key → a new draft `quotation` with the same routes |
| `GET /owner/payees?q=` | `accounts.post` | `payees[]` (up to 200): `id` (the ledger), `name`, `kind` (`party` / `person` / `general`), `group`, `balance` (positive: they owe us; negative: we owe them). Suppliers, vendors, brokers and the working crew are there before anything is posted to them |
| `POST /owner/payments` | `accounts.post` | once per key: `ledger_account_id` (a payee), `amount`, `paid_on` (default today, not ahead), `mode` (`cash` / `bank`), `bank_account_id`, `reference` (required for bank: UTR, cheque no.), `narration` (what for, required) → `voucher` {`id`, `number` (`PV/…`), `date`, `amount`, `narration`} — paid to a crew member, it clears their earnings |


## 13. My profile, the company's business details, Help (added in M28)

The person's own profile is the same for both apps and every company (no `X-Company` needed); it keeps working when
the company is read-only. The email and mobile are how they sign in: shown, not changed here. Saving is a plain
multipart `POST` (for the photo) — sending the same thing twice is harmless, so no `Idempotency-Key`.

| Call | Permission | Body → answer |
|---|---|---|
| `GET /profile` | signed in | `name`, `initials`, `email`, `email_verified`, `mobile`, `mobile_verified`, `locale`, `languages` (code → its own name, the ones the provider enabled), `date_of_birth`, `photo_url` (null without a photo; the URL changes when the photo does, so it can be cached) |
| `POST /profile` (multipart) | signed in | `name` (required), `locale`, `date_of_birth` (Y-m-d, 14 years ago or earlier), `photo` (JPG / PNG / WebP up to 8 MB; the server keeps the middle square, 512 px, camera data dropped — crop on the phone first if the person chose a square), `remove_photo` → the profile |
| `GET /profile/photo` | signed in | the person's own photo (JPEG) |
| `GET /help` | signed in | the provider's support contacts (console → Settings): `brand`, `email`, `phone`, `whatsapp` (digits) and `whatsapp_url` (a chat with a first line), `faq_url`, `contact_url`, `terms_url`, `privacy_url`, `version` — a missing contact is null; show only those there |
| `GET /owner/company` | office member | the company as it prints: `can_edit`, `name`, `legal_name`, `short_code`, `address_line1`, `address_line2`, `city`, `state_code`, `state`, `postal_code`, `phone`, `email`, `website`, `country`, `currency`, `timezone`, `logo_url`, `tax_ids[]` (`regime`, `number`, `state_code`, `default`; edited on the web, Settings → Tax), `regions` (code → name, for the picker) and `regions_label` (State / Emirate) |
| `POST /owner/company` (multipart) | `settings.company` | the fields above (`name` required; Indian PIN codes have 6 digits) and `logo` (PNG / JPG / WebP up to 300 KB, 2000 px), `remove_logo` → the company |
| `GET /owner/company/logo` | office member | the logo image |

`GET /me` → `account.photo_url` as well, for the side drawer.
