# Web App — Structure, Flows and Screen Layouts

The web app is for office users: **owner, manager, accountant, workshop,
viewer**, plus the **super-admin** area. Built with Laravel, server-rendered
pages, card-based layout, and an icon on every menu item and button. It works
on desktop, tablet and phone browsers.

The **marketing website** (public pages, pricing, sign-up) is a separate part
of the same Laravel project. It is described at the end of this file.

---

## 1. Page layout

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ☰  [Logo] Sharma Roadlines ▾   🔍 Search trip / truck / LR / person   🔔 3  👤 │
├──────────────┬──────────────────────────────────────────────────────────┤
│ 🏠 Dashboard  │                                                          │
│ 🚚 Trips      │                 PAGE CONTENT (cards)                     │
│ ✅ Approvals 9│                                                          │
│ 🗺 Live map   │                                                          │
│ 🧾 Invoices&LR│  ← LR, invoices, receipts, hire challans, templates      │
│ 📒 Ledgers  ▸ │  ← Driver · Client · Truck · Supplier · Vendor           │
│ 📁 Documents  │  ← Document Manager                                      │
│ 🚛 Trucks     │                                                          │
│ 👥 People     │                                                          │
│ 🤝 Parties    │  ← clients, suppliers, vendors (one firm, many roles)    │
│ 🏦 Accounts   │                                                          │
│ 💼 Payroll    │                                                          │
│ 📊 Reports    │                                                          │
│ ⚙ Settings   │                                                          │
│ ❓ Help       │                                                          │
│              │                                                          │
│ v1.0.0 (12)  │  ← version line, as required by the guideline           │
└──────────────┴──────────────────────────────────────────────────────────┘
```

- The sidebar folds into icons on small screens and into a bottom drawer on phones.
- **Global search** finds trips by number, trucks by registration, LR numbers,
  people and parties.
- A **branch filter** appears only for companies that use branches.

---

## 2. Sitemap (company users)

```
Dashboard
Trips
 ├── All trips (filter: status, date, truck, driver, party, route)
 ├── New trip
 ├── Trip page
 │    ├── Overview      (crew, truck, route, consignments, status)
 │    ├── Timeline      (every road event, photos, approvals)
 │    ├── Map           (planned vs actual route, replay, stops)
 │    ├── Load & docs   (LR, e-way bill, invoice, POD)
 │    ├── Money         (crew entries + office entries, income)
 │    ├── Settlement    (cash account per crew member)
 │    ├── Profit        (direct and full profit, vs budget, vs past trips)
 │    └── History       (audit log of every change)
 ├── To settle
 ├── Consignments (all LRs by status; FTL / PTL / mixed; load factor)
 ├── Part-load network (phase 2: bookings, manifests, godown stock,
 │                      delivery runs, inter-branch accounts)
 └── Route rates (saved rates, km and budgets per route)
Approvals            (crew entries waiting for check, disputed, queries)
Live map             (trucks and crew on duty, last seen, alerts)
Invoices & LR        (full spec: 10-invoicing-lr-templates.md)
 ├── LRs / bilties (create from trip, copies, POD status, LR books)
 ├── Invoices (single LR, consolidated with LR annexure, supplementary,
 │             credit/debit notes, bill submission tracking)
 ├── Receipts (money receipts with TDS and deductions)
 ├── Hire challans (hired / market trucks)
 ├── Payment vouchers · Quotations · Loading slips · Fuel slips
 ├── Work queues: POD pending · Ready to bill · Not submitted · Overdue
 └── Templates (template list, editor, copy sets, message templates)
Ledgers              (full spec: 09-ledgers-and-documents.md)
 ├── Driver ledger (crew: trip cash · earnings · loan buckets, settlements,
 │                  salary summary, cash register PDF, recoveries, loans)
 ├── Client ledger (statement, bill-wise outstanding, ageing, TDS receivable,
 │                  deductions, reminders, balance confirmation)
 ├── Truck ledger  (truck P&L, per-km figures, fuel bill report, utilisation,
 │                  loan/EMI view, report card)
 ├── Supplier ledger (hire register, advances, POD-gated balance, TDS,
 │                    advance-without-POD ageing)
 └── Vendor ledger (bills, fuel slips + pump statement matching, workshop
                    estimates, MSME due dates, payments)
Documents            (Document Manager: all documents, compliance dashboard,
                      expiry calendar, renewals, shares, reminder rules)
