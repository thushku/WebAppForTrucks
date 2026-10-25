# Project State

Updated at the end of every build round. Newest first.

## 2026-10-25 — Milestone M8: the tax engine (India GST, UAE VAT)

**Built (in `webapp/`, module `app/Modules/Tax`):**
- **Rates and rules as data with dates** (blueprint 11, D15): tax types (CGST, SGST, UTGST, IGST, VAT), **tax codes**
  with the e-invoice category (S standard, Z zero, E exempt, AE reverse charge, O outside scope), printed legal text,
  law reference and the **papers a zero rate needs**; **dated rates** with their split (same state / other state /
  union territory, or one VAT line); **rules** from freight facts (charge class, India GTA option, transport type,
  main carrier or subcontractor, buyer registered, buyer category). The most specific matching rule wins; **no match
  stops the invoice with a message, never 0 %**. Every company gets its country's preset (`CountryDefaults` → 
  `TaxPresets`; `php artisan fleet:tax-presets` for existing companies); a preset applied again only adds what is
  missing.
- **India GST preset**: GTA **5 % reverse charge** (the default; tax shown as notional, "Tax payable on reverse charge:
  Yes"), **5 % forward** (no ITC), **18 % forward from 22 Sep 2025** and the **old 12 % before it** (dated rule and
  rate), exempt for unregistered buyers and for non-GTA transport (no consignment note), zero-rated SEZ supplies (LUT
  papers), vehicle hire to a GTA exempt, warehousing / customs 18 %, add-ons (loading, detention, demurrage, fuel
  surcharge, packing, toll recovered) and late-payment interest **follow the freight line**, disbursements as pure
  agent and damage claims outside scope. SAC 996791 (GTA) / 996511 / 996601…
- **Place of supply** (IGST Act s.12(8)): the registered buyer's GSTIN state; for an unregistered buyer the state
  where goods were handed over; never guessed. Same state → CGST + SGST, **CGST + UTGST in union territories without a
  legislature**, other state → IGST.
- **UAE VAT preset**: domestic 5 %; export / import / international **0 % as main carrier, only with the transport
  document and exit/customs papers** (or "pending evidence" when the company chooses warn); **5 % when we are a
  subcontractor on the UAE leg**; a missing transport type stops the invoice.
- **Calculator** ported from Clinic Ledger (discount spread pro rata, inclusive or exclusive prices, per-part
  rounding) plus reverse charge, **3-decimal currencies** (BHD, KWD, OMR…) and a separate round-off line (rupee
  rounding on in India, off in the UAE).
- **TDS** (new Income-tax Act 2025 from 1 Apr 2026): section **393(1) (old 194C)** 1 % individual/HUF, 2 % others;
  **393(4) (old 194C(6))** nil with the ≤10-truck declaration. The expected TDS a client deducts from our invoice, and
  the TDS we deduct from hired-truck owners (the hire challan now uses the same service).
