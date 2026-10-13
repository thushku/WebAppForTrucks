# Accounting, Tax and Payroll (India + UAE)

Decision D10: the web app must be a **full accounting system** that meets the
legal rules of each country, starting with **India** and **UAE**.

Source: research notes in `research_notes/Truck fleet app research India UAE/`
(`india_legal_accounting.md`, `uae_legal_accounting.md`). Items marked
**(verify)** could not be confirmed from primary sources during research and
must be checked with a Chartered Accountant (India) or a UAE tax agent before
that feature is built.

> **Simple idea:** the crew and the office do their normal work (trips, fuel,
> settlements, salary). The system writes the accounting entries by itself in
> the background. The accountant only adds what the operations side cannot
> know (bank charges, loans, capital, depreciation, adjustments).

---

## 1. The accounting core (same for every country)

| Part | What it does |
|---|---|
| **Chart of accounts** | Groups and ledgers, with a ready-made default per country (India: Tally-style groups; UAE: IFRS / IFRS-for-SMEs style). Each party, crew member, truck, bank and tax gets its own ledger automatically. |
| **Vouchers** | Sales invoice, purchase bill, receipt, payment, journal, contra (cash↔bank), expense, credit note, debit note, payroll. Each has its own number series per financial year. |
| **Double entry** | Every voucher must balance (debit = credit) before it can be posted. |
| **Cost centres** | Every line can carry **trip**, **truck** and **branch**. This gives profit per trip, per truck and per branch straight from the books. |
| **Financial years and locks** | Each year can be locked. A locked period cannot be changed; corrections go into the open period. |
| **Audit trail** | Every create, edit, cancel is logged with old and new values, user, time, IP and device. **It cannot be switched off** (India Companies Act rule). Logs are kept 8 years (India) / 7 years (UAE). |
| **No deleting** | Posted vouchers are cancelled with a reason, never deleted. |
| **Attachments** | Bills, receipts and photos are linked to vouchers. |
| **Bank reconciliation** | Import bank statement (CSV/Excel), match with entries, show differences. |
| **Outstanding** | Receivables and payables with ageing (0-30, 31-60, 61-90, 90+ days), reminders to parties. |
| **Fixed assets** | Trucks, trailers and equipment with depreciation (Indian Companies Act / Income-tax rates; IFRS for UAE). |
| **Statements** | Day book, ledger, trial balance, profit & loss, balance sheet, cash flow. India companies: Schedule III format **(verify)**. UAE: IFRS format. |
| **Export** | Excel / PDF for everything; **Tally XML** export of vouchers and masters for Indian accountants **(verify format)**. |
| **Year-end** | Closing entries, carry forward balances, lock the year. |

### 1.1 Automatic entries from operations

| Event | Entry written by the system |
|---|---|
| Cash advance to driver Ravi | Dr Ravi (crew ledger) · Cr Cash/Bank |
| Fuel paid in cash by Ravi, approved | Dr Fuel expense [trip, truck] · Cr Ravi |
| Fuel on pump credit, approved | Dr Fuel expense [trip, truck] · Cr Pump (vendor) |
| Fuel on company fuel card | Dr Fuel expense · Cr Fuel card account (matched with card statement) |
| Toll by FASTag / Salik | Dr Toll expense [trip, truck] · Cr FASTag / Salik wallet |
| Payment without receipt, approved | Dr "Trip payments without receipt" expense [trip, truck] · Cr Ravi |
| Ravi returns cash at settlement | Dr Cash · Cr Ravi |
| Bata for Ravi | Dr Bata expense [trip] · Cr Ravi |
| Monthly salary | Dr Salary expense · Cr Ravi (then payment: Dr Ravi · Cr Bank) |
| Freight invoice to ACC Ltd | Dr ACC Ltd · Cr Freight income [trip, truck] (+ tax lines as per country) |
| Party pays freight | Dr Bank · Cr ACC Ltd (TDS deducted by party → Dr TDS receivable) |
| Office-side trip expense added after close | Dr Expense [trip] · Cr Cash/Bank/Party |

Rejected entries post nothing. A cancelled entry posts an exact reversal.

---

## 2. Country pack: India

### 2.1 Business set-up

| Setting | Options |
|---|---|
| Entity type | Owner-driver (individual, often no GST), proprietorship, partnership/LLP, company (turns on Companies Act features) |
| GST registrations | One or more GSTINs (multi-state), each with its own invoice series |
| GTA tax option per GSTIN per financial year | **5% reverse charge** (default) · **5% forward charge** (no input credit) · **18% forward charge** (with full input credit). Options in force from 22 Sep 2025 (GST 2.0). Chosen through Annexure V; locked for the year. Deadline date: **(verify — sources differ: 15 or 31 March)** |
| Non-GTA mode | A transporter who issues **no consignment note** is not a GTA; road transport is then exempt. Hiring a vehicle to a GTA is exempt. The app supports this with "bill of supply" instead of tax invoice. |
| SAC codes | 9965 / 996511 (goods transport by road) and related codes, held in a dated rate table so future changes are just new rows |

### 2.2 Documents

