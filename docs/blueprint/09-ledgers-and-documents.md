# Ledger Modules and Document Manager

> **See also `13-parity-additions.md`** — additions from the Trucksarthi code study (D17) that extend this file; where they differ, that file wins.

Decision **D13**: the product must have every feature of Trucksarthi, as
separate modules. This file specifies six of the seven modules:

1. Driver Ledger (covers every crew member: driver, co-driver, helper)
2. Client Ledger
3. Truck Ledger
4. Supplier Ledger (hired / market trucks)
5. Vendor Ledger
6. Document Manager

The seventh, **Invoices & LR**, is in `10-invoicing-lr-templates.md`. Tax rules
are in `11-tax-engine.md`.

Sources: `research_notes/Trucksarthi modules and global invoicing/`
(`trucksarthi_features.md`, `transport_modules_expert.md`). Items marked
**[TS]** are Trucksarthi features we must match. Items marked **(verify)** need
a CA / tax-agent check before they are built.

> **Words used on screen** (Trucksarthi uses the same meaning, confirmed on
> their About page):
> - **Supplier** = someone who **supplies a truck** for our load: a market-truck
>   owner, a fleet operator or a vehicle broker. We owe them **lorry hire
>   (bhada)**.
> - **Vendor** = someone who **sells us goods or services**: fuel pump,
>   workshop, tyre dealer, spare parts, insurance, RTO agent, etc.
>
> Because plain accounting uses both words for the same thing, each screen shows
> a one-line hint ("Supplier = owner or broker of a hired truck").

---

## 0. How the ledgers work together

All five ledgers are **views of one accounting book** (`07-accounting.md`). They
are not five separate money boxes. When Ravi gets an advance, one voucher is
written (Dr Ravi, Cr Cash) and it appears in the Driver Ledger, the cash book,
the trip and the truck at the same time. This keeps every number in agreement.

```
          ┌──────────── one accounting book (vouchers) ────────────┐
          │                                                        │
 Driver   │  Client     Truck          Supplier      Vendor        │
 Ledger   │  Ledger     Ledger         Ledger        Ledger        │
 (person  │  (party     (cost-centre   (party        (party        │
 account) │  account)   view by truck) account)      account)      │
          └────────────────────────────────────────────────────────┘
```

### 0.1 Features every ledger has (built once, reused)

| Feature | What it does |
|---|---|
| **Plain-language balance** | Shows "Ravi will give you ₹18,750" / "You will give ACC Ltd ₹4,200" in green or red. Accountants can switch to Dr/Cr. |
| **Running balance statement** | Date, entry type, number, reference (trip / LR / bill), note, money in, money out, balance after each row. |
| **Opening balance** | A single total, or **bill-wise** (each old unpaid bill with its date, so ageing is correct from day one). For customers moving from Tally, Excel or paper. |
| **Bill-wise matching** | Every money line is marked **New bill**, **Against bill**, **Advance** or **On account**. A "match" screen applies on-account money to bills later. |
| **Filters** | Period or financial year, entry type, trip, truck, LR, branch, status, amount range, "only unsettled". |
| **Statements and sharing** **[TS]** | PDF on letterhead, Excel, CSV; one-tap **WhatsApp share** (PDF or secure link) with a ready-made text summary; email; English, Hindi and other languages. |
| **Reminders** | Per ledger type (below); WhatsApp/SMS/email templates with placeholders. |
| **Attachments** | Photos/PDFs on any entry (receipt, signed voucher, UPI screenshot, POD), compressed to the upload limit. |
| **Maker-checker approvals** | Owner sets which entries need a second person: payments above ₹X, write-offs, deductions, back-dated entries. |
| **Acknowledgement** | The other side confirms in their app ("received ₹5,000") or with an OTP. Ends "I never got it" disputes. |
| **Party with several roles** | One firm can be a client **and** a supplier/vendor. Each role has its own view; a **set-off entry** (with approval) nets them. |
| **Audit, period lock, no delete** | As in `07-accounting.md`. Cancel with reason only. |
| **Import / export** | Excel import of masters and opening balances; Tally XML export. |
| **Search everywhere** | One box: LR no., bill no., truck no., UTR, cheque no., name, mobile. |

