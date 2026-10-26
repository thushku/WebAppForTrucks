# Decisions Log

This file records every product decision we have agreed on, in plain English.
Both the web app session and the phone app session must follow it.
When a decision changes, update this file and add the date.

Last updated: 14 Oct 2026

---

## 1. What we are building

A cloud (SaaS) platform for truck owners and transport companies, from a single
owner-driver with one truck up to companies with 100+ trucks.

- **Web app** (owner, manager, accountant, super-admin): plan trips, approve
  expenses, see profit, run full accounts, manage trucks and people.
- **Phone apps** (native B4A for Android, B4I for iOS later): the crew record
  everything on the road with GPS, time and photos, even without network.
- **Marketing website**: public site with pricing, trial sign-up, FAQ, contact.

First markets: **India**, then **UAE**.

---

## 2. Confirmed decisions

| # | Topic | Decision | Source |
|---|---|---|---|
| D1 | Web app stack | Laravel + MySQL (MariaDB locally). It is a web app, and it follows the same security rules as the website guideline (validation, CSRF, XSS/CSP, lockout, hidden admin URL, captcha, email OTP for admins, transactions, indexes, 250-500 KB uploads). | User, 13 Oct 2026 |
| D2 | Desktop app | Not needed now. The web app is used on desktop computers. | User, 13 Oct 2026 |
| D3 | Where work happens | Web app is built and tested in the cloud session. Phone app is built in a separate session on the user's laptop, in `C:\Users\Thushar\OneDrive\Documents\Claude\PhoneApp WebApp ForTrucks`, which has B4A and emulators installed. The cloud session must leave a complete handover pack in `docs/handover/`. | User, 13 Oct 2026 |
| D4 | Code sharing | One GitHub repo (`thushku/WebAppForTrucks`). The laptop gets it with `git clone` and stays in sync with `git pull` / `git push`. | Agreed, 13 Oct 2026 |
| D5 | Dual approval for police / officer payments | **Only unofficial payments** (paid without a proper receipt, such as a bribe) need approval by both drivers. Official fines (e-challan, receipted RTO or checkpost fees) need no second approval; they need the receipt or challan number and photo. | User, 13 Oct 2026 |
| D6 | When the second driver approves | Any time until the trip is settled or closed. Until then the entry shows **"Waiting for 2nd approval"**. A trip cannot be settled or closed while such an entry is still waiting. | User, 13 Oct 2026 |
| D7 | Dual approval on/off | The owner can turn the dual-approval rule **on or off** for the company. | User, 13 Oct 2026 |
| D8 | After a trip is closed | Entries made by the crew are **locked** (read-only). The owner or manager can still **add or edit office-side expenses and income** linked to that trip (for example commission, office charges, later bills, insurance or EMI share). Every change is kept in the audit log. | User, 13 Oct 2026 |
| D9 | Trip number | Generated automatically from short codes (date, shipper, from, to, serial). The format is designed in the blueprint (see section 3 below). | User, 13 Oct 2026 (format left to us) |
| D10 | Accounting | The web app must include a **full accounting system** that meets the legal rules of each country. Start with **India and UAE**. Other countries are added later as "country packs". | User, 13 Oct 2026 |
| D11 | Driver change log | When one person takes the driving seat from another, the app records a **driver change** entry (time, GPS, odometer). This gives exact driving hours for each driver and supports rest-time alerts. | User, 13 Oct 2026 |
| D12 | Offline | With no network, **every entry is kept on the phone with its actual time** (and GPS) and is pushed to the server automatically when signal returns. Nothing needs to be re-typed. | User, 13 Oct 2026 |
| D13 | Match Trucksarthi, module by module | Besides our own features, the product must have **every feature of the Trucksarthi app**, organised as separate modules: **Driver Ledger, Client Ledger, Truck Ledger, Supplier Ledger, Vendor Ledger, Document Manager, Invoices & LR (consignment note)**. Each module gets its own full specification. | User, 14 Oct 2026 |
| D14 | Invoicing like Clinic Ledger | A fully functional invoicing module **like the one in the founder's Clinic Ledger product** (`thushku/clinicledger`), with **invoice templates and a template editor**. Its design and code are studied and reused where possible. | User, 14 Oct 2026 |
| D15 | Tax rules worldwide | Invoicing must handle GST / VAT / sales tax **for any country**, through a configurable tax engine with ready-made country presets (India and UAE first). | User, 14 Oct 2026 |
| D16 | Full load and part load | Handle **Part Load (PTL/LTL)** and **Full Load (FTL)**: several consignments inside one truck journey, single bulk trips, and **market-truck hiring with broker commissions and supplier settlements** (as Trucksarthi). Spec: `blueprint/12-ftl-ptl-operations.md`. Multi-consignment trips, hired trucks and broker commission in phase 1; branch/godown network operations (manifests, transshipment, delivery runs) in phase 2. | User, 14 Oct 2026 |
| D17 | Read Trucksarthi's public code | Approved: read `kaptickventures/trucksarthi-frontend` **only to check screens and fields**; no code is copied. | User, 14 Oct 2026 (answer to Q3) |
| D18 | Packages and services approved | Laravel Sanctum (phone-app login), Leaflet (map), Firebase Cloud Messaging (push), map/geocoding API keys (Mappls, Google). | User, 14 Oct 2026 |
| D19 | Paid verification service | Approved: RC / DL / PAN / GST / bank checks through an aggregator, built behind a **switchable provider interface**. Provider chosen on price and coverage when phase 2 starts (candidates: Cashfree — Trucksarthi uses it for PAN/GST/bank — Surepass, Gridlines, IDfy). Cost per check is recorded per company. | User, 14 Oct 2026 (answer to Q4) |
| D20 | Pricing set by the super-admin | No pricing is fixed in code. The super-admin (SaaS provider) sets plans, prices (per truck / flat / lifetime), billing periods, trial days (default 14, extendable per company), student tier, coupons and plan limits. **Our reading of the same answer:** product name/branding and the list of enabled languages are also super-admin settings — to be confirmed. | User, 14 Oct 2026 (answer to Q2/Q1) |
| D21 | SMS / WhatsApp OTP provider | Approved: mobile OTP sign-in for owners and crew through an SMS/WhatsApp provider (India: DLT registration). Built behind a switchable driver; `log` driver for development until the provider account is opened. | User, 25 Oct 2026 (answer to Q5) |
| D22 | Start building | Blueprint approved: build the web app milestone by milestone in the cloud session, as long as credit lasts. | User, 25 Oct 2026 |