| Document | Rules the app enforces |
|---|---|
| **LR / consignment note** | One per consignment; own number series; consignor, consignee, GSTINs, vehicle, from/to, goods, weight, freight, who pays GST |
| **Tax invoice** (Rule 46) | All mandatory fields; **"Tax payable on reverse charge: Yes/No"**; place of supply; number **max 16 characters** using letters, digits, `-` and `/`, **unique per financial year**, for example `SR/26-27/00042` |
| Bill of supply | For exempt services |
| Credit / debit notes | Linked to the original invoice |
| **E-way bill** | Store the EWB number per consignment; track validity (1 day per 200 km; 20 km for over-dimensional cargo); alert before expiry; Part B (vehicle) updates on truck change; consolidated EWB. No EWB for documents older than 180 days; extensions capped at 360 days in total. Phase 2: direct API link through a GST Suvidha Provider. |
| **E-invoice (IRN)** | **GTA services are exempt from e-invoicing** at any turnover. Only needed for other taxable supplies when turnover is above ₹5 crore (with a 30-day reporting limit at ₹10 crore+). Phase 2+, only if customers need it. |

### 2.3 Tax reports and exports

| Report | Notes |
|---|---|
| GSTR-1 / GSTR-1A data (JSON + Excel) | GSTR-3B outward tables are hard-locked from July 2025; corrections go via GSTR-1A |
| GSTR-3B working | Reverse-charge supplies in table 3.1(d) |
| Input credit matching | Purchase register vs GSTR-2B / Invoice Management System (IMS) |
| GSTR-9 annual data | |
| TDS | New Income-tax Act 2025 (from 1 Apr 2026): contractor TDS is **section 393(1)** (old 194C): 1% individuals/HUF, 2% others, above ₹30,000 per payment or ₹1,00,000 per year. Transporters owning ≤10 goods carriages with PAN declaration: **section 393(4)** (old 194C(6)) — no TDS, but must still be reported. Show **both old and new section numbers** during 2026-27. |
| TDS receivable | Track TDS deducted by customers; match with Form 26AS / AIS |
| Presumptive income | Per-vehicle calculator for small owners (old 44AE → new section 58) **(verify text)** |
| Tax audit | Turnover monitor for audit (old 44AB → new section 63) **(verify thresholds)** |
| MSME payments | Tag vendors with Udyam number; warn when payment passes 15/45 days (old 43B(h) → new 37(2)(g)) |

### 2.4 Cash rules for driver expenses

- A cash payment to one payee above **₹35,000 in a day** (limit for goods-carriage
  operators; ₹10,000 for others) is not allowed as a business expense. The crew
  app warns: "Cash over ₹35,000 to one pump in a day — use card or UPI."
- Cash **receipts** of ₹2 lakh or more from one person are blocked.

### 2.5 Books and retention (companies)

- Audit trail always on; **8 years** retention; **daily backup on servers in
  India**. Indian companies' data is therefore hosted in an Indian data centre.

### 2.6 Payroll — drivers and helpers

The four **Labour Codes** came into force on **21 Nov 2025**. The Motor Transport
Workers Act is now part of the OSH Code.

| Rule | App behaviour |
|---|---|
| 8 hours/day, 48 hours/week | Hours monitor from driver-change log and GPS; alerts |
| Overtime at **2×** wages, only with consent | Overtime calculation + consent record |
| "Wages" must be at least 50% of total pay | Salary structure check with a warning |
| Fixed-term staff get gratuity after 1 year | Gratuity accrual |
| PF, ESI, professional tax, bonus, leave accrual | Calculations and exports **(verify current thresholds and state rules before build)** |
| Advances and deductions | Instalment plans; deduction limit check **(verify cap)** |
| Registers and wage slips | Electronic registers in the required formats **(verify formats)** |

---

## 3. Country pack: UAE

### 3.1 Business set-up

| Setting | Options |
|---|---|
| Entity type | Mainland company, free-zone company (qualifying free-zone person flag), sole establishment |
| VAT | Registration status and TRN. Monitor: mandatory at **AED 375,000** taxable turnover, voluntary at **AED 187,500** (rolling 12 months + next 30 days) |
| Corporate tax | 9% above AED 375,000 profit **(verify)**. Small business relief for revenue ≤ AED 3m, extended to tax periods ending by **31 Dec 2029** (Ministerial Decision 131/2026). Cash basis allowed only up to AED 3m revenue; IFRS for SMEs up to AED 50m; full IFRS + audit above. |

### 3.2 VAT on transport

| Case | VAT |
|---|---|
| Domestic transport within UAE | 5% standard |
| International transport (export/import, GCC cross-border) | 0% zero-rated, with evidence (origin/destination country, exit/customs documents, CMR or bill of lading) |
| UAE leg of an international move | 0% **only if the same company carries the international leg**; a subcontractor doing only the UAE leg charges 5% (FTA clarification 2025) |
| Salik tolls and Parkin parking | 5% VAT from **1 Jun 2026** (date-aware tax code; before that, out of scope) |
| Imported services (foreign software, foreign repairs) | Reverse charge; from 2026 no self-invoice is needed |

