# Clinic Ledger — invoicing, templates and the template editor (study for the truck platform)

_Study date: 14 Oct 2026. Written for decisions D13, D14 and D15 in `docs/00-decisions.md`._

**What was read (read-only, nothing changed in either repo):**

- App code: `/home/user/clinicledger` (Laravel 13, last commit `64cc1ba`, 8 Oct 2026). `CLAUDE.md` and `AGENTS.md` were read first.
- Project folder: `/home/user/clinicledger-project`: `PROJECT-STATE.md` (sections 3, 5, 6, 8 and 9 were searched), `REVIEW-2026-10-05.md`, `TESTING.md`, `docs/PLAN.md`, `docs/TEMPLATE-EDITOR.md`, the theme photos in `docs/themes/`, `docs/phase*.png`, the six owner screenshots (`*.PNG`) and `android/`.

All file paths below are relative to `/home/user/clinicledger` unless they start with `clinicledger-project/`. Line numbers are from the code as it is today.

---

## Overview

Clinic Ledger is a multi-tenant SaaS for visiting doctors and dentists. It bills clinics and private clients. Its invoicing module is mature. It was built in milestones M4 and M5 (28 Sep 2026), and the template editor was built in five phases between 6 and 7 Oct 2026. About 391 Pest tests were passing on 7 Oct.

**The invoicing module in one paragraph.** A user starts a **draft** invoice: from logged work ("visits"), from a period, or blank. The user edits it in a **builder** with a live preview. When the user **finalises** it, one database transaction does all of this:

- takes a gap-free number under a row lock
- locks the exchange rate
- freezes a **snapshot** of the seller, buyer, bank and settings (encrypted)
- freezes the **design** (the template settings)
- posts to the party ledger
- recalculates the party balance
- creates a secure public link
- writes the audit log

From then on the invoice can never be edited. The only fixes are **void** and **credit/debit notes**. Payments become numbered **receipts** at once. Receipts can be split across invoices, and any money left over is kept as advance credit. **Statements** are printed live for a chosen period.

**Every document is one self-contained HTML page with print CSS.** The same HTML is used in four places:

- the on-screen preview
- the browser's print dialog
- the server PDF, made by the Chrome already installed on the server and run headless (no PDF library)
- the secure public link

**How templates work.** A template is not HTML that users write. It has three parts:

1. **Eight built-in themes.** These are Blade files (Modern, Classic, Minimal, Bold band, Sidebar, Compact, Elegant serif, Medical statement). Each theme has an invoice layout and a receipt layout.
2. **A design.** This is a small, strictly checked JSON object kept per account in the `document_designs` table. It holds the theme, colours, font, sizes, section order, show/hide switches and document titles.
3. **The frozen copy of that design** kept on every issued document.

The **template editor** is a full-screen page. The real document sits in the middle, and the server redraws it after every change. The user can click a section and drag it up or down, and resize the logo, the margins and the totals column with handles. A sidebar has six tabs (Theme, Layout, Type, Fields, Titles, Share), and the editor has undo/redo, print preview, PDF, export/import of the design as JSON, and reset.

**Key facts at a glance**

