# Project State

Updated at the end of every build round. Newest first.

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
