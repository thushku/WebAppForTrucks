# Screens of the two apps

Each screen: what it is for, what it shows, what it sends, and the API it uses
(sections of `api-v1.md`). Sketches and design rules: `docs/blueprint/05-mobile-app-ux.md`.
"Phone" means the phone's own database (see `sync-design.md`): screens read from it, so
they open instantly and work without network.

Every screen has the sync state in its top bar (✓ All sent · ⟳ 5 waiting · ⚠ No network),
an icon beside its title, and big buttons (48 dp, main actions 64 dp).

## Crew app (drivers, co-drivers, helpers)

Bottom navigation: **Home · Trip · Money · More**. (Leave and salary slips come with
payroll; hide the tab until the API has them.)

| # | Screen | Shows / does | API |
|---|---|---|---|
| C1 | **Splash** | logo, name, a progress bar that follows real steps (open the database, check the token, load settings, count waiting items), version and build | `GET /ping` (clock check) |
| C2 | **First sign-in** | mobile number + 8-digit code, or scan the office's QR (`fleet-activate:<mobile>:<code>`) | `POST /auth/activate` (§2) |
| C3 | **Choose company** | only when the login has several companies; the current one on Home with a switcher | `GET /companies` |
| C4 | **Set PIN** | 4–6 digits twice; offer fingerprint/face afterwards (stored on the phone only) | `POST /auth/pin` |
| C5 | **Unlock** | PIN pad + fingerprint; locks after `settings.app_lock_minutes` in the background; "Forgot PIN? Ask the office" | — (checked on the phone; the PIN also confirms a driver change) |
| C6 | **Home** | current trip card (number, route, next stop, status), **Duty on/off**, waiting-for-me card (payments to approve), the announcement banner, papers expiring | `GET /crew/sync` (§3), `GET /me` (announcement, read-only); `duty.start` / `duty.stop` (§6) |
| C7 | **Trip offer** | a sent trip: route, stops, loads, truck, instructions; **Accept** / **Reject** (reason) | `trip.accept`, `trip.reject` |
| C8 | **Start trip** | odometer (photo first), fuel gauge, checklist; starts GPS tracking (foreground service) | `trip.start` + photos |
| C9 | **Trip** | timeline of every crew entry with its status chip (approved, waiting, asked, rejected), stops with **Reached / Left**, loads with **Loaded / Delivered** | sync; `stop.reached`, `stop.left`, `load.loaded` |
| C10 | **Add entry** | grid of entry types from `lists.event_types` (icons, short words) | — |
| C11 | **Fuel** | camera first (odometer, pump meter, receipt), then litres, rate (amount worked out), full tank, pump, who paid | `POST /crew/events` (§4) + photos |
| C12 | **Toll / fee / parking / food / loading / repair / other** | amount, who paid, the fields of the type, optional photo | §4 |
| C13 | **Payment without receipt** | authority, reason, amount; goes to the other driver for a yes/no | §4 (`no_receipt`) |
| C14 | **Waiting for me** | the other driver's payments without receipt: **Yes** / **No** (reason) | `event.second_approve`, `event.second_reject` |
| C15 | **Money received** | from office / party / other, amount, reference | §4 (`money_received`) |
| C16 | **Rest / note / incident** | rest start and end (out of driving hours), notes, breakdown, accident… | §4 |
| C17 | **Driver change** | who takes the wheel; their PIN when they are not this phone's person; odometer (photo if the owner wants) | `driver.change` |
| C18 | **Delivery (e-POD)** | per load: packages and weight received, shortage, damage, receiver name and mobile (OTP when asked), signature, POD photo, unloading times | `load.delivered` + `pod` photo |
| C19 | **End trip** | odometer; "end early" with a reason | `trip.end` |
| C20 | **Entry detail** | what was sent, photos, status, the office's question; **Change** or **Cancel** until it is checked | `PATCH /crew/events/{uuid}`, `.../cancel` |
| C21 | **Money (My Khata)** | trip cash I hold, what the company owes me, the net, the statement; per settled trip its settlement | `GET /crew/money` (§3), sync → `trips[].settlement` |
| C22 | **Papers** | truck papers and my licence, readable offline at a checkpost (images cached on the phone) | sync → `papers[]`; `GET /crew/papers/{id}/files/{file}` |
| C23 | **Raise complaint** | title, details, priority, odometer, photo | `complaint.raise` |
| C24 | **Waiting to send** | every item not yet sent, oldest first, with **Send now**; warns after 24 hours | — (the phone's queue) |
| C25 | **Phone health check** | battery saving, autostart, background data, with phone-brand steps (Xiaomi, Oppo, Vivo, Realme, Samsung) | — |
| C26 | **Settings** | language, PIN, fingerprint, night mode, sign out | `POST /auth/logout` |
| C27 | **About** | version and build, server, company, last sync | `GET /me` |

Permissions are asked in this order, each after a short "why" screen: location while
using the app → camera → notifications (blueprint 05 §5). "Location all the time" is not
asked in version 1.

## Owner app (owner, manager, accountant, workshop, viewer)

Side menu: **Dashboard · Approvals · Live map · Trips · Trucks · Papers · Bills ·
Ledgers · Scan to sign in on the web · Settings · About**. Show only what `GET /me` →
`permissions` allows (codes in the table).

| # | Screen | Shows / does | Permission | API |
|---|---|---|---|---|
| O1 | **Splash, sign-in, PIN** | as the crew app, but sign-in by SMS/WhatsApp code | — | `POST /auth/otp`, `/auth/otp/verify` (§2) |
| O2 | **Dashboard** | trucks (on trip, free, workshop), trips running, entries to check, profit this month, spent today, alerts, LR-to-cash queues, the announcement; pull to refresh | `dashboard.view` | `GET /owner/dashboard` (§10) |
| O3 | **Approvals inbox** | one card per entry with photo thumbnails (tap to zoom), flags in red, mileage for fuel; **Approve** / **Ask** / **Reject** | `event.verify` | `GET /approvals`, `POST /approvals/{uuid}` (§7) |
| O4 | **Live map** | every running trip's last point; a quiet phone in orange; tap → trip | `map.view` | `GET /owner/map` |
| O5 | **Trips** | tabs Running · Planned · Ended · Settled; search by number or vehicle | `trip.view` | `GET /owner/trips` |
| O6 | **Trip detail** | route, stops, loads (freight when allowed), crew and their answer, entries to check, spent, profit; **Route** button; call the driver | `trip.view` | `GET /owner/trips/{uuid}` |
| O7 | **Route replay** | the track with the entries on it, play/pause slider | `map.view` | `GET /owner/trips/{uuid}/route` |
| O8 | **Trucks** | each truck's status and its next paper to expire | `truck.view` | `GET /owner/trucks` |
| O9 | **Papers** | expired and expiring papers, soonest first; open the file | `document.view` | `GET /owner/papers`, `.../files/{file}` |
| O10 | **Bills** | tabs Open · Overdue · Paid; balance due, days overdue; **Share on WhatsApp** | `invoice.view` (share: `invoice.send`) | `GET /owner/invoices`, `POST /owner/invoices/{id}/shared` |
| O11 | **Bill detail** | lines, tax, paid, due; open the PDF; share | `invoice.view` | `GET /owner/invoices/{id}` (`pdf_url`) |
| O12 | **Ledgers** | parties, crew, bank and cash with their balances; search | `ledger.view` | `GET /owner/ledgers` |
| O13 | **Statement** | a ledger's lines for a period, opening and closing; crew buckets | `ledger.view` | `GET /owner/ledgers/{id}` |
| O14 | **Scan to sign in on the web** | camera → "Sign in on Chrome on Windows?" → Yes | any office login | `POST /web-login/inspect`, `/web-login/approve` (§9) |
| O15 | **Settings / About** | language, PIN, fingerprint, sign out; version and build | — | `GET /me`, `POST /auth/logout` |

| O16 | **New trip** | pick route, truck (busy ones marked), crew, client, material, weight, rate (and a printed LR number); **Save** or **Save and send** | `trip.create` (`trip.assign`) | `GET /owner/masters`, `POST /owner/trips`, `.../send` (§12) |
| O17 | **Bill LRs** | the LRs waiting for a client, tick and **Make the bill** / **Make and issue** | `invoice.create` (`invoice.finalise`) | `GET /owner/unbilled`, `POST /owner/invoices`, `.../finalise` |
| O18 | **Money received** | client, amount, mode, UTR or cheque, TDS; shows which bills it paid | `payment.record` | `POST /owner/receipts` |
| O19 | **Add a paper** | truck or person, type, number, expiry, camera photo(s) | `document.manage` | `POST /owner/papers` (multipart) |
| O20 | **Notifications** | the inbox behind the pushes; tap opens what it is about | — | `GET /notifications`, `POST /notifications/read` (§11) |

Every O16–O19 write sends an `Idempotency-Key` made when the person taps (§12), so a retry on a bad network never
makes a second trip, bill or receipt. Still web only: hire challans, credit and debit notes, payments, settlement.