### Defaults we are using (user did not object)

| # | Topic | Default |
|---|---|---|
| A1 | Apps per role | One **crew app** (driver, co-driver, helper; screens change by role) and one **owner/manager app**. One person may have more than one role. |
| A2 | GPS source | Phone GPS in version 1. Connecting existing truck GPS devices comes later. |
| A3 | Fleet type | Own fleet first. **Changed 14 Oct 2026 because of D13:** a basic **Supplier Ledger with hired/market trucks** (lorry hire challan, advances, balance paid after POD, TDS) is now in phase 1, to match Trucksarthi. Attached-truck commission accounting and broker networks stay in a later phase. |
| A4 | Single-driver trips with dual approval on | The manager gives the second approval instead of a co-driver. (Proposal, confirm when reviewing the blueprint.) |

---

## 3. Proposals waiting for user review

These are our recommendations. They become decisions once the user approves
the blueprint.

### P1. Trip number format

```
YYMMDD-SHP-FRM-TO-NN
261013-ACC-COK-BOM-01
```

| Part | Meaning | Example |
|---|---|---|
| `YYMMDD` | Trip date | `261013` = 13 Oct 2026 |
| `SHP` | Shipper (party) short code, 3 letters, set in the party master | `ACC` |
| `FRM` | Loading place short code, 3 letters, set in the place master | `COK` = Kochi |
| `TO` | Unloading place short code | `BOM` = Mumbai |
| `NN` | Running number of trips created by the company on that date | `01` |

- The date and serial alone make it unique; the letters make it easy to read
  aloud on the phone.
- The number is fixed when the trip is assigned to a crew, and it does not
  change after that, even if the route changes.
- Trip numbers are **not** invoice numbers. GST invoice numbers have their own
  series (maximum 16 characters, unique per financial year), for example
  `INV/26-27/00042`. LR numbers have their own series too.

### P2. Driver change entry

- The person taking the seat taps **"I am driving now"** in the crew app.
- The app records the time, GPS location and odometer reading (photo optional,
  owner can make it required).
- The other driver's driving time stops at the same moment.
- Reports show driving hours per driver per trip, per day and per week, with
  alerts when legal or company limits are crossed.

