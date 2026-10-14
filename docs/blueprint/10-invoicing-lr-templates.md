# Invoices & LR, Document Templates and the Template Editor

> **See also `13-parity-additions.md`** — additions from the Trucksarthi code study (D17) that extend this file; where they differ, that file wins.

Decisions **D13** (Trucksarthi parity: Invoices & LR module), **D14** (invoicing
like Clinic Ledger, with invoice templates and a template editor) and **D15**
(tax rules for any country — see `11-tax-engine.md`).

Sources: `research_notes/Trucksarthi modules and global invoicing/`
(`clinicledger_invoicing_study.md`, `transport_modules_expert.md`,
`trucksarthi_features.md`). **[CL]** = taken from Clinic Ledger. **[TS]** =
Trucksarthi feature. **(verify)** = CA / tax-agent check before building.

> **Main idea:** the founder's Clinic Ledger already has a mature invoicing and
> template system in Laravel. We **reuse its design and much of its code**,
> rename clinic words to transport words, and add the documents a transporter
> needs (LR with copies, freight bill with many LRs, lorry hire challan, money
> receipt with TDS).

---

## 1. Document families

Each family has its own number series, its own layout in every theme, its own
"what prints" switches and its own titles.

| Family | Documents | Side | Posts to accounts? |
|---|---|---|---|
| **LR** **[TS]** | Lorry receipt / bilty / GR / consignment note | Sales (operations) | Only when freight is collected on it (Paid / To-Pay) |
| **Invoice** **[CL][TS]** | Tax invoice, bill of supply, simplified tax invoice (UAE), plain invoice, **supplementary invoice** (detention, rate difference) | Sales | Yes |
| **Notes** **[CL]** | Credit note, debit note | Sales / purchase | Yes |
| **Receipt** **[CL]** | Money receipt / receipt voucher (with TDS-deducted settlement), driver To-Pay collection receipt | Sales | Yes |
| **Hire challan** | Lorry hire challan / truck hire note / freight memo | Purchase (supplier) | Yes |
| **Payment voucher** | Payment to driver, supplier or vendor, with receiver's signature | Purchase | Yes |
| **Quotation** | Quotation / rate confirmation / proforma (can be converted to a bill) | Sales | No |
| **Operations slips** | Loading slip, trip sheet, fuel slip, delivery challan, manifest (part-load, later) | Operations | No |
| **Statements** **[CL]** | Statement of account (any ledger), driver cash register, salary slip, trip settlement, itemised bilties report **[TS]**, fuel bill report **[TS]** | Reports | No |

Document type is stored as text with validation (not a fixed database list), so
new types can be added without a migration — a lesson from Clinic Ledger.

---

## 2. Life of a money document **[CL]**

```
DRAFT ──finalise──► FINALISED ──send──► SENT ──open──► VIEWED ──pay──► PARTIAL ──► PAID
  │                     │                                                     
  └─ delete allowed     └─ VOID (reason required, number kept)  · OVERDUE (computed)
```

- **Draft:** fully editable; shows a DRAFT watermark; no number yet.
- **Finalise** happens in **one database transaction**:
  1. recalculate all lines and tax;
  2. take the next number under a row lock (gap-free);
  3. lock the exchange rate;
  4. freeze a **snapshot** of seller, buyer, bank and settings (encrypted where
     it holds bank details);
  5. freeze the **template design** used, so the document never changes look;
  6. create a secure public link;
  7. post the accounting voucher (Dr party, Cr income, Cr tax…);
  8. update the party's balance;
  9. write the audit log.
- After finalising, the document **can never be edited**. Fixes are made by
  **void** (number kept, reason required) or a **credit/debit note**. This is
  enforced in the model itself, so no screen, job or console command can bypass
  it.
- Payments become numbered **receipts** at once.

---

## 3. Numbering **[CL]** + transport rules