Trucks
 ├── All trucks
 ├── Truck page
 │    ├── Overview      (status, current trip, driver, location)
 │    ├── Documents     (RC, insurance, fitness, permit, PUC, tax… with expiry)
 │    ├── Trips
 │    ├── Fuel & mileage
 │    ├── Tyres         (position map, fitment, km run, removal)
 │    ├── Service       (service history, next due by date/km)
 │    ├── Complaints    (open / in progress / solved)
 │    └── Costs & profit
 ├── Complaints board  (all trucks, drag between columns)
 ├── Service jobs
 └── Tyre store
People
 ├── All people (drivers, helpers, office staff)
 ├── Person page
 │    ├── Profile & documents (licence, badge, ID, bank, emergency contact)
 │    ├── Trips
 │    ├── Driving hours
 │    ├── Ledger        (advances, settlements, bata, salary, bonus, deductions, payments)
 │    ├── Leave
 │    ├── Complaints & incidents
 │    └── App & device  (activation code, last sync, app version, GPS status)
 └── Leave requests
Parties
 ├── Customers (shippers / consignees)
 ├── Vendors (fuel pumps, workshops, tyre dealers, brokers, others)
 └── Party page (details, tax IDs, rates, trips, ledger, outstanding)
Accounts                (full detail in 07-accounting.md)
 ├── Vouchers (sales invoice, purchase bill, receipt, payment, journal,
 │             contra, expense, credit note, debit note)
 ├── Ledgers & groups (chart of accounts)
 ├── Day book
 ├── Cash & bank (bank reconciliation)
 ├── Outstanding (receivables, payables, ageing)
 ├── Tax (country pack: India GST / TDS / e-way bill / e-invoice;
 │        UAE VAT / corporate tax / e-invoice)
 ├── Financial statements (trial balance, profit & loss, balance sheet, cash flow)
 ├── Year-end close
 └── Export (Excel, Tally XML)
Payroll
 ├── Monthly run (salary + bata + bonus + allowances − deductions − advances)
 ├── Payslips
 ├── Payment (bank file / UAE WPS salary file)
 └── Statutory registers (country pack)
Reports
 ├── Trip profit · Truck profit · Route analysis
 ├── Fuel: mileage, fuel vs GPS distance, mismatch flags
 ├── Expenses by category · Payments without receipt (by route, by person)
 ├── Driver: driving hours, trips, km, incidents, entries rejected
 ├── Documents expiring · Service due
 └── Outstanding by party
Settings
 ├── Company (name, country, currency, tax IDs, financial year, logo)
 ├── Branches
 ├── Users & roles
 ├── Rules (dual approval on/off, auto-approve, bata rules, freight visible
 │          to crew, required photos, app lock time, driving limits)
 ├── Number formats (trip, LR, invoice, receipt, hire challan…; per branch / tax registration; LR books)
 ├── Invoices & templates (simple settings page; link to the full template editor)
 ├── Tax (country preset, registrations, tax codes and rules, declarations)
 ├── Masters (places + short codes, materials, expense categories,
 │            truck types, leave types, document types)
 ├── Labels & custom fields (rename "Trip" to "Run"/"Hire", add extra fields)
 ├── Subscription & billing
 ├── Audit log
 └── Data export