---

## 1. Driver Ledger (crew ledger)

One personal account (khata) per crew member: driver, co-driver, helper. Each
person sees their own in the crew app.

### 1.1 Three buckets inside one ledger

Mixing trip cash with salary in one number is the most common cause of driver
disputes, so the ledger keeps three buckets and a net total:

| Bucket | Meaning | Example |
|---|---|---|
| **Trip cash** | Company money in the person's hands for trip spending | Advance ₹20,000 − approved spending ₹16,250 = ₹3,750 still with Ravi |
| **Earnings** | Salary, bata, incentives, overtime, reimbursements, minus deductions | Salary ₹18,000 + bata ₹4,200 − fine recovery ₹500 |
| **Loan** | Personal loan or salary advance being recovered in instalments | ₹30,000 loan, ₹3,000 a month |

The screen shows each bucket plus **"Net balance due"** **[TS]**. An explicit
"Adjust trip cash against salary" entry moves money between buckets, with
approval.

### 1.2 Entry types

| Entry | Key fields | Notes |
|---|---|---|
| Opening balance | Amount, date, bucket, direction | Migration from a paper khata |
| **Advance** (trip or general) **[TS]** | Date, amount, mode (cash / UPI / bank / fuel-card load / FASTag recharge), trip, paid from (cash / bank / branch), UTR, purpose, photo of signed voucher, **acknowledgement** by the person | Most-used entry |
| Trip expense | Posted automatically when the manager approves a road event (`03-trip-lifecycle.md`) | Rejected events post nothing |
| **Fuel "who paid"** **[TS]** | Owner / driver / fuel card / pump credit | Only driver-paid fuel changes the driver's balance |
| Cash collected for the company | To-Pay LR freight, advance from consignor, scrap sale; LR no., party | Debits the driver, credits the client |
| Cash returned / deposited | Office cash, bank deposit slip, UPI reference | Closes trip cash |
| Crew-to-crew handover | Driver A gives cash to driver B on the road; both confirm | One entry, two ledgers |
| **Salary** **[TS]** | Month, days present/leave/absent, fixed pay, earned pay, statutory deductions | From payroll |
| Bata / daily allowance | Rule per role (driver and helper rates differ): per day / trip / km / route table | Posted at trip close |
| Incentives and bonus | Trip incentive, **diesel-saving incentive**, on-time bonus, festival bonus, statutory bonus | |
| Overtime | From the driver-change log and driving hours | Labour Codes / UAE law |
| Reimbursement | Person spent own money (e.g. a repair) | |
| **Recoveries / deductions** | Fine recovery (challan no.), shortage/damage passed on from client, **diesel shortage** (km/l below standard × rate), missing tools/tarpaulin, rejected expense, advance recovery. Basis document, calculation shown, approval, person notified, **legal cap check** (UAE ≤ 50% of wage, damage ≤ 5 days/month; India cap (verify)) | Must be explained and capped |
| Loan and EMI recovery | Principal, interest (**UAE: no interest, written consent**), instalment, start month, schedule, auto-deduct from salary, prepayment, waiver | Separate loan bucket |
| Salary / earnings payout | Mode, UTR, bank batch file; UAE WPS file | |
| **Trip settlement** **[TS]** | Received, spent, balance; result: returned / paid / **carried forward** / adjusted against salary | From `03-trip-lifecycle.md` §8 |
| Security deposit | Held from the driver, refunded at exit | Kept as a liability |
| Full & final settlement | All buckets + gratuity + leave encashment − pending recoveries; settlement letter; checklist (keys, phone, documents returned) | Driver exit |
| Write-off | Owner only, with reason | |

### 1.3 Views and reports