- A hand-chosen tax code needs a reason (≥ 10 letters); every result carries a **trace in plain words** ("Buyer
  registered: …", "Place of supply Maharashtra: …", "Detention follows the freight line", "Reverse charge: ₹2,500 is
  for the buyer to pay").
- **Settings → Tax**: our GSTINs / TRN (validated; state from the GSTIN; default; cancelled from), the **GTA option
  per financial year** (locked once recorded, Annexure V reference), invoice rules (round total, evidence policy, our
  TDS declaration), **try a case** (the full answer with reasons), codes with their dated rates and **"new rate from a
  date"** (closes the previous one; old rates are never edited), rules (point at another code, SAC, on/off).
- Fixed on the way: the Trip rules page saved every company setting (it would have switched the new tax settings off);
  it now saves only its own.
- Demo: Sharma Roadlines' Kerala GSTIN with the default GTA option for 2026-27.

**Tests:** 164 passing (new `TaxTest`, 12 tests: the blueprint's worked examples and more).

**Not yet:** e-invoice adapters (UAE ASP before 1 Jan / 1 Jul 2027, India IRN only for non-GTA supplies), return
exports (GSTR-1/3B, VAT201), registration-threshold monitor, tax on advances, multi-currency tax in local currency
(exchange rates come with invoicing), purchases and input credit (with accounting), other country presets.

**Next:** M9–M10 — invoices on this engine (from LRs and trips, credit and debit notes, receipts), the document
templates and editor ported from Clinic Ledger.

## 2026-10-25 — Milestone M7: approvals, settlement, close, office entries

**Built (in `webapp/`):**
- **Approvals inbox** (`/approvals`, menu badge with what waits for the office): tabs waiting for check / waiting for
  2nd approval / query open / disputed, kind and trip filters; each row with time, trip, person, amount, payment mode,
  photos (look-alikes outlined), the automatic flags in words. **Approve**, **Ask** (a question the crew see in their
  app) or **Reject** (with the reason); **tick and approve together** only rows without flags. The same buttons are on
  the trip's timeline, which now also shows every decision (who, when, the note).
- **The crew answer a question** by correcting the entry in their app (the note is the answer); it goes back to the
  office. **Second approval** history kept too. On a **one-driver trip** the manager gives the second approval and the
  check in one step (A4, "Approve (2nd + check)"); with a co-driver on the trip the office waits for them (D6).
- **Settlement (hisaab)** per crew member once the trip has ended and every entry is decided (nothing waiting for the
  2nd driver or the office, no open query or dispute): advances + money collected on the road − **approved spending
  from their own pocket** (cash, UPI; fuel card / FASTag / credit is the company's money) = **returns to the office**
  or **the company owes**; settled as returned / paid to the crew member / carried to the next trip / adjusted in the
  personal ledger (only the ways that fit the balance), with a reference. **Bata** suggested by the company rule (per
  day — calendar days in the company's time zone — or per trip), editable, recorded for the personal ledger. When
  everybody is settled the trip is **settled**; a trip without our crew (hired truck) is marked settled directly.
- **Close**: crew entries locked for good (also edit and cancel); the **office still adds income and expenses** to a
  closed trip (D8) — later workshop bills, commission, office charges, claims — each audited, cancelled with a reason.
  **Reopen** is the owner's (`trip.reopen`), with a written reason: settlements are voided and kept, logged as
  sensitive.
- **Trip profit (direct)** on the trip page: freight and charges + other charges + office income + commission earned
  − approved road spending (every payment mode) − lorry hire − commission paid − office expenses − bata; per km; what
  still waits for approval is shown apart.
- **Owner app API**: `GET /api/v1/approvals` (with flags, photo links and the allowed decisions), `POST
  /api/v1/approvals/{uuid}`; the crew's sync carries their own settlement. Documented in `docs/handover/api-v1.md`.
- Trip list: "To settle" and "Settled" tabs. Demo: a Kochi → Chennai trip that ended last week, its entries
  approved, waiting for settlement; the running trip's entries wait in the inbox.

**Tests:** 152 passing (new `ApprovalTest`, 5 tests; the phone helpers moved to `tests/Pest.php`).

**Not yet:** posting settlements, bata and office entries to the books and the personal ledger (M11 accounting);
"carried to the next trip" as an opening advance on that trip; auto-approve rules beyond small tolls (e.g. matching
FASTag statements); notifying the crew of a question by push (Firebase, with the push work).

**Next:** M8 — the tax engine (India GST incl. GTA RCM/forward charge, UAE VAT), then M9–M10 invoicing with
templates and the editor (ported from Clinic Ledger), M11 accounting and the five ledgers.

## 2026-10-25 — Milestone M6: the phone API

**Built (in `webapp/`, API reference for the phone session in `docs/handover/api-v1.md`):**
- **Signing a phone in** (`/api/v1/auth/*`): crew with the office's 8-digit **activation code** (once, 72 h; local or
  +91/+971 numbers; 8 wrong codes per number per hour, then a pause); owners and office people with a **code by
  SMS/WhatsApp** (D21, `log` driver until the provider is set up; the same answer for unknown numbers). One Sanctum
  token per phone, **90 days**, renewed with `/auth/refresh`, ended with `/auth/logout`; the `devices` row records
  platform, model, version, push token, last seen / sync / GPS. App **PIN** (4–6 digits, easy ones refused).
- **Sync** (`GET /crew/sync`, full or `since`): the person's trips (sent, accepted, running, ended ≤ 15 days) with
  stops, loads, e-way bills, crew and instructions; entries on those trips; **second approvals waiting for me**; who
  holds the wheel; duty; the truck's papers and their own (files downloadable to show at a checkpost); the rules the
  app needs; the entry types and payment modes. **Never sent:** the office note, freight (unless the owner allows it,
  or "to pay" loads the driver collects), other people's ID papers, the manager's flags.
- **Entries** (`POST /crew/events`, batches of 100, one answer per entry): fuel, toll, weighbridge, official fine with
  receipt, **payment without receipt**, loading, parking, repair, food, other, money received, rest, incident, note —
  each validated by type; unknown fields dropped. Saved **once per phone-made UUID**. **Trusted time** rebuilt from
  the phone's uptime counter (else GPS time). Starting status: recorded / waiting for check / **waiting for the 2nd
  driver** (dual approval on, another driver on the trip; on a one-driver trip the manager when the rule says so, A4)
  / approved at once for tolls under the owner's limit. **Flags for the manager** (never a refusal): odometer going
  back, more litres than the tank, mileage since the last full tank outside the truck's range ±25 %, litres × rate ≠
  amount, entered far from the GPS track, fake location, changed clock, late entry, cash to one payee in a day above
  ₹35,000, the same photo used before. A **repair opens a truck complaint**. Own entries can be **edited** (old values
  kept) or **cancelled** with a reason until checked.
- **Photos** (`POST /crew/events/{uuid}/photos`): shrunk to ≤ 1600 px / 500 KB, fingerprinted (exact bytes and
  look-alike hash within 4 bits among the last 60 days): a re-used photo is flagged; a retry is harmless; the **POD
  photo marks the load's POD received**. "1 of 3 photos" while uploads are pending.
- **GPS** (`POST /crew/gps`, ≤ 500 points, batch UUID): impossible points dropped (future, > 7 days, 0,0, faster than
  200 km/h); each point linked to the trip running then.
- **Commands** (`POST /crew/commands`, kept answer per UUID so a retry never runs twice, a refusal rolls back fully):
  accept / reject a trip (with reason, back to the office), **start** (odometer checked against the truck, starter
  holds the wheel) and **end**, stop reached / left, loaded (seal, weight), **delivered = e-POD** (receiver, packages,
  short, damaged, remarks, unloading times, part delivery), **driver change** (on another's phone the incoming driver's
  PIN; odometer photo when the owner requires it), the second driver's **yes / no with a reason** (any time until
  settled, D6), duty on / off, raise a truck complaint.
- **Trip page — "From the road"**: every entry in time order (company time), amount, payment mode, odometer, the
  status, the flags in plain words, photos (look-alikes outlined), a map link, the 2nd driver's reason; totals and how
  many wait. Crew card: who rejected the trip and why; **driving hours per person, rests taken out**.
- Permissions: drivers record (`event.record`), helpers only when the owner allows it; second approval needs
  `event.second_approve` and being a driver/co-driver on that trip, never the one who paid.
- Demo: the running Kochi → Mumbai trip now has a start, fuel, three tolls, a payment without receipt waiting, food, a
  night halt and a GPS track. `fleet:demo --fresh` works again (it failed on hired-truck trips since M5: the demo
  company's rows are now emptied table by table before the company is deleted).

**Tests:** 147 passing (new `PhoneApiTest`, 14 tests that act like phones).

**Not yet:** push notifications (Firebase, approved D18) for new trips and second approvals; the office's devices list
(switch a phone off); the live map and route replay (Leaflet, approved D18); driving-limit alerts (hours are
counted); GPS-silence alerts; owner-app endpoints beyond sign-in, QR web login and `/me` (approvals, dashboard —
with M7 and M12); `gps_points` monthly partitions on the production server.

**Next:** M7 — the approvals inbox (web and owner app: approve, query, reject; the manager's second approval on
one-driver trips), settlement per crew member (advances, own-pocket spending, bata), close, office entries after close.

## 2026-10-25 — Milestone M5: trips (full load, part load, hired trucks)

**Built (in `webapp/`):**
- **Trips** (`/trips`): list with status tabs, date, place (origin, destination or any stop), truck, client and trip-no.
  filters, reset, newest first, and a **totals bar** "N trips · freight · other charges · total" that follows the
  filters (13 §A #4).
- **New trip**: date, load type (FTL / PTL / mixed), kind (loaded / empty run / round trip), branch, **our truck or a
  hired truck** (supplier + one of the supplier's trucks), driver, co-driver, helpers, from/to, planned km, planned
  start and delivery, fuel and toll budget, return-of trip, **other charges billed to the client** (#1), instructions
  to the crew and an **office note the crew never see** (#2). The FTL quick form includes the load.
- **Loads (consignments / LRs)**: billing party, consignor, consignee, material, said-to-contain, packages and kind,
  actual and charged weight, basis (trip / tonne / kg / km / package) and rate — **filled from the approved rate
  contract** for the party, route, material and truck type, with its minimum weight — loading/unloading charges,
  advance from the party, LR payment (to be billed / paid / to pay / free), **GST paid by**, e-way bill no. and
  validity, consignor invoice, client PO/indent, hazardous / over-dimension, risk; UAE: transport type for VAT and main
  carrier / subcontractor. **Freight is always worked out on the server** (a typed total is not trusted).
- **Part loads**: stops between from and to (load / unload / both / fuel / border); each load picks up and drops at
  its own stops (drop after pickup); the page shows what loads and unloads at each stop and a **load bar per leg**
  against the truck's payload, with an over-payload warning.
- **Send to crew = the dispatch gate**: the truck and every crew member must be free (no other open trip, truck not in
  the workshop) and have no expired or missing dispatch-blocking paper (licence only for people at the wheel). Papers
  can be overridden with a written reason (≥ 10 letters), kept on the trip and logged as sensitive. Sending fixes the
  **trip number** `261025-ACC-COK-BOM-01` (shipper = consignor's or billing party's short code; `MTY` for an empty
  run) and gives every load its **LR number**.
- **Start / end** by the office (the crew app does it in M6): odometer checks against the truck, truck on trip → free,
  loads in transit → delivered, "ended early". **Cancel** before it runs, with a reason; LR numbers stay used.
- **Hired trucks**: **lorry hire challan** — vehicle, driver, hire fixed or per tonne, broker and commission (taken
  from the hire or paid by us, mirrored in the broker register), office charge, **TDS 194C suggested** (1 % for
  individual/HUF PAN, 2 % others, nil with the supplier's ≤10-truck declaration for the year — to be confirmed by a
  CA), balance days after POD, terms. Issue numbers it `HC/26-27/00001` and fixes the hire; then advances, fuel,
  detention, extras, deductions (with a reason) and payments, never above the balance; settled at zero. The trip page
  shows **margin on hire** (freight − hire).
- **Broker commissions** on a trip, either way (we pay a load broker / we earned it), fixed or % of freight.
- **Crew advances** (cash / UPI / bank with reference) to the trip's crew only.
- Demo: a running Kochi → Mumbai cement trip with an advance, a part-load draft Kochi → Walayar → Coimbatore with two
  clients, and a hired truck Bhiwandi → Pune with its issued challan and an advance.

**Tests:** 133 passing (new `TripTest`, 15 tests).

**Not yet:** "+ Add new" quick forms inside the trip form (#3), trip report PDF/Excel (#5), lock of invoiced trips
(#6, with invoicing), split deliveries and e-POD (M6), cost sharing per consignment (with accounting), suggestions
from the last trip on the same route.

**Next:** M6 — the phone API (activation, sync pull/push, photos, GPS, commands, stop tasks, e-POD) with a test
script that acts like a phone; then M7 approvals and settlement.

## 2026-10-25 — Milestone M3 (part 2): Document Manager

**Built (in `webapp/`):**
- **Documents** (`/documents`): papers of trucks, people, parties and the company. Dashboard tiles **Expired / within 7 /
  15 / 30 days / Missing**, "needs attention" and "all" lists by owner kind, each row with its expiry chip and a "File
  missing" chip when only the dates are on file (13 §D #17).
- Add a paper with number, issuer, issue/valid-from/expiry dates, cost, insurer or agent, where the original is kept,
  notes, and files. **Photos are re-saved as JPEG ≤ 1600 px and ≤ 500 KB** (turned upright, camera data dropped);
  PDFs must really be PDFs and ≤ 2 MB; anything else is refused. Files live in the company's private folder and are
  served only through a checked route.
- **Renew** makes a new version: the old details and files stay read-only in the history with who replaced them;
  reminders start again. **Correct** fixes the current version. **Pause reminders** until a date with a reason.
- **Required papers** per truck (all, or one truck type), per person (all, or drivers…) and for the company; missing
  ones show as **Missing** tiles on the owner's page, in the dashboard, and in the truck's **Important dates** card
  (with a green / amber / red document-health dot).
- **Dispatch check** ready for trips (M5): `Compliance::blockers()` lists expired dispatch-blocking papers (insurance,
  fitness, permits, PUC, licence; UAE Mulkiya, RTA permit, tracker) and missing required ones; a driver without a valid
  licence is blocked too.
- **Personal ID papers** (Aadhaar, PAN, bank proof, address proof; Emirates ID, passport, visa) are hidden from anyone
  without `people.id_documents`, cannot be shared by link, and every look at their files is logged as sensitive.
- A driver's **licence document** keeps the person's licence number and expiry in step (the person form then shows
  them read-only).
- **Reminders**: `php artisan documents:remind`, scheduled daily at 07:00 India time; a reminder at the type's
  reminder days, then 15, 7, 1 and the day, then weekly after expiry, each step once; one email per company to the
  people who may manage documents.
- **Share links**: tick papers → purpose and 1/3/7/30 days → an expiring link (WhatsApp button); the public page shows
  the papers with images watermarked "Copy shared on <date> for <purpose>", counts opens, can be ended early, and
  answers "This link no longer works" afterwards.
- Document types now carry "Expired stops dispatch" and "Personal ID" flags (Settings → Lists); India and UAE presets
  extended (AIS-140, speed governor, badge, medical, police verification, Carriage by Road Act, GTA Annexure V,
  Form 16A, ≤10-truck declaration; UAE RTA/ITC permit, ILOE, visa…).
- Demo company now has papers for every truck with a spread of expiry dates and required-paper rules.

**Tests:** 118 passing (new `DocumentTest`, 10 tests).

**Later:** OCR auto-fill, Vahan/Sarathi checks (provider approved, D19), push and WhatsApp reminder channels, upload
from the crew app (M6), cost posting to the truck ledger (M11), insurance claims.

**Next:** M5 — trips with FTL / PTL / mixed loads, stops, several consignments (LR data), own or hired truck, hire
challan, broker commission, trip number, and the dispatch check.

## 2026-10-25 — Milestone M4: trucks and people

**Built (in `webapp/`):**
- **Trucks** (`/trucks`): list by status with open-complaint counts; profile with registration (RC owner required,
  state, RTO, registration date → vehicle age), build (make, model, year, emission norm, fuel, truck/body type, wheels,
  axles, lift axle, deck length, size label), weights and capacity (GVW, unladen, payload, tank), normal mileage
  loaded/empty, ownership (own / attached with its owner party / leased), loan (financier, EMI, day, end), toll tag,
  GPS tracker id, branch. A registration number is in the fleet once however it is typed. The odometer is typed once,
  then only moves forward (service jobs, complaints, status changes; trips in M5). Status by hand (free, workshop,
  breakdown, out of service, sold); "on trip" is left to trips.
- Truck page: details, open complaints, service due, and **share details as text** (pick fields; chassis and engine
  show the last 5 characters only) by copy or WhatsApp (Trucksarthi parity #18).
- **Complaints**: raised from the truck page (crew too, with `complaint.raise`), numbered `C-1, C-2…`, priority,
  category; a board across trucks (open → in progress → waiting for parts → solved → closed, most urgent first);
  allowed status moves only; every note and change kept in the complaint's history; assign a person and a workshop.
- **Service**: schedules per truck (every N km and/or N days, "soon" within 10% or 15 days, "overdue"); service jobs
  numbered `SJ/26-27/0001` with parts and labour lines — totals always worked out from the lines; "Mark done" freezes
  the job, resets its schedule, moves the odometer and frees a truck that was in the workshop; cancelled jobs keep
  their number.
- **People** (`/people`): drivers, helpers, office, mechanics; list with the **licence chip** (on file / expires in N
  days / expired / missing); profile with licence (class, expiry, hazardous endorsement, badge), pay basis (monthly /
  per trip / per km), emergency contact, blood group, usual truck. **ID numbers (Aadhaar shown as XXXX XXXX 1234,
  Emirates ID checked) and bank details are encrypted**; only `people.id_documents` can enter or reveal them, each
  reveal is logged as sensitive and never stored in the session.
- **Crew-app activation codes**: 8 digits + QR, valid 72 hours, once; a new code retires the old one. Issuing a code
  gives the person a login — the Account for their mobile number (shared if they already drive for another company,
  P7) with the app role for their type (driver / helper…). Changing the mobile number ends the old app login in this
  company. `ActivationService::redeem()` is ready for the phone API (M6).
- Demo company now has four trucks, six crew, a service schedule with a finished job, and two complaints.

**Tests:** 108 passing (new `FleetTest` 7, `PeopleTest` 7).

**Not yet (later milestones):** tyres, fuel and mileage, costs and profit per truck, documents tab (next: Document
Manager), trips tab, driving hours, ledger, leave.

**Next:** Document Manager — documents of trucks, people, the company and parties, with versions (renewals), expiry
dashboard and reminders, required types ("Missing" tiles), secure share links; then the dispatch check that blocks a
trip when a required document has expired.

## 2026-10-25 — Milestone M3 (part 1): parties

**Built (in `webapp/`):**
- **Parties** (`/parties`): one firm, many roles — client, consignor, consignee, supplier (hired trucks), vendor,
  broker — with a one-line hint for Supplier vs Vendor (P4). List with role tabs and counts, search by name, code,
  mobile or GSTIN; switched off, never deleted.
- Party form by role: identity (trade/legal name, short code for trip numbers offered from the name, group/parent),
  contact and address, tax (first GSTIN/TRN, PAN, TAN), client credit and billing (limit, days from invoice or
  submission, action over the limit, billing cycle, POD rule, references needed, vendor code, portal, late interest,
  TDS they deduct), supplier (kind, rating), vendor (category, Udyam/MSME size with the 45-day hint, pump location),
  our payment days, opening balance with side and date, blacklist with reason.
- Party page: details, **GSTIN/TRN registrations** (several per party for plant-wise billing; one main; state taken
  from the GSTIN; PAN must match), contacts (call buttons), **bank accounts** (encrypted at rest, last 4 digits on
  screen, adding/removing needs `settings.bank`, logged as sensitive with the numbers redacted), Call and WhatsApp
  buttons, **Rates** tab, **Trucks** tab for suppliers (hired trucks; one registration number listed once however it is
  typed).
- **Rate contracts**: draft → approve → new version from a date (the old version ends the day before); route rows by
  from/to place, material, truck type, basis (trip / tonne / kg / km / package), rate, minimum guaranteed weight,
  detention free hours and rate, loading/unloading/extra-drop charges; diesel escalation fields. `RateFinder` picks the
  most specific approved row valid on the trip date and works out freight with the minimum weight.
- Tax number checks: GSTIN with GSTN's check character and real state code, PAN with holder type, UAE TRN.
- Money formatting ported from Clinic Ledger (`App\Support\Money`, ₹ 1,23,456.00), plus amount in words.
- Demo company now has clients (ACC with two GSTINs and approved cement rates, UltraTech, Kerala Agro), a supplier with
  a hired truck, a fuel pump and an MSME workshop.

**Tests:** 94 passing (new `PartyTest`, 14 tests).

**Next:** M4 — trucks (profile, documents dates, complaints, service) and people (profiles, activation codes); then
the Document Manager across trucks, people, company and parties.

## 2026-10-25 — Milestone M2: company set-up, team, roles, masters, numbering, QR sign-in

**Built (in `webapp/`):**
- Settings area (`/settings`) with a side list showing only the pages the person may open:
  - **Company** — trading and legal name, short code, address with GST state code (India) or emirate (UAE), PIN,
    contacts, logo (≤ 300 KB, content-checked, stored privately and served to members only). Country, currency and
    financial year stay as set at sign-up.
  - **Branches** — code unique per company, one head office, switched off rather than deleted.
  - **Trip rules** — dual approval on/off (D7), second approver on one-driver trips (A4: manager, or none),
    auto-approve tolls below an amount, crew-app rules (freight visible, helpers record, photos, app lock, stop prompt,
    GPS silence alert), driving limits, bata rule and amounts, cost sharing method, cash-per-payee warning. Defaults in
    code with country overrides (UAE: bata in AED, no cash limit); only changed rules are stored; one audit line per
    save.
  - **Team** — invite by email with roles; the link lets a new person choose a password (counts as email verified) and
    an existing login join after signing in (a link alone never joins an existing login); suspend, let back in, remove
    (row kept for history, roles taken away), resend invitation, unlock a locked login.
    Nobody can give or take away a role above their own level; the Owner role needs `role.grant_elevated`; the last
    active Owner can never be removed.
  - **Roles** — permission grid by area; rename; new role as a copy of another. Nobody can add or remove a permission
    they do not hold, or edit a role more senior than their own; the Owner role is not editable.
  - **Places** — short codes for trip numbers (P1), code offered from the name while typing (BHI, BHI2…), unique per
    company, type (city, customer site, pump, toll, weighbridge, godown, border), optional map position, search.
  - **Lists** — materials, expense categories (running / maintenance / office, receipt needed), truck types, body
    types, document types (truck / person / company / party, reminder days), leave types, complaint categories.
    Country presets for India and UAE are added at sign-up; preset items can be renamed or hidden, not deleted.
  - **Numbering** — every document type (trip, LR, invoice, credit/debit note, receipt, payment voucher, hire challan,
    quotation, fuel slip, journal) with prefix, format, restart (financial year / month / day / never), start number
    and next number, live preview. Ported from Clinic Ledger's DocumentNumbering.
  - **Sign-in security** — company two-step policy (off / each person / always) and who is locked out now.
  - **Activity** — the company's audit log with date, person and area filters.
- **Sign in with your phone** (P7): the login page shows a QR code on request; the owner app calls
  `POST /api/v1/web-login/inspect` (shows "Chrome on Windows, IP") and `/approve` (Sanctum token + `X-Company`); the
  waiting browser is signed in once into that company. Codes live 2 minutes; only hashes are stored; the poll secret
  must match the browser's own session.
- `php artisan fleet:demo [--fresh]` — demo company Sharma Roadlines (Kochi head office, Mumbai branch, 10 places,
  accountant and manager). Sign in: `demo@fleet.test` / `Demo-Pass-2026` (also `anita@` and `vikram@fleet.test`).

**Tests:** 80 passing (37 before; new files `SettingsTest`, `NumberingTest`, `TeamTest`, `QrLoginTest`).

**Notes / decisions taken while building:**
- Number series keep **one counter per period** (`document_counters`: "2026-27", "2026-10-25", "all") instead of
  Clinic Ledger's single counter plus a scan of issued numbers. A document dated in an earlier year continues that
  year's own series, and the daily trip serial restarts each day without reading the trips table. Numbers already on
  a document are still skipped (tables are listed in `DocumentNumbering::DOCUMENTS` as their modules arrive).
- Default formats use `{FY2}` ("26-27") so an Indian invoice number fits GST Rule 46's 16 characters:
  `INV/26-27/00001`. India's tax invoice, credit and debit note series refuse formats that could pass 16 characters
  or use characters other than letters, digits, "-" and "/". A restarting series must show its period in the format.
- Numbers are dated by the company's own clock (a trip at 01:30 IST belongs to that Indian day).
- Team invitations here are for office people with email. Crew who use only the phone app get an activation code
  under People (M4).
- Fixed on the way: the audit log asked strict-mode models for columns they do not have (any update of a branch
  failed); a 14-day trial showed "15 days left" because the trial end was midnight UTC instead of midnight in the
  company's time zone; the company is now resolved before route-model binding, so company rows in URLs are
  checked against the company.

**Next:** M3 — parties (clients, suppliers, vendors, brokers) with tax IDs, credit terms and rate contracts, and the
Document Manager.

## 2026-10-25 — Milestone M1: project set-up and security base

**Built (in `webapp/`):**
- Laravel 13 skeleton with Clinic Ledger's approved package set + Laravel Sanctum (D18). Tailwind 4, Vite 8, plain
  JavaScript, Pest 4. Self-hosted fonts incl. Noto Devanagari and Arabic.
- Identity: one `accounts` row per person, `company_users` memberships (a person can work for several companies, P7),
  roles copied into each company (owner, manager, accountant, workshop, viewer, driver, helper) with permission codes.
- Company isolation: `BelongsToCompany` global scope; a query without a company throws.
- Sign-in by email or mobile + password; the lock ladder 15 min → 1 h → locked until unlocked by hand (company owner
  or super-admin); built-in picture captcha; optional/required email code per company; trust this computer 30 days;
  same answer for every miss; every attempt recorded.
- Sign-up with a free trial (days set by the super-admin, D20), country preset (IN/AE), email verification link;
  forgot / reset password by single-use link.
- Super-admin console under a secret path (`PLATFORM_PATH`), picture + mandatory email code on every sign-in;
  overview, unlock locked logins, settings (trial days, product name, support contacts, enabled languages, captcha).
  `php artisan platform:admin` / `platform:unlock`.
- Security headers on every response (CSP with per-response script nonce, no framing, no sniffing).
- Append-only audit log with secret redaction.
- API: `/api/v1/ping`, `/api/v1/me` (Sanctum token + `X-Company` header).
- App layout: grouped menu with tinted tiles, version line, sign-in splash with a real progress bar, page-load bar.

**Tests:** 37 passing (`php vendor/bin/pest`).

**Notes / decisions taken while building:**
- Product name default "Rhydo Fleet" (placeholder; the super-admin sets the real name, D20).
- `public/build/` is not committed (Clinic Ledger rule); run `npm run build` after pulling. This replaces the earlier
  blueprint note that built files would be committed.

**Next:** M2 — company settings, branches, team invitations and roles screens, QR web login, masters (places with short
codes, categories), number series.