```

---

## 3. Key screens

### 3.1 Dashboard

```
┌───────────────────────────────────────────────────────────────────────┐
│ Good morning, Mr. Sharma                         Today ▾  All branches ▾│
├──────────────┬──────────────┬──────────────┬──────────────────────────┤
│ 🚚 Trucks 12  │ 🟢 Running 7  │ ✅ To check 9 │ 💰 Month profit ₹3.82 L  │
│ 🟡 3 idle     │ 🏁 Ended 2    │ ⚠ Disputed 1 │ ▲ 12% vs last month     │
│ 🔴 2 workshop │ 📋 To settle 4│ ❓ Queries 2  │                          │
├──────────────┴──────────────┴──────────────┴──────────────────────────┤
│ 🗺  LIVE MAP (small)                       │ ⚠ ALERTS                  │
│   [ map with truck pins ]                  │ Insurance KL07AB1234 6 days│
│                                            │ Ravi 5 h non-stop driving │
│                                            │ Fuel mismatch MH12XY9876  │
│                                            │ Truck stopped 3 h (Pune)  │
│                                            │ No GPS from Anil 25 min   │
├────────────────────────────────────────────┼───────────────────────────┤
│ 📈 Income vs cost — last 6 months           │ 🧾 Spending today          │
│   [ bar chart ]                            │ Fuel 39,100 · Toll 6,250  │
│                                            │ Other 2,850               │
└────────────────────────────────────────────┴───────────────────────────┘
```

### 3.2 New trip

One page with cards, top to bottom. Saving keeps it as **Draft**; **Send to
crew** assigns it.

```
┌───────────────────────────────────────────────────────────────────────┐
│ ➕ New trip                     Trip no: 261013-ACC-COK-BOM-01 (preview)│
├───────────────────────────────────────────────────────────────────────┤
│ 📅 Date [13-10-2026]  Type (•) Loaded ( ) Empty ( ) Round trip         │
├───────────────────────────────────────────────────────────────────────┤
│ 🚛 Truck  [KL-07-AB-1234 ▾]  ✓ Free  ✓ Docs valid  ⚠ PUC 20 days       │
├───────────────────────────────────────────────────────────────────────┤
│ 👤 Driver [Ravi ▾]  👤 Co-driver [Suresh ▾]  👤 Helper [Anil ▾]        │
│    Licence ✓          Licence ✓               —                       │
├───────────────────────────────────────────────────────────────────────┤
│ 📍 Route  From [Kochi (COK) ▾] → To [Mumbai (BOM) ▾]  [+ Add stop]     │
│    Planned km [1,385]   [ Show on map ]                               │
├───────────────────────────────────────────────────────────────────────┤
│ 📦 Load 1   Party [ACC Ltd ▾]  Consignee [ACC Depot Bhiwandi ▾]       │
│    Material [Cement ▾]  Qty [25] [MT ▾]  Rate basis [Per tonne ▾]     │
│    Rate [3,400]  Freight ₹85,000  Advance from party [0]              │
│    LR no [auto]  E-way bill [optional]                  [+ Add load]  │
├───────────────────────────────────────────────────────────────────────┤
│ 💵 Cash to crew   Ravi [20,000] (Cash ▾)  Suresh [0]  Anil [0]         │
│    Fuel card [IOCL •••4521 ▾]   FASTag [linked to truck ✓]           │
├───────────────────────────────────────────────────────────────────────┤
│ 🎯 Budget (from last 5 trips on this route)                            │
│    Fuel ₹48,000 · Toll ₹6,800 · Bata ₹3,600 · Other ₹2,000            │
├───────────────────────────────────────────────────────────────────────┤
│ 📝 Instructions [ Collect ₹10,000 balance at Pune ]                    │
├───────────────────────────────────────────────────────────────────────┤
│                         [ Save draft ]   [ ✈ Send to crew ]           │
└───────────────────────────────────────────────────────────────────────┘
```

### 3.3 Trip page — Timeline tab

```
┌───────────────────────────────────────────────────────────────────────┐
│ 🚚 261013-ACC-COK-BOM-01   🟢 RUNNING   KL-07-AB-1234   Ravi · Suresh │
│ [Overview][Timeline][Map][Load & docs][Money][Settlement][Profit][History]│
├───────────────────────────────────────────────────────────────────────┤
│ Filter: (All) (Money) (Waiting) (Photos) (Driver changes)             │
├───────────────────────────────────────────────────────────────────────┤
│ 13 Oct 06:10  ▶ Trip started · Ravi · Odo 1,23,175  [📷][📷]          │
│ 13 Oct 09:40  🛣 Toll ₹455 · Kumbalam · FASTag          ✓ Approved     │
│ 13 Oct 11:42  💸 No receipt ₹300 · Police · Udupi                      │
│               By Ravi · ⏳ Waiting for Suresh (2nd approval)            │
│ 13 Oct 14:05  ⛽ 180.5 L · ₹16,678 · Odo 1,24,560 [📷][📷][📷]          │
│               Mileage since last full tank: 3.9 km/l (normal 3.8–4.2) │
│               [ ✓ Approve ] [ ❓ Ask ] [ ✗ Reject ]                     │
│ 13 Oct 14:30  🔁 Suresh driving · Odo 1,24,610                         │
│               ⓘ Entered 14:30 on phone · received 16:02 (was offline) │
└───────────────────────────────────────────────────────────────────────┘
```

Every entry shows both **when it happened** and, if different, **when the
server received it**. This makes offline entries clear.

### 3.4 Approvals inbox

A table with checkboxes: time, trip, person, type, amount, photos, flags
(🔴 GPS far from track, 🔴 mileage outside normal, 🟠 late entry, 🟠 entered
offline > 24 h). Bulk approve for rows with no flags.

### 3.5 Settlement tab

```
┌───────────────────────────────────────────────────────────────────────┐
│ 💵 Settlement                                    Trip status: ENDED    │
├───────────────────────────────────────────────────────────────────────┤
│ ⚠ 0 entries waiting for 2nd approval   ✓ 0 queries open   ✓ POD in    │
├──────────────────────────┬──────────────────────┬─────────────────────┤
│ Ravi (driver)            │ Suresh (co-driver)   │ Anil (helper)       │
│ Received     35,000      │ Received        0    │ Received       0    │
│ Spent       −16,250      │ Spent        −850    │ Spent       −200    │
│ ────────────────────     │ ─────────────────    │ ──────────────────  │
│ Must return  18,750      │ Company owes   850   │ Company owes  200   │
│ Settle by [Cash recd ▾]  │ [Add to ledger ▾]    │ [Add to ledger ▾]   │
├──────────────────────────┴──────────────────────┴─────────────────────┤
│ Bata (to personal ledgers): Ravi 1,800 · Suresh 1,500 · Anil 900       │
│                                             [ ✓ Settle all ]           │
└───────────────────────────────────────────────────────────────────────┘
```

### 3.6 Truck page — Overview

```
┌───────────────────────────────────────────────────────────────────────┐
│ 🚛 KL-07-AB-1234   Tata Signa 4825.TK · 12 wheeler · 25 MT             │
│ 🟢 On trip 261013-ACC-COK-BOM-01 · Driver Ravi · Near Udupi (5 min ago)│
│ [Overview][Documents][Trips][Fuel][Tyres][Service][Complaints][Costs]  │
├────────────────┬────────────────┬────────────────┬────────────────────┤
│ 📄 Documents    │ 🔧 Service      │ ⛽ Mileage      │ 💰 This year       │
│ ✓ 5 valid       │ Next: 1,30,000 │ 3.9 km/l       │ Income ₹28.4 L     │
│ ⚠ PUC 20 days   │ or 15 Nov      │ (target 4.0)   │ Cost   ₹21.1 L     │
│                 │                │                │ Profit ₹7.3 L      │
├────────────────┴────────────────┴────────────────┴────────────────────┤
│ 🛠 Open complaints                                                     │
│ #C-118 Brake noise (front left) · Raised by Ravi 10 Oct · In progress  │
│ #C-121 AC not cooling · Raised by Suresh 12 Oct · Open                 │
└───────────────────────────────────────────────────────────────────────┘
```

### 3.7 Person page — Ledger

A bank-statement style list: date, description, trip link, money in, money
out, running balance. Filters by type (advance, bata, salary, bonus,
deduction, payment, settlement). Print or share as PDF.

---

## 4. Super-admin area (hidden URL)

```
Companies (tenants) — list, status, plan, trucks used, trial end, last active
 ├── Company page — plan, extend trial, suspend, support access log