| View | Content |
|---|---|
| Person statement | Running balance per bucket and net; filter by trip, month, type |
| **Monthly salary summary** **[TS]** | Salary, bata, incentives, deductions, net, paid, balance |
| **Driver cash register PDF** **[TS]** | All cash given and spent, per trip, in English/Hindi, WhatsApp share |
| All-crew cash position | Who holds how much company cash, and for how many days |
| Advance ageing | Advances not settled for more than N days |
| Registers | Recoveries, loans, bata, incentives, salary register, wage slips |

### 1.4 In the crew app ("My Khata")

- Shows the person's own buckets, statement and salary summary **[TS]**, in
  their language, read-only.
- The person can **flag** an entry ("not received" / "wrong amount"). This opens
  a query thread with the office.
- Acknowledge advances with one tap.

### 1.5 Reminders

To the manager: trip cash unsettled > N days, cash with one person > ₹X, loan
instalment due. To the person: monthly statement on WhatsApp.

---

## 2. Client Ledger

One account per **billing party** (the one who pays). On an LR there can be
three parties: **consignor** (sends), **consignee** (receives) and **billing
party** (pays; either of them, or a third party). Only the billing party carries
a balance; consignor and consignee are address-book roles.

### 2.1 Client set-up

| Item | Fields |
|---|---|
| Profile **[TS]** | Legal and trade name, short code (for trip numbers), **group/parent** (e.g. "UltraTech – all plants"), **several GSTINs/TRNs with state and address** (plant-wise billing), PAN, TAN, contacts (dispatch, accounts, bill submission), invoice email, our vendor code at the client, client portal URL |
| Credit control | Credit limit, credit days, due date counted from **invoice date or bill submission date**, action when crossed: warn / block new trip / manager override with reason |
| Billing rules | Cycle (per LR / weekly / fortnightly / monthly), bill only with POD (original / e-POD / none), copies needed, annexure format, references the client requires (PO / indent / shipment no.) |
| Tax behaviour | GST mode for this client (RCM / FCM 5% / FCM 18% / exempt); does the client deduct TDS (rate, section); our "≤10 trucks" declaration given this FY (see 2.2) |
| **Rate contracts** | Route, material, truck type, basis (per trip / tonne / kg / km / package), rate, **minimum guaranteed weight**, validity, version, approval; **diesel escalation clause**; detention free hours and **slab rates**; loading/unloading and multi-drop charges; penalties; payment terms. Rates fill the trip/LR automatically. |
| Late-payment interest | % per year after due date; optional interest debit note |

### 2.2 Entry types

| Entry | Notes |
|---|---|
| LR booked, not yet billed | Shown in "Unbilled LRs" with its value (not a money entry yet for To-Be-Billed) |
| Advance received | At loading or before the trip; against trip/LR; adjusted on the bill automatically |
| **Invoice** **[TS]** | Single-LR or consolidated (many LRs), supplementary (detention, rate difference); see `10-invoicing-lr-templates.md` |
| **Receipt — partial or full** **[TS]** | Mode (NEFT / RTGS / UPI / cheque / cash), UTR/cheque, bank; **split across bills**, advance, or on account |
| TDS deducted by client | Per bill: section (old 194C / new 393(1)), rate, amount, quarter; matched later with 26AS/AIS; Form 16A collected |
| Deductions by client | Shortage, damage, late delivery, late POD, rate difference; evidence; posted as a credit note or claim; option to **recover from the driver or supplier** (linked recovery) |
| Credit / debit note | Linked to invoice, reason codes |
| Cheque bounce | Reverses the receipt; bank charges to client; reminder for legal-notice window |
| Write-off / refund / set-off | Owner approval |
| Our ≤10-truck TDS declaration | If our customer owns ≤10 trucks, they give each client a declaration each financial year so the client deducts no TDS; we store the signed copy, print it, and remind before 1 April **(verify sections)** |

### 2.3 From LR to cash — work queues on the dashboard

```
LR booked ─► Loaded ─► Delivered ─► POD received ─► POD verified ─► Billed
   ─► Bill submitted (date, mode, acknowledgement / portal ref, courier docket)
   ─► Due ─► Part payments + TDS + deductions ─► Bill closed
```