| Rule | Detail |
|---|---|
| Series per document type | LR, invoice, credit note, debit note, receipt, hire challan, payment voucher, quotation, fuel slip… |
| Format tokens | `{PREFIX} {BRANCH} {GSTIN-STATE} {FY} {YYYY} {YY} {MM} {0001}` — for example `COK/26-27/000123` |
| Scope | Per company, and optionally **per branch** and **per tax registration** (GSTIN/TRN) |
| Year | Restart every financial year or never; **each financial year keeps its own series** (back-dated documents never clash) |
| Start number | Editable until the first document of the series |
| Live preview | Shows the next number while setting up |
| India invoice check | **Max 16 characters**, only letters, digits, `-` and `/`, unique per financial year (GST Rule 46) — the format screen blocks anything longer |
| **Manual LR books** | For pre-printed LR books: book register (book no., range from–to, issued to branch/agent/driver, date); each leaf used / unused / **cancelled** / lost; a typed LR number is accepted only if it is in an issued book and unused; gap and duplicate report |
| Display | Numbers, dates and money never wrap in print; word joiners after hyphens **[CL lesson]** |

---

## 4. LR / bilty / consignment note **[TS]**

Created **from a trip** (one trip can have several LRs) or on its own; listed,
edited (while draft), shared and printed.

### 4.1 Fields

| Block | Fields (M = must, C = common, O = optional) |
|---|---|
| Header | M: transporter name, address, phone, GSTIN, PAN; C: logo, Carriage by Road Act registration no., branch; O: tagline, website |
| Title and copy | M: "CONSIGNMENT NOTE / LORRY RECEIPT"; **copy label**; C: risk banner ("AT OWNER'S RISK" / "AT CARRIER'S RISK") |
| Identity | M: LR no., date; C: booking branch, delivery branch, book no. (manual books); O: trip no., client order/indent/shipment no. |
| Route and vehicle | M: from, to, vehicle no.; C: driver name and mobile, vehicle type; O: via points, ETA |
| Parties | M: consignor and consignee (name, address); C: GSTINs, phones, **billing party** if different; O: notify party |
| Goods | M: description ("said to contain"); C: packages and type, private marks, actual weight, charged weight; O: HSN, dimensions (over-size), hazardous class, seal/container no. |
| Commercial | C: consignor invoice no(s), date(s), value(s); **e-way bill no(s) and validity** (several per LR allowed); O: PO no. |
| Value and risk | C: declared value; risk (owner's / carrier's) and **risk charge**; insurance (by whom, insurer, policy no., amount) |
| Charges | C: rate basis and rate, freight, LR charge, hamali (loading/unloading), door collection/delivery, risk charge, other; GST (only if forward charge on the LR); total; O: advance, balance |
| Payment | M: **Paid / To-Pay / To-Be-Billed / Free of charge**; M: **GST payable by: consignor / consignee / transporter** |
| Delivery | O: godown or door delivery, free days before demurrage |
| Footer | C: remarks, terms (or a back page), booking clerk signature and stamp, consignor signature, **POD box** (received in good condition, packages received, shortage/damage, receiver name, signature, stamp, date/time) |
| Digital extras | O: **QR code** to track the trip or view the e-POD; "page 1 of N" |

### 4.2 Copies

- A **copy set** per company: default **4 copies — CONSIGNOR COPY, CONSIGNEE
  COPY, DRIVER / LORRY COPY, OFFICE COPY**; optional ACCOUNTS / POD / BRANCH
  copies. (The Carriage by Road Act requires at least 3, original to the
  consignor.)
- All copies print in **one PDF**, each page with its copy label band, an optional
  tint per copy (like coloured LR books), and an option to **hide freight** on
  chosen copies (for example the driver copy).
- Optional **terms and conditions back page**.

### 4.3 Proof of delivery (POD) **[TS]**

- **e-POD in the crew app:** receiver name, mobile **OTP**, signature on the
  phone, stamp photo, GPS and time, packages received, shortage/damage remarks,
  unloading start and end (for detention).
- **Physical POD:** date the signed LR copy came back to the office; sent to the
  client with the bill (courier docket).
- POD status drives billing (Client Ledger queues) and supplier balance payment.

### 4.4 LR reports **[TS]**

**Itemised bilties report** (PDF/Excel, filters by client, period, route,
truck, payment type), LR register, unbilled LRs, To-Pay collection register,
POD pending.

---

## 5. Freight invoice / bill **[CL][TS]**