Each trip/consignment gets a **transport type** flag (domestic / export /
import / UAE leg of international) and a **principal or subcontractor** flag,
so the right VAT is applied automatically.

### 3.3 Documents

| Document | Rules |
|---|---|
| Tax invoice | All Article 59 fields **(verify exact list)**; issued within 14 days |
| Simplified invoice | Up to AED 10,000; not for reverse-charge supplies |
| Credit note | Linked to the original invoice |
| **E-invoice (Peppol, PINT-AE)** | Must go through an **Accredited Service Provider (ASP)**; we cannot send directly to the tax authority. **Revenue ≥ AED 50m:** appoint ASP by 30 Oct 2026, live **1 Jan 2027**. **Others:** appoint by 31 Mar 2027, live **1 Jul 2027**. Penalties: AED 100 per late invoice (up to AED 5,000/month); AED 5,000/month for not implementing. |

**Our plan:** the invoice data model is built to match PINT-AE fields from day
one (TRN / Peppol IDs, line VAT category codes S/Z/E/O/reverse charge, currency,
invoice type codes, credit-note references). Sending and receiving e-invoices is
done by integrating with one or more ASPs (phase 2, before UAE launch). Fuel
companies, workshops and Salik will also send **inbound** e-invoices, which the
system should receive and post automatically.

### 3.4 Payroll — UAE Labour Law (Federal Decree-Law 33 of 2021)

| Rule | App behaviour |
|---|---|
| Pay split | **Basic** separate from allowances (housing, transport, food, trip allowance); overtime, gratuity and leave pay are calculated on basic |
| Overtime | 125%; **150% between 22:00 and 04:00**; max 2 hours/day; warn if no 1-hour break after 5 hours |
| Ramadan hours, midday break (12:30-15:00, 15 Jun-15 Sep) | Calendar rules for helpers doing outdoor work |
| Annual leave | 30 days a year (2 days/month in months 6-12) |
| Sick leave | 15 days full pay, 30 days half pay, 45 days unpaid |
| Gratuity | 21 days' basic pay per year for the first 5 years, 30 days after; capped at 2 years' pay; monthly provision |
| **Deductions** | Total ≤ **50%** of wage; damage ≤ 5 days' wage per month; advances need **written consent** and **no interest**. Payroll run is **blocked** if a cap is broken. |
| Traffic fines recovered from driver | Only within the deduction caps and with a documented basis |
| **WPS** | Salary file (SIF) export per bank format; salaries due by the **1st of the next month** (Ministerial Resolution 340/2026, from 1 Jun 2026), at least 85% paid on time; bonus and variable pay through WPS |
| Unemployment insurance (ILOE), Emiratisation | Policy tracker per employee; headcount monitor for 20-49 and 50+ staff companies |

### 3.5 Road rules that touch accounts and trips

- **Fines register:** fine number, date/time, place, authority, violation,
  amount, black points, truck, driver (matched by GPS and trip time). Black-point
  totals per driver.
- **Truck-ban times** (Dubai from 1 Oct 2026; Abu Dhabi peak bans) are stored as
  editable time-based zones. Trip planning warns if the expected time falls in
  a ban window.
- **Salik / Darb** statement import and matching to trip and driver.

---

## 4. Hosting and data protection by country

| | India | UAE |
|---|---|---|
| Law | DPDP Act 2023 + DPDP Rules 2025 (core duties from about **May 2027**) | PDPL (Federal Decree-Law 45 of 2021); consent-first |
| Hosting | **Indian data centre** (also needed for Companies Act daily backup) | **UAE data centre** by default |
| GPS tracking | Employment purpose allowed; contract owner-drivers need consent; on-duty only | Explicit, versioned, withdrawable consent; on-duty only |
| ID documents | Aadhaar: store only masked (first 8 digits hidden) | Emirates ID stored encrypted; access logged |
| Breach | 72-hour reporting workflow | Prompt tenant notification |
| Retention | Raw GPS points thinned/purged after a set time; trip summaries and books kept 8 years | Books 7 years; raw GPS purged sooner |

**Recommendation:** run **two separate installations** of the same code, one in
India and one in the UAE. Each country's customers sign up on their own
regional site. This meets both countries' hosting rules simply.

---

## 5. Build order for accounting

| Phase | Scope |
|---|---|
| **1 (with first India launch)** | Accounting core, automatic entries from trips and settlements, LR, GST tax invoice / bill of supply, credit notes, receipts and payments, outstanding, bank reconciliation, statements, audit trail, Tally/Excel export, GSTR-1/3B data, TDS tracking, cash-limit warnings |
| **2** | Payroll (India Labour Codes), e-way bill API, fixed assets and depreciation, presumptive income, MSME report |
| **3 (before UAE launch)** | UAE pack: VAT engine, tax invoice, PINT-AE e-invoice via ASP, WPS SIF, UAE payroll, Salik/Darb import, fines register, corporate tax data |
| Later | India e-invoice (IRN) for non-GTA supplies, other countries |

Every item marked **(verify)** gets a CA / tax-agent check before it is coded.