| Queue tile | Lists |
|---|---|
| POD pending | Delivered LRs without POD, aged |
| Ready to bill | POD verified, not billed |
| Billed, not submitted | Bills not yet handed to the client |
| Submitted — not due / overdue | With ageing |
| Short-paid bills | Paid ≠ bill − TDS − deductions |

### 2.4 Reports

Client statement **[TS]**, payment due / outstanding **[TS]**, transaction
report per client **[TS]**, bill-wise outstanding, ageing (editable slabs, by
bill or due date, per client/group/branch), unbilled revenue, TDS receivable
reconciliation, deductions analysis, client profitability, days-sales-outstanding
and collection forecast, year-end balance confirmation letter.

### 2.5 Reminders and sharing

Payment reminders before due, on due and every N days after (WhatsApp / SMS /
email, editable templates with party, amount, bill numbers, due date, pay link);
invoice share with **UPI QR for the balance**; client ledger PDF in English/Hindi
on WhatsApp **[TS]**. Later: a client portal to view bills, PODs and statements.

---

## 3. Truck Ledger

A **cost-centre view**: every voucher line tagged with the truck adds up here.

### 3.1 What goes in

| Group | Items |
|---|---|
| Income | Freight from its trips, detention, loading/unloading recovered, other charges; hire income when our truck works for another transporter |
| **Running costs** **[TS]** | Fuel and AdBlue (adjusted for tank level), FASTag recharge/tolls, loading/unloading, challans/fines, payments without receipt, bata, crew wage share, weighbridge, parking |
| **Maintenance** **[TS]** | Servicing, breakdown and accident repairs, tyres, batteries, lubricants, spares; insurance claim money reduces accident cost |
| Tyres | As purchased, or **cost per km** (tyre cost ÷ expected km × km run) |
| Fixed costs | **EMI split into principal and interest** (only interest is a cost), insurance (spread monthly), road tax, permits, fitness, PUC, tracker subscription, FASTag fees, yard rent |
| Depreciation | Book and tax methods shown separately (verify rates) |
| Overheads | Office costs allocated by days / km / trips / revenue (owner chooses) |

### 3.2 Views

| View | Content |
|---|---|
| **Truck profit** **[TS]** per month | Income − running costs = **contribution**; − fixed costs = **operating profit**; − depreciation and interest = **net profit**; cash and accrual views |
| Per-km figures | km (loaded/empty), revenue/km, cost/km, profit/km, **km/l vs standard** |
| **Fuel bill report** **[TS]** | All fuel for the truck, who paid, receipts, km/l |
| Utilisation | Days on trip / idle / workshop / breakdown; trips per month; empty-km % |
| Break-even | Fixed cost ÷ contribution per km = km needed per month |
| Truck comparison | All trucks side by side; same-model averages |
| Life-to-date | Revenue, cost, profit, loan paid, book value since purchase; profit/loss on sale |
| Budget vs actual | Monthly budget per truck |
| Truck register | Trips with income, cost and profit; drill down to entries |
| Loan view | Lender, EMI schedule, principal/interest paid, outstanding, foreclosure amount, hypothecation on RC (NOC after closure) |
| Truck report card | One-page PDF for partners and lenders; WhatsApp share |

---

## 4. Supplier Ledger (hired / market trucks)

Decision D13 brings this into **phase 1** (previously default A3 put hired
trucks later; see `00-decisions.md`).

### 4.1 Supplier set-up

| Item | Fields |
|---|---|
| **Supplier profile** **[TS]** | Type: owner-operator / fleet operator / vehicle broker; name, mobile, address, **PAN** (verified), GSTIN (if a GTA), Aadhaar (masked, optional), bank account (verified by penny-drop, phase 2), UPI, preferred routes, rating, **blacklist flag** with reason |
| Supplier vehicles | Reg no., type/body/size, RC copy, **RC owner name** (Vahan check, phase 2) vs PAN name, insurance/permit/fitness validity, usual driver name, mobile, licence |
| ≤10-truck declaration | Per supplier **per financial year**: signed copy, trucks owned, PAN, date; valid for that year only; March reminder to collect the next one |
| Default terms | Advance % at loading, balance days after POD, who pays tolls/loading, detention terms, penalties, office charge (munshiana), commission |