| Feature | Detail |
|---|---|
| Single-LR invoice | One bill per LR; can be created automatically when POD is verified |
| **Consolidated invoice** | Client, period, tax registration, LR picker ("**Add unbilled LRs**", POD-verified only); **summary + LR annexure** (LR no., date, vehicle, from–to, material, weight, rate, freight, detention, other, total); annexure totals tie to the invoice; optional POD images bundled into one PDF |
| Supplementary invoice | Detention/halting, extra loading, rate difference (diesel escalation), weight difference — linked to the original |
| Charge lines | Freight (by basis), detention (days × slab rate), loading/unloading, multi-point, toll (if billable), **fuel surcharge**, other |
| Line items | Date, description, detail, item code (SAC / HSN), unit, qty, rate, line discount %, tax per line **[CL]** |
| Discount | % or amount, spread across lines before tax **[CL]** |
| Less advance | "Less advance received on <date>" → **net receivable** (tax stays on the full value) |
| TDS note | Optional "TDS @1%/2% will be deducted by you" or "net payable after TDS" helper line (not part of the tax invoice) |
| **Tax** | From the tax engine (`11-tax-engine.md`): India GTA modes (5% reverse charge / 5% forward / 18% forward / exempt → bill of supply); place of supply **mandatory** (never defaulted silently); UAE 5% / 0% by transport type |
| Declarations | Printed automatically from a **dated, editable declaration table** by tax mode, e.g. "Tax payable on reverse charge: **Yes**" **(exact wording: verify)** |
| Round-off, amount in words **[CL]** | Indian style ("Rupees One Lakh Twenty Thousand Only"); AED "Dirhams … and … Fils"; in the document language |
| Bank and pay **[CL]** | Bank name, account, IFSC/IBAN, branch; **UPI QR for the balance due** (India); payment link QR elsewhere |
| Terms | Payment terms, late-payment interest, claims window, jurisdiction |
| Sign-off | Signature and stamp images; "For <Company> – Authorised Signatory"; digital signature (later) |
| **Copies** | Services (GTA freight is a service) → **2 copies: ORIGINAL FOR RECIPIENT, DUPLICATE FOR SUPPLIER** (GST Rule 48). Goods invoices (e.g. scrap sale) → 3 copies. Extra copies allowed (EXTRA / OFFICE COPY). |
| Bill submission | Submitted on, to whom, mode (hand / courier / email / client portal), acknowledgement no., portal ref; due date can start from submission |
| UAE | "Tax Invoice" title, TRN, VAT category per line, AED, optional **English + Arabic bilingual** template; e-invoice through an accredited provider (2027) |
| Created from a trip **[TS]** | "Create invoice" button on the trip page and in the owner app |

---

## 6. Other transport documents

### 6.1 Lorry hire challan (Supplier Ledger)

| Block | Fields |
|---|---|
| Header | Company, branch, GSTIN, PAN; "LORRY HIRE CHALLAN"; copy label (SUPPLIER COPY / OFFICE COPY) |
| Identity | Challan no., date, trip no., **LR no(s)** |
| Supplier | Name, mobile, address, **PAN**, ≤10-truck declaration for this FY (yes/no + date), GSTIN, bank/UPI |
| Broker | Name, mobile, commission (₹ or %) |
| Vehicle and driver | Vehicle no., type/size, RC owner name, driver name, mobile, licence no. |
| Load | From, to, consignor, consignee, material, packages, weight, freight basis |
| Money | **Hire amount** (fixed or rate × weight); detention terms; loading/unloading if payable; less **advance(s)** (date, mode, UTR, to whom), fuel given (slip nos.), commission, office charge, **TDS** (section, rate, amount, or "nil — declaration on file"); **balance payable** |
| Terms | Balance paid N days after POD; POD late fee; shortage/damage deducted; late-delivery penalty; supplier responsible for goods in transit; jurisdiction |
| Sign-off | Prepared by, approved by, supplier/driver signature or **in-app acceptance with OTP** |

### 6.2 Money receipt **[CL]** + transport additions

Receipt no., date, received from (and on behalf of), amount in figures and
words, mode (cash / cheque / NEFT / RTGS / UPI), cheque no./date, bank, UTR;
**against**: bill-wise list (bill no., date, amount, adjusted), or advance against
LR/trip, or on account; **TDS deducted by the party** shown so that **gross =
cash + TDS**; deductions settled in this receipt; balance after; "subject to
realisation" for cheques. A **receipt voucher** under GST applies to advances for
forward-charge supplies **(verify)**.

**Driver To-Pay collection** variant: LR no., To-Pay amount, collected by
(driver), vehicle, place, time and GPS from the crew app.