### P3. Unreceipted payment wording

In the app, the category is called **"Payment without receipt"**, separate from
**"Official fine / fee (with receipt)"**. Plain, neutral wording is safer for the
company's books. The owner should confirm the final wording with their CA or
lawyer.

### P4. Supplier vs Vendor (confirmed by research)

- **Supplier** = owner or broker of a **hired / market truck** (we owe lorry hire).
- **Vendor** = seller of goods or services (fuel pump, workshop, tyres, spare
  parts, insurance, RTO agent…).
Trucksarthi uses the same meaning. Screens show a one-line hint.

### P5. Copies

- **LR:** default 4 copies — Consignor, Consignee, Driver/Lorry, Office (law
  needs at least 3). Configurable.
- **Freight invoice:** GTA freight is a *service*, so GST Rule 48 needs **2
  copies** — "Original for Recipient" and "Duplicate for Supplier". (The
  3-copy set with "Duplicate for Transporter" is for goods invoices only.)
  Extra copies allowed.

### P6. Same technical style as Clinic Ledger

To keep the founder's products consistent and reuse code (D14): Laravel 13,
PHP 8.3, MySQL/MariaDB, Blade, Tailwind CSS 4, Vite 8, plain JavaScript modules
(no Alpine/Livewire/Vue), Pest tests, **PDF by headless Chromium** (no PDF
package), QR codes / amount in words / CSV written in-house. See
`blueprint/01-architecture.md` §10.

### P7. Crew working for several companies; QR login

- One crew member's mobile login can belong to **several companies**
  (Trucksarthi has this). The crew app shows and switches the current company.
- Office users can sign in to the web app by **scanning a QR code** with the
  owner app (like WhatsApp Web), as well as with a password.

### P8. Which look a document keeps (added 26 Oct 2026, M10)