| Topic | Clinic Ledger answer |
|---|---|
| Stack | Laravel 13, PHP 8.3+ (pinned 8.3), MariaDB/MySQL, Blade, Tailwind CSS 4, Vite 8, vanilla JS ES modules (no Livewire, no Alpine, no Vue), Pest 4 |
| Composer packages | Only `laravel/framework` and `laravel/tinker` (+ Laravel's default dev tools). **No PDF, QR, Excel or money package.** |
| PDF | Headless Chrome/Edge/Chromium driven directly with `--print-to-pdf` (`PdfRenderer`). Falls back to the browser's print dialog. |
| Template storage | Blade theme files + one JSON design per document family (`document_designs.design`) + frozen `design_snapshot` per invoice |
| Built-in templates | 8 themes × (invoice layout + receipt layout); statement uses its own layout in the theme's colours |
| Document types | Invoice / tax invoice, credit note, debit note, receipt (payment), statement of account. **No quotation/estimate, proforma, delivery challan or recurring invoice.** |
| Tax | Country packs for 52 countries; named tax rates with components (CGST+SGST / IGST by state, or a fixed split like GST+QST); inclusive/exclusive; exempt/zero; global on/off; per-invoice on/off; per-line rate |
| Languages on documents | English, Spanish, French, German, Portuguese, Arabic (right-to-left), Hindi; per party/per invoice |
| Fonts | Self-hosted woff2 (no Google requests); Noto Sans Devanagari and Noto Sans Arabic in every font stack; fonts embedded in PDFs |
| Sharing | Email (PDF attached + link), WhatsApp `wa.me` share link, secure public link with open tracking, download PDF, print; payment reminders by email |
| Payments | Multi-invoice allocation (auto oldest-first or manual), partial, advance credit, void/bounce with reversal, UPI QR on rupee invoices |

**How a document is drawn**

```
Settings › Invoices & templates ─┐                 ┌─ preview (iframe, server-drawn on every change)
Template editor (design JSON) ───┼─► DesignSchema::normalize() + forPlan()  (strict check)
Builder (per-invoice look)  ─────┘          │
                                            ▼
             DocumentDesigns (document_designs row, or derived from old settings columns)
                                            │
Invoice / Payment / Ledger rows ──► DocumentData (plain arrays: seller, buyer, lines, totals, labels, blocks, design)
                                            │       (finalised: read from frozen snapshot + design_snapshot)
                                            ▼
             DocumentTheme::vars()  ──►  Blade: documents/invoice|receipt|statement
                                            │      head = CSS custom properties from the design + fonts
                                            │      themes/<theme>/invoice.blade.php  (one @case per section)
                                            ▼
                                  self-contained HTML
                     ┌──────────────┬──────────┴───────┬──────────────────┐
                 screen/iframe   browser print   PdfRenderer (headless   public link /i/{token}
                                                 Chrome --print-to-pdf)
```

---

## 1. User-facing features of invoicing

### 1.1 Document types

| Document | How it is made | Statuses | Number series (default) | Notes |
|---|---|---|---|---|
| **Invoice** | Draft from visits (today / week / month / custom period / picked visits / all open) or blank; or one visit → one invoice from the visit page; phone can prepare and finalise | `draft → finalised → sent → viewed → partial → paid`, `void`; **overdue** is computed (open, money due, past due date) | `INV-{FY}-{0001}` | Title is "Invoice", or the "tax invoice" title when tax is charged on it |
| **Tax invoice** | Same document as the invoice | same | same series | The title switches automatically when `tax_enabled` is on. The account can type its own wording ("TAX INVOICE", "GST invoice") |
| **Credit note** | From an issued invoice (or debit note): lines copied into a draft to reduce or remove | `draft → issued`, `void` | `CN-` | Needs a **reason** (printed). Never more than "left to credit". Takes money off the invoice balance; the rest is held as party credit. No bank, QR or due date printed |
| **Debit note** | From an issued invoice: blank draft for an extra charge | like an invoice | `DN-` | Owed like an invoice (due date, overdue, reminders, payments) |
| **Receipt** | Recording a payment (no draft stage) | `recorded`, `void`, `bounced` | `RCT-` | Shows the invoices settled, the amount applied to each and the balance after; "Received" stamp |
| **Statement of account** | Ledgers › a party › period | not issued; drawn live each time | none | Opening balance, entries with running balance, closing balance, ageing (0–30 / 31–60 / 61–90 / 90+) |

Credit and debit notes are rows of the same `invoices` table, told apart by `doc_type` (decision 78). They use the same lines, calculator, snapshot, design, numbering, sending, link and PDF code.

**Not built:** quotation/estimate, proforma, delivery challan, recurring invoices, refund voucher, a way to apply held credit to a later invoice, emailing statements, XLSX export (CSV only).

### 1.2 Numbering

Code: `app/Modules/Settings/Services/DocumentNumbering.php`. Screen: Settings › Invoices & templates › Document numbering.

- There is one sequence per document type: invoice, receipt, credit note, debit note. Each has a **prefix**, a **format**, a **start number** and a **restart policy**.
- Format tokens are `{PREFIX} {FY} {YYYY} {YY} {MM}` and a counter such as `{0001}`. The width of the counter sets the zero padding. `isValidFormat()` (line 134) rejects a format without a counter or with unknown tokens.
- `{FY}` prints `2026-27` when the financial year crosses two calendar years, or `2026` when it does not. The financial-year start comes from the account (country default, editable).
- The **start number** can be changed only until the first document is numbered. This lets a customer continue from an old system.
- Restart policy is "every financial year" or "never". **Each financial year keeps its own series** (decision 80). A document dated in the previous year, but finalised after the new year started, takes the old year's next number. The new year's counter does not move.
- Numbers are taken **only at finalisation**, under `lockForUpdate` (`assign()`, line 64). So drafts never use up numbers, there are no gaps, and a void keeps its number. If a number is already taken, it skips past the highest one issued.
- `preview()` (line 55) shows "next number" without using it up. It is used in the builder ("Will be numbered INV-…") and in the settings table.
- Database: `invoice_sequences` has a `series_key` column (`default` or a party id) ready for "a series per party". That screen was never built.

### 1.3 Customer (party) data

There are two party kinds (`clinics`, `clients`). Both have the same billing fields:

- name, code, colour
- **currency**, **document language (`locale`)**, **payment terms in days**
- email, phone, website
- address lines, city, **state + `state_code`** (the state code drives CGST/SGST against IGST), postal code, country
- **`tax_id`** (the customer's GSTIN, printed on the invoice)
- opening balance and date, notes, active flag

A clinic also has contacts: one primary contact and contacts marked "receives invoices". Its contact name prints as "Attn:", and its email is used for sending. A clinic can also have its own `template_key`, a theme override that is copied onto its new drafts. A client can carry a `reference_no` (patient number), printed when the "Patient number" field is on.

When an invoice is finalised, the buyer block is **frozen** into the snapshot (`DocumentData::snapshot()`, `app/Modules/Invoicing/Services/DocumentData.php:36-105`). Later edits to the party never change an issued invoice.

### 1.4 Line items

Each line (`invoice_lines`) has:

- service date, description, detail ("Saturday rate · Patient #1042")
- **item code** (HSN/SAC or the local equivalent)
- unit (hour / service / visit / day / session), quantity, unit rate
- **line discount %**
- **tax rate** (one of the account's named rates, or none)

The calculator then writes back the `line_subtotal`, taxable amount, `tax_amount`, `tax_components` JSON (`{"CGST":9,"SGST":9}`), `tax_name`, `tax_rate` and `line_total`.

Lines come from logged work (with the rate rule that priced them), from preset services (priced by the rate resolver), or as free text. The builder (`resources/views/invoices/builder.blade.php`, `resources/js/invoice-builder.js`) mirrors the totals live in JavaScript. **The server recalculates everything on save** (`InvoiceCalculator` is the single authority).

### 1.5 Taxes, discount, round-off, amount in words

- **Taxes:** a global "Charge tax" switch; a per-invoice switch; a rate per line; prices inclusive or exclusive of tax (per invoice; the default comes from settings). Tax prints per component in the totals ("CGST 9%", "SGST 9%"). An optional tax column shows per-line tax. An exempt rate prints no tax line. Details are in section 4.
- **Discounts:** a per-line % plus an invoice-level discount (% or fixed amount). The invoice discount is **spread pro rata over the lines** so that tax is charged on the discounted amount. The last line absorbs the rounding drift.
- **Round-off:** a per-invoice checkbox rounds the total to the whole unit. It prints a "Round off" line with the adjustment.
- **Amount in words:** `app/Support/NumberWords.php`. It uses the Indian system (lakh/crore) for INR, NPR, PKR, BDT and LKR, international wording otherwise, and knows about 40 currency names ("Rupees forty thousand only"). **English only.** It is printed only when the document language is English (`DocumentData.php:233`).

### 1.6 Payments, partial payments, balances, ledger

- **Record payment** (`PaymentService::record()`, `app/Modules/Invoicing/Services/PaymentService.php:83`) takes the date, amount, method (bank transfer / UPI / cash / cheque / card / online / other), reference (UTR, cheque no.) and notes. The money is allocated over the party's open invoices **oldest-due-first automatically or by hand**.
  - An allocation can never exceed an invoice's balance or the amount received.
  - What is left over stays as **advance credit** (`party_balances.credit_available`).
  - The invoice rows are locked during the transaction. The receipt number, snapshot and public token are created in the same transaction.
- **Invoice settle rule** (`Invoice::settle()`, `app/Modules/Invoicing/Models/Invoice.php:167`): `balance_due = total − amount_paid − amount_credited`. Status follows: paid, partial, viewed, sent or finalised.
- **Void / bounced** payment: all allocations are reversed, the invoices reopen, and a ledger reversal is posted.
- **Ledger** (`ledger_entries`): append-only per party. Kinds: opening, invoice (debit), receipt (credit), credit_note, debit_note, adjustment, reversal. The running balance is computed when the ledger is read, never stored.
- **Balances read model** (`party_balances`): invoiced, received, balance due, overdue, four ageing buckets, credit available, open invoices, last invoice/payment. It is **recomputed in the same transaction** as every finalise, void and receipt (`BalanceService`), so list screens never add up invoices.

### 1.7 Reports

`app/Modules/Reports/ReportService.php`. Each report is a flat table, so the screen and the CSV are identical. Filters: period (FY / last FY / quarter / month / custom), party and currency. Totals appear only when one currency is shown.

- Revenue by party (with a credit-notes column)
- Receipts by method
- Outstanding & ageing
- **Tax collected** (by component and rate, with an invoice detail view)
- **Item-code (HSN/SAC) summary**
- Services performance
- Visits & hours

Credit notes count as negative. The tax and item-code reports are plan-gated (`reports.tax`). The CSV is UTF-8 with a BOM, so Excel opens it cleanly.

### 1.8 Sharing

| Channel | How | Code |
|---|---|---|
| Email | Subject and message pre-filled (party contact, amount, due date, link); **the PDF is attached when the server can make one**; reply-to is the business | `InvoiceSendController::email`, `app/Mail/InvoiceMail.php` |
| WhatsApp | `https://wa.me/<digits>?text=…`. A hand-off link, **not** the WhatsApp Business API (that is listed as "coming soon") | `InvoiceSendController::whatsappUrl()` |
| Secure link | `/i/{token}`: 40-character random token stored on the control database (`document_links`); no sign-in; throttled 60/min; `noindex`; marks the invoice **viewed** and logs the opener's IP; page has "Open / print" and, for rupee invoices, "Pay with a UPI app" | `PublicDocumentController` |
| Download PDF / Print | `invoices/{id}/document?pdf=1` or `?print=1`; each is recorded in the delivery trail | `InvoiceController::document` |
| Reminders | Daily job `cl:overdue` at 08:00: emails invoices exactly N days overdue (default 1/7/14), once per invoice per day, if enabled and the plan allows | `app/Console/Commands/RunOverdue.php` |
| Phone | Share via email/WhatsApp/link, PDF/print, from `/app` pages | `resources/views/m/invoice.blade.php` |

Every send, print, download and open is a row in `document_deliveries` (channel, recipient, status sent/failed/opened/noted, error). Sending moves `finalised → sent`, and opening the link moves it to `viewed`.

**UPI payment QR** (decision 71): this is an optional switch, "with the amount due" or "just the UPI id". It prints the standard `upi://pay?pa=…&pn=…&am=…&cu=INR&tn=Invoice …` link as a QR code beside the bank details. It appears only on INR invoices whose bank account has a valid UPI id, and never on void or fully paid invoices. The QR encoder is the app's own (`app/Support/QrCode.php`, 572 lines, no package). **It had not been scanned by a real phone** when last recorded.

### 1.9 Multi-language documents

- Seven document languages are in `app/Support/Locales.php`: en, es, fr, de, pt, **ar (right-to-left)**, hi. The list of right-to-left languages also has he, ur and fa, ready for later.
- The document language is chosen **per party** (`locale`) and copied to each invoice. It can be changed in the builder. It is independent of the screen language (an Arabic invoice from an English screen is normal).
- All printed labels come from `lang/<locale>/documents.php` (~110 keys: "Bill to", "Place of supply", "Amount due", "Credit note no.", …). `:tax` in a label is replaced with the account's tax name (GST/VAT/…).
- **Right-to-left:** the `<html dir="rtl">` attribute flips the layout. CSS uses logical properties (`margin-inline-start`, `text-align: start/end`), and the editor's totals handle reverses its direction in RTL. A photo of Arabic and Hindi invoices is in `clinicledger-project/docs/phase5-hindi-arabic.png`.
- Document titles can be overridden by the account. An empty title means "use the document language's own word".

### 1.10 Currency

- Each party has its own currency. The account has a **base currency**.
- Exchange rates are entered by hand (`exchange_rates`). `FxService` looks for the latest rate on or before the date, direct or inverse. A daily feed is planned, not built.
- The rate is **locked at finalisation** (`fx_rate`, `base_total`). It prints as "1 USD = 83.2 INR" when the invoice currency differs from the base currency.
- Formatting (`app/Support/Money.php`): ICU `NumberFormatter` with six number styles (`in` ₹1,23,456.00, `us`, `uk`, `eu`, `fr`, `ch`). Zero-decimal currencies are JPY, KRW, VND and CLP. **3-decimal currencies (KWD, BHD, OMR) are not handled. They print with 2 decimals.**
- The multi-currency feature is plan-gated (`billing.multi_currency`).

### 1.11 Other user-facing details

- DRAFT / VOID watermark across the page.
- **Per-invoice show/hide** of blocks in the builder (address, registration numbers, tax registration, bank, signature, notes, terms, period, item codes, amount in words, patient). These override the account design (`invoices.blocks` JSON, `Invoice::block()`).
- Default note, default terms and default payment terms in settings.
- Legal name (when different), website and a "note on invoices" per bank account are printed (decision 90). The legal name matters for GST invoices.
- Logo and signature images, up to 500 KB. Larger pictures are shrunk in the browser before upload. Files are named by content hash and never deleted, so old documents keep their image (`BrandImages`).
- "Computer-generated document · no signature required" fine print.

---

## 2. The template system

### 2.1 How templates are stored

There are three layers. **None of them is "HTML with placeholders" that users can edit.** Users never type HTML or CSS. That is a deliberate security and quality choice.

| Layer | Where | What it holds |
|---|---|---|
| Theme layouts (code) | `resources/views/documents/themes/<theme>/{invoice,receipt,style}.blade.php` + shared parts in `themes/_parts/` and `themes/_base-css.blade.php` | The arrangement and styling of each of the 8 themes. Each `invoice.blade.php` is a `@switch($section)` with one `@case` per section (header, parties, table, totals, notes, footer) |
| The account's design (data) | Table `document_designs` (migration `database/migrations/tenant/2026_10_06_000013_document_designs.php`): `family` (invoice / receipt / statement, unique per tenant), `design` JSON, `schema_version`, `updated_by` | Theme choice, colours, font, sizes, section order and hidden sections, print switches, document titles, paper |
| Frozen design per document | `invoices.design_snapshot` (JSON) and `payments.snapshot.design` | The design an issued document was printed with, so it never changes later |

The design schema is in `app/Modules/Invoicing/Design/DesignSchema.php` (version 1). A full design looks like this:

```json
{
  "schema": 1,
  "layout": 2,
  "theme": "modern",
  "accent": "#0F766E",
  "colours": { "heading": "#0F1F1E", "body": "#1F2937", "muted": "#6B7280" },
  "font": "jakarta",
  "base_pt": 9.5,
  "margin_mm": 16,
  "logo": { "size_mm": 16, "position": "left" },
  "totals_mm": 70,
  "density": "comfortable",
  "paper": "A4",
  "sections": { "order": ["header","parties","table","totals","notes","footer"], "hidden": [] },
  "fields": { "logo": true, "address": true, "statutory": true, "tax_registration": true, "item_codes": false,
              "tax": true, "words": true, "bank": true, "qr": false, "qr_amount": true, "signature": true,
              "notes": true, "terms": true, "period": true, "patient": true },
  "titles": { "invoice": null, "tax_invoice": null, "credit_note": null, "debit_note": null,
              "receipt": null, "statement": null }
}
```

The allowed values are constants in `DesignSchema.php`:

- `THEMES` (lines 36–45)
- `FONTS` (55–60)
- `SECTIONS` (66)
- `FIELDS` (69–72)
- `TITLES` (74)
- `LOGO_POSITIONS`, `PAPERS`, `DENSITIES` (76–80)
- `RANGES` (83–88): text 9–12 pt (default 9.5), margins 10–25 mm (16), logo 8–30 mm (16), totals column 40–90 mm (70)
- `TITLE_MAX` 60 characters
- `SWATCHES`: 6 accent colours
- `PALETTES`: 6 palettes of accent + heading + body + muted. Every text colour reaches a 4.5:1 contrast ratio on white.

**`normalize()` (lines 205–321) is the gatekeeper.** It is run on every input: settings form, editor preview, editor save and imported file. It does the following:

- Every value is checked against its list or range.
- Anything unknown is **dropped and reported**, at the top level and inside nested parts.
- Missing values are filled from the theme's defaults.
- Sections are de-duplicated, and the items table can never be hidden.
- Titles are stripped of HTML tags, control characters and bidi-override characters. The joiners that Devanagari and Arabic need are kept.
- A file from a newer schema version says so.

The report reads in the editor's own words and the user's language, for example "Text size: 40 is outside 9–12, 12 is used".

**`forPlan()` (lines 332–359) applies the plan limits.** Without Pro, a user gets:

- any theme
- the 6 swatches, plus each theme's own colour
- the theme's font
- the default text colours
- the first palette

**Layout versions.** `layout: 1` is the old pre-6-Oct layout and `layout: 2` is the rebuilt themes. An issued document keeps the layout it was issued with. This is how they rebuilt all themes without changing any invoice already sent.

**A bridge to the old settings columns.** Before the editor existed, the look was stored in columns (`business_profiles.template_key/accent_colour/font/paper/logo_position/show_address/show_signature`, `account_settings.show_item_codes/show_tax_registration/tax_invoice_wording`). `DocumentDesigns` (`app/Modules/Invoicing/Design/DocumentDesigns.php`) handles this in three ways:

- It reads the saved row, or **derives** a design from those columns if the account never saved one (`for()`, lines 33–52).
- It **mirrors** a saved design back into the columns (`mirrorToSettings()`, 128–146).
- It **syncs** changes from other settings pages into the saved design (`syncFromProfile()`, 92–121).

This bridge caused two bugs (section 8). **For the truck platform, start with the design JSON as the only source. Do not copy the bridge.**

### 2.2 Built-in templates

Eight themes are in `DesignSchema::THEMES`. Short descriptions are in `InvoiceSettingsController::THEME_HINTS` (lines 40–49), and photos are in `clinicledger-project/docs/themes/`.

| Theme | Default accent / font | Invoice layout | Receipt layout |
|---|---|---|---|
| Modern | teal `#0F766E`, Plus Jakarta Sans | Colour band with identity and title, amount-due card | Amount-received card + "Received" stamp |
| Classic | ink `#111827` | Centred letterhead, double rule, ruled table with numbered lines, amount in words, "For <business>" | Double-ruled acknowledgement |
| Minimal | teal | White space, hairlines, one large total in the accent | Giant figure |
| Bold band | orange `#E8590C` | Dark header over an accent rule, accent amount block, striped rows, pay-by-QR beside totals | Dark card |
| Sidebar | violet `#7C5CE0` | Identity, addressee, bank, QR and signature in a colour column (own frame `sidebar/open.blade.php`) | Tinted band |
| Compact | blue `#2B6CB0` | Dense header with a grid of facts (title/no., date, due date, **place of supply**), tinted table head, **tax breakdown beside totals** | Accent bar |
| Elegant serif | gold `#B7791F`, Source Serif 4 | Warm paper, gold rules, italic title, centred letterhead | — |
| Medical statement | green `#2F855A` | Practice panel, "Prepared for" and period panels, register table, fees/paid/balance box | — |

Every theme's receipt has its own arrangement: the amount received as the hero, a rotated "Received" stamp with the date (`_parts/stamp.blade.php`), and then the invoices it settled (`_parts/receipt-applied.blade.php`).

**Shared parts** are in `resources/views/documents/themes/_parts/`. They make sure the eight themes differ only in arrangement, never in which facts they print. The parts are:

- `logo`
- `lines-table` (optional numbered and dated columns; item-code and tax columns follow the switches)
- `totals-rows` (subtotal, discount, tax components, "incl. tax" note, round off, total, paid, balance due, words)
- `notes` (reason for notes, notes, terms)
- `bank`
- `qr`
- `signature`
- `fine`
- `stamp`
- `receipt-applied`
- `receipt-summary`

`DocumentTheme::vars()` (`app/Modules/Invoicing/Design/DocumentTheme.php:13-112`) works out once what every theme prints. That covers the seller lines, the IDs line, the buyer lines, the bank lines, and a `meta` list of label→value pairs in reading order (number, issue date, against invoice, due date, period, reference, **place of supply**, currency, exchange rate).

**Statements** follow the theme's colours and font but keep one plain layout. The Sidebar column left too little room for the ledger, so it is not used for statements.

### 2.3 What the template editor lets a user change

Route: `GET /settings/invoices/designer` (permission `settings.invoice`). Files:

- `app/Http/Controllers/Settings/DesignEditorController.php`
- `resources/views/settings/designer.blade.php`
- `resources/js/design-editor.js` (829 lines)
- `resources/js/design-preview.js`

Screenshot: `clinicledger-project/docs/phase3-template-editor.png`.

**Top bar:**

- Back to "Invoices & templates".
- Document tabs **Invoice · Credit note · Debit note · Receipt**. Invoice, credit note and debit note share one layout and differ only in title and wording. The receipt has its own layout.
- "All changes saved / Unsaved changes" indicator.
- **Undo / Redo** (Ctrl+Z / Ctrl+Y, up to 100 steps). Ctrl+S saves.
- **Print preview** (the browser print dialog on the real page).
- **PDF** (the server's Chrome prints the sample with the unsaved design).
- **Save design**.
- Leaving the page with unsaved changes asks first.

**On the page itself (handles, "constrained to the layout", approved 6 Oct):**

- Click a section to get an outline and a dark toolbar. The toolbar has a **grip to drag the section** above or below the others (a line shows where it will land), **up/down arrows** and **hide** (the items table cannot be hidden).
- With the header selected, a **corner handle resizes the logo** (8–30 mm).
- A dashed **page-margin guide** can be dragged (10–25 mm).
- With the totals selected, an **edge handle sets the totals column width** (40–90 mm).
- A measurement tip shows "Logo 18 mm" while dragging.
- Free pixel placement was **deliberately not offered**. It would break with long tables, second pages, Letter paper and RTL languages, and the PDF could no longer be trusted to match the preview.

**Sidebar tabs:**

| Tab | Controls |
|---|---|
| **Theme** | The 8 themes as small drawings in the chosen colour (`settings/partials/theme-thumb.blade.php`, pure CSS); **6 accent swatches** + the theme's own colour; **a colour picker + hex box** (Pro) with a live contrast readout; **6 palettes** (accent, headings, body, muted in one click; palettes beyond the first are Pro). Choosing a theme brings its own colour, font and logo position unless the user had changed them. |
| **Layout** | Section list (drag, arrows, eye to hide); logo size; logo position (left / centre / right); page margins; totals width; table rows (comfortable / compact); paper (A4 / Letter) |
| **Type** | Font (4: Plus Jakarta Sans, Manrope, Source Serif 4, Noto Sans, each shown in its own face; Pro); text size 9–12 pt in 0.5 steps; heading / body / muted text colours with a contrast check (4.5:1 for text, 3:1 for headings and the accent; Pro) |
| **Fields** ("What prints") | 14 switches: logo, business address, tax registration (label follows the country, e.g. GSTIN), registration numbers (statutory fields), bank details, UPI QR code (+ "with the amount due"), tax columns, item codes (label follows the country, e.g. HSN/SAC), amount in words, service period, patient number, signature block, notes, terms |
| **Titles** | Six titles: Invoice, **Invoice when tax is charged** (e.g. "TAX INVOICE", "GST INVOICE", "Factura fiscal"), Credit note, Debit note, Receipt, Statement. Max 60 characters; empty means the document language's own word; the preview heading follows as you type (300 ms delay) |
| **Share** | **Export** `clinicledger-design-invoice.json` (format, schema, family, time, app version; never the logo or bank details); **Import** a .json (max 64 KB, checked on the server exactly like a save, shown in the preview, waits for Save); **Reset to the theme's defaults** (keeps the titles) |

**Not in the editor** (useful when comparing with the founder's wish list):

- No free editing of the printed labels ("Bill to", "Qty"…). They come from language files.
- No custom footer text field. Only notes, terms, the bank note and the fixed fine print.
- **One design is edited, the invoice family's.** Receipts and statements follow it. The schema already supports a receipt design of its own, but there is no screen for it.
- **No named templates, duplicate or "save as".** One design per family per account.
- **No per-branch template.** The only per-party override is the clinic's theme key.
- No custom fields and no font upload.
- Paper is only A4 and Letter.
- The handles need a mouse or pen. On a phone the page shows at about 40% and the sidebar does the work.

### 2.4 Live preview — how it works

- The editor page **never draws a document itself**. Every change posts the whole design to `POST /settings/design/preview` (`DesignEditorController::preview`, lines 53–63). The server:
  1. checks it with `normalize()` + `forPlan()` (`checked()`, lines 123–136; body capped at 64 KB, line 31)
  2. builds a sample with `SampleDocuments`
  3. renders the **real document views**
  4. returns `{html, design, dropped}`
- `SampleDocuments` (`app/Modules/Invoicing/Design/SampleDocuments.php`) uses **the account's latest issued invoice, shown unpaid** (so the QR can be seen). An account with nothing issued gets a sample invoice in its own currency with its own identity, logo and bank. Nothing is saved; the models exist in memory only.
- `DocumentPreview` (`resources/js/design-preview.js:54-161`) loads the HTML into **two same-origin iframes that take turns**. The new drawing replaces the old one only after it has loaded and its fonts are ready, so the page never blinks. The page is scaled to the column width.
- Colours and sizes are applied **instantly** by setting CSS custom properties on the frame (`applyVars()`, `cssVars()` mirrors `DesignSchema::cssVariables()`). The server drawing then follows.
- If the server changed anything (plan limits, bad values), the editor adopts the server's version and shows a toast "Not taken as given: …".
- Save is `PUT /settings/design` (`save()`, lines 65–76). It goes through `DocumentDesigns::save()` in a transaction and is audited as `design.saved`.

The **Settings › Invoices & templates** page (`resources/views/settings/invoices.blade.php`, `resources/js/invoice-settings.js`, `InvoiceSettingsController`) is the simpler everyday version. It has the theme grid, swatches, font, logo position, paper, titles, the payment QR card, "What prints" and a live preview beside them. Below the preview are **numbering** and **defaults** (payment terms of new parties, document language, default note and terms). Saving writes the defaults, the numbering and the design **in one transaction** (`update()`, lines 83–180).

### 2.5 Defaults per document type, duplicate/reset, per-party templates

| Feature | Clinic Ledger |
|---|---|
| Default template per document type | Per **family**: invoice (+ credit/debit notes), receipt, statement. Titles per type. In practice only the invoice family is editable; the others follow it |
| Per-invoice look | The builder can change theme, accent, font, paper and the block switches **for one draft** (`invoices.template_key/accent_colour/font/paper/blocks`); these win over the live design (`DesignSchema::withInvoiceLook()`, 188–196) |
| Per-party template | `clinics.template_key` overrides the theme for that clinic's new drafts |
| Duplicate template | Not built (export → import is the nearest thing) |
| Reset | "Reset to the theme's defaults", keeps the titles |
| Per-branch | Not built (no branches in Clinic Ledger) |

---

## 3. Rendering and PDF

### 3.1 No PDF library: headless Chrome

- `app/Modules/Invoicing/Services/DocumentRenderer.php` renders the Blade views (`documents.invoice`, `documents.receipt`, `documents.statement`) to one self-contained HTML string. Images are data URIs. In PDF mode, fonts are inlined as base64.
- `app/Modules/Invoicing/Services/PdfRenderer.php` runs the Chrome, Edge or Chromium **already installed on the server** with `--headless=new --print-to-pdf --no-pdf-header-footer --virtual-time-budget=4000`. It always uses **its own `--user-data-dir`**, so it never touches a running desktop Chrome.
  - It writes the HTML to `storage/app/private/pdf/<uuid>.html`, runs Chrome, reads the PDF and deletes both files (lines 87–131).
  - It looks for the browser in the usual Windows and Linux paths (lines 19–30), or uses `DOCUMENTS_CHROME_PATH`.
  - The timeout is `DOCUMENTS_PDF_TIMEOUT` (45 s).
- `config/documents.php`: `pdf_driver` = `auto` (use Chrome if found, else the browser's print dialog), `chromium` (insist) or `browser` (never on the server).
- Decision 59 (3 Oct): "No Puppeteer, no npm package". This is Browsershot's approach without Browsershot.
- A one-page PDF is about 180–280 KB. Fonts are drawn as outlines (Type 3, because they are variable fonts), but text can still be selected and searched.

**For the truck platform:** the truck blueprint (`docs/blueprint/01-architecture.md` line ~176) lists "DomPDF (or similar)" as a package to approve. **The study recommends Clinic Ledger's approach instead**, for four reasons:

1. The PDF is the same HTML as the live preview, so "what you see is what prints".
2. Modern CSS (grid, flex, custom properties, `box-decoration-break`) works. DomPDF supports little of it.
3. Arabic shaping and Indian scripts (Devanagari, and later Malayalam/Tamil) render correctly in Chrome. DomPDF handles complex scripts poorly.
4. No Composer package is needed (the founder's "ask before installing" rule).

The production server needs a Chromium. The founder's DigitalOcean test droplet already has one (Clinic Ledger decision 115).

### 3.2 Fonts (Indian scripts and Arabic)

- All fonts are **self-hosted**. `tools/fetch-fonts.py` fetched 7 families once from Google Fonts into `public/fonts/*.woff2` plus `manifest.json` (decision 66): Plus Jakarta Sans, Bricolage Grotesque (display face for titles and amounts), Source Serif 4, Manrope, Noto Sans, **Noto Sans Devanagari**, **Noto Sans Arabic**.
- `app/Support/Fonts.php`:
  - `preloadTags()` preloads two Latin files.
  - `linkedCss()` links the files on screen.
  - `inlineCss()` embeds **every subset as base64 data URIs** for the PDF, each with its `unicode-range`. Chrome then embeds only the faces the text actually uses.
- **Every font stack names Noto Sans Devanagari and Noto Sans Arabic** (`DesignSchema::FONTS`, lines 55–60, and the `--display` variable). So a Hindi or Arabic document, or a Hindi or Arabic name on an English document, prints in the app's own fonts on any server. Before this fix (7 Oct), Hindi PDFs used the server's Nirmala UI and Arabic bold came out in Times New Roman (section 8).
- **For trucks:** add the Noto faces for the languages the truck platform will need (Malayalam, Tamil, Kannada, Telugu, Gujarati, Bengali, Urdu). The Q1 language question is still open.

### 3.3 Print CSS and page sizes

The main print rules are in `resources/views/documents/partials/head.blade.php` and `themes/_base-css.blade.php`:

- **The design becomes CSS custom properties** (`head.blade.php:24`, `DesignSchema::cssVariables()`, lines 378–389): `--accent --heading --ink --muted --tint --base --margin --logo --totals --row-pad --display`. The themes read only these.
- `@page { size: A4|Letter; margin: 0 }` (line 29). The margin is drawn as the `.page` box's padding. `.page` is 210×297 mm (A4) or 216×279 mm (Letter). `print-color-adjust: exact` keeps colour bands in print.
- **Margins on every page:** `box-decoration-break: clone` repeats the padding at each page break (`_base-css` line 10).
- **Page breaks:**
  - Each section moves to the next page whole (`.d-sec { break-inside: avoid }`). Only the items table may split.
  - The table head repeats (`thead { display: table-header-group }`).
  - A row is never cut in two.
- **"Fit automatically"** (`partials/fit.blade.php`, decision 87): a small script (under the CSP nonce) measures the page. If the document would spill onto a second page only because of its rows, it switches to compact rows (5 px padding instead of 9 px). It runs on load (the server PDF prints what loads) and again before the browser prints.
- **Watermark** "DRAFT" or "VOID" (localised).
- **Right-to-left:** `dir="rtl"` on `<html>`, with logical CSS properties throughout.
- **Editor-only CSS** is injected into the preview frame (`EDIT_CSS` in `design-preview.js`): hover and selected outlines on `[data-section]`, shown on screen only. Every theme section carries `data-section="<key>"` so the handles can find it.

---

## 4. Tax handling

### 4.1 Where tax is configured

- **Tax & country page** (`AdvancedSettingsController`, route `settings.advanced`, permission `settings.tax`) writes to `account_settings`:
  - `tax_enabled` (global switch, **off by default everywhere**)
  - `tax_system_name` (GST / VAT / TVA / IVA / Sales tax …)
  - `tax_registration_label` (GSTIN / TRN / VAT no. …) and `tax_registration_secret` (**encrypted**; checked against the country's pattern, e.g. the GSTIN regex)
  - `show_tax_registration`
  - `prices_include_tax`
  - `tax_invoice_wording` ("Document title when tax is on")
  - **`home_state` + `home_state_code`** (for place of supply)
  - `show_item_codes`, `item_code_label`, `item_code_default`
  - `number_style`, `date_format`
- **Named tax rates** (`tax_rates`, `TaxRateController`): name, kind (exempt / zero / reduced / standard / other), rate %, **components**, description, default flag. Components are typed as text and parsed by `TaxRateController::parseComponents()`:
  - `CGST 9, SGST 9 | IGST 18` → `{mode: by_state, same_state: {CGST: 9, SGST: 9}, other_state: {IGST: 18}}`
  - `GST 5, QST 9.975` → `{mode: fixed, parts: {GST: 5, QST: 9.975}}`
  - empty → a single line named after the tax system
- **Each service** carries a tax rate and an item code. Each line can change both.

### 4.2 Country packs (is it India-only?)

**No, it is country-aware.** `app/Platform/Locale/CountryDefaults.php` lists **52 countries**: name, currency, time zone, language, FY start month and day, tax name. `app/Platform/Locale/CountryPacks.php` adds, per country:

- **named rates**: explicit lists for IN, CA, US, HK, QA and KW (lines 21–45); a generic "Exempt / Standard / Reduced… / Zero-rated" set built from a standard + reduced rate table for about 45 more countries (lines 47–59)
- the registration label and a regex (61–80)
- the item-code label (HSN/SAC for India, on by default; classification codes for MY, CN, BR; 82–87)
- number style and date format (90–100)
- the statutory fields to propose (102–116)
- the tax-invoice wording

The pack is **copied once** into the account's own settings (`SettingsProvisioner`). Everything stays editable. Changing the country re-applies the pack and **adds** missing rates without deleting anything the user typed (decision 19). The rates are "as of 2026, verify at launch" (REVIEW C20).

India pack: Exempt (default for healthcare), 18% / 5% / 12% / 28%, each split as CGST+SGST (same state) or IGST (other state). UAE: VAT 5%, label TRN `^[0-9]{15}$`.

### 4.3 The calculation

`app/Modules/Invoicing/Services/InvoiceCalculator.php`, `compute()` lines 31–143. This is the only place tax arithmetic happens.

1. line gross = qty × rate; line subtotal = gross − line discount %
2. subtotal = Σ line subtotals
3. invoice discount (% or amount), **spread pro rata** over the lines (the last line absorbs the drift)
4. tax **per line, per component**:
   - **exclusive**: tax = taxable × %
   - **inclusive**: taxable = net ÷ (1 + total %), and the last component takes the rounding
   - exempt rate or zero rate → no components
5. components are summed into `invoice_taxes` rows (code, label "CGST 9%", rate, taxable amount, tax amount)
6. optional round-off to the whole unit

`components()` (lines 150–170) picks `same_state` or `other_state` for `by_state` rates, `parts` for `fixed` rates, or one line named after the tax system.

### 4.4 Place of supply

- When a **draft is created** (`InvoiceService::newDraft()`, lines 185–221):
  - `same_state` = (party `state_code` == account `home_state_code`), or **null if either is missing**
  - `place_of_supply` = the party's state **name** as text
- Both are stored on the invoice and copied to its notes.
- The calculator treats `same_state = null` as **same state** (`$sameState = $options['same_state'] ?? true`, line 36). **Risk for India:** a party without a state code silently gets CGST+SGST. The truck platform should require the state code (2-digit GST state code) whenever tax is on, and should block finalising when it is missing.
- "Place of supply" is printed only when tax is enabled on the invoice.

### 4.5 HSN/SAC

- `services.item_code` and the per-line `item_code`, with an account default (`item_code_default`). The label follows the country (`HSN/SAC`) and is editable.
- The item-code column is a print switch.
- The **item-code report** shows taxable value and tax per code.

### 4.6 What is missing for "GST/VAT that works globally"

None of the following exist in the code (searched):

- reverse charge
- bill of supply
- export / LUT zero-rating
- per-party tax override (planned in PLAN §2.13, not built)
- withholding tax / TDS
- multiple GST registrations per account
- date-effective rates
- cess
- e-invoicing (India IRN, UAE PINT-AE, KSA ZATCA)
- e-way bill
- **tax amount shown in the base/local currency** on a foreign-currency invoice (UAE requires the VAT amount in AED; verify)
- 3-decimal currencies
- amount in words in languages other than English

These are listed as additions in section 10.

---

## 5. Data model

The tenant tables live on "shards" (each tenant migration has its own `shard()` helper). The control tables live on the control database. Primary keys are **UUID strings**, and every table has `tenant_id`, with composite unique keys `(tenant_id, id)` and composite foreign keys. Money is `DECIMAL(14,2)` (`16,2` in balances), FX is `DECIMAL(16,8)`, and tax % is `DECIMAL(7,3)`.

| Table | Migration | Key columns |
|---|---|---|
| `invoices` | `tenant/2026_09_28_000009_create_invoicing_tables.php` + `000013` + `000015` | `number` (unique per tenant, null until finalised), **`doc_type`** (invoice / credit_note / debit_note), **`related_invoice_id`**, `reason`, `status` ENUM(draft, finalised, issued, sent, viewed, partial, paid, void), `party_type`/`party_id`, `client_id` (patient), `practitioner_id`, `issue_date`, `due_date`, `period_from/to`, `reference` (PO), `currency`, `base_currency`, `fx_rate`, `subtotal`, `discount_type/value/amount`, `tax_enabled`, `tax_mode` (exclusive/inclusive), `tax_total`, `round_total`, `rounding_adjustment`, `total`, `amount_paid`, **`amount_credited`**, **`credit_applied`**, `balance_due`, `base_total`, **`place_of_supply`**, **`same_state`**, `template_key`, `accent_colour`, `font`, `paper`, `locale`, **`blocks`** JSON (per-invoice show/hide), **`snapshot`** (encrypted JSON), **`design_snapshot`** JSON, `notes`, `terms`, `public_token`, `finalized_at`, `sent_at`, `viewed_at`, `paid_at`, `voided_at`, `void_reason`, soft deletes. Indexes on status+date, party+status+date, due date, token, related, doc_type+date |
| `invoice_lines` | same | `visit_id`, `visit_item_id`, `service_id`, `service_date`, `description` (255), `detail` (160), `item_code` (32), `unit` ENUM, `quantity` (10,2), `unit_rate`, `discount_percent`, `line_subtotal`, `tax_rate_id`, `tax_name`, `tax_rate`, **`tax_components`** JSON, `taxable_amount`, `tax_amount`, `line_total`, `rate_rule_id`, `sort_order` |
| `invoice_taxes` | same | `code` (CGST/SGST/IGST/VAT), `label` ("CGST 9%"), `rate`, `taxable_amount`, `tax_amount`, `sort_order` |
| `ledger_entries` | same | party, `entry_date`, `kind` ENUM(opening, invoice, receipt, credit_note, debit_note, adjustment, reversal), `reference`, `description`, `debit`, `credit`, `currency`, `base_amount`, `document_type/id`, `created_by` — append-only |
| `document_deliveries` | same | `document_type` (invoice/receipt/statement), `document_id`, `channel` (email/whatsapp/link/download/print/system), `recipient`, `subject`, `message`, `status` (sent/failed/opened/noted), `error`, `sent_by`, `sent_at`, `opened_at` |
| `exchange_rates` | same | `from_currency`, `to_currency`, `rate`, `rate_date` (unique per day), `source` (manual/feed) |
| `payments` | `tenant/2026_09_28_000010_create_payments_tables.php` | `number`, `status` (recorded/void/bounced), party, `received_on`, `amount`, `allocated_amount`, `unallocated_amount`, `currency`, `base_currency`, `fx_rate`, `base_amount`, `method` ENUM, `reference`, `notes`, `locale`, `snapshot` (encrypted; includes the frozen design and the settled invoices), `public_token`, `voided_at`, `void_reason`, `recorded_by` |
| `payment_allocations` | same | `payment_id`, `invoice_id`, `amount` |
| `document_designs` | `tenant/2026_10_06_000013_document_designs.php` | `family` (unique per tenant), `design` JSON, `schema_version`, `updated_by` |
| `invoice_sequences` | `tenant/2026_09_27_000007_create_settings_tables.php` (+ 000015 widens the ENUM) | `doc_type` ENUM(invoice, receipt, credit_note, debit_note), `series_key`, `prefix`, `format`, `start_number`, `next_number`, `reset_policy` (never/fiscal_year), `fy_key`, `last_assigned_at` |
| `business_profiles` | same | display/legal name, profession, qualifications, contact, address, state/state_code, country, `logo_path`, `signature_path`, old look columns, `default_due_days`, `document_locale`, `default_note`, `default_terms`, `show_address`, `show_signature` |
| `account_settings` | same | tax switch and names, encrypted registration, `prices_include_tax`, `tax_invoice_wording`, `home_state(_code)`, item-code settings, `number_style`, `date_format`, `country_pack_applied`, reminder settings |
| `statutory_fields` | same | `key`, `label`, `value_secret` (encrypted), `show_on_invoice`, `is_custom`, `sort_order` |
| `bank_accounts` | same | `label`, `bank_name`, `holder_name`, `account_number` (encrypted) + `account_last4`, `branch_code` (IFSC/sort code/routing) + its label, `iban` (encrypted) + last4, `swift`, **`upi_id`**, `currency`, `is_default`, `show_on_invoice`, `notes` (printed under bank details) |
| `tax_rates` | `tenant/2026_09_27_000006_create_catalog_tables.php` | `name`, `kind`, `rate`, **`components`** JSON, `description`, `is_default`, `sort_order`, `is_active` |
| `services` | same | `name`, `code`, `category`, `unit`, `default_rate`, `currency`, `tax_rate_id`, **`item_code`**, favourites, `origin` |
| `clinics` / `clients` / `clinic_contacts` | `tenant/2026_09_27_000005_create_parties_tables.php` | see 1.3 |
| `party_balances` | same (+ `credit_available` in 000010) | per party and currency: invoiced, received, balance due, overdue, 4 ageing buckets, open invoices, last invoice/payment, computed_at |
| `document_links` (control DB) | `control/2026_09_28_000004_create_document_links_table.php` | `token`, `tenant_id`, `document_type`, `document_id`, `expires_at` |

---

## 6. Code architecture

### 6.1 Folders

- `app/Modules/Invoicing/` has three parts:
  - `Design/`: DesignSchema, DocumentDesigns, DocumentTheme, SampleDocuments
  - `Models/`: Invoice, InvoiceLine, InvoiceTax, Payment, PaymentAllocation, LedgerEntry, DocumentDelivery, DocumentDesign, ExchangeRate
  - `Services/`: InvoiceService, InvoiceCalculator, PaymentService, LedgerService, BalanceService, FxService, DocumentData, DocumentRenderer, PdfRenderer, PaymentQr, MoneySummary, PartyFigures
- `app/Modules/Settings/`:
  - `Models/`: AccountSettings, BusinessProfile, BankAccount, InvoiceSequence, StatutoryField
  - `Services/`: DocumentNumbering, BrandImages, SettingsProvisioner, AccountReset
- `app/Modules/Catalog/`: TaxRate, Service, RateRule, RateResolver
- `app/Modules/Parties/`: Clinic, Client, ClinicContact, PartyBalance, PartyLookup, PartyService
- `app/Modules/Reports/ReportService.php`
- `app/Platform/Locale/`: CountryDefaults, CountryPacks
- `app/Support/`: Money, NumberWords, QrCode, Fonts, Locales, FiscalYear, Period, Csv, Text
- Controllers in `app/Http/Controllers/Invoicing/` (InvoiceController, InvoiceSendController, PaymentController, PaymentSendController, LedgerController, PublicDocumentController) and `app/Http/Controllers/Settings/` (DesignEditorController, InvoiceSettingsController, TaxRateController, AdvancedSettingsController, StatutorySettingsController, BankAccountController, CurrencySettingsController, BusinessSettingsController)
- Mail: `app/Mail/{InvoiceMail,ReceiptMail,ReminderMail}.php`

### 6.2 Key code with line references

| What | Where |
|---|---|
| Finalise (number, FX, snapshot, design freeze, token, visits billed, ledger, balances, audit) | `app/Modules/Invoicing/Services/InvoiceService.php:406-503` |
| Void (reverse ledger, reopen visits, credit note gives back) | `InvoiceService.php:505-556` |
| Draft creation incl. `same_state` | `InvoiceService.php:185-221` (same_state 193–195) |
| Builder save (replace lines) and recalculate | `InvoiceService.php:277-300`, `302-351`, `354-402` |
| Credit / debit note drafts | `InvoiceService.php:91-183` |
| Immutability guard (model-level) | `app/Modules/Invoicing/Models/Invoice.php:37` (`MUTABLE_AFTER_FINAL`) and `:80` (`static::updating`) |
| Settle / creditable / per-invoice blocks | `Invoice.php:167`, `:153`, `:288` |
| Tax maths | `app/Modules/Invoicing/Services/InvoiceCalculator.php:31-143`, components `150-170` |
| Numbering | `app/Modules/Settings/Services/DocumentNumbering.php`: `assign()` 64, `nextNumberFor()` 107, `render()` 120 |
| Payment record / void | `app/Modules/Invoicing/Services/PaymentService.php:83`, `:168` |
| Document data (snapshot, invoice, receipt, statement, labels) | `app/Modules/Invoicing/Services/DocumentData.php:36`, `111`, `132`, `260`, `293`, `367`, `473` |
| Design schema / normalize / plan rules / CSS variables | `app/Modules/Invoicing/Design/DesignSchema.php:205`, `332`, `378` |
| Design store | `app/Modules/Invoicing/Design/DocumentDesigns.php:33`, `61` |
| Theme variables | `app/Modules/Invoicing/Design/DocumentTheme.php:13` |
| Editor controller | `app/Http/Controllers/Settings/DesignEditorController.php` (preview 53, save 65, pdf 95, checked 123) |
| PDF | `app/Modules/Invoicing/Services/PdfRenderer.php:87` |
| UPI QR | `app/Modules/Invoicing/Services/PaymentQr.php:29`, `:54` |
| Tax component text parser | `app/Http/Controllers/Settings/TaxRateController.php` (`parseComponents`) |
| Document entry views | `resources/views/documents/{invoice,receipt,statement}.blade.php`; head `partials/head.blade.php`; fit `partials/fit.blade.php` |
| Theme example | `resources/views/documents/themes/modern/invoice.blade.php` (`@switch($section)`), `modern/style.blade.php` |
| Editor JS | `resources/js/design-editor.js`: state/undo 102–154, handles placement 202–258, section list 302–384, controls 415–497, `setTheme` 499, contrast 550–562, drag 595–649, handles 651–697, save 735, export 760, import 778, reset 808 |
| Preview JS | `resources/js/design-preview.js`: `cssVars` 12, `DocumentPreview` 54, `render` 82, `show` 103, `openPdf` 164 |

### 6.3 Routes (`routes/web.php`)

- Invoices (lines 244–260): index, new, store, from-visit, show, edit, credit-note, debit-note, update, finalise, document, void, send (show / email / whatsapp / link). Each route has its own permission middleware: `invoice.view`, `invoice.create`, `invoice.finalise`, `invoice.send`, `invoice.void`.
- Payments (263–272), ledgers + CSV + statement (275–278), reports (281–288).
- Public links: `GET /i/{token}` and `/i/{token}/document` (141–142, throttle 60/min).
- Phone pages `/app/*` (291–316) call the same services.
- Settings: invoices (351–352), designer (354), design preview / save / pdf (355–357), advanced (Tax & country, 358–361), currencies (362–366).

### 6.4 Front end

- **Blade + Tailwind 4 + vanilla ES modules** bundled by Vite. Vite inputs (`vite.config.js`): `app.css`, `app.js`, `invoice-settings.js`, `design-editor.js`, `help.js`, `site.css`, `site.js`.
- No Alpine, no Livewire, no Vue/React.
- Page data is passed to scripts through `<script type="application/json" data-…-json>` blocks, never inline JavaScript. This is required by the CSP.
- Other modules: `invoice-builder.js` (lines and live totals), `payment-form.js`, `ui.js` (csrf, toasts, image shrinking).

### 6.5 Packages

| Package | Purpose |
|---|---|
| `laravel/framework` ^13.17 | Everything |
| `laravel/tinker` | Console REPL |
| dev: `pestphp/pest` ^4.7 + `pest-plugin-laravel`, `phpunit/phpunit` ^12.5, `mockery`, `fakerphp/faker`, `nunomaduro/collision`, `laravel/pail`, `laravel/pao`, `laravel/pint` (installed, **not run**: it would reformat 162 files) | Tests and tools |
| npm dev: `vite` ^8, `laravel-vite-plugin` ^3.1, `tailwindcss` ^4 + `@tailwindcss/vite`, `concurrently`; optional `@laravel/multiplex` | Build |
| **Written in-house instead of a package** | PDF (headless Chrome driver), QR encoder (ISO 18004), amount in words, money formatting (ICU via the PHP `intl` extension), CSV, captcha, fonts (self-hosted), country packs |

PHP extensions used: intl, mbstring, gd, zip, pdo_mysql, sodium, bcmath.

---

## 7. Security and quality conventions (keep the truck platform consistent)

1. **Nothing new is installed without the owner's approval.** Laravel Boost was declined. Everything in the lock files is approved.
2. **Every input is validated**: types, `max:` lengths on every string, `Rule::in` for enums, ranges, regex for colours (`^#[0-9A-Fa-f]{6}$`), numbering formats and UPI ids, `uuid` on ids, `email:filter`.
3. **User-designed data is never markup.** The design JSON is normalised strictly and unknown keys are reported. Request bodies are capped (64 KB for designs). Titles are cleaned of tags, control characters and bidi overrides.
4. **CSP with a per-response nonce** (`app/Http/Middleware/SecurityHeaders.php:30`):
   - `script-src 'self' 'nonce-…'` and no inline handlers. Styles keep `'unsafe-inline'`.
   - `frame-ancestors 'self'`, `form-action 'self'`, `object-src 'none'`.
   - Fonts and images are served by the app only.
   - Other headers: X-Frame-Options SAMEORIGIN, nosniff, Referrer-Policy same-origin, Permissions-Policy, HSTS on https, and no `X-Powered-By`.
5. **Escaping:** Blade `{{ }}` everywhere. Raw output only for constants from code tables, for example the font stacks. (A bug taught this: section 8.)
6. **Every financial write is one database transaction.** Rows are locked with `lockForUpdate` (the sequence row, the invoice being finalised, open invoices during payment allocation).
7. **Finalised documents are immutable**, enforced in the model's `updating` event. This guards against console commands and jobs too, not only screens. Corrections are made by void (a reason is required and the number is kept) or by a credit/debit note.
8. **Snapshots:** seller, buyer, bank and settings are frozen at finalisation (encrypted, because they include the bank account), and so is the design.
9. **Encryption at rest** with Laravel's `encrypted` cast: bank account numbers and IBANs (last 4 digits kept in clear), statutory values, the tax registration number, invoice and receipt snapshots. Column names contain `secret` / `account_number` / `iban` so the audit log redacts them.
10. **Audit log** on every notable change (`Audited` trait, `AuditRecorder`), with severity levels (`notable`, `sensitive` for voids).
11. **Permissions on every route** (`permission:` middleware) plus checks in the controllers (e.g. voiding an issued invoice needs `invoice.void`). Tenant scoping comes from the `BelongsToTenant` trait on every tenant model. Tests cover tenant isolation, guarded routes and security probes (XSS payloads, SQL in search fields, guessed links, a PHP file renamed .png, CSRF).
12. **Public document links:** 40-character random tokens, throttled, `noindex`, drafts never shown, an optional expiry column.
13. **Uploads:** PNG/JPEG/WebP only, 500 KB maximum, SVG refused, files named by content hash, kept outside `public/`.
14. **Tests** (Pest):
    - The clock is frozen at 2026-09-28 09:00.
    - Run with input closed (`< /dev/null`).
    - `TranslationsTest`: every new `__()` string exists in all six language files.
    - `PerformanceTest`: every page under 60 queries and a time budget, on an account with 1,500 invoices.
    - Migration tests: foreign-key names unique per database.
    - The PDF tests run against a real Chromium.
    - Template tests: `DesignEditorTest` (9), `DesignFilesTest` (6, ten hostile files), `DocumentDesignTest` (7), `ThemesTest` (7), `DocumentPrintTest` (5), `PdfTest` (2), `CreditDebitNotesTest` (10), `TaxTitleTest` (3), `QrCodeTest` (7, with an independent decoder), `NumberingYearsTest` (6).
15. **Performance:** read models (`party_balances`) instead of summing; aggregate SQL for list counters; ICU formatters cached; `simplePaginate(100)`.
16. **Process:** plan first, prototype second, code last. Every round updates `PROJECT-STATE.md` (decisions, the owner's words, errors and remedies, open items, tests). Edit code with the editor tool, not `sed` or shell here-documents, because those mangled backslashes in class names.
17. **Phone pages call the same services** as the desk pages with the same permissions, so nothing is duplicated.

---

## 8. Known issues and lessons

### 8.1 The owner's screenshots (5 Oct, settings screens) and what was done

Source: `PROJECT-STATE.md` decision 69, §4 entry "(5 Oct, evening)", §5 note on label wrapping.

| Screenshot | What it shows | Fix |
|---|---|---|
| `alignment issues.PNG` | Tax & country › "Country & financial year": the label "Financial year starts" wraps to two lines, so its select box sits lower than the others; the Numbers and Dates selects are cut off ("₹1,23", "31/03") | The form became **two rows** (country · FY start · day / current FY · numbers · dates). Rule adopted: **single-line labels** (explanations go under the field as a hint) and **`items-end`** on label-and-field rows |
| `colour selection in two rows.PNG` | Accent swatches wrap onto two rows in a narrow column ("looks ugly") | **Swatches in one row** (grid `1.7fr 1fr 1fr .8fr`). In phase 3 the "Custom · your own accent and font" presets were removed: one accent is chosen directly |
| `default document language alignment.PNG` | "Default document language" wraps and is misaligned with "Default due (days)"; the note "Numbering is set under Tax, country & numbering" | Short labels ("Document language"); **numbering moved into Invoices & templates**; later the field became "Payment terms of new clinics and clients (days)" (decision 89) |
| `heading as GST .PNG` | The heading "GST rates" on the Tax page. The owner asked: "why it is showing 'GST Rates' … will it change depending on countries? if not … use the word 'Tax Rates'" | **Kept on purpose:** the heading is the account's own tax name ("VAT rates" in the UK). The sentence "GST here" was changed to "GST in India, VAT in the UK and EU, …" so the product does not read as India-only |
| `your state alignment .PNG` | "Your state (for place-of-supply components)" wraps and pushes its field below "State code" and "Document title when tax is on" | Label "Your state" with the place-of-supply note under the field; bottom-aligned row |
| `LOOKS BEAUTIFULL.PNG` | The Settings sub-menu with tinted icon tiles, which the owner liked | Used as the style of the **main menu** (Work / Money / People groups), with Settings folded inside it (decision 73) |

**Lessons for the truck platform:**

- Test every form at narrow widths and in long languages (German, Hindi, Arabic).
- Keep labels on one line and put the help text under the field.
- Bottom-align fields in a row.
- Use `min-w-0` and `grid-cols-1` so grid items do not force sideways scrolling.
- Keep money, document numbers and dates from wrapping (`white-space: nowrap`).
- Use word joiners after hyphens in document numbers. "INV-2026-27-0144" broke at a hyphen in a narrow column.
- Never call a tax by one country's name in global copy.

### 8.2 GST / tax-invoice heading wording (decision 85)

The owner typed "TAX INVOICE" as the title, but documents printed the language's own word "Tax invoice", because the comparison ignored case. In addition, an unrelated settings save reset a typed title (round 9). **Fix:** only an empty title or the exact pack wording "Tax invoice" means "use the language's word". Any other spelling prints exactly as typed, in every document language. Test: `TaxTitleTest`.

**Lesson:** the "title when tax is on" must be a plain, exact, user-owned value, kept in one place.

### 8.3 Template, PDF and printing bugs found and fixed

- **Every document printed in the browser's serif font for 8 days.** `{{ $fontFamily }}` inside `<style>` escaped the quotes to `&#039;`, which is not decoded in CSS, so the declaration was dropped. Fixed by raw output of a constant and a test for the declaration. A stray `</div>` that pushed the footer outside the page was found in the same photo.
- **Settings switches stopped reaching documents** once a design was saved, because the design JSON and the old columns disagreed (round 9). Fixed with bridges and `StatutorySwitchesTest`. **Lesson: keep one source of truth for the design.**
- **The editor's "Invoice" tab showed a DEBIT NOTE**, because the sample took the latest document of any type. Fixed to take the latest invoice only.
- **Page 2 began at the paper edge.** `@page margin 0` plus padding applied only to the first page box. Fixed with `box-decoration-break: clone`. Trying `break-before: avoid` on the footer made Chrome split the footer itself, so that was dropped.
- **Hindi and Arabic PDFs used the server's fonts** (Nirmala UI; Times New Roman for Arabic bold). Fixed by adding the Noto faces to every stack and embedding them. A test checks that any font named in a PDF is one of the app's own.
- **Statements overflowed:** the Sidebar layout squeezed the ledger, and "Page 1 of 1" was a constant in the footer. Fixed: reference under the entry, ageing on one line, plain layout for Sidebar statements, page numbers removed.
- **A long document's bank details, QR and signature were left alone on the last page.** "Fit automatically" now uses compact rows when that keeps the document on one page. **Still open:** a document that still overflows prints as drawn, and its footer may stand alone on the last page.
- **Phase-3 browser findings:**
  - The numbering table squeezed beside the preview (moved below, full width).
  - The page scrolled sideways at tablet width (`min-w-0`).
  - The selection toolbar covered the colour band (the section is now measured together with its children).
  - Saving reported nothing when the plan limits replaced a value (the two reports were merged).
- **Blade traps:** `@json(...)` splits its argument at commas, so values for scripts are computed in `@php` first. A `use` statement inside `@php` is not allowed. A test searching for a CSS class matched the `<style>` block in `<head>`.
- **Naming clash:** a relation called `notes` collided with the `notes` column. Renamed to `relatedNotes`.
- **ENUM columns block new document types:** `invoice_sequences.doc_type` was an ENUM, so the debit-note series failed until a migration widened it. **For trucks: use `VARCHAR` plus validation instead of ENUM for document types.**
- **Numbering across the year end:** a back-dated invoice restarted at 1 and hit the unique index. Fixed by decision 80 (each financial year keeps its own series).
- **Dates and time zones:** `diffInDays()` between a UTC date cast and "today" in Asia/Kolkata gave 6.77 days, which was truncated to 6, so a reminder was missed. Do day maths on date strings.
- **Right-to-left details** visible in `clinicledger-project/docs/phase5-hindi-arabic.png`: in the Arabic invoice, "4 h" shows as "h 4" and the period "01/09/2026 – 30/09/2026" reads reversed. Wrap numbers, units, date ranges and codes in `<bdi>` or `dir="ltr"` spans. Not recorded as fixed.

### 8.4 Gaps still open (from §6 of PROJECT-STATE and REVIEW)

- The UPI QR has not been scanned with a real phone.
- The builder's preview shows the last **saved** draft (it is not live like the editor).
- Each editor preview is a round trip to the server (about 1 s under `artisan serve`).
- One design only (the invoice family). No receipt design screen. No named templates.
- Credit held is not applied to later invoices. There is no refund document.
- A credit note copies a fixed-amount discount as it is (needs manual adjustment).
- Amount in words is English only. 3-decimal currencies are not supported. FX rates are manual.
- No WhatsApp Business API, only share links (the Pro plan lists the API as "coming soon").
- Statements are not emailed. No XLSX. No scheduled exports.
- No per-party number series (column exists, no screen).
- The six translations are machine-written and need a native review. Language polish is deferred (decision 114).

---

## 9. The Android folder

`clinicledger-project/android/` contains a **B4A 13 project**: `ClinicLedger/ClinicLedger.b4a` (869 lines), `Starter.bas`, `firebase/FirebaseMessaging.bas` + `README.md`, `README.md`, the built `ClinicLedger-1.0.2.apk`, and emulator screenshots in `emulator-2026-10-05/`.

- **What it is:** a **WebView shell** around the server's mobile pages (`/app`), copied from the founder's Plan My Leave app.
  - Package `com.clinicledger.app`, version 1.0.2 (versionCode 3).
  - minSdk 24 (Android 7), targetSdk 34, portrait, Material theme in the brand teal.
- **Native parts:**
  - The server address is asked once and remembered (`server.txt`).
  - Cookies are kept between launches.
  - The back button navigates the page.
  - `wa.me`, `mailto:` and `tel:` links open in the phone's apps.
  - **Downloads** (PDFs and CSVs) go through the Android DownloadManager with a notification.
  - The file chooser opens the camera or gallery.
  - A **WebView version check** (Chrome 111+ is needed for Tailwind 4) with a link to Play.
  - Its own **loading and "can't reach the server" screens** (Try again / Change server, 20-second timeout).
  - **Biometric sign-in** with Android's own `BiometricPrompt`, written in inline Java with no extra library. The device secret is encrypted with AES-GCM under an Android Keystore key, exchanged for a one-minute hand-off link, and rotated on every use.
  - Firebase push is prepared but needs the owner's Firebase project.
- **Libraries:** core, javaobject, okhttputils2, json, runtimepermissions, phone. All ship with B4A, so nothing is downloaded.
- **Build command:** `B4ABuilder.exe -task=Build -BaseFolder=… -Project=ClinicLedger.b4a`.
- **Conventions:**
  - The JavaScript bridge (`ClinicLedgerShell`: `hasDevice`, `canAuthenticate`, `storeDevice`, `biometricSignIn`…) is used by the web page.
  - The user agent ends with `ClinicLedgerAndroid/1.0`, so the server can hide browser-only links inside the app.
  - Every server change is recorded in PROJECT-STATE §9 as "server-side only, the shell stays at 1.0.2".
  - Lessons: a theme's `windowBackground` must reference a colour resource (`@color/…`), not a raw hex value; declare every library the code uses.
- **Relevance to trucks:** the truck crew app is a **native B4A app with offline storage** (D12). That is very different from this shell. The owner/manager app could reuse the shell pattern. From this folder the useful parts for the phone-app handover are: the biometric code, the download and share handling, the offline/loading screens, the build-by-command-line recipe and the README style. **Documents (PDFs) should still be made on the server.** The phone downloads or shares them, or shows the `/i/{token}` page.

---

## 10. Reuse plan for the truck platform

### 10.1 Ground rules for porting

- The truck blueprint uses **BIGINT ids, `company_id`, UUIDv7 only on phone-created rows, a single database, and double-entry vouchers**. Clinic Ledger uses UUID string ids, `tenant_id`, shards and a party ledger. When porting:
  - Replace `BelongsToTenant` with the truck company scope.
  - Replace `DB::connection($this->context->connection())->transaction()` with `DB::transaction()`.
  - Drop the `shard()` helpers.
  - Posting an invoice must create a **voucher** (Dr party, Cr freight income, Cr output tax), not only a `ledger_entries` row. Keep the `party_balances`-style read model.
- **Do not port the old-columns bridge** (`DocumentDesigns::mirrorToSettings/syncFromProfile`, `DesignSchema::fromProfile/fromTemplate/toTemplate`, layout 1). Start with design JSON only.
- Use `VARCHAR` + validation for `doc_type` and status, not ENUM.
- Same founder, so reuse is allowed. Keep the file headers' plain-English docblocks style.

### 10.2 What can be copied almost as-is

| Clinic Ledger part | Port effort | Changes |
|---|---|---|
| `DesignSchema` (strict design JSON, normalize, forPlan, cssVariables, contrast) | Low | Add document families and fields for transport; add paper sizes; remove `patient`/`period` fields or rename |
| Theme Blade files (8 themes, `_parts`, `_base-css`, `head`, `fit`) | Low–medium | Rename labels; add LR/freight-bill sections; keep `data-section` markers |
| Template editor (`design-editor.js`, `design-preview.js`, `designer.blade.php`, `DesignEditorController`) | Low | Document tabs become the truck document types; sample documents become a sample LR, freight bill and receipt |
| `SampleDocuments` idea (latest real document, shown unpaid, else a sample in the company's currency) | Medium | New samples per document type |
| `DocumentRenderer` + `PdfRenderer` (headless Chrome) + `config/documents.php` | Copy | Replaces the blueprint's DomPDF; needs Chromium on the server |
| `Fonts` + `tools/fetch-fonts.py` + `public/fonts` manifest | Copy | Add Noto faces for Malayalam, Tamil, Kannada, Telugu, Gujarati, Bengali, Urdu (per Q1 answer) |
| `InvoiceCalculator` | Copy, then extend | See 10.5 (reverse charge, categories, withholding) |
| `DocumentNumbering` (tokens, per-FY series, row lock, preview) | Copy | Add `{BRANCH}` / `{GSTIN-STATE}` token, series per branch/GSTIN, India 16-character check; doc types: trip, LR, freight bill, hire challan, receipt, voucher… |
| `TaxRate` + `parseComponents` + `CountryPacks` / `CountryDefaults` | Copy | Add transport presets (India GTA 5% RCM / 5% FCM / 18% FCM, exempt hire to a GTA; UAE 5%/0%); date-effective rates |
| `InvoiceService` lifecycle (draft → finalise → void, credit/debit notes, immutability guard, snapshot, design freeze, public token, deliveries) | Medium | Party types become one `parties` table; visits become consignments/LRs; post vouchers |
| `PaymentService` (allocation, advance credit, void/bounce) | Medium | Add **TDS deducted by the party** on receipts; post receipt vouchers |
| Sending (email + PDF, WhatsApp link, public link + open tracking, reminders) | Copy | WhatsApp Business API later |
| `PaymentQr` + `QrCode` | Copy | UPI QR for India; for UAE, no UPI. Use a link QR to the public page or payment link |
| `NumberWords` | Copy, then extend | Add Hindi/Arabic words and 3-decimal currencies |
| `Money` | Copy, then fix | 3-decimal currencies (KWD, BHD, OMR) |
| `SecurityHeaders` (CSP nonce), validation style, audit, encrypted casts, frozen-clock tests | Copy | — |
| Reports pattern (flat tables, CSV = screen) | Copy | Add GSTR-1/3B data, freight register, LR register |

### 10.3 Clinic-specific parts to generalise

| Clinic Ledger | Truck platform |
|---|---|
| Clinic / Client (two tables, `party_type` clinic or client) | One `parties` table (customer / vendor / both; shipper, consignee, broker…) with contacts |
| Practitioner (`practitioner_id`) | Company user who raised the document + **branch** |
| Tenant (account) | Company (+ branches, each possibly with its own GSTIN and series) |
| Visit / visit items, "Add uninvoiced visit" | Trip → consignment / LR; "Add unbilled LRs" |
| Patient (`client_id`, `reference_no`, field `patient`) | Consignor / consignee / the party's reference (PO, indent, shipment no.) |
| Service period (`period_from/to`, field `period`) | Trip or billing period (keep it generic) |
| Services (per hour / procedure / visit / day / session) and rate rules | Freight rate basis (per trip / per tonne / per kg / per km / per package / per container), route rates, detention, loading/unloading (hamali), other charges |
| Statutory fields (council registration, PAN) | PAN, CIN, MSME/Udyam, transport licence, IEC; UAE trade licence |
| "Medical statement" theme, sample "Root canal" lines | Transport-themed samples; rename the theme (e.g. "Ledger statement") |
| Labels: "Patient", "Prepared for" | "Consignor", "Consignee", "Vehicle", "From → To" |

### 10.4 What is missing for transport documents

Recommended model: keep Clinic Ledger's idea of **document families**, where each family has its own layouts in every theme, its own section list, its own field switches and its own titles. Extend the families from 3 to about 6. Each family gets its own number series, a design per company, an optional per-branch design, and a frozen design on every issued document.

| New document | What it needs beyond Clinic Ledger |
|---|---|
| **LR / bilty (consignment note)** | <ul><li>**Multiple copies in one print or PDF:** a configurable list (default: Consignor copy, Consignee copy, Driver/Truck copy, Office/Accounts copy). Each copy is a page with a copy label band, an optional tint per copy, and the option to hide freight on some copies.</li><li>Fields: LR no. and date, branch, vehicle no., driver, from → to; consignor and consignee with GSTINs and addresses; billing party.</li><li>Goods: packages, method of packing, "said to contain", actual and charged weight, material.</li><li>Consignor's invoice no. and value; e-way bill no. and validity; seal/container no.</li><li>Freight, rate basis, other charges.</li><li>**Freight terms: Paid / To Pay / To Be Billed (TBB).**</li><li>**GST payable by** (consignor / consignee / transporter) and the reverse-charge statement.</li><li>Risk (owner's / carrier's), insurance details.</li><li>Delivery-at address, remarks, signatures.</li><li>An optional **terms-and-conditions back page**.</li><li>A QR code linking to tracking or POD.</li></ul>No statutory GST format exists; the old Rule 4B fields are standard practice. An LR is not a tax invoice and posts no revenue unless freight is collected ("Paid" / "To Pay") |
| **Freight bill (GTA tax invoice / bill of supply)** | <ul><li>**Many LRs on one bill** for one billing party. Lines are LRs: LR no./date, vehicle, from→to, material, packages, weight, rate basis, rate, freight, detention, hamali, other charges.</li><li>Totals, **less advance received**, and a note of **TDS to be deducted**.</li><li>GST as the GTA option requires: **5% RCM** prints "Tax payable on reverse charge: Yes" and charges no tax; **5% or 18% forward charge**; or **bill of supply** when the supply is exempt.</li><li>Place of supply, SAC code, number of at most 16 characters unique per FY, "unbilled LRs" picker.</li></ul> |
| **Lorry hire challan / hire memo** (hired or market trucks, phase 2 per A3) | <ul><li>Issued to a truck owner or broker. Vehicle no., owner name, **PAN** and the TDS section/declaration (sec. 393 / old 194C; the ₹35,000 limit for goods-carriage operators; verify), RC, driver and licence.</li><li>Route and LR references, agreed hire, **advance paid**, deductions (shortage, damage, late delivery, commission), TDS, balance payable.</li><li>Posts a purchase/expense voucher. It is a payable-side document, so it needs a "supplier" family.</li></ul> |
| **Money receipt** | <ul><li>Clinic Ledger's receipt covers most of it.</li><li>Add "against LR / freight bill no.", **TDS deducted by the party** (cash + TDS settles the bill), on-account/advance, and cheque or UTR details.</li><li>Optional **thermal (80 mm) and A5** layouts for counter receipts.</li></ul> |
| Credit / debit note | Port as-is, linked to a freight bill (shortage or damage claims, rate differences) |
| Quotation / rate quote, proforma | New simple family (no numbering lock, can be converted to a bill) |
| POD / delivery receipt | A consignee acknowledgement page (could be the LR's consignee copy, signed and photographed by the crew app) |

**Paper sizes to add:** A5 (portrait and landscape), A4 landscape, Legal, and thermal 80 mm for receipts. Add "copies per sheet" (for example 2×A5 on A4) as an option.

### 10.5 What is missing for global taxes

1. **Tax category per line** (standard / zero / exempt / out of scope / reverse charge / export). Use the UBL/PINT codes (S, Z, E, O, AE, G) from day one, as `07-accounting.md` already plans for PINT-AE.
2. **Reverse charge** at invoice level: tax is computed for reporting but not charged; the mandatory wording is printed; GSTR-1/3B tables are filled.
3. **Document type by tax status:** tax invoice / bill of supply (India) / simplified tax invoice (UAE, up to AED 10,000; verify) / plain invoice. The title follows automatically and stays user-editable, as in decision 85.
4. **Place of supply that never defaults silently:** India 2-digit state codes, UAE emirates, a country for exports. Block finalising when it is missing and tax is on (Clinic Ledger defaults to "same state").
5. **Multiple registrations** per company (GSTIN per state or branch, UAE TRN per entity), each with its own series and letterhead identity.
6. **Date-effective rates** (`valid_from` / `valid_to`), e.g. Salik VAT from 1 Jun 2026 and GST rate changes.
7. **Withholding / TDS** lines shown below the total, which do not change output tax. Receipts can settle with "TDS deducted".
8. **Tax in local currency** on foreign-currency invoices (the AED VAT amount for UAE; verify) and FX rates per document.
9. **3-decimal currencies** and cash rounding rules per currency.
10. **E-document adapters** (`e_documents` table already planned): e-way bill (India, phase 2), UAE PINT-AE via an accredited provider (2027), India IRN only for non-GTA supplies, and more countries later.
11. **Amount in words** in the document language, and **bilingual documents** (e.g. English + Arabic labels side by side, common in the UAE; verify the legal need).
12. A **per-party tax override** (overseas client zero-rated, SEZ) as PLAN §2.13 intended.
13. Keep the country-pack pattern: defaults copied into company settings, always editable, re-applying a pack only adds, and every "(verify)" item gets a CA or tax-agent check before it is built.

---

## Spec-ready summary

### A. Everything the truck platform needs to "match Clinic Ledger" (invoicing)

- [ ] Document types: **invoice / tax invoice, credit note, debit note, receipt, statement of account**. Credit and debit notes live in the invoice table (`doc_type`, `related_invoice_id`, required `reason`).
- [ ] Lifecycle: **draft → finalised → sent → viewed → partial → paid**, computed **overdue**, **void with reason** (keeps its number). A credit note is "issued", never "open".
- [ ] **Immutable after finalisation**, enforced in the model. Only status, sending and payment columns change. Fixes go through void or credit/debit notes.
- [ ] Finalise in **one transaction**: recalculate → number under row lock → lock FX rate → freeze snapshot (seller, buyer, bank, settings; encrypted) → freeze design → public token → post ledger/voucher → recompute party balance → audit.
- [ ] **Numbering** per document type: prefix + format tokens `{PREFIX} {FY} {YYYY} {YY} {MM} {0001}`, start number (editable until the first document), restart every FY or never, **each FY keeps its own series**, gap-free, live preview of the next number.
- [ ] Party data: currency, document language, payment terms, address with **state code**, tax ID, contacts (primary / receives invoices); a per-party theme override; frozen on the document.
- [ ] Lines: date, description, detail, item code (HSN/SAC), unit, qty, rate, line discount %, tax rate per line; picked from a catalogue with rate rules or typed freely.
- [ ] Invoice discount (% or amount) **spread pro rata** before tax; **round-off** line; **amount in words** (Indian lakh/crore for INR).
- [ ] Tax: global switch + per-invoice switch; named rates with **components** (CGST+SGST / IGST by place of supply; fixed splits); **inclusive/exclusive**; exempt/zero; tax summary by component; optional tax column; country packs with editable defaults.
- [ ] Payments: record against many invoices (**auto oldest-first or manual**), partial payments, **advance credit**, void/bounce with full reversal; numbered **receipt** with "applied to" table and balance after.
- [ ] **Party ledger** with running balance, opening balance, **ageing buckets**, `party_balances` read model updated in the same transaction; statement PDF per period; CSV.
- [ ] Reports: revenue, receipts by method, outstanding & ageing, **tax collected**, **item-code summary**; filters for period, party and currency; CSV identical to the screen.
- [ ] Sharing: **email with PDF attached + secure link**, **WhatsApp share link**, **public link `/i/{token}`** (throttled, noindex, marks viewed), download PDF, print; **delivery trail** for every send, open, print and download; **automatic overdue reminders**.
- [ ] Multi-language documents (per party/per invoice, independent of the screen language), **Arabic right-to-left**, Indian scripts, labels in language files, tax name substituted into labels.
- [ ] Multi-currency with base currency, FX rate locked per document, printed rate; ICU money formatting styles.
- [ ] **UPI QR** on rupee invoices (amount due or payee only); "Pay with a UPI app" on the public page.
- [ ] DRAFT / VOID watermark; per-document show/hide blocks; default note and terms; legal name, website, bank note printed; logo and signature images (≤ 500 KB, shrunk in the browser, kept forever).

### B. Templates and the template editor

- [ ] **8 built-in themes** (Modern, Classic, Minimal, Bold band, Sidebar, Compact, Elegant serif, a statement-style theme), each with **its own layout per document family**. Statements use the theme's colours on a plain layout.
- [ ] **Design JSON per company (and optionally per branch) per document family**, strictly validated (`normalize` + `forPlan`); unknown values dropped and reported in the user's language; capped at 64 KB; **frozen on every issued document**; a layout version so old documents never change look.
- [ ] Editor top bar: **document-type tabs**, undo/redo (Ctrl+Z/Y), Ctrl+S, **print preview**, **server PDF of the unsaved design**, **Save**; unsaved-changes guard.
- [ ] **Handles on the live page**: click a section → drag above/below, arrows, hide (the items table is never hidden); **logo corner resize** (8–30 mm); **margin guide** (10–25 mm); **totals width edge** (40–90 mm). Constrained to the layout, with no free pixel placement.
- [ ] **Theme tab:** themes as thumbnails in the chosen colour; **6 accent swatches in one row** + the theme's own colour; **colour picker + hex** with a contrast readout; **6 palettes** (accent, heading, body, muted).
- [ ] **Layout tab:** section order list (drag/arrows/hide), logo size and **position left/centre/right**, margins, totals width, **table density** (comfortable/compact), **paper** (A4/Letter, plus A5, A4 landscape and thermal 80 mm for transport).
- [ ] **Type tab:** fonts served by the app and embedded in PDFs (4 choices), **text size 9–12 pt**, **heading/body/muted colours** with a WCAG contrast check.
- [ ] **Fields tab ("What prints"):** logo, business address, tax registration (country label), registration numbers, bank details, payment QR (+ with amount), tax columns, item codes (country label), amount in words, period, reference, signature, notes, terms, plus transport fields (vehicle, consignor/consignee GSTIN, e-way bill, freight terms, GST payable by, copy labels…).
- [ ] **Titles tab:** one title per document type, including the **title when tax is charged** ("TAX INVOICE" / "GST INVOICE" / "Factura fiscal"); empty means the document language's own word; printed exactly as typed; ≤ 60 characters, plain text only.
- [ ] **Share tab:** export the design as JSON (no logo or bank data, with schema and app version), **import** (server-checked, previewed, waits for Save), **reset to the theme defaults** (keeps the titles).
- [ ] **Live preview** = the real document drawn by the server (latest real document shown unpaid, or a sample with the company's identity and currency); two iframes taking turns; colours and sizes applied instantly via CSS custom properties.
- [ ] A simpler **Settings › Invoices & templates** page (theme, colour, font, logo, paper, titles, QR, what prints, live preview, numbering, defaults) saved in one transaction.
- [ ] Plan gating in one place (`forPlan`): themes and swatches for all plans; custom colours, fonts, text colours and extra palettes for higher plans.
- [ ] **Beyond Clinic Ledger (recommended):** named templates with **duplicate / save-as** and a **default per document type**; a design for receipts and other families; per-branch designs; **LR multi-copy** printing with copy labels; editable footer text; `<bdi>` isolation for numbers and dates in right-to-left documents.

### C. Rendering

- [ ] One self-contained HTML per document with print CSS; `@page` size; margins repeated on every page (`box-decoration-break: clone`); sections never split; the table head repeats; **"fit automatically"** with compact rows.
- [ ] **PDF by headless Chrome/Chromium driven directly** (no DomPDF, no Puppeteer); falls back to the browser print dialog; `pdf_driver` switch; own `--user-data-dir`; temp files deleted.
- [ ] Self-hosted fonts with **Noto script faces in every stack**, embedded as base64 in PDFs; add Malayalam, Tamil and other Indian scripts and Arabic as the language list requires.

### D. Reuse plan in one view

- [ ] **Copy almost as-is:** DesignSchema, the theme Blade files and shared parts, the template editor JS, controller and view, DocumentRenderer + PdfRenderer, Fonts + fetch script, InvoiceCalculator, DocumentNumbering, TaxRate + component parser + country packs, PaymentQr + QrCode, NumberWords, Money, the sending/public-link/delivery-trail code, SecurityHeaders, the test patterns (hostile design files, every theme renders, frozen clock, translations, performance budget).
- [ ] **Port with renames:** clinic/client → party; practitioner → user + branch; tenant → company; visit → trip/consignment/LR; patient → consignor/consignee/reference; service units → freight rate basis; ledger entries → vouchers + party balance read model; UUID/shards → BIGINT + `company_id` (+ UUIDv7 for phone rows).
- [ ] **Do not port:** the old-columns bridge (mirror/sync/fromProfile, layout 1), ENUM document types, the clinic-specific samples and labels.
- [ ] **Build new:** LR/bilty with multiple copies and a terms page; freight bill with many LRs, advance and TDS notes, GTA RCM/FCM handling and bill of supply; lorry hire challan (payable side, PAN/TDS); money receipt with TDS-deducted settlement and thermal/A5 layouts; quotation/proforma; POD page.
- [ ] **Global tax additions:** per-line tax categories (S/Z/E/O/AE/G), invoice-level reverse charge, tax-status document types, mandatory place of supply (state code / emirate / country), multiple registrations with their own series, date-effective rates, withholding lines, tax in local currency, 3-decimal currencies, amount in words in more languages, bilingual Arabic option, per-party tax override, and e-document adapters (e-way bill, PINT-AE, IRN). Every "(verify)" item goes to a CA / UAE tax agent before it is built.