Plans & pricing — monthly / annual / lifetime, per-truck limits, student tier
Trials — default trial days (14), extend for one company
Coupons — create codes (percentage / fixed, valid dates, max uses, plans)
SaaS invoices & payments
Support access requests
Storage — usage per company, prune old files (safe placeholders, no broken images)
Database — full export (.sql.gz), import / restore
Security — lockout settings, locked accounts, unlock
Announcements — message shown to all companies
System health — queue, cron, disk, errors, app versions in use
Audit log
```

---

## 5. Marketing website (public)

Built to `docs/guidelines/SaaS_Website_Design_Prompt.md`: fluid modern design,
fast on slow phones, splash with progress bar, version line.

```
Home         — hero with animated phone + dashboard mockup, problem → solution,
               key features, trust signals, call to action "Start 14-day free trial"
Features     — Trips · Crew app · Fuel control · Accounts & tax · Trucks · Payroll
Pricing      — plans table, monthly/annual switch, coupon code box
FAQ          — searchable accordion
About us
Contact      — form (captcha, validation)
Sign up      — company name, country, owner name, email, mobile, password, captcha
               → email verification → company setup wizard
Log in / Forgot password
Legal        — terms, privacy policy, refund policy
```

**Company setup wizard** (first login): country and currency → tax IDs →
financial year → add first truck → add first driver (shows activation code) →
"Download the crew app" QR code.