- An **issued bill or note keeps the design it was issued with** (frozen on the
  bill, with the template's name and version): editing or removing a template
  never changes what a client already received.
- **LRs and money receipts print with today's template.** Their content is
  fixed once made; only the look follows the template, so a company that
  switches to the Compact A5 LR reprints old LRs in the new size.
- Templates are chosen in this order: the bill's own choice in the builder, the
  client's, the branch's, the company default, the built-in design.

### P9. How the book is kept (added 26 Oct 2026, M11)

- **One ledger per party**, even when the firm is a client and a supplier: the
  balance is what is really owed between us. It sits with the debtors when the
  party is a client, otherwise with the creditors. (Set-off entries per role
  come later if asked.)
- **A crew member has one personal account with buckets** — trip cash and
  earnings (loan later) — so "Ravi holds ₹3,750 of trip cash" and "the company
  owes Ravi ₹4,200 bata" never mix (09 §1.1).
- **Locked days never change**: an entry or reversal for a locked day posts on
  the first open day, its own date kept beside it.
- The client's **advance at loading** posts only when it is recorded as money
  (the crew's "money received", or a receipt). The bill still prints "less
  advance received".
- The system's vouchers are numbered `V/26-27/000001`; the accountant's
  journals `JV/26-27/00001`. Bills, receipts and challans keep their own numbers
  as the voucher's reference.

### P10. Plans, payment and the read-only rule (added 26 Oct 2026, M13)

- **Until a payment gateway is approved, payment is recorded by the console.**
  The owner pays the provider (bank transfer, UPI) and the super-admin puts the
  company on the plan from a date, with the reference and any coupon; the price
  comes from the plan. The billing page gives the provider's WhatsApp and email
  with the request filled in.
- **Limits only stop adding.** A plan's truck and user limits refuse one more
  truck or invitation; nothing already there is taken away or hidden. A company
  on its trial has no limits.
- **Read-only covers the phones too.** When the trial or plan ends (checked every
  night) the web and the phone API read but do not write; the phone keeps what
  the crew entered in its queue (answer `423 read_only`) and sends it after
  renewal, so nothing entered on the road is lost. The owner can still approve a
  web sign-in by QR and reach the billing page.
- **A plan in use is switched off, never deleted**, so old subscriptions keep
  their name and price. Moving to another plan ends the running one the day
  before.

### P11. The website (added 26 Oct 2026, M14)

- **The website's prices are the console's plans**: only plans switched on and
  marked "shown on the website" appear, per country; a coupon typed on the
  pricing page shows its effect (or why it does not apply) and rides into
  sign-up, where the plan and coupon chosen are kept on the company for the
  console to apply at the first payment.
- **About us and the legal pages ship as drafts the super-admin can replace**
  in the console (plain text, nothing that runs). The shipped terms, privacy
  and refund texts are a starting point and need the owner's (and a lawyer's)
  review before launch.
- **No invented facts on the site**: no customer counts, ratings or
  testimonials until there are real ones; numbers in the drawn mockups are
  examples.

### P12. How the basic pay run works (added 26 Oct 2026, M16)

- **Pay comes from the person's pay basis**: monthly salary for each calendar
  month the period touches, for the days inside the period and inside their
  employment (no attendance yet, so every such day counts); per trip — trips
  they were crew on that ended in the period; per km — kilometres of their
  driving shifts.
- **Nobody is paid twice for a day**: a person already in a live run for any
  day of the period is left out of a new one (and told).
- **Approval makes the pay owed** (salary expense, the person's earnings
  credited); deductions take back trip cash or a loan they hold, or are
  recovered as income (fines). **Payment** clears the earnings; it may also pay
  the bata already owed from settled trips in the same payment.
- **A run is cancelled, never edited** once approved: its postings are
  reversed; a run with a paid payslip cannot be cancelled.
- Full payroll (attendance, leave, Labour Codes, PF/ESI, UAE WPS) stays in
  phase 2–3.

### P13. Your data, deleting a company, and what a plan can switch off (added 26 Oct 2026, M17)

- **The whole company downloads as one ZIP** (Settings → Your data, owner by
  default): a CSV per table of the company's data, bank and ID numbers
  readable, one-time codes and secret links left out, and every uploaded file.
  At most three an hour; every download is logged as sensitive.
- **Deleting a company** needs the company's name typed, the owner's password
  and a reason; it happens **72 hours later** unless anyone with the right
  cancels; all owners get an email. Then every row and file of the company is
  removed, the company's row stays as "deleted" for the console, and **one
  AES-256-encrypted ZIP is kept for 8 years** (`RETENTION_YEARS`; India's books
  of account; UAE VAT needs 5) and then removed. The provider's subscription
  records stay.
- **A plan's modules switch features off by taking their permissions away**
  for everybody in the company, so menus, pages and the phone API follow.
- **Storage**: office uploads stop when the plan's storage is full; the crew's
  photos from the road are never refused (they are the proof) — the owner is
  warned from 90 %.

### P14. Opening balances and bank reconciliation (added 26 Oct 2026, M19)

- **Opening balances sit on the ledgers**, dated the first day of the
  financial year, typed per ledger or brought from Tally or Excel as a CSV.
  Only balance-sheet ledgers have them (income and expenses start each year at
  nothing; a company starting mid-year puts the profit so far in "Reserves and
  surplus"). **What does not balance goes to "Difference in opening
  balances"**, so the trial balance always agrees and the gap stays visible.
  They cannot change while the books are locked; every change is logged.
- **The bank's statement comes in as CSV** (every Indian and UAE bank gives
  one); no bank feed or aggregator (that would need approval). Lines brought
  in before are left out by a print of date, amount, words and reference.
- **A line matches by itself only when it is clear**: one book entry of the
  same amount on the same side of the bank ledger within 7 days, not matched
  already — or, among several, the one whose reference carries the line's UTR
  or cheque number. Everything else waits for a person: match by hand (within
  31 days), book it as bank charges or interest (a journal made from the line),
  or leave it out with a reason. A match never changes the book; undo is
  always possible.
- **A party's statement** prints on the letterhead, saves as PDF and goes by
  email with the company's words (Settings → Messages → Sending a statement);
  every send is kept.

### P15. Who is told what, and how (added 26 Oct 2026, M20)

- **Every notice goes to the person's inbox** (the web bell, both apps) and, when
  a phone has given its Firebase token, as a push. Notices belong to one company.
- **Who hears what**: the crew — a trip sent to them or called off, a question
  on or rejection of their entry, their pay paid; the other driver — an entry
  waiting for their second approval; those who approve (`event.verify`) — how
  many entries wait; those who see trips — an incident at once; those who keep
  papers — the day's reminders (with the email). Approvals themselves tell
  nobody (no noise for what went right).
- **Like news collapses**: one "entries wait" notice keeps the count, updated
  as entries come and decisions are made, pushed again only after 30 quiet
  minutes, and marked read for everyone once nothing waits.
- **Firebase HTTP v1 with the project's service-account key**, signed with
  PHP's own OpenSSL — no SDK or package. The key file lives outside the
  repository (`FIREBASE_CREDENTIALS`); without it pushes go to the log. Pushes
  go through the queue (a worker must run); a token Firebase no longer knows
  is forgotten.
- Per-person choice of which notices to push, quiet hours and WhatsApp/SMS
  notices come later (WhatsApp Business needs a provider — your approval).

### P16. Quotations, LR books and printed vouchers (added 26 Oct 2026, M21)

- **A quotation is not a tax document** and is never posted: it shows the
  rates and their basis with a note that GST/VAT comes on the bill. A draft is
  edited freely; **issuing gives it its QT/ number and fixes it** — a new offer
  is a copy. "Expired" is read from the valid-until date, never stored.
- **Accepted → the client's rate contract** (a draft with the same routes,
  approved on the client's page as before), so their LRs fill the rate in by
  themselves. Any route of a sent or accepted quotation can start a trip with
  the rate filled in; the load remembers the quotation.
- **LR books**: a typed LR number is taken only from a leaf of a book in use,
  not on another load and not cancelled or lost; it is kept as the book's
  prefix and the leaf number (leading zeros dropped). Books may not overlap.
  **An LR keeps its number** once it has one (auto or typed): a wrong leaf means
  cancelling the LR. "Skipped" leaves are the free ones below the highest used
  leaf — the ones to look for. Loads without a typed number keep the automatic
  series, so offices can mix both.
- **Every voucher prints to sign** (payment, receipt, journal, contra) with the
  amount in words and prepared / approved / received boxes.

### P17. Branches at work (added 26 Oct 2026, M22)

- **The branch filter is a view, not a permission**: with two branches or
  more, the header has "All branches / Kochi / Mumbai"; the lists (trips,
  trucks, people, bills, quotations, approvals, LRs to bill), the dashboard,
  the live map and the profit and loss follow it. Trucks and people with no
  branch show in every branch; trips and bills with no branch only under "All".
- **A person can be limited to one branch** (Settings → Team → Works for):
  they see and open only that branch's trips, bills and quotations, and its
  trucks and crew (plus the shared ones) — on the web and in the owner app; a
  URL to another branch's record answers "not found"; they plan trips only for
  their branch. An owner is never limited.
- **The books stay company-wide** (trial balance, balance sheet, ledgers,
  GST/TDS registers): give the accounts permissions only to people who work
  for the whole company. Services never apply the branch (a truck on a trip in
  another branch is still busy; numbering and postings are unchanged).
- A bill takes its trips' branch when they share one; new trips, bills and
  quotations start in the branch shown.

---

## 4. Still open

| # | Question | Needed before |
|---|---|---|
| Q1 | Which languages besides English? (D20 reading: the super-admin enables languages; English + Hindi first.) (For example Hindi, Malayalam, Tamil, Urdu, Arabic.) The apps will be built language-ready from day one. | Phone app build |
| ~~Q2~~ | **Answered (D20): set by the super-admin.** Pricing details (per truck per month? trial days, plans, student tier, coupon rules) — the website guideline says to confirm before coding pricing. For comparison, Trucksarthi: one plan with all modules, ₹5,999/year (website) or ₹9,999/year (app stores), 30-day free trial, driver app free. | Marketing website and billing build |
| ~~Q3~~ | **Answered (D17).** May we read Trucksarthi's **public** GitHub code (`kaptickventures/trucksarthi-frontend`) to check exact screens and fields? Read only, no copying. Alternative: screenshots of their app from you. | Final field lists |
| ~~Q4~~ | **Answered (D19).** Paid verification services (Vahan RC / Sarathi DL / PAN / GST / bank checks through an aggregator such as Surepass, Gridlines, Cashfree or IDfy — about ₹4 per check listed) — approve a provider? | Phase 2 Document Manager |
| ~~Q5~~ | **Answered (D21).** Mobile OTP sign-in for owners (as Trucksarthi) needs an **SMS or WhatsApp OTP provider** (India needs DLT registration). Approve a provider? Until then owners sign in with email + password or QR. | Owner sign-up flow |