Layouts: A4, A5 and **thermal 80 mm** for counter receipts.

### 6.3 Payment voucher, loading slip, trip sheet, quotation, delivery challan

| Document | Key fields |
|---|---|
| Payment voucher | Voucher no., date, payee, amount and words, mode/UTR, against (trip / hire challan / bill), TDS, prepared / approved / received signatures |
| Loading slip | Slip no., date, truck, driver and mobile, consignor, loading point, material, expected qty/weight, LR no. (if known), advance given, instructions |
| Trip sheet | Route, km, expenses, advances, settlement — printable version of the trip page |
| Quotation / rate confirmation | Route, truck type, rate basis, rate, validity, detention terms, payment terms, acceptance signature; convert to trip/LR |
| Delivery challan | Movement that is not a sale (own tyres, spares between branches) |
| Fuel slip | Slip no., truck, driver, pump, litres or amount (see Vendor Ledger) |

---

## 7. Payments and allocation **[CL]** + transport additions

- Record one payment against **many invoices**: automatic (oldest first) or
  manual allocation; partial payments; leftover kept as **advance credit**.
- **TDS deducted by the client** and **client deductions** (shortage, damage,
  late POD) settle the bill together with the cash.
- Void / cheque bounce with full reversal.
- Every receipt is numbered and shows an "applied to" table and the balance
  after.
- **Party balance** read model updated in the same transaction (fast lists,
  no summing millions of rows).

---

## 8. Sharing and delivery **[CL][TS]**

| Channel | Detail |
|---|---|
| **WhatsApp** **[TS]** | One-tap share of the PDF from the phone's share button, or a `wa.me` link with the secure document link and a ready-made message. (WhatsApp Business API with automatic sending: phase 2.) |
| Email | PDF attached + secure link |
| **Secure public link** `/d/{token}` | 40-character random token, throttled, not indexed by search engines, optional expiry, never shows drafts; marks the document **viewed** |
| Download / print | PDF; browser print |
| Delivery trail | Every send, open, print and download is logged |
| Reminders | Automatic overdue reminders (email; WhatsApp in phase 2) |
| Languages **[TS][CL]** | Document language per party or per document, independent of the screen language: English, Hindi (+ regional after Q1), Arabic (right-to-left) |

---

## 9. Templates

### 9.1 How a template is stored **[CL]**

Users never type HTML or code (safe and reliable). A template has three layers:

1. **Built-in themes** (Blade layouts in our code), each with its own layout per
   document family.
2. **A design** = a small, strictly checked JSON object (theme, colours, font,
   sizes, section order, hidden sections, "what prints" switches, titles, paper,
   copy set…). Unknown values are dropped and reported in plain words.
3. **A frozen copy of the design on every issued document**, plus a layout
   version, so old documents never change look.

### 9.2 Built-in themes

| Theme | From | Good for |
|---|---|---|
| Modern, Classic, Minimal, Bold band, Sidebar, Compact, Elegant serif | **[CL]** (8 themes; the clinic "Medical statement" becomes **Ledger statement**) | Invoices, receipts, notes, statements |
| **Classic LR (boxed)** | New | LR in the traditional Indian book look, ruled boxes, copy band |
| **Compact A5** | New | LR, money receipt, loading slip |
| **Thermal 80 mm / 58 mm** | New | Money receipts and LR on the road (Bluetooth printer in the phone app) |
| **Bilingual** | New | English + Arabic (UAE) or English + Hindi side by side, right-to-left aware |

### 9.3 Template features

