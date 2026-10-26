# Project State

Updated at the end of every build round. Newest first.

## 2026-10-26 — Milestone M47: the client's own page (decision P34)

**Built (in `webapp/`, `Parties/Services/ClientPage`, `/c/{token}`):**
- **One link per client** with everything they ask about: their loads of the last 60 days (as billing party, consignor
  or consignee) — LR, date, route, status and a **Track** link to each one's tracking page (M45, left out once that has
  ended) —, their **open bills** (number, date, due date in red when past, balance) each opening its own secure bill
  page, and what is **due** and **overdue**; the company's phone. No sign-in, never indexed; never another client's
  loads, never the freight of loads not yet billed, never the crew.
- Made from the client's **Billing** tab ("Client page": copy, WhatsApp with the words ready, open, **Stop the link**)
  by whoever may send bills (`invoice.send`); the same link until stopped, then a new one. A stopped or unknown link
  answers "This link no longer opens" (410).
- Owner app: `GET`/`POST`/`DELETE /owner/clients/{id}/page`. Hindi for every new string.

**Tests:** 298 passing (new `ClientPageTest`, 2 tests: three loads — one billed, one waiting, one moved to another
client —, the page shows the two loads with two tracking links and the ₹75,520 bill with its link, not the other
client's LR nor the unbilled ₹62,900 freight; stopped → 410, a new link differs; the owner app's make, give the same,
stop, and 404 for a supplier).

## 2026-10-26 — Milestone M46: a pump's credit statement (decision P33)

**Built (in `webapp/`, `Accounts/Services/CardStatements`):**
- Accounts → Card statements also takes **a pump's credit statement**: choose the pump (a vendor, fuel pumps first),
  bring its CSV in (bill date, vehicle, bill no., amount); each bill matches the crew's fuel entry "on credit with the
  pump" at that pump — same truck, same amount, within a day —; a bill nobody entered is **booked as the truck's fuel,
  owed to the pump**; credit entries at that pump with no bill are listed. Bill numbers are kept per pump.
- **Fixed**: a card statement whose dates did not fit the first date format failed to import (the date reader threw
  instead of trying the next format); bill numbers and bill dates are now recognised as columns.
- Migration 000031: `card_statements.vendor_party_id`. Hindi for every new string.

**Tests:** 296 passing (`CardStatementTest`, a second test: a pump's statement refused without the pump, two bills — one
matched to the driver's ₹9,500 credit fuel, one booked to the pump's ledger —, the ₹4,750 entry with no bill listed).

## 2026-10-26 — Milestone M45: the client's tracking link (decision P32)

**Built (in `webapp/`, `Trips/Services/Tracking`, `/t/{token}`):**
- **A tracking link per LR**, shared from the trip page's loads ("Tracking link": copy, WhatsApp with the words ready,
  open) or the owner app (`POST /owner/consignments/{id}/track`); the same link every time.
- The client's page (no sign-in, never indexed): the LR, the route, the consignor and consignee, the goods; **the steps**
  — booked, loaded, on the way, reached, delivered — with their times; **while on the road the nearest known place**
  ("Near Thrissur, about 9 km") and the last update, with a map link rounded to about a kilometre; **the delivery** — when,
  received by whom, short or damaged; the company's phone. Never the freight, the crew or office notes.
- The link ends 30 days after delivery, or when the LR or trip is cancelled (a "link has ended" page).
- Hindi for every new string.

**Tests:** 295 passing (new `TrackingTest`, 2 tests: the link made once, the page before the trip starts without freight,
driver or office note, on the road near Thrissur with the position rounded, delivered with the receiver and 2 short, ended
31 days later, an unknown link; the owner app's same link).

## 2026-10-26 — Milestone M44: FASTag and fuel-card statements (decision P31)

**Built (in `webapp/`, `Accounts/Services/CardStatements`, Accounts → Card statements):**
- **Bring in** the CSV from the FASTag or fuel-card portal: the header row is found by its words (date and time,
  vehicle, plaza or pump, amount or debit, transaction id), day-first dates in the company's time zone; recharges and
  refunds are left out; a charge already brought in is never brought in twice; each charge finds its truck by the plate
  and the trip the truck was on then.
- **Matched by itself** to the crew's entry paid by that card — same truck, same amount (within ₹0.50), within a day, the
  nearest — or **by hand** from the entries of that truck; a charge nobody entered is **booked** as the truck's toll or
  fuel (and the trip's) from the card's wallet ledger, or **set aside** with a reason; undo a match.
- The statement page lists **entries that say the card paid but have no charge** — worth a question to the crew.
- The demo has a FASTag statement for the running trip: two tolls matched, one charge nobody entered.
- Migration 000030: `card_statements`, `card_statement_lines`. Hindi for every new string.

**Tests:** 293 passing (new `CardStatementTest`: a FASTag CSV with a title row and a recharge — four charges in, one
matched by itself, a ₹255 charge matched by hand to a ₹250 entry, a ₹95 charge booked as the truck's and trip's toll from
the FASTag wallet, another truck's charge set aside only with a reason, a booked charge not booked twice, the entries
without a charge, the same file again bringing nothing in, a file without the columns refused).

## 2026-10-26 — Milestone M43: credit and debit notes from the owner app

**Built (in `webapp/`, `Api/Owner/WriteController::storeNote`):**
- `POST /owner/invoices/{id}/notes` against an issued bill, with its reason: a **credit note** for the whole bill (its
  lines copied) or for an amount before tax (one line of the bill's service), a **debit note** with its lines (detention,
  rate difference…); the tax is the bill's, by the engine, as on the web; issued at once when asked (with
  `invoice.finalise`), else left as a draft to finish on the web. A credit beyond what is left to credit is refused.
- With this every money document can be made from the phone (api-v1 §12, screens O11).

**Tests:** 292 passing (new `OwnerNotesTest`: ₹5,000 credited before tax as CN/26-27/0001 for ₹5,900 and the bill's
balance down to ₹69,620, the same key replayed, a full credit then refused, a debit note without lines refused, a
detention debit note issued, a draft left against the bill).

## 2026-10-26 — Milestone M42: settling a trip and hire challans from the owner app

**Built (in `webapp/`, `Api/Owner/SettlementController`, `Api/Owner/HireController`; api-v1 §16):**
- **Settle a trip from the phone**: each crew member's trip cash — advances, money collected, spent from their pocket,
  the balance and the suggested bata —, what still waits (entries to decide), settle each person (the balance decides
  which ways are allowed), a hired truck's trip with no crew in one step, then close.
- **A hired truck's hire challan from the phone**: a new one starts from the trip (vehicle, driver, the loads' weight, the
  suggested TDS), a draft changed whole, issued with its number, then the money on it — advance, fuel given, detention,
  extras, deductions, the balance paid (never more than the balance; paid to zero it is settled).
- The challan's rules now live in `HireService` (`rules`, `prepare`, `lineRules`, `draftFor`), shared by the web form and
  the app; a money line's date may not be after today in the company's time zone.
- Every write once per `Idempotency-Key`. Only credit and debit notes stay web-only.

**Tests:** 291 passing (new `OwnerTripMoneyTest`, 2 tests: nothing to settle before the trip ends, a driver's ₹5,000
advance returned (paying him refused), settled once per key, all three settled then closed; a hire challan from its
suggested start through draft, edit, issue, an advance without its mode refused, an overpayment refused, the balance paid
and settled).

## 2026-10-26 — Milestone M41: a person's trips and driving hours