### 4.2 Market-truck trip flow

A trip can use **our truck** or a **hired truck**. For a hired truck:

```
Trip created with "Hired truck" ─► Lorry hire challan (agreed hire, terms)
   ─► Advance(s) at loading ─► Loaded / in transit (supplier's driver can use the
       crew app as a guest, or office records milestones)
   ─► Delivered ─► POD received & verified ─► Deductions + TDS ─► Balance paid
```

The client freight and the hire amount on the same trip give the **margin per
load**.

### 4.3 Entry types

| Entry | Notes |
|---|---|
| **Lorry hire challan** **[TS: hire rate]** | Credits the supplier with the agreed hire; links trip, LRs, vehicle. Fields in `10-invoicing-lr-templates.md` |
| Advance at loading | One or more; cash / bank / UPI / cash to the market driver; UTR; acknowledgement |
| Fuel given on supplier's account | Diesel from our credit pump charged to the supplier, deducted from hire (links Vendor Ledger) |
| Detention / extras payable | Per POD dates and agreed slabs |
| Deductions | Shortage, damage, late delivery, late POD fee, claim passed on from the client, office charges, advance recovered, fuel given |
| Broker commission | Deducted from hire or paid separately; % or fixed |
| **TDS on hire** | Automatic: valid PAN + declaration for the year → **nil TDS but still reported**; otherwise 1% (individual/HUF) / 2% (others) above ₹30,000 per payment or ₹1,00,000 per year; no PAN → higher rate; TDS certificate to the supplier; old and new section numbers shown **(verify)** |
| **Payment / balance due** **[TS]** | Balance released only when POD is verified, deductions entered and TDS applied; batch payment file |
| GST | Hiring a goods vehicle to a GTA is exempt; special cases (verify) |

### 4.4 Reports and controls

Supplier statement (per supplier and per vehicle) **[TS]**, hire register with
margin per load, pending balance split (POD not received / received not paid /
paid), **advance without POD ageing** (biggest loss risk), TDS register,
WhatsApp share of hire challan and statement, POD upload link for the supplier.

---

## 5. Vendor Ledger

### 5.1 Vendor set-up

| Item | Fields |
|---|---|
| **Vendor profile** **[TS]** | Category: fuel pump, workshop/mechanic, tyre dealer, spare parts, batteries/lubricants, insurance company/agent, RTO agent, GPS/FASTag provider, loading labour, weighbridge, towing, rent, CA/legal, other; GSTIN/TRN, PAN, **Udyam no. and type** (micro/small/medium), payment terms (credit days; written agreement yes/no), bank/UPI, default TDS section, input-credit eligible flag; for pumps: **GPS location** (to match fuel entries) |
| Price agreements | Pump discount per litre, tyre/parts rate lists, workshop labour rates |

### 5.2 Entry types and workflows

| Entry | Notes |
|---|---|
| **Purchase / expense bill** **[TS]** | Bill no., date, tax ID, items/services, HSN/SAC, taxable value, tax, input credit eligible/blocked, truck/trip/branch, attachment, due date |
| **Fuel pump credit** | Office issues a numbered **fuel slip** (slip no., truck, driver, litres or amount, pump); the crew's fuel entry links the slip; the pump's monthly statement is imported (Excel/CSV) and **auto-matched**; exceptions flagged (missing slip, extra litres, rate above agreed, wrong truck) |
| Workshop | Estimate → owner approval → job card → bill (parts + labour) → warranty; links service jobs |
| Tyre dealer | Tyre serial numbers on the bill create tyre records; retread bills; warranty claims as credit notes |
| Insurance | Premium bill → policy in Document Manager → expense spread to the truck; claim receivable |
| RTO agent | Government fee + agent charge, per truck and document; links the renewal in Document Manager |
| **Payments** **[TS]** | Bill-wise, advance, on account; batch payment run (bills due → bank file); payment advice by WhatsApp/email |
| Debit notes | Returns, short supply, rate dispute |
| TDS on vendors | Repair/loading labour contracts, professional fees, rent, etc. **(verify section mapping under the new Act)** |
| **MSME due-date control** | Due = agreed days (max 45) or 15 days without written agreement; alerts at 30/40/45 days; year-end list of overdue MSME dues (tax disallowance, s.37(2)(g)) **(verify)** |
| Input credit checks | Purchase register vs GSTR-2B / IMS; vendor not filing; reversal if unpaid after 180 days **(verify)** |