| Feature | Detail |
|---|---|
| **Named templates** | Several templates per document family, with **duplicate / save as**, rename, and **default per document type** |
| **Defaults per branch, tax registration and client** | A big client can have its own format; each branch prints its own address |
| Template versions | Each save is a version; documents print with the version used when issued; roll back to an earlier version |
| Logo, colours, fonts **[CL]** | Logo size/position; 6 accent swatches in one row + theme colour + colour picker with contrast check; 6 palettes; 4+ fonts served by the app and embedded in PDFs, including Noto faces for Indian scripts and Arabic |
| "What prints" switches **[CL]** + transport | Logo, address, tax registration (label follows country), registration numbers, bank, payment QR (with amount), tax columns, item codes, amount in words, period, reference, signature, notes, terms **+ vehicle, consignor/consignee tax IDs, e-way bill, payment type, GST payable by, risk banner, POD box, copy labels, freight on copy** |
| **Rename labels** | Change printed words ("Bill to" → "Billed to"); also the way to support more languages |
| **Custom fields** | Add your own fields (text / number / date / list) per document type, with a "show on print" switch |
| Column order and widths | Drag to reorder item-table and LR-annexure columns |
| Header and footer | Repeat on every page of long annexures; editable **footer text** |
| Letterhead | Header image/background, or **blank header space for pre-printed letterhead** |
| **Pre-printed stationery mode** | Print only the values at set millimetre positions onto old LR books and pre-printed bills |
| Terms and declarations library | Reusable text blocks; tax declarations come from the dated declaration table |
| Signature and stamp | Per user or per branch; uploaded images (≤ 500 KB) |
| Watermarks | Automatic DRAFT, VOID/CANCELLED, COPY, PAID |
| Bank + dynamic UPI QR **[CL]** | QR amount = balance due |
| **Copy sets** | Per document type (LR 4 copies, invoice 2, hire challan 2…), all in one PDF |
| Paper sizes | A4, Letter (from CL) + **A5 portrait/landscape, A4 landscape, Legal, thermal 58/80 mm**, custom size for LR books; "2 × A5 on one A4" |
| Number and date formats | Indian grouping (1,20,000.00), lakh/crore words, 3-decimal currencies (KWD, BHD, OMR), date formats |
| Barcode / QR | LR barcode or QR for scanning at the godown; tracking/POD QR |
| Page breaks | "Continued" lines, repeated table header, sections never split, margins on every page **[CL lesson]** |
| Message templates | WhatsApp / SMS / email texts per purpose and language, with placeholders (party, document no., amount, due date, link) |
| Right-to-left safety | Numbers, units, dates and codes wrapped so they read correctly in Arabic **[CL lesson]** |
| Permissions | Only owner/admin roles edit templates; every change is logged |
| Plan limits | Applied in one place (as Clinic Ledger's `forPlan`) |
| Export / import / reset **[CL]** | Export the design as a JSON file (no logo or bank data), import (checked on the server, previewed, waits for Save), reset to theme defaults (keeps titles) |

### 9.4 The template editor **[CL]** + additions

A full-screen page. The **real document** sits in the middle and is redrawn by
the server after every change (latest real document shown unpaid, or a sample
with the company's own name, logo and currency).

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ ← Templates  [LR][Invoice][Credit note][Receipt][Hire challan][Quotation]…  │
│ Template: "Classic LR – Kochi branch" ▾  [Duplicate] [Set default]           │
│ ● All changes saved     ↶ Undo  ↷ Redo   👁 Print preview  📄 PDF  💾 Save    │
├───────────────────────────────────────────────────┬─────────────────────────┤
│                                                   │ [Theme][Layout][Type]   │
│     ┌───────────────────────────────────────┐     │ [Fields][Titles][Copies]│
│     │ ▓▓▓ CONSIGNOR COPY ▓▓▓                │     │ [Labels][Share]         │
│     │ [LOGO]  SHARMA ROADLINES     LR 000123│     │                         │
│     │         GSTIN 32AAAAA0000A1Z5         │     │ Copies                  │
│     │ ┌─ section toolbar: ⋮⋮ ↑ ↓ 👁 ─────┐  │     │ ☑ Consignor  ☐ hide ₹   │
│     │ │ Consignor ........ Consignee ...  │  │     │ ☑ Consignee  ☐ hide ₹   │
│     │ └────────────────────────────────────┘  │     │ ☑ Driver     ☑ hide ₹   │
│     │ Goods table ...........................│     │ ☑ Office     ☐ hide ₹   │
│     │ Charges ............. Total ₹85,000   │     │ ＋ Add copy             │
│     │ POD box ...............................│     │ Tint per copy  ● ● ● ●  │
│     └───────────────────────────────────────┘     │ Paper: A5 ▾  2 per A4 ☐ │
│                 Page 1 of 4 (copies)  ◀ ▶          │ ☐ Terms back page       │
└───────────────────────────────────────────────────┴─────────────────────────┘
```

| Part | Controls |
|---|---|
| Top bar **[CL]** | Document-type tabs, template picker, duplicate, set default, saved indicator, undo/redo (Ctrl+Z/Y, 100 steps), Ctrl+S, print preview, **server PDF of the unsaved design**, Save, unsaved-changes guard |
| On the page **[CL]** | Click a section → outline + toolbar: **drag up/down**, arrows, hide (the item/goods table can never be hidden); logo corner resize (8–30 mm); margin guide (10–25 mm); totals width (40–90 mm); live measurement tip. No free pixel placement (it breaks long tables, page 2, RTL and PDF match). |
| Theme tab **[CL]** | Theme thumbnails in the chosen colour; swatches in one row; colour picker + hex with contrast readout; palettes |
| Layout tab **[CL]** + | Section order, logo size and position, margins, totals width, table density, **paper size**, copies per sheet |
| Type tab **[CL]** | Font, text size 9–12 pt, heading/body/muted colours with contrast check |
| Fields tab **[CL]** + | "What prints" switches incl. transport fields; **custom fields**; column order |
| Titles tab **[CL]** | One title per document type, including the title when tax is charged ("TAX INVOICE"); empty = the document language's own word; printed exactly as typed; ≤ 60 characters, plain text |
| **Copies tab** (new) | Copy set, labels, tint, hide freight per copy, terms back page |
| **Labels tab** (new) | Rename printed labels per language |
| Share tab **[CL]** | Export / import design JSON, reset |
| **Pre-printed mode** (new) | Upload a scan of the old LR book as a guide; place value boxes by millimetre; test print |

A simpler **Settings › Invoices & templates** page (theme, colour, font, logo,
paper, titles, payment QR, what prints, live preview, numbering, default terms)
saves everything in one transaction **[CL]**.

---

## 10. Rendering **[CL]**

- Every document is **one self-contained HTML page with print CSS**. The same
  page is used for the editor preview, browser printing, the public link and the
  PDF.
- **PDF is made by headless Chromium on the server** (no PDF package). It shapes
  Hindi, Malayalam, Tamil and Arabic correctly, which simple PHP PDF libraries
  do not. Falls back to the browser print dialog. (Server needs Chromium
  installed.)
- Fonts are self-hosted (no Google requests) with Noto faces for Indian scripts
  and Arabic in every font stack, embedded in PDFs.
- `@page` sizes per paper; margins repeated on every page; table header repeats;
  "fit automatically" uses compact rows to keep short documents on one page.
- **Thermal printing from the phone app:** the server sends a ready image (PNG)
  of the receipt/LR sized for 58/80 mm; the B4A app prints it over Bluetooth as
  a raster image (Indian scripts do not print correctly as plain printer text).
- PNG preview image for WhatsApp messages.

---

## 11. Reuse plan from Clinic Ledger

| Copy almost as-is | Port with renames | Do not port | Build new |
|---|---|---|---|
| Design schema (strict JSON, normalise, plan limits, contrast), theme Blade files and shared parts, template editor (JS, controller, view), document renderer + headless-Chromium PDF, fonts + fetch script, invoice calculator, numbering, tax rate components + country packs, UPI QR + QR encoder, amount in words, money formatting, sending / public link / delivery trail, security headers (CSP nonce), test patterns | clinic/client → party; practitioner → user + branch; tenant → company; visit → trip / consignment / LR; patient → consignor / consignee / reference; service units → freight rate basis; ledger entries → vouchers + party balance; UUID ids and shards → BIGINT + `company_id` (UUIDv7 only for phone-made rows) | The old settings-columns bridge (caused two bugs), layout version 1, ENUM document types, clinic samples and labels | LR with copies, back page and POD box; consolidated freight bill with LR annexure; supplementary invoice; hire challan; money receipt with TDS; payment voucher; quotation; operations slips; named templates with duplicate and defaults per branch/registration/client; versions; custom fields; label renames; copy sets; extra paper sizes and thermal; pre-printed mode; bilingual layout; global tax additions (`11-tax-engine.md`) |

Ported code keeps Clinic Ledger's plain-English docblocks and conventions.

---

## 12. Reports from this module

LR register, itemised bilties **[TS]**, unbilled LRs, To-Pay collections,
POD pending, invoice register, sales by client/route/truck, outstanding and
ageing, tax collected by rate and component, item-code (SAC/HSN) summary, hire
challan register, receipts by method, document delivery trail. CSV/Excel always
identical to the screen **[CL]**.