**Built (in `webapp/`, `People/Services/PersonActivity`, the person page's tabs):**
- The person page is in tabs now: **Profile** (as before), **Trips** — the trips they were crew on in a period (this
  financial year unless chosen) with their role, the vehicle, km and what they spent on each —, **Driving hours** — time at
  the wheel less the rests they recorded, days driven and the average, km, the longest stretch and how many shifts went
  past the company's limit without a break (red), each shift, and day by day (a night drive split over both days) —;
  Leave, Documents and Ledger link to their places. For those who see trips.
- The owner app has both (`/owner/crew/{person}/trips`, `/driving`).

**Tests:** 289 passing (new `PersonTabsTest`: a driver's trip with ₹2,000 spent, 7 h 30 min at the wheel over two
shifts after a half-hour rest, one over the five-hour limit, day by day, on the page and in the owner app).

## 2026-10-26 — Milestone M40: leave (decision P30)

**Built (in `webapp/`, `People/Services/Leave`, People → Leave):**
- **Leave types** are the company's list (India: casual 12, sick 12, earned 15 days a year; UAE: annual, sick, unpaid) and
  always an unpaid one. **Balances** per financial year — the year's days for the part of it the person works, less
  approved and asked-for days — on the person page (a card per type: "9 of 12 left") and in both apps.
- **Ask from the crew app** (`GET /crew/leave`, commands `leave.apply` / `leave.cancel`, screen C28) or **record in the
  office** (approved at once for someone who may approve). Overlaps, more than 60 days, too far back or ahead, and a paid
  type beyond what is left are refused with the reason.
- **People → Leave**: waiting requests first with **Approve** / **Turn down** (and a note) and **Cancel**; tabs by status;
  who is on leave today; the dashboard counts the waiting ones for those who decide; the people list shows "On leave till".
- Notices: `leave.asked` to those who approve, `leave.decided` to the person — a new "Leave" group in My notifications.
- **Unpaid leave comes off a monthly salary** in the pay run (those days are not paid), and **a trip whose crew is on
  leave that day needs an override** to be sent (the dispatch check).
- The owner app decides leave and sees a person's (§15, screen O35). The demo has one request waiting, one taken and one
  coming up. Migration 000029: `leave_requests`. Hindi for every new string.

**Tests:** 288 passing (new `LeaveTest`, 4 tests: the app's balances, a request asked once (the retry replayed), an overlap
and too much sick leave refused, the owner told, approved on the web, the driver told, the balance after, an approved one
not cancelled from the app; unpaid leave off October's salary (28 of 31 days) and casual leave not, a trip to someone on
sick leave stopped and free again once it is cancelled; the year's days for someone who joined in October; the owner
app's decision once per key).

## 2026-10-26 — Milestone M39: a truck's trips, fuel and money (decision P29)

**Built (in `webapp/`, `Fleet/Services/TruckActivity`, the truck page's last three tabs):**
- **Trips**: the truck's trips in a period (this financial year unless chosen) — number, date, route, driver, status, km,
  freight (for those who see it) — with the totals.
- **Fuel**: litres, fuel cost, the km its trips ran, **km per litre** from one full tank to the next against its normal
  mileage (red when short), fuel per km, and each fill with its rate, odometer, pump and status.
- **Costs**: the books for the truck — income, costs, profit; by ledger and month by month —, then as estimates only what
  the books do not hold by truck (its papers and usual driver's salary for the period's days, its tyres' wear on the trips
  that ended), and the **profit after the truck's costs**, per km; a link to the books' truck profit.
- Each tab only for those who may see it (trips `trip.view`; fuel `report.view` / `event.verify`; costs `report.view` /
  `accounts.view` / `ledger.view`); the owner app has all three (`/owner/trucks/{id}/trips`, `/fuel`, `/costs`; screen O34).
- **Fixed on the way**: a reversed voucher was counted backwards in a truck loan's balance (M35) and in a truck's own
  bills of 90 days (M37) — a cancelled voucher is left out, so its reversal must be too. Both now leave out the pair.
- Hindi for every new string.

**Tests:** 284 passing (new `TruckTabsTest`, 2 tests: the trips, three fills to 7 km a litre, ₹55,000 in the books less
6 days of papers and salary and ₹350 of tyre wear, the owner app's three; a workshop login sees none of the three; and in
`EmiTest`, an EMI voucher reversed leaves the loan and the truck's costs as they were).

## 2026-10-26 — Milestone M38: tyres (decision P28)

**Built (in `webapp/`, `Fleet/Services/Tyres`, Trucks → Tyres, the truck's Tyres tab):**
- **The tyre store and register**: tyres added as a set (one serial number per line, the same brand, size, cost and
  expected km), each with its status — in the store, fitted, at the retreader, scrapped —, km run, cost and cost per km;
  tabs, search, the tyres near the end of their km on top, and **what each brand gives** (km a finished tyre gave, its
  actual cost per km after the scrap money, against the expected).
- **The truck's Tyres tab**: its axles drawn front to back from its wheel count (10 wheels: front pair, two twin rear
  axles, the spare), the tyre at each position with a km bar (amber at 90 %, red past 100 %); **fit** a tyre from the
  store, **take off** (worn out, burst, sold → scrapped with the scrap money; retread → the retreader; repair, rotation →
  the store), **move or swap** (on the same truck or to another); the truck's tyre history.
- A tyre's page: its runs on each truck, its retreads, **send for retreading**, **back from the retreader** (cost and km
  added), **scrap**, edit the details.
- **Each trip's tyre wear**: the km each tyre was on the truck within the trip's odometer readings × its cost per km,
  under the direct profit with the truck's fixed costs (now "Profit after the truck's costs"), in the routes report
  ("after truck costs", CSV column) and the owner app (`tyre_wear`, `tyres`).
- Dashboard alert for tyres near their end; the owner app's tyres (§14, screens O32, O33), each write once per key.
- The workshop permission now reads "Work on complaints, service jobs and tyres". The demo has two trucks on full sets,
  two worn tyres scrapped, a new set in the store and one at the retreader (Cochin Tyre House).
- Migration 000028: `tyres`, `tyre_fitments`, `tyre_retreads`. Hindi for every new string.

**Tests:** 281 passing (new `TyresTest`, 4 tests: positions for 4–22 wheels and their names; a set added, a repeat serial
refused, fit, a taken or unknown position refused, a move with a low reading refused, a move and a swap with each
fitment's km, off for retreading, back with its cost and km, scrapped with the actual cost per km; a trip's wear of
₹1,225 for four tyres — one fitted halfway, the spare none — on the trip page, the routes report and the owner app, the
near-the-end alert; the owner app's add, fit and off once per key, a taken position refused).

## 2026-10-26 — Milestone M37: a truck's fixed costs a day and each trip's share (decision P27)

**Built (in `webapp/`, `Fleet/Services/TruckCosts`):**
- **What a truck costs a day, running or not** — worked out from what is already entered, nothing new to type: each current
  paper with its cost (insurance, permits, fitness, PUC) spread over the days it is valid, the usual driver's monthly
  salary over 30 days (drivers paid per trip or km are the trip's own cost), and what the books hold for the truck but for
  no trip in the last 90 days (repairs, service, EMI interest). A card on the truck page with the parts.
- **Each trip's share** for its calendar days on the road, under the trip's direct profit: "Truck's fixed costs, 3 days
  (estimate)" and **Profit after fixed costs**. The direct profit is unchanged. A hired or attached truck counts none.
- **Routes report**: "after fixed" under each lane's profit per trip, and two more CSV columns; the owner app has
  `fixed` and `net` per route, `fixed_costs` on the trip and on the truck (api-v1 §10, §12; screens O6, O26, O30).
- **A payment voucher may name its truck** (web and owner app), so a repair paid straight from cash counts in that
  truck's profit and costs a day.
- On the way: long trip, bill, receipt and truck numbers in page headings wrap on a phone (the trip page was 12 px too
  wide at 390 px); the trip page's old line "shared costs are not spread to trips yet" is gone.

**Tests:** 277 passing (new `TruckCostsTest`, 3 tests: papers, salary and 90 days of the truck's own bills — not older ones
nor a trip's — to ₹1,200 a day, a three-day trip's ₹3,600 on the trip page, the truck page, the routes report and the owner
app, a payment tagged to the truck, an attached truck counting none, a trip's days in the company's time zone).

## 2026-10-26 — Milestone M36: the screens in Hindi (decision P26 — a first translation, to be reviewed)

**Built (in `webapp/`, `lang/hi.json`):**
- **Every web screen, email and notice in Hindi** — 3,973 strings (the screens' words and the labels kept in code: statuses,
  types, lists), in everyday office Hindi with the English words offices use (ट्रिप, बिल, LR, GST, EMI…), from one glossary.
  Choose it under My profile (or `?lang=hi`). **A first translation: a Hindi speaker should read it before launch.**
- **Each reader in their own language**: inbox notices and pushes are written once per reader language (a Hindi driver and
  an English co-driver of the same trip each read theirs), the morning summary likewise, and the phone API answers in the
  person's chosen language (`Locales::using()`).
- Context slips fixed on the way: a free truck now reads **Available** (it read like "free of charge"), unused LR leaves
  **Unused**, two "Open" links **View**.
- `TranslationsTest`: every `__()` / `trans_choice()` string has its Hindi with the same placeholders and plural parts — a
  new string without its Hindi fails the suite.

**Tests:** 274 passing (new `TranslationsTest`, 2 tests: coverage and placeholders of every string; a Hindi screen, a trip
notice in Hindi and in English to two crew, the owner app's labels in Hindi).

## 2026-10-26 — Milestone M35: truck loans and EMIs (decision P25)

**Built (in `webapp/`, `Fleet/Services/TruckLoans`, Trucks → EMIs):**
- On the truck: the financier, EMI, its day, the loan's end (there already), now also the **first EMI**, the **interest
  rate** and — for those who keep the books — **what the loan owed at the start of the financial year** (the opening of
  the truck's own loan ledger, "Truck loan — KL 07 AB 1234 (HDFC Bank)").
- **Trucks → EMIs**: every truck on loan with its EMI, the next one due (amber within a week), overdue ones in red,
  what is still owed (from the books) and the loan's end; **EMI paid** on each row (which month, the date, the amount, the
  interest part — by default the rate on what is owed —, cash or bank with the UTR) posts a payment voucher: principal
  off the loan, interest as the truck's cost (so the truck's profit counts it). Once per EMI; a list of EMIs paid with
  their vouchers. The truck page shows the next or overdue EMI and what is owed.
- Alerts: overdue EMIs (critical) and EMIs due within three days on the dashboard; EMIs of the coming week in the
  morning summary. The owner app sees and pays them (`GET /owner/emis`, `POST /owner/trucks/{id}/emis`, screen O31).
- The demo has two trucks on loan (HDFC Bank, Shriram Finance).
- Migration 000027: `trucks.loan_rate`, `loan_starts_on`, table `truck_emis`.

**Tests:** 272 passing (new `EmiTest`, 2 tests: the loan set from the truck form with its opening, the next EMI, two
overdue on 10 December on the page and the dashboard, the default interest, a bank EMI needing its UTR, paid once, no
EMI on a wrong day, the voucher's principal, interest and truck, what is owed after and next month's interest, the truck
page; the owner app's list and an EMI paid in cash once per key with the defaults).

## 2026-10-26 — Milestone M34: odometer against GPS (decision P24)

**Built (in `webapp/`, `Road/Services/GpsDistance`, `fleet:gps-check`):**
- Each ended trip gets its **distance by GPS** (the crew phone's points, without fake locations, poor fixes or jumps no
  truck can make) and the **share of the trip the GPS covered**, beside the odometer's km. With good coverage (80 %+)
  and the two more than 15 % apart the trip is flagged — a tampered odometer or a run nobody entered.
- Shown on the trip page (Distance: odometer · GPS · coverage, the warning), as a dashboard alert for the last 30 days
  linking to the trip list's new "Odometer and GPS apart" filter (also in the trip report's words), and in the owner
  app's trip (`gps_km`, `gps_coverage`, `km_check`, `km_gap`).
- Measured when the trip ends and again each night for three days, as phones that were offline send late points.
- Migration 000026: `trips.gps_km`, `gps_coverage`, `km_check`.

**Tests:** 270 passing (new `GpsKmTest`, 2 tests: a 100-minute run of points with a fake one and a jump left out — about
100 km, full coverage, the 250 km odometer flagged on the trip, the dashboard, the trip list and the owner app, then
corrected and found agreeing by the nightly run; 20 minutes of GPS on a five-hour trip not compared).

## 2026-10-26 — Milestone M33: the routes report (route analysis)

**Built (in `webapp/`, `Reports::lanes()`, Reports → Routes, `GET /owner/reports/routes`):**
- The trips that ended in the period **by route** (from → to; an empty run on its own line): trips, and per trip the
  income (the LRs' freight and charges, billed or not — or the bill when that is more), the cost from the book, the
  profit and margin, the km, income per km, days on the road, the best and worst trip, the three main costs (fuel, toll,
  loading…) and the last trip with its freight — what to quote the next client on the lane. Excel (CSV) like every
  report; the owner app has it (screen O30).

**Tests:** 268 passing (new `RoutesReportTest`: two loaded trips and a toll on one, an empty run back — the lane's
averages, margin, km and per km, the main cost, the empty line; the CSV row; the owner app's answer).

## 2026-10-26 — Milestone M32: the morning summary (decision P23)

**Built (in `webapp/`, `Reports/Services/Digest`, `fleet:digest`, view `emails/digest`):**
- **At 07:00 company time**, by email and in the inbox (and the phone, low priority): yesterday's trips started and ended
  (with the km), on the road now; money billed, received, paid out and spent on the road (by kind); the month's profit
  so far; what waits — entries to approve, trips to settle, PODs to collect, LRs to bill, overdue bills — and papers;
  the five clients who owe the most, overdue. The subject is the headline ("Sharma Roadlines — Sunday 25 Oct: 2 trips
  ended · ₹50,000.00 received · ₹25,520.00 overdue").
- **Daily, weekly (Monday, for last week) or off**, per person and company, under My notifications on the web and in both
  apps (`digest`); owners get the daily one until they choose. Only people who see the whole company; none for a
  read-only company; each summary once (`digest_deliveries`), whatever the hourly run does.
- The email is plain tables with inline styles (mail apps drop style sheets) and fits a 375 px phone.

**Tests:** 267 passing (new `DigestTest`, 2 tests: two trips ended, a bill and a part payment yesterday and an overdue
balance — nothing at 06:10, the owner's email at 07:10 with the headline, the amounts, the km, the overdue client and the
settings link, the inbox notice, nothing twice; the choice off on the web and weekly from the phone, a wrong value
refused, nothing on Sunday, the manager's weekly email on Monday with last week's dates).

## 2026-10-26 — Milestone M31: quotations and payments in the owner app (decision P22)

**Built (in `webapp/`, `Api/Owner/DealsController`; api-v1 §12):**
- **Quotations from the phone**: the list by tab (open, accepted, closed, all), one with its routes, **new** (once per
  key; today's date, 15 days' validity and the usual terms by default; `issue` sends it at once), a draft saved again,
  **send** (the `QT/` number, the client's link and the WhatsApp words with it), the answer (accepted with how, declined,
  cancelled with why), **copy** for a new offer. The rules moved into `Quotations::rules()` / `input()`, shared with the web.
- **Payments from the phone**: the payees with what we owe them, a payment voucher (`PV/`) from cash or the bank (UTR for
  the bank), once per key, `accounts.post` as on the web. The posting moved into `Accounts/Services/Payments`, shared
  with Accounts → Payment.
- Fixed on the way: a new vendor, supplier, broker or crew member could not be paid until something had been posted to
  them (no ledger yet); the payee list now makes their ledger, on the web too.
- Handover: screens O28 Quotations, O29 Pay.

**Tests:** 265 passing (new `OwnerDealsTest`, 2 tests: a quotation refused without a destination, made once per key with
the defaults, saved again with a second route, sent with its number, link and words, not changed once sent, listed by
tab, cancelled only with a reason, accepted, copied, a viewer reading only; payees found by name, a bank payment needing
its UTR, paid once per key, the vendor's balance, no cash or bank payees, a manager without the right).

## 2026-10-26 — Milestone M30: number series per GSTIN and per branch (decision P21)

**Built (in `webapp/`, `Settings/Services/DocumentNumbering`, Settings → Numbering):**
- **A run per GSTIN** (GST Rule 46): with two or more GSTINs the main one keeps the company-wide series and each other
  GSTIN numbers its tax invoices, credit notes and debit notes in its own run, starting with its branch's code
  (`BOM/26-27/00001`, `BOMCN26-27/0001`, always within 16 characters). Before, every GSTIN's series started from the same
  `INV/` prefix, so the never-twice guard interleaved them and no GSTIN had a consecutive run.
- **A branch with its own GSTIN bills from it**: a bill of that branch's trips (or started in that branch) takes the
  branch's GSTIN, and the tax is worked out again from that state (a Mumbai bill to a Kerala client is IGST).
- **Per-branch series** for LRs, quotations and hire challans: "Number each branch separately" on the company-wide
  series (shown once there are two branches); each branch's run starts as `BOM/LR/…`; a document with no branch keeps
  the company series.
- `{BR}` prints the branch's code (it printed the branch's database number before).
- Settings → Numbering: the company-wide series, then **Per branch** and **Per GSTIN**, each with its live preview.

**Tests:** 263 passing (new `SeriesTest`, 2 tests: two GSTINs — the main bill `INV/26-27/00001`, the Mumbai branch's bill
`BOM/26-27/00001` from its own GSTIN with IGST, its credit note `BOMCN26-27/0001`, the screen's Per GSTIN previews; the
per-branch switch, the screen's Per branch previews, a Mumbai LR `BOM/LR/26-27/00001` and a branch-less one in the company
run, `{BR}` as the code).

## 2026-10-26 — Milestone M29: the owner app's masters (13-parity #9)

**Built (in `webapp/`, `Api/Owner/MastersController`; api-v1 §12 "Masters"):**
- **Clients, places, trucks and crew from the phone**: card lists with search and Active / Inactive (Working / Left
  for crew), open one, **add** (once per Idempotency-Key), **edit** (only the fields sent change, so the phone sends
  just what was edited), **deactivate** — never delete. The web's permissions and checks: a client's GSTIN / TRN checked
  on add (tax numbers otherwise on the web), unique codes made from the name, a truck's registration against the fleet
  and the plan's truck limit, the odometer typed once, a truck on a trip keeps its status, a crew member's mobile unique
  and not changed from the phone while they use the crew app, "has left" refused while on a trip that has not ended.
- Client cards show what is billed and not paid; crew cards the licence and papers chips; the forms' choices are in
  `GET /owner/masters` → `forms`.
- A person limited to a branch adds trucks and crew only in that branch (the branch sent is ignored for them).
- Handover: screens O24 Clients, O25 Places, O26 Truck form, O27 Crew.

**Tests:** 261 passing (new `OwnerMastersTest`, 3 tests: clients — add once per key, the code and GSTIN, a taken
code, edits of one field, no tax number on edit, deactivate and the lists, search —, places — the code and type, the map
pin both-or-neither —, a viewer reading only; trucks — add, a duplicate registration, edit, the odometer kept, the
status —, crew — the list with papers, add with licence and pay, a taken mobile, the app user's mobile, leaving while on
a trip, leaving and back —, the workshop's rights; the branch limit on adds and edits).

## 2026-10-26 — Milestone M28: my profile, the company's business details and Help (13-parity #14, #28, #30)

**Built (in `webapp/`, `Platform/Auth/Profile`, `Platform/Tenancy/CompanyProfile`; decision P20):**
- **My profile** (the header menu → My profile, and `GET`/`POST /profile` for both apps): name, screen language (the
  screens switch at once), date of birth (optional) and a **photo** — the middle square, 512 px, re-saved as JPEG so the
  camera's data is dropped, private (served only to its owner); shown in the header and the app's drawer
  (`/me` → `account.photo_url`). Email and mobile are shown, not changed (they are how one signs in). Audited.
- **Business** for the owner app (`GET`/`POST /owner/company`, logo at `/owner/company/logo`): the company as it prints
  — names, address, contacts, logo, GSTIN/TRN listed — read by the office, changed with `settings.company` under the
  same rules as Settings → Company (both now use `CompanyProfile`).
- **Help** (the header menu → Help, and `GET /help`): WhatsApp, call and email support from the provider's settings
  (only those set; the contact form when none), questions and answers, terms, privacy, the version.
- A read-only company (plan ended) still lets people keep their own profile and notices (web and app).
- Handover: api-v1 §13, screens O22 My profile (Personal · Business), O23 Help, C26 Settings.

**Tests:** 258 passing (new `ProfileTest`, 3 tests: the web profile — refusals, the language followed, the photo
squared to 512 and served, removed in a read-only company —; the apps' profile and photo, `/me`, a PDF refused, Help, the
Business tab read and saved with a logo, a viewer reading but not saving; Help with and without the provider's contacts).

## 2026-10-26 — Milestone M27: the crew money desk (13-parity #19, #23)

**Built (in `webapp/`, `Accounts/Services/CrewCash`; decision P19):**
- **Quick cash on a crew member's page** (the new Khata card, with `trip.settle`) and in the owner app
  (`GET /owner/crew/{id}/khata`, `POST /owner/crew/{id}/cash`, once per Idempotency-Key): **Given to them** or
  **Spent for the company**, the amount, what for (required), the date (not ahead), and one of their trips that is sent
  and not settled. Given for a trip is that trip's advance (the settlement counts it); given without a trip is an
  **advance against pay** or **paying what we owe them**, on a payment voucher (PV/); spent is the expense — on the trip
  and truck when chosen — owed to them with their pay. Every entry is audited; a wrong one is reversed in the books.
- The card shows the khata in short: trip cash, earnings, **advance against pay** and the net in words, with a link to
  the full statement. The khata's net now counts advances everywhere (ledger page — a fourth card —, owner app,
  My Khata's new `advance`); before, a payslip's loan deduction was left out of the net.
- **People list** (#19): each person's papers as one chip (All in order / N expired · N missing · N due soon, from the
  required papers and expiry dates, in a few queries for the whole page) linking to their papers, the mobile to tap and call,
  and **Share contact** on WhatsApp (name — role — mobile); the same chip and Share contact on the person page.
- Updated the handover: api-v1 §12 (the two crew-cash calls, the masters' cash lists, `advance` in My Khata, the ledger's
  `buckets`), screens O21 **Crew cash**.
- Fixed on the way: a screen-reader label in the people table's last column widened the whole page on a phone.

**Tests:** 255 passing (new `CrewMoneyTest`, 3 tests: cash for a trip as its advance and in the settlement, an advance
against pay by UPI with its reference, spending on a trip and truck, paying dues back, the buckets and the words, the
refusals — another person's trip, a date ahead, no remark —, a viewer without the form; the owner app's khata and a
write once per key, 428 and 422; the people list's chips and the share link, the person page's chip and Share contact).

## 2026-10-26 — Milestone M26: notification priorities and my notification settings (13-parity #15, #29)

**Built (in `webapp/`, `Platform/Notifications/NotificationSettings`):**
- Every notice has a **priority**: high (a trip sent or called off, a second approval asked of you, an incident), medium
  (questions, entries waiting, papers, quotations), low (pay paid, the test). The bell's page shows unread ones first,
  high ones on top with an **Urgent** chip and a red dot; the push carries it and a low one does not wake the phone.
- **My notifications** (the bell → My notifications, and `GET`/`PUT /notifications/settings` for both apps): switch off
  any group for the phone (trips, my entries, entries to approve, incidents, papers, pay, quotations) — the inbox keeps
  them — and set **quiet hours** (across midnight too) when only high notices ring. Per person and per company.

**Tests:** 252 passing (new `NotificationSettingsTest`, 3 tests: the priorities, the inbox order and the push urgency; a
group off and quiet hours at 11 pm and 9 am, per company; the phone API's settings, validation and `priority`, the
quiet-hours arithmetic).

## 2026-10-26 — Milestone M25: the trip desk (13-parity #3–5, #25)

**Built (in `webapp/`):**
- **"+ New" inside the trip form** (#3): client (name, mobile), driver (name, mobile), truck (registration, RC owner),
  place (name, full address → a customer site) in a dialog; the new record joins every list it belongs in and is
  chosen at once (a new place fills From when empty, else To); the rest is completed later on its own page. Each needs
  its own right (`party.manage`, `people.manage`, `truck.manage`, `settings.masters`); the plan's truck limit holds.
- **Trip list → Billing filter** (#4): Not invoiced / Invoiced, unpaid / Paid, with the other filters and the totals bar.
- **Trip report** (#5) of the filtered list: **Report** (the letterhead, when it was made, the period and the filters in
  words, one row per trip — date, number, route, truck, driver, client, freight, other charges, total, office note —
  and the totals; landscape A4 to print or save), **PDF** (server Chromium, else the page) and **Excel** (CSV with a BOM;
  cells that would run as formulas made plain).
- **Annexure driver column** (#25), off by default, switched on in the bill template; "Total trips / LRs" and
  "Subtotal (N LRs)" were there already.
- Fixed on the way: a consolidated bill with several LRs read each trip's truck one by one (now loaded with the bill).

**Tests:** 249 passing (new `TripDeskTest`, 3 tests: the four quick adds and their refusals, a trip planned with them,
who may; the billing filter on three trips (paid, billed, not billed), the report's rows, words and totals, the PDF
fallback, the spreadsheet's rows; the annexure without and with the driver column).

## 2026-10-26 — Milestone M24: the client's billing desk, quotations answered online

**Built (in `webapp/`; 13-parity #20–21; decision P16 extended):**
- **Client page → Billing** (for clients, with `invoice.view`): **Not billed yet** (the LRs waiting and their value),
  **Billed, not paid**, **Overdue**, **Received this year**; the LRs to bill with tick boxes → **Make the bill for the
  ticked LRs**; the bills with status, balance, PDF and **Settle** (the receipt form filled for that bill); the payments
  with mode, reference and the bills they paid, **Add payment**; the client's quotations, **New quotation**. The stale
  "Ledger — coming later" tab is gone (the Ledger button stays).
- **Quotations answered on the client's link**: a sent quotation gets a secure link (`/d/{token}`, like a bill), in its
  email and WhatsApp words. The client reads it on any phone and **accepts** (name, a PO number) or **declines** (why)
  — once, while it is not expired. The office sees "Answered on the client link by …", when it was first opened, and
  gets a notice ("ACC Limited accepted quotation QT/…"); logged.
- The statement, quotation and voucher pages fit a phone (wide tables scroll inside themselves).
- Branch limit (M22) closed on two more paths: the owner app's approvals list and decisions, and a truck's or a
  person's papers opened by URL.
- "LRs waiting to be billed" is one rule (`Consignment::waitingToBill`) for the bills page, the client page and the app.

**Tests:** 246 passing (new `ClientBillingTest`, 2 tests: the billing tab's totals and lists, billing ticked LRs,
Settle filled for the bill, payments and the year's money, a viewer without the buttons, the workshop without the page;
the client link in the words, read without signing in (not indexed), opened noted, decline needs a reason, accepted once,
the office's notice and audit, no answer on an expired quotation).

## 2026-10-26 — Milestone M23: the owner app makes things

**Built (in `webapp/`, `Api/Owner/WriteController`; api-v1 §12; decision P18):**
- `GET /owner/masters`: everything the phone's forms pick from (places, clients, trucks with the trip they are busy on,
  crew, materials, branches, bank accounts, freight bases, payment types, receipt modes, paper types).
- `POST /owner/trips` (with its load, and `send` to the crew at once — or nothing is made if sending is refused) and
  `POST /owner/trips/{uuid}/send`; `GET /owner/unbilled` and `POST /owner/invoices` (a bill from LRs, `finalise` to issue)
  and `/finalise`; `POST /owner/receipts` (cash, UPI, NEFT, cheque, TDS, deductions; set against the oldest bills);
  `POST /owner/papers` (photos or a PDF of a truck's or a person's paper).
- **Run once**: every write needs an `Idempotency-Key`; the answer is kept (with the crew's commands) so a retry never
  makes a second trip, bill or receipt; refusals are not kept.
- The trip form's rules now live in `Trips/Concerns/ValidatesTrips`, used by the web and the phone.

**Tests:** 244 passing (new `OwnerWriteTest`, 3 tests: the masters, no key 428, a refusal not kept then the same key
working, plan and send, the replay giving the same trip, a key reused for something else 409, a busy truck refusing
with nothing left half made, a draft sent later, viewer 403; LRs waiting, a bill issued at once and its replay, a draft
issued afterwards, an LR billed twice refused, money by NEFT with TDS set against the oldest bill, a manager and a
viewer refused; a paper with a PDF, without a file refused, ID paper types hidden, another company's truck not found).

**Not yet:** hire challans, credit/debit notes and payments from the phone; quotations on the phone.

## 2026-10-26 — Milestone M22: branches at work

**Built (in `webapp/`, `app/Platform/Tenancy/{BranchContext,HasBranch}`; blueprint 04 §1; decision P17):**
- **The branch filter** in the header for companies with two active branches or more ("All branches", Kochi, Mumbai…):
  trips, trucks, people, bills, quotations, the approvals inbox and its badge, LRs waiting to be billed, the dashboard
  (trucks, trips, approvals, money this month, the chart, spending today, the LR-to-cash queues), the live map and the
  profit and loss follow it. Trucks and people with no branch show in every branch. It is a view: the owner still opens
  any record.
- **Staff limited to a branch**: Settings → Team → a person → **Works for: only the Mumbai branch** (an owner cannot
  be limited). They see only that branch's lists, cannot open another branch's trip, truck, person, bill or quotation by
  its URL (not found), cannot decide another branch's crew entries, and plan trips only for their branch; the owner app
  lists (trips, trucks, bills) follow the same limit. Changing it is logged.
- New trips, bills and quotations start in the branch shown; a bill made from LRs takes their trips' branch.
- Demo: trucks and trips in Kochi and Mumbai; **vikram@fleet.test works for the Mumbai branch only**.

**Tests:** 241 passing (new `BranchTest`, 2 tests: the filter on trucks, trips and bills, the owner still opening other
records, a new trip's branch, the dashboard count, back to all, another company's branch refused, no filter with one
branch; a manager limited on the team page — not an owner — the lists, the 404s, trips only for the branch, the owner
app's trips and trucks, back to the whole company).

**Not yet:** per-branch number series on the screens (the `{BR}` token exists), branch on documents, payroll and
receipts, a branch-wise trial balance, branch-to-branch transfers (phase 2: manifests, transshipment, D16).

## 2026-10-26 — Milestone M21: quotations, pre-printed LR books, vouchers to sign

**Built (in `webapp/`; blueprint 10 §1, §3, §6.3; decision P16):**
- **Quotations** (Invoices & LR → Quotations): a client, dates, subject, printed terms and an office note, and routes
  with the rate contract's columns (from, to, material, truck type, basis, rate, minimum weight, detention, loading and
  unloading, a note). Draft → **sent** with the next `QT/26-27/0001` number and fixed (a new offer is a copy) →
  accepted (how: "PO 4512") or declined (why) or cancelled; past its date it shows **Expired**. Printed on the
  letterhead with an acceptance block, as PDF, **by email** with the company's words (Settings → Messages now has
  "Sending a quotation") and **WhatsApp**. Accepted: **Make it the rate contract** (a draft on the client's page with the
  same routes, approved as usual — then their LRs fill the rate in); every route has **Start a trip** (the trip form comes
  filled in, the load remembers the quotation, the quotation lists its trips).
- **LR books** (Invoices & LR → LR books): the register of pre-printed books (book no., printed prefix, first and last
  leaf, branch, given to, date; no overlaps). The load form has **LR number from a printed book** while books are in
  use: it is taken only from a free leaf of a book in use ("a-01005" is leaf A-1005), refused when outside every book,
  already used, cancelled or lost, or the book is closed; empty → the automatic series as before. An LR keeps its number.
  Each book's page shows every leaf (used — with its trip —, cancelled, lost, free, **skipped**), the counts, and lets the
  office mark a leaf spoilt or lost with why (or free it again) and close the book; all logged.
- **Vouchers to sign**: every voucher's page has **Print to sign** — payment, receipt, journal or contra voucher on the
  letterhead with paid to / received from, through which cash or bank, the reference, the amount in words, the lines and
  prepared / approved / received boxes.
- The statement, the quotation and the voucher share one page style and letterhead (`documents/_page-*`).
- Demo: a sent quotation to Kerala Agro Foods (two routes), the Aluva agent's LR book 12 (A-1001–A-1100, one leaf spoilt).

**Tests:** 239 passing (new `QuotationTest`, 3 tests: the draft with a wrong row and wrong dates refused, sent with its
number and fixed, the print with every column and no office note, PDF fallback, email and delivery, WhatsApp text,
expired, copy, viewer; accepted → rate contract → approved → the rate finder uses it, a trip from a route remembering
the quotation, a cancelled one starting none; the payment voucher print. New `LrBookTest`, 2 tests: the register and
its refusals, typed numbers in any form, refused outside / twice / lost / closed, the automatic series, the report and
skipped leaves, freeing a leaf, the audit, viewer; a typed leaf on a load added to a sent trip, an LR's number never
changing).

**Not yet:** a client accepting a quotation online (a secure link like the bill's), quotation templates in the template
editor, LR books handed to the crew app (the driver typing the leaf), receipt vouchers for advances under GST,
multi-currency bills (with the UAE launch).

## 2026-10-26 — Milestone M20: notifications — the bell, the phones' inbox, Firebase push

**Built (in `webapp/`, `app/Platform/Notifications`; decision P15; api-v1 §11):**
- **One inbox per person and company**: a bell with the unread count in the web header and a Notifications page
  (open one → it is read and takes you there; mark all read); the same list for both phone apps
  (`GET /notifications`, `POST /notifications/read`, `unread_notifications` in `GET /me`).
- **What is told to whom** (`Notices`): the crew — a trip sent to them or called off, a question on or rejection of
  their entry (with the office's note), their pay paid (amount, how, reference); the other driver — an entry waiting
  for their second approval; whoever approves — **one** "N entries wait for your approval" notice that keeps count,
  is pushed again only after 30 quiet minutes and is cleared when nothing waits; whoever sees trips — an incident at
  once; whoever keeps the papers — the day's paper reminders (with the email).
- **Firebase push** (`Push/FcmGateway`, FCM HTTP v1): the service-account key's JWT is signed with PHP's OpenSSL and
  exchanged for an access token kept for the hour — no SDK, nothing installed. Android high priority on channel
  `fleet`, iOS badge = unread count, `data` with the company and what to open. A phone token Firebase no longer knows
  is forgotten; a busy Firebase is retried by the queue; nothing here can fail the request that caused it. Without a
  key (`PUSH_DRIVER=log`, the default) pushes go to the log. `php artisan fleet:push <mobile|email>` sends a test.
- Handover: api-v1 §11 (calls, the push message, every `kind` and what to open), local set-up §5 (Firebase project,
  the key outside the repository, `php artisan queue:work`). The demo's owner has two notices waiting.

**Tests:** 234 passing (new `NotificationsTest`, 4 tests: a trip called off and a trip sent reaching the crew's
phones, a crew member without a token keeping it in the inbox, the phone inbox with paging, read and `/me`, another
person's notice untouched; the co-driver asked, one counting notice for approvers held then pushed after 31 minutes,
the bell and page, opening and its redirect, another's notice not found, a question to the driver, the notice updated
and cleared, the workshop told nothing; an incident, pay paid, papers due; Firebase — the JWT verified with the
key, the v1 message, the token cached, an unregistered phone forgotten, Firebase down marked failed, no key → log).

**Not yet:** a person's choice of which notices reach the phone and quiet hours, notices in the person's own language
(Q1), WhatsApp or SMS notices (needs a provider — your approval), email digests of the bell.

## 2026-10-26 — Milestone M19: opening balances, bank reconciliation, statements to print and send

**Built (in `webapp/`, `app/Modules/Accounts`; blueprint 07 "Bank reconciliation"; decision P14):**
- **Accounts → Opening balances**: debit and credit per ledger for the balance-sheet ledgers (clients, suppliers,
  crew, cash, banks, loans, capital), dated the first day of the financial year; or **bring them from Tally or Excel**
  as a CSV (Name, Debit, Credit — or Name, Amount, Type), matched by name to ledgers and parties (a party without a
  ledger gets one; names not found are listed). What does not balance sits in "Difference in opening balances" and the
  page says by how much. Not changed while the books are locked; logged. New system ledger "Owner's capital".
- **Accounts → Bank reconciliation**: choose the bank (the company's default account first), **bring in its statement
  as CSV** — the header row is found below the bank's title lines, column names of SBI, HDFC, ICICI, Axis and UAE banks
  are read, dates day first (with or without the time), "1,23,456.50", "(500)" and "250 Dr" amounts. Lines brought in
  before are left out (two alike lines in one file stay two). **What is clear matches by itself** (one entry of the same
  amount and side within 7 days, or the one carrying the line's UTR / cheque number); the rest are matched by hand from
  the entries that fit, **booked as bank charges or interest** in one click, or left out with a reason; undo any time.
  The top shows the book's balance and the bank's on the statement's last day, what is on the statement but not in the
  book, what is in the book but not on the statement (cheques not yet cleared), and whether that explains the gap.
- **A party's statement of account** (Ledgers → a ledger → Statement / PDF): on the letterhead with the party's tax
  ID, opening, every entry, closing and "X will give you / you will give X"; prints, saves as PDF (server Chromium, or
  the print page), and **Email this statement** with the company's words (Settings → Messages now has "Sending a
  statement" for email and WhatsApp); every send is kept.
- The demo (`fleet:demo`) brings in an HDFC statement: two lines matched by themselves, SMS charges and interest left to
  book, and the reconciliation agreeing.

**Tests:** 230 passing (new `BankTest`, 4 tests: opening balances typed, the difference, income ledgers left out, the
CSV with a BOM and an unknown name, a bad file, the lock, who may; a statement printed, the PDF falling back to the print
page, emailed with the HTML attached and the delivery kept, a viewer refused; a bank CSV with title lines — auto
matches by amount and by UTR, the same file again adding nothing, the summary and the page; match by hand, a wrong
entry refused, bank charges booked once, a line left out needing a reason, the gap shown, viewer / accountant, another
company's line not found).

**Not yet:** bank feeds straight from the bank (needs an aggregator — your approval), PDF or Excel statements (save as
CSV first), matching one bank line to several book entries (a cheque paying two bills — book one receipt for both),
reconciliation of the cash book.

## 2026-10-26 — Milestone M18: bills and receipts by email, message templates, overdue reminders

**Built (in `webapp/`, `app/Modules/Invoicing/Services`; blueprint 10 "Email", "Reminders", "Message templates"):**
- **Send by email** on every issued bill and receipt: to (up to five addresses), copy to, subject and message filled
  in from the company's wording and the document; the **PDF attached** when the server can make one, the **secure
  link** in the message, replies going to the company's email. Every send — or failure — is kept with the bill's
  deliveries; a bill sent by email turns "sent". Laravel's own mailer (set `MAIL_*` in `.env`; nothing new installed).
- **Settings → Messages**: the words for sending a bill, a receipt and an overdue reminder, by email (subject and
  message) and WhatsApp, with `{placeholders}` (company, client, document, number, amount, total, dates, days
  overdue, link) and a live preview with sample values. Left as shipped (or reset), a message follows the shipped
  words. The WhatsApp share on the web and in the owner app uses the same templates.
- **Overdue reminders**: switched on in Settings → Messages with the days after the due date (3, 15, 30 by default);
  `php artisan fleet:reminders` (daily, 09:05 India time) emails the client once per step; a bill found late gets
  one reminder for the last step reached, not a pile; a client can be kept out ("Email them reminders" on the client's
  page); read-only companies and credit notes are left out; a client without an email is noted, not retried.
- Documents are named as printed in messages ("Tax invoice", "Bill of supply", "Credit note").

**Tests:** 226 passing (new `MessagesTest`, 3 tests: a bill by email to two addresses and a copy with the link, the
delivery and status, the WhatsApp text from the template, a receipt by email, who may send; the company's wording with
the preview, wrong reminder days refused, the reset; reminders off by default, once per step, a client kept out, a
bill found late, read-only companies).

**Not yet:** WhatsApp Business API sending (phase 2 — today WhatsApp opens on the phone with the text ready), SMS,
statements by email (statements as PDF come with M11 part 3), per-language message sets (Q1).

## 2026-10-26 — Milestone M17: your data — export, deleting a company, plan limits enforced

**Built (in `webapp/`, `app/Platform/Data`; blueprint 09 §8; decision P13):**
- **Settings → Your data**: **download everything** as one ZIP — a CSV per table of the company's data (found from the
  database itself, so a new table is never forgotten), the company and its team, bank and ID numbers readable,
  one-time codes and secret links left out, cells that would run as spreadsheet formulas made safe, every uploaded
  file, and a read-me with the row counts. Works while the company is read-only; at most three an hour; logged.
- **Delete this company**: type its name, your password and why; it happens **72 hours later** (a red banner on every
  screen until then, all owners emailed) and anyone allowed can cancel. `php artisan fleet:deletions` (hourly) then
  writes one **AES-256-encrypted copy** for the law (kept 8 years, `RETENTION_YEARS`), removes every row and file of
  the company (other companies untouched), leaves the company's row as "deleted" for the console, and later removes
  the copy. The console shows deleted companies and the copies kept.
- **A plan's modules now switch parts off**: leaving a module out of a plan takes its permissions away for everyone
  in the company — menu, pages and phone API alike (`Plan::MODULE_PERMISSIONS`; payroll is now a module too).
- **Storage limit enforced**: office uploads are refused when the plan's storage would be exceeded; the crew's road
  photos are never refused; the dashboard warns from 90 %.
- The website FAQ and the draft terms and privacy texts now describe the export and the deletion as built.

**Tests:** 223 passing (new `DataTest`, 4 tests: the ZIP's contents — another company's data left out, bank numbers
readable, secret columns and tables out, files in — read-only still downloads, who may; the deletion countdown with
wrong name and password refused, the banner, cancel, nothing at 71 hours, the purge at 73, the other company untouched,
the law copy opening only with its password, the console, the copy removed after 8 years; a plan without invoicing on
the web and in `GET /me`; the storage warning and refusal).

**Notes:** the law copy's password comes from the app key and the company (`CompanyDeletion::password`) — keep the
`APP_KEY` safe and never change it on a live server, or old copies cannot be opened.

## 2026-10-26 — Milestone M16: the basic driver pay run

**Built (in `webapp/`, module `app/Modules/Payroll`; 13-parity #16, #24; decision P12):**
- **Payroll** in the menu (was "soon"; `payroll.view` to see, `payroll.run` to make, approve and pay — the owner and
  the accountant by default). A **pay run for any period** (up to three months) for the drivers and helpers, or for
  everyone with a pay set.
- **Pay worked out from each person's pay basis**: monthly salary prorated by the days of each month inside the
  period and their employment (joined on the 10th of October: 22 of 31 days); **per trip** — trips ended in the
  period; **per km** — kilometres of their driving shifts. People with no pay set are listed with a link to set it.
  Nobody is paid twice for a day (overlapping runs leave them out, with a message).
- **Draft**: add bonuses, overtime, allowances, or deductions (trip cash recovered, loan or advance recovered, fine or
  damage, other) with a description; work everything out again from the latest trips and rates; leave a person out.
  Deductions above the pay are refused.
- **Approve**: each payslip's pay is owed — salary expense debited, the person's earnings credited (deductions take
  back trip cash or a loan, or are recovered as income). **Mark paid** per payslip (cash, UPI or bank, date,
  reference), optionally **paying all owed** — the pay plus bata already owed from settled trips — in one payment;
  the run turns paid when everyone is. **Cancel** while nothing is paid reverses every posting. `fleet:books` knows
  payslips and their payments.
- **Payslip** to print or save as PDF (base, additions, gross, deductions, net, how and when paid, bata included);
  the person's page lists their payslips; the crew app's My Khata (`GET /crew/money`) now has `payslips[]`.
- Demo: last month's pay run, approved, Ravi's payslip with a festival bonus paid by bank.

**Tests:** 219 passing (new `PayrollTest`, 3 tests: monthly prorated, per trip and per km pay, people without pay,
overlapping runs; bonus and deductions on a draft, refused once approved, approval into the books, payment with the bata
owed, the run paid, the payslip page, the person's page, the crew app's payslip, the books rebuild finding nothing
missing; cancelling an approved run by reversal, a new run for the same days, who may see payroll).

**Not yet (phase 2–3, decision P12):** attendance and leave, statutory deductions (PF, ESI, professional tax, TDS on
salary), Labour Codes, UAE WPS salary file, salary advances as their own entry (today: a loan deduction recovers one).

## 2026-10-26 — Milestone M15: the handover pack for the phone apps, and the owner app's API

**Built (in `docs/handover/` and `webapp/`; roadmap §2–3, blueprint 05):**
- **`docs/handover/README.md`** — what the phone session builds, the reading order, the founder's rules for phone
  apps (native B4A/B4I only, nothing installed without asking, version shown, PIN and fingerprint), where the
  projects live (`phoneapp/crew`, `phoneapp/owner`, `phoneapp/shared`), and how to ask for API changes.
- **`local-setup.md`** — the web app on the laptop (portable PHP, XAMPP's MariaDB untouched), reaching it from the
  B4A emulator (`10.0.2.2`), Nox and a real phone, cleartext only in debug builds, demo sign-ins for the web, the
  owner app (SMS code in the log) and the crew app.
- **`screens.md`** — 27 crew-app screens and 15 owner-app screens, each with what it shows and sends, the
  permission and the API call.
- **`sync-design.md`** — the phone's SQLite tables, one ordered send queue per company, photos after their entry,
  GPS batches resent under the same id, what to do with every answer (saved, refused, 401, 403, 423 read-only, 429,
  5xx), back-off, clocks after a restart, the location service rules for Android 14/15, pulling, tokens, the PIN,
  several companies, forward compatibility.
- **`api-v1.md`** gained §9 (signing the web in by QR) and §10 (the owner app's other screens), and My Khata.
- **The owner app's API** (`/api/v1/owner/*`, each behind the web's permission): the dashboard (money only for
  those who may see it), the live map (trips by their public id), trips with filters and search, a trip's detail
  (stops, loads with freight when allowed, crew answers, entries to check, spent, profit from the books) and its
  route, trucks with their next paper to expire, papers expiring or expired (ID papers only with
  `people.id_documents`, every look logged), bills by tab with what is due and days overdue, a bill's detail, sharing
  a bill on WhatsApp (marks it sent, gives the text and the link), ledgers with balances and a ledger's statement.
- **The crew's My Khata**: `GET /crew/money` — the trip cash they hold, what the company owes them, the net and
  their statement, from the books.
- **`php artisan fleet:code <mobile>`** prints a crew member's activation code (demo company by default, or
  `--company=<id>`), logged like the people page's.

**Tests:** 216 passing (new `OwnerApiTest`, 5 tests: dashboard, map, trips, trip detail and route, trucks, and who
sees money; papers with ID papers kept back and logged; bills, overdue, sharing on WhatsApp, ledgers and a statement,
and who may share; the activation-code command; My Khata after an advance and an approved fuel entry).

**Not yet (API v2, with the phone session's asks):** making a trip, an LR, a bill or a receipt from the owner app,
hire challans, uploading papers by camera; leave and salary slips in the crew app (with payroll); push
notifications from the server.

## 2026-10-26 — Milestone M14: the marketing website, sign-up from the pricing page, getting started

**Built (in `webapp/`; blueprint 04 §5, the website guideline, decisions D20 and P11):**
- **The public website** on its own layout: a splash with a real progress bar on the first page of a visit (it
  leaves by itself even without scripts), the version line in the footer, slanted sections and the product drawn
  as an office screen and a crew phone in HTML/CSS (no images to wait for), small reveal animations where the
  browser supports scroll timelines and none for people who ask for less motion, page titles, descriptions, Open
  Graph tags, `sitemap.xml`. "/" is the website for visitors and the dashboard for people signed in.
- **Home** (promise, trial button with the console's trial days, the three money leaks and their fixes, how a trip
  runs, the features, India/UAE specifics, data ownership), **Features** (trips, crew app, fuel control, invoices
  and LR, accounts and tax, trucks and documents, live map and reports, driver pay marked "coming soon"),
  **Pricing**, **FAQ** (grouped, searchable as you type, every answer in the page without scripts), **About us**,
  **Contact**, **Terms**, **Privacy** (DPDP Act 2023 and UAE PDPL) and **Refund policy**.
- **Pricing from the console's plans** (only those offered and shown on the website), per country (India / UAE),
  a monthly / yearly switch that works without scripts, the yearly saving, per-truck prices, "Free" for a free
  plan, and a **coupon box**: the price with the coupon and the full price struck through, or why the coupon does
  not apply (unknown, ended, not for these plans). "Start free trial" carries the plan and coupon into sign-up.
- **Sign-up** shows the chosen plan and coupon and keeps them on the company; the console's company page shows
  "Chose on the website" and fills them in for the first payment. The terms and privacy links open the pages.
- **Contact form**: checked fields, the picture challenge, a hidden field that catches robots, rate-limited; each
  message is kept (console → Overview, "Answered" clears it) and mailed to the support address with reply-to.
- **Console → Settings → Website**: replace About us, Terms, Privacy and Refund (plain text with `## ` headings
  and `- ` lists; HTML is stripped and everything escaped), go back to the shipped text, and set the crew app's
  download link (shown as a QR code).
- **Getting started** (`/welcome`): the first sign-in after confirming the email opens it; five steps that tick
  themselves from the data — company address, GSTIN/TRN and the financial year's first month (fixed once the
  books have an entry), the first truck, the first driver (with where to make the activation code), the crew app
  (QR of the download link), the first trip. A dashboard card shows "Getting started: n of 5 done" until the owner
  finishes or skips. Adding the company's tax registration now goes through one service (`Tax/Services/
  Registrations`) for Settings → Tax and these steps.
- The theme gained the ink shades 200, 600 and 800 (some screens used them and showed no colour).

**Tests:** 211 passing (new `WebsiteTest`, 6 tests: every public page, the splash once per visit, signed-in people
sent to the dashboard; pricing per country, the yearly saving, hidden and switched-off plans left out, coupons
applied or refused with the reason; the plan and coupon carried through sign-up to the console; the contact form's
checks, the robot trap, the message in the console and the mail; the console's website texts, escaped, and the reset;
getting started from the first sign-in to finishing, the financial year locked by the books).

**Notes:** the shipped Terms, Privacy and Refund texts are drafts written for this product — please have them
reviewed (refund period, governing law, grievance contact) and edit them in console → Settings → Website before
launch. The site shows no customer counts or testimonials until there are real ones (P11).

**Not yet:** online payment on the pricing page (needs a gateway, your approval), languages on the website (it is
English; the app has Hindi), blog / help articles, a company's own data export (`data.export`).

## 2026-10-26 — Milestone M13: plans, subscriptions, coupons, limits, storage, database export

**Built (in `webapp/`, `app/Platform/Billing` and the console; blueprint 04 §4, decisions D20 and P10):**
- **Plans** (console → Plans): name, description, country (or every country), currency (INR, AED, USD), priced
  per truck, flat or once for life; monthly, annual or lifetime prices; the most trucks, users and storage; the
  modules included; a student tier; shown on the website and the billing page or hidden; switched off, never
  deleted, once in use.
- **Coupons**: a percentage or an amount off, from and until dates, a use limit, for some plans or all; refused in
  plain words (switched off, not started, ended, used up, not for this plan, another currency).
- **Companies** (console → Companies): every company with its status (on trial, paying, read-only, suspended),
  plan, trucks, trial end, last seen and owner, searchable. A company's page shows its plan and use against the
  limits, and lets the super-admin **put it on a plan** (from a date, trucks billed, coupon, payment reference;
  the running plan ends the day before), **extend the trial** (with a reason), **suspend** it (nobody can sign in;
  nothing deleted) and **resume** it; its subscriptions, team and billing history.
- **The nightly sweep** (`php artisan fleet:subscriptions`, scheduled at 00:20 IST): subscriptions past their end
  date end; companies whose trial or plan has run out become **read-only** on the web and now also on the phone
  API (`423 read_only`; the phone keeps its queue and sends it after renewal; QR sign-in approval still works).
  `GET /me` gives the company's status, `read_only`, trial end and the announcement (api-v1.md updated).
- **Limits**: adding a truck or inviting a team member beyond the plan is refused with the plan's name and the
  way out; nothing already there is touched. A company on its trial has no limits.
- **Settings → Plan and billing** (`billing.view`; open while read-only): the plan or trial with its end date,
  trucks, team members and storage against the limits (meters), the plans offered in the company's country with
  the price for its trucks ("Free" for a free plan), buttons that open WhatsApp or email to the provider with the
  request filled in, and what has been paid. The trial card and the read-only banner link to it.
- **Console → System**: storage per company against its plan; **files no row points to** (left by a failed upload
  or a deleted company; files younger than an hour are left alone) counted and removed; the **database export**
  (mysqldump / mariadb-dump, gzip, streamed, never kept on the server, logged as sensitive, at most 6 an hour);
  the **announcement** shown at the top of every company's screens and in the apps.
- Every settings page on a phone: the side list now scrolls sideways instead of making the page wider than the
  screen (found while checking the billing page at 390 px).
- Demo: sample plans (Starter, Fleet per truck, Lifetime, Student; Fleet in AED for the UAE) and the coupon
  LAUNCH25. Console sign-in for local use: `php artisan platform:admin you@example.com`.

**Tests:** 205 passing (new `BillingTest`, 5 tests: plans and coupons in the console, a subscription with the coupon
taken off, a used-up coupon, a wrong period, moving plans; the nightly sweep making an ended trial read-only on the
web and the phone and a recorded payment opening it again, then the month running out; truck and invitation limits
and the billing page; trial extension, suspension and resumption with their reasons in the log; storage clean-up,
the database export and the announcement on the web and in `/me`).

**Notes:** restoring a database is done with the `mysql` command on the server (`gunzip < file.sql.gz | mysql
fleet`), deliberately not by uploading through a web page. Online payment (Razorpay or Stripe) needs your go-ahead
before it is added; until then payments are recorded in the console.

**Not yet:** online payment and automatic renewal reminders by email/WhatsApp, a company's own full-data export
(`data.export`), modules switched off per plan (the plan records them; the menu does not yet hide them), storage
limit enforced on upload (shown, not yet refused).

## 2026-10-26 — Milestone M12: the dashboard, the live map, route replay, work queues, reports

**Built (in `webapp/`, module `app/Modules/Reports`; blueprint 04 §3.1, §2):**
- **The dashboard** with real numbers: trucks (on trip, free, in workshop), trips running (sent to crew, to
  settle), crew entries to check (disputed, questions open), **profit this month** from the book against the same
  days of last month; the **live map** of running trips; **alerts**, most urgent first — papers expired or expiring
  within 7 days, a driver at the wheel longer than the company's limit without a break, a crew phone quiet longer than
  the company allows, crew entries with flags; **spent on the road today** by category; **income and costs for the
  last six months** from the book (a column chart drawn on the server: thin columns, a legend, a tooltip listing both
  series per month on hover or keyboard focus, and a table view); the **work queues from LR to cash** — POD pending,
  ready to bill, billed but not sent, overdue bills with the amount. Money tiles only for those who may see money
  (`report.view`, `accounts.view` or `ledger.view`).
- **Live map** (menu → Live map, `map.view`): every running trip where its crew's phone last was (Leaflet, decision
  D18; tiles from OpenStreetMap), refreshed every minute, a quiet phone in orange, the list beside with the
  vehicle, driver, route, speed and how long ago. Markers are drawn as circles (no image files); names reach the
  map as text, never HTML.
- **Route replay** on the trip page (running, ended, settled, closed): the GPS track, start and last point, the
  road entries that carry a place (popup with what and how much), and a play / pause slider that runs a truck dot
  along the track with the time and speed. Tracks over 2,000 points are thinned for the browser, the last point
  kept.
- Demo: the finished Kochi → Chennai trip now has its GPS track (with the night at Salem) and its entries on the
  map.
- **Reports** (menu → Reports, `report.view`; CSV with `report.export`), for a period: **trip profit** from the
  book for trips that ended (income, cost, profit, km, profit per km, margin); **road spending** by category and by
  who paid (crew cash, own UPI, fuel card, FASTag, pump credit…); **payments without receipt** by person, by route
  and each entry (authority, reason, place, status); **fuel and mileage** per truck (fills, litres, fuel cost, km of
  the trips that ended, km per litre against the truck's standard — below 95 % marked — and fuel per km);
  **drivers** (trips, km driven, hours at the wheel, money entries, rejected entries, payments without receipt).
- Leaflet 1.9.4 added to `package.json` (approved, D18); it loads only on pages with a map (its own file).

**Tests:** 200 passing (new `DashboardTest`, 4 tests: the owner's day with spending, map positions and the quiet-phone and
long-driving alerts; the route with entries and thinning; income and costs from the book, the work queues and who sees
money; every report and its CSV, and who may see and download them).

**Notes:** the OpenStreetMap tile servers are for light use; before launch switch the tiles to a provider with a key
(Mappls or Google, approved in D18). `npm audit` reports advisories in the Vite development server and
`launch-editor` (development tools only, not in the built app); updating them needs your go-ahead.

**Not yet:** a branch filter on the dashboard and reports, fuel against GPS distance (mismatch flags), a truck's own
GPS device, route analysis (rates and budgets per route), the outstanding-by-party report (in Accounts → Ageing for
now), scheduled reports by email.

## 2026-10-26 — Milestone M11 (part 2): payments, ageing, GST / VAT return data, TDS registers, Tally

**Built (in `webapp/`, `app/Modules/Accounts/Services/{Registers,TaxReturns,TallyExport}`):**
- **Payment voucher** (Accounts → Payment, `PV/26-27/00001`): pay a vendor (the pump's monthly bill, the workshop),
  a truck supplier, a crew member's earnings (bata, salary) or an expense, from office cash or one of our bank
  accounts (UTR needed for bank). A ledger that we owe shows **Pay**.
- **Ageing** (Accounts → Ageing): clients' open bills by days past the due date (or the bill date) — not due,
  1–30, 31–60, 61–90, over 90 — each bill under its client, the client's unapplied advance beside; the hire still
  owed on issued challans to each truck supplier, by the challan's age. CSV.
- **Tax returns** (Accounts → Tax returns), prepared from the bills as issued, never sent anywhere:
  India — **GSTR-1** for a GSTIN and month: B2B (4A / 4B, reverse charge marked "Y"), B2C large, B2C small, credit /
  debit notes (registered, unregistered), nil / exempt (bills of supply), the **HSN / SAC summary** (table 12) and
  **documents issued** (table 13, with cancelled ones), each downloadable with the GST offline tool's sheet columns;
  a **GSTR-3B working** (tax charged by head, supplies under reverse charge, exempt). UAE — the **VAT 201** output
  boxes: standard-rated supplies and VAT per emirate (1a–1g), zero-rated (4), exempt (5), totals (8).
- **TDS registers** (Accounts → TDS): **deducted by clients** — per receipt and bill, quarter (Q1 = Apr–Jun),
  PAN (from the party, else from its GSTIN), TAN, section, what the bill expected and what was deducted, the
  difference flagged — for matching with Form 26AS / AIS; **deducted by us** on lorry hire — per challan, PAN,
  section, rate, TDS, and the nil-TDS reason (the declaration of 10 trucks or fewer is still reported). CSV.
- **Tally import file** (Day book → "Tally import file (XML)", `data.export` / `statement.export`): our groups under
  Tally's reserved groups (Sundry Debtors, Duties & Taxes, Cash-in-Hand…) and our own sub-groups created under
  them, every ledger with its opening balance, every voucher of the period (Sales, Credit Note, Receipt / Payment
  when cash or bank moves, else Journal) with debits negative and ISDEEMEDPOSITIVE, the way Tally writes its own
  exports; a crew member and a party with the same name are told apart. The export is in the audit log.
- Fixed on the way: day counts mixed the company's time zone with plain dates (a challan a day old showed "not
  due"); a voucher from a moment (a road entry after midnight in Kochi) is now dated on the company's clock, not
  UTC. The payment voucher series' duplicate guard pointed at a table that does not exist.

**Tests:** 196 passing (`AccountsTest` now 12: payments, ageing and the TDS register, GSTR-1 tables and the RCM case,
the UAE VAT 201 boxes, the Tally file read back as XML, dates on the company clock).

**Not yet (M11 part 3 or later):** bank statement import and reconciliation, payment reminders, bill-wise opening
balances (import from Tally / Excel), statements as PDF on letterhead and WhatsApp share, purchases with input tax
(GSTR-2B / IMS matching, UAE boxes 9–11), the GSTR-1 JSON for direct upload, Tally import checked in a real Tally
(verify), year-end closing.

## 2026-10-26 — Milestone M11 (part 1): the accounting core and the ledgers

**Built (in `webapp/`, module `app/Modules/Accounts`; blueprint 07 §1, 09 §0):**
- **One book.** Account groups and ledgers (`account_groups`, `ledger_accounts`), vouchers and their lines
  (`vouchers`, `voucher_lines`), the books lock (`book_locks`). The chart is ready-made per country (Tally-style
  groups in India — Sundry debtors, Duties and taxes, Direct expenses…; IFRS wording in the UAE — Trade receivables,
  Cost of revenue…). Nobody sets anything up: each client, supplier, vendor, crew member, bank account, expense
  category (from the company's list), income class and tax (Output CGST / SGST / IGST / VAT) gets its ledger the
  first time it is used.
- **Every voucher balances** (debits = credits, to the paisa) or is refused; **a document posts once** (the voucher
  carries its source — a retry or a rebuild never counts twice); **nothing is edited or deleted** — enforced in the
  models; a cancel is the **exact reversal**, the original marked cancelled and linked to it. Number series: `V/26-27/
  000001` for the system's vouchers, `JV/26-27/00001` for journals typed by the accountant.
- **Automatic entries** (blueprint 07 §1.1), written in the same database transaction as the operation (so a failed
  posting stops the operation and the book never disagrees with the trips): issued bill / supplementary / debit note
  (Dr client; Cr freight or the charge's income ledger with **trip, truck, branch and LR** on each line; Cr output
  tax; round off) and credit note (the other way); receipt (Dr bank or cash, Dr **TDS receivable**, Dr the client's
  deductions; Cr the client); void of either → reversal. Trip advance (Dr the crew member's **trip cash**, Cr cash /
  bank). Approved road entry: spending Dr its expense [trip, truck], Cr **by who paid** — the crew member's trip cash
  (cash / own UPI), Fuel cards, FASTag / Salik wallet, the named pump on credit (or "Pump and vendor credit"), office
  cash; paid by the party → nothing; money from the party → Dr crew trip cash, Cr the LR's billing party. Rejected
  entries post nothing; an approved entry taken back is reversed. Settlement: bata Dr Bata [trip], Cr the crew
  member's **earnings**; the trip cash balance returned in cash, paid to them, or moved to their personal account
  (bucket to bucket); voided settlement → reversal. Office entries after close (D8): expense or income with the
  party or cash. **Lorry hire challan** at issue: Dr Lorry hire [trip], Cr the supplier, less **TDS payable**,
  munshiana (office charges recovered) and commission (owed on to the broker, or ours); its lines — detention and
  extras, advances and the balance (cash / bank), fuel given, deductions; cancelled challan → reversals. Broker
  commission typed alone (payable / receivable). Finished **service job**: Dr Service / Repair on road / Accident
  repairs [truck], Cr the workshop or cash.
- **Locked books**: Accounts → Lock (owner only by default, `accounts.lock`): nothing dated on or before the lock
  date changes — a late entry, a voided bill or a reversal posts on the first open day with the document's own date
  shown beside it; opening locked days again needs a reason; every lock is kept and audited.
- **Ledgers** (menu → Ledgers, `ledger.view`): clients, suppliers and vendors, crew — each with its balance **in
  plain words** ("UltraTech Cement will give you ₹15,620.00", "You will give Raju Transport ₹7,245.00"), totals to
  receive / to pay / company cash with crew; the **running statement** of any ledger (opening, each voucher, balance
  after it, closing) for any period, CSV for Excel. A crew member's ledger shows the **buckets** (trip cash,
  earnings, net balance due — 09 §1.1) and each bucket's statement. Links from the party and person pages
  ("Ledger", "Khata") and from the trip ("This trip in the books").
- **Accounts** (menu → Accounts, `accounts.view`: owner, accountant): overview (profit for the period, clients owe,
  you owe, cash and bank, trial balance check, latest vouchers), **day book**, **trial balance**, **profit and loss**
  (for the company, one truck, one trip or one branch — from the cost centres on the lines), **balance sheet** (with
  the profit so far), **truck profit** (income, running costs, maintenance, profit per truck; one truck month by
  month — the truck ledger, 09 §3), each **voucher** with a link to its document, the **chart of accounts** with
  balances, "Add a ledger" (capital, loans… with an opening balance), and the **journal voucher** for what the
  operations cannot know (bank charges, capital, corrections), reversible by hand; system vouchers are cancelled only
  from their document. CSV for the day book, trial balance and ledgers (Excel opens them; a cell that starts like a
  formula is written as text).
- `php artisan fleet:books [--company=]` posts whatever is not in the book yet (records from before M11), never twice,
  and ends with the balance check. Demo: the owner's capital in cash and the HDFC account (a journal), every trip,
  bill, receipt, challan and service job posted by itself; the book balances (₹7,46,730 each side).
- Fixed on the way: the numbering guard for money receipts looked in a table that does not exist (`money_receipts`
  → `receipts`); the trip advance, settlement, office entry, hire line and broker commission models gained their
  `trip()` / `challan()` relations.

**Tests:** 190 passing (new `AccountsTest`, 6 tests: crew money end to end, bill and receipt with TDS and voids, the
lock, every book screen and CSV, permissions, the rebuild; the hired-truck test now also checks the supplier, TDS,
broker and munshiana in the book). Every book screen checked in a real browser on the demo, no console errors.

**Not yet (M11 part 2):** GSTR-1 / 3B data and the TDS registers (26AS matching), Tally XML export, bank statement
import and reconciliation, ageing (0–30 / 31–60 / 61–90 / 90+) and reminders, bill-wise opening balances (import
from Tally / Excel), statements as PDF on letterhead and WhatsApp share, salary and loans in the crew ledger
(payroll, M-later), fixed assets and depreciation, input VAT on UAE fuel, multi-currency vouchers, the client's
advance at loading when it is not recorded as money (today it posts only when the crew records it or a receipt is
made), year-end closing.

## 2026-10-26 — Milestone M10: templates and the template editor

**Built (in `webapp/`, `app/Modules/Invoicing/Design` + `Services/DocumentTemplates`, ported from Clinic Ledger — D14):**
- **The design schema** (`DesignSchema`) for each document kind — **bills and notes**, the **LR**, the **money
  receipt**: theme, accent colour, font (Noto with the Indian-script and Arabic faces, system, serif, typewriter),
  paper (A4, A4 landscape, A5, Letter, thermal 80 mm), table density, text size, margins, logo height, **what prints**
  (17 switches on a bill — logo, address, contact, GSTIN, PAN, SAC column, tax summary, amount in words, bank, UPI QR,
  signature, note, terms, LR annexure and its vehicle column, TDS line, "computer-generated" line; 12 on an LR — trip,
  driver, e-way bills, consignor invoice, declared value, GST payable by, risk banner, POD box, Carriage by Road Act
  line…; 6 on a receipt), **renamed titles** (Tax invoice → "Freight bill"…) and **labels** (Bill to, Consignor, Said
  to contain, Authorised signatory…), **extra lines** under our details (Udyam, transporter ID — up to 6), a **footer
  line** and the **copy set** (1–6 copies, each with its label and a light tint; on the LR, whether that copy hides
  the freight). Every value is checked against the lists and ranges; anything else is dropped and **listed in plain
  words** (an imported file, a tampered form). Text is plain: markup, control and direction-override characters are
  taken out. The values printed inside the page's style sheet are checked again where they are printed.
- **Themes** as one style sheet driven by the design (CSS custom properties, sizes in rem so the text size scales the
  page): bills — **GST classic (boxed)**, **Modern band** (header on the accent colour), **Minimal** (hairlines);
  LR — **Classic LR (boxed)**, **Compact A5**; receipt — **A5 receipt**, **Thermal 80 mm** (one narrow column for a
  roll printer). Right-to-left page direction for Arabic / Urdu.
- **Which design prints** (`DocumentTemplates::resolve`): the template chosen on the bill in the builder ("Prints
  with"), else the **client's** template, else the **branch's**, else the company **default**, else the built-in
  design (so a company that never opens the editor still prints the GST invoice and the four-copy LR). A client or
  branch has one template per kind: giving it to one takes it off the other.
- **An issued bill keeps its look**: the design (with the template's id, name and version) is **frozen on the bill at
  issue**; changing or removing the template later never reprints it. LRs and receipts print with today's template
  (their content is fixed; only the look follows).
- **Versions**: every save that changes the design is a new version (the same design saved again adds none);
  **"Bring back"** copies an old version forward as the newest — the history only grows. Duplicate, make default,
  remove (not the default), **export** as a JSON design file and **import** one (here or in another company; what
  does not fit is listed).
- **Settings → Templates** (permission `settings.templates`: owner, accountant): the templates of each kind with
  their theme, paper, version, clients and branches; **new from a theme**; import. **The editor**: every choice on
  the left, the **document itself on the right, redrawn from the form as it changes** (the form posts to a preview
  address and the page that comes back fills a frame that runs no scripts; nothing is saved until Save), scaled to fit
  the column; the company's **latest real document** with this design, or a **sample** with our own name, address and
  bank when there is none yet; a warning when the accent is too light to read on paper; who prints with it; versions.
- Demo: "GST classic" (default, with a jurisdiction footer), "Modern blue" for Kerala Agro Foods, "Classic LR"
  (default), "Compact A5 — Mumbai" (three copies) for the Mumbai branch, "A5 receipt".
- Fixed on the way: the template list must not be cached on a service object — Laravel keeps a controller (and what
  is injected into it) from one request to the next, so a print after a save showed the old design (found by the
  tests; the cache was removed).

**Tests:** 184 passing (new `TemplateTest`, 9 tests; the invoice test helpers moved to `tests/Pest.php` so both files
share them). Screens checked in a real browser (list, editor with the preview following the form on all seven themes,
issued bill keeping its look), no console errors.

**Not yet:** a logo position / signature image upload in the editor, per-language label sets (Q1), a quotation /
payment-voucher template (with those documents, M9 part 2).

## 2026-10-26 — Milestone M9 (part 1): invoices, notes, receipts, LR print

**Built (in `webapp/`, module `app/Modules/Invoicing`, ported from Clinic Ledger — D14):**
- **Bills from LRs** (`/invoices`): choose the client, tick their LRs waiting to be billed (one, or several for a
  **consolidated bill with an LR annexure** — "Total trips / LRs: N", "Subtotal (N LRs)"), or a blank bill. Lines per
  LR: freight (its basis in the detail, e.g. "18.5 MT × ₹3,400 per tonne"), loading, unloading, other charges; the
  trip's "other charges billed" once per trip; the party's advance as **"less advance received"** (tax on the full
  value). Bills domestic and international loads separately.
- **The builder** of a draft: dates, client reference, our GSTIN / the client's GSTIN billed, loading state (India) or
  transport type and our role (UAE), papers on file, discount (% or amount, spread before tax), inclusive rates,
  lines (charge class, description, detail, qty, unit, rate, discount, the tax "by the rules" or chosen by hand with a
  reason), add more LRs, notes, terms. Every save works the tax out with the **engine** (M8) and shows it with "why";
  a case the engine cannot decide is shown on the draft and **stops issuing**.
- **Issue (finalise) in one transaction**: tax again, the number (`INV/26-27/00001`; per GSTIN when the company has
  several), the seller / buyer / bank **snapshot** (encrypted), the LRs marked **billed** and **locked** (13 §A #6:
  their trip and LR can no longer change), the secure link, the client's balance, the audit log. A finalised bill is
  **never edited — enforced in the model** (also against code); it is voided (number kept, reason, sensitive in the
  log, LRs free again) or corrected by a **credit note** (lines copied with the bill's own tax and rates; never more
  than is left to credit), a **debit note** or a **supplementary bill** (detention, rate difference — add-ons take the
  original freight's tax).
- **Receipts** (`/receipts`): money received + **TDS the client deducted** + other deductions (shortage, damage —
  with what) settle bills **oldest first or as typed**; TDS spread over the bills (each bill knows its TDS for 26AS
  matching); money left over is the client's **advance**; numbered at once (`RCT/26-27/00001`); void (cheque
  bounced) puts the bills back.
- **Client balances** kept in step (outstanding, overdue, advance, open bills, oldest due) for fast lists.
- **Printed documents** — one self-contained HTML page each, used for the screen preview, printing, the public link
  and the PDF: **tax invoice / bill of supply / supplementary / credit / debit note** in the two GST copies (Original
  for recipient, Duplicate for supplier — Rule 48), GSTIN, state and PAN, place of supply, **"Reverse charge:
  Yes/No"**, SAC per line, tax summary (reverse charge shown as payable by the recipient), round off, amount in words,
  less advance / received / balance due, the expected TDS line, bank details, **UPI QR for the balance due**, terms,
  sign-off, DRAFT / VOID watermarks; **LR** in its four copies (consignor, consignee, driver/lorry — freight hidden,
  office) with tinted pages, risk banner, goods, weights, e-way bills, GST payable by, POD box; **money receipt** (A5).
- **PDF by the server's headless Chromium** (no PDF package; falls back to the browser's print), file names
  `Invoice-INV-26-27-00001.pdf`.
- **Secure public link** `/d/{token}` (no sign-in, throttled, not indexed; opening marks the bill viewed) and
  **WhatsApp share** with a ready message; every share and open in the delivery trail. **Bill submission** record
  (by hand / courier / email / portal, date, docket).
- **Settings → Bank accounts**: account number, IBAN and UPI id encrypted, shown masked; default / on bills / in use.
- Trip page: "Print LR" and "Bill it" per LR, "Billed" link once billed. Menu: Invoices & LR.
- Demo: our HDFC account, the Chennai trip's LR billed (GTA reverse charge, UltraTech in Tamil Nadu → IGST
  notional) and part-paid with TDS.
- Fixed on the way: a line's empty SAC hid the rule's SAC in the engine (now the engine's decision wins).

**Tests:** 175 passing (new `InvoiceTest`, 11 tests, including a real PDF).

**Not yet (M9 part 2):** ~~templates and the template editor~~ (done in M10), email sending, manual LR books, bill of supply/receipt
voucher for advances, multi-currency bills, quotations, payment vouchers, the client page billing view (13 §A #20–21),
posting to the books (M11).

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