### 5.3 Reports

Vendor statement and ageing **[TS]**, payables due, statement reconciliation
(vendor's statement vs our books), balance confirmation, fuel slip register and
mismatch report.

> **Country note:** in India diesel is outside GST (no input credit on diesel;
> lubricants and AdBlue carry GST). In the UAE, fuel VAT is recoverable with the
> supplier's TRN.

---

## 6. Document Manager

One module for documents of **trucks, crew, company, clients, suppliers and
vendors**.

### 6.1 Document types (defaults per country; company can add more)

| Owner | India | UAE |
|---|---|---|
| **Truck** **[TS]** | **RC** (with hypothecation), **insurance** (policy, period, IDV, insurer), **fitness**, **permit** (national/state + NP authorisation), **PUC**, road tax, FASTag, AIS-140 tracker certificate, speed governor certificate, loan papers/NOC, purchase invoice | Mulkiya, insurance, RTA/ITC permits, Salik tag, tracker (SecurePath/Asateel) |
| **Crew** **[TS]** | **Driving licence** (transport, hazardous endorsement), badge, **Aadhaar (first 8 digits masked)**, PAN, bank proof, photo, address proof, medical/eye test, police verification, appointment letter | Licence, Emirates ID (encrypted), passport, visa, labour card, medical, ILOE policy |
| Company | GST certificate(s), PAN, TAN, Udyam, trade licence, Carriage by Road Act registration, GTA Annexure V filing | Trade licence, VAT TRN certificate, insurance |
| Client | Contract, rate agreement, GST certificate, PAN, Form 16A (TDS certificates) each quarter, our ≤10-truck declaration | Contract, TRN certificate |
| Supplier / vendor | PAN, ≤10-truck declaration per FY, RC copies, cancelled cheque, Udyam, GST certificate | TRN certificate, trade licence |

### 6.2 Features

| Feature | Details |
|---|---|
| Document record | Type, owner, number, authority, issue date, valid from, **expiry date**, amount paid, vendor (insurer/agent), files (front, back, multi-page PDF), notes, **where the original is kept** |
| **Expiry alerts** **[TS]** | Per type: days before (default 30 / 15 / 7 / 1) and **repeat after expiry** until renewed; recipients (owner, manager, driver); channels (push, SMS, email, WhatsApp); snooze with reason |
| Compliance dashboard | Expired / due in 7 / 15 / 30 days; calendar; per-truck "document health" green/amber/red |
| **Renewal** **[TS]** | "Renew" makes a **new version**; old one archived read-only; renewal history; who renewed and through which agent |
| Cost posting | Renewal cost posts to the truck ledger (vendor bill or cash); insurance and road tax spread over the validity period |
| Dispatch gate | Expired document blocks dispatch; manager override with reason (`03-trip-lifecycle.md` §4) |
| **Upload from both apps** **[TS]** | Owner app, crew app (photo/file), web; bulk upload with auto-link by file name |
| **Share** **[TS]** | One document or a **document pack** ("all papers of KL-07-AB-1234") on WhatsApp as PDF or as an **expiring secure link**, with watermark "Copy shared on <date> for <purpose>" |
| Crew access | Crew app shows the current truck's documents **offline** for roadside checks |
| OCR auto-fill (phase 2) | Reads number and dates from the image; low-confidence fields go to a review queue |
| **Vahan RC fetch** (phase 2) **[TS]** | By reg no.: owner name, class, fuel, norms, insurance/PUC/fitness/permit/tax validity, financer, blacklist status — through an aggregator (Surepass, Gridlines, Cashfree, IDfy) behind a switchable provider interface. Paid per check, so it needs your approval. |
| Sarathi DL fetch, DigiLocker pull, e-challan check (phase 2) | Through the same provider interface, with the driver's OTP consent for DigiLocker |
| Storage limits | Images compressed (250–500 KB rule); PDF size cap; **storage quota per plan**; old versions never silently deleted while legally needed |
| Privacy | Crew ID documents visible only to owner/HR roles; Aadhaar masked; every view and download logged |
| Insurance claims | Claim no., surveyor, estimate, approved, received, linked to accident and repair |

---

## 7. Trucksarthi parity checklist

Every Trucksarthi feature found, and where we cover it. (Source:
`trucksarthi_features.md` §7.)

| Trucksarthi feature | Our place |
|---|---|
| Driver ledger: salary, trip advances, expenses, settlement, net balance due, monthly salary summary, driver cash register PDF, WhatsApp text summary, who-paid on fuel | §1 |
| Client ledger: client master, ledger, partial/full payments, payment due, client ledger PDF, transaction report | §2 |
| Truck ledger: running vs maintenance expenses, fuel bill report, profit per truck, truck documents | §3, §6 |
| Supplier ledger: market-truck supplier, hire rate, payments, balance due | §4 |
| Vendor ledger: fuel pumps, mechanics, spare parts, RTO agents; bills, payments, balance | §5 |
| Document manager: RC, insurance, fitness, PUC, permit, licence, Aadhaar, expiry alerts, upload from both apps, share | §6 |
| Bilty/LR/GR and GST invoice from a trip, itemised bilties report, POD upload, PDF English/Hindi, WhatsApp share, e-way bill | `10-invoicing-lr-templates.md` |
| Trips: create, assign, status one-tap (started / in transit / completed), trip expenses, trip profit, live progress | `03-trip-lifecycle.md` |
| Expenses & fuel: categories, running vs maintenance, 2-step fuel form with receipt photo and who-paid | `03-trip-lifecycle.md` §5, §1 here |
| Reports: income, expense, trip, profit, P&L overview, transaction reports | `04-web-app-ux.md` Reports |
| Driver app (free): own trips/money/ledger only, status updates, expenses, POD/document upload, offline, optional location, **one driver working for several owners** | `05-mobile-app-ux.md` + §8 below |
| Web dashboard with **QR login from the phone app** | §8 below |
| Signup with business verification (PAN, GST, bank), biometric lock, theme, invoice settings, English/Hindi | §8 below, `02-roles-permissions.md` |
| Account deletion with 72-hour cancellable countdown; data kept read-only when the plan expires | §8 below |
| 30-day free trial, one plan with all modules, driver app free | Pricing (decision Q2 pending) |
| Support by phone, WhatsApp, email; grievance officer; demo booking | Marketing website |

## 8. Platform features added for parity

| Feature | Design |
|---|---|
| **One crew member, several companies** | A crew member's login (mobile number) can belong to more than one company. The crew app shows the current company on the home screen and switches when a notification from another company is opened. Each company sees only its own data. (Data model: a global `accounts` identity linked to per-company `users`.) |
| **QR login to the web app** | Web login page shows a QR code (refreshes every 2 minutes). The owner app scans it to sign the browser in, like WhatsApp Web. We allow **many** signed-in users with roles (Trucksarthi allows one desktop). |
| Business verification at signup (phase 2) | PAN, GSTIN and bank checks through a verification provider (paid per check; needs your approval) |
| Account deletion | Owner requests deletion → 72-hour countdown that can be cancelled → data export offered → deletion (legal retention copies kept as required by law, then purged) |
| Plan expiry | Data stays **read-only** after the plan ends; everything is restored on renewal |
| Theme | Light / dark / follow phone |
