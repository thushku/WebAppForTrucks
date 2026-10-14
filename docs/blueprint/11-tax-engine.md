# Global Tax Engine (GST / VAT / Sales Tax)

Decision **D15**: invoicing must handle tax rules for **any country**. India and
UAE come first (details in `07-accounting.md`); other countries are added as
**data presets**, not new code.

Source: `research_notes/Trucksarthi modules and global invoicing/global_tax_invoicing.md`
(24 countries + EU rules, how Odoo / Zoho / Xero / Avalara / Stripe Tax / SAP B1 /
Tally model tax, data model, calculation steps). Every country item marked
**(verify)** there must be checked by a local tax adviser before that preset is
switched on.

> **Simple idea:** rates and rules live in database tables with start and end
> dates. The code only knows *how* to calculate. When India changes a GST rate
> or a new country is added, we add rows — we do not change code.

---

## 1. What the engine must do

| # | Capability | Example |
|---|---|---|
| 1 | **Country presets as data**: regimes, tax types, codes, dated rates, groups, rules, return boxes. Each company gets a copy it can adjust. | India GST, UAE VAT, Saudi VAT preloaded |
| 2 | **Dated rates** chosen by the tax-point date; credit notes reuse the original invoice's rate | India GST 2.0 from 22 Sep 2025; Salik VAT from 1 Jun 2026 |
| 3 | **Multi-part taxes** on the same base, optional compound part, per-unit or fixed parts | CGST 9% + SGST 9%, or IGST 18%; Canada GST + PST; cess |
| 4 | **Prices with or without tax**, back-calculation for tax-inclusive prices | ₹1,000 incl. 10% → 909.09 + 90.91 |
| 5 | **Rounding rules** per country: per line or per document; 2 or 3 decimals (OMR, BHD, KWD); cash rounding as a separate "Round off" line | Rupee round-off |
| 6 | **Tax categories** with standard e-invoice codes: S standard, Z zero, E exempt, AE reverse charge, K intra-EU, G export, O outside scope (+ L, M, B), each with exemption reason code and printed legal text | "Tax payable on reverse charge: Yes" |
| 7 | **Place of supply** worked out by a per-country resolver; **never defaulted silently** — missing data blocks finalising | India same-state vs other-state; GCC domestic vs international |
| 8 | **Rule engine on freight facts**: service class, transport leg, **carrier role (principal / subcontractor)**, origin/destination, buyer status, seller's tax option (India GTA RCM 5% / FCM 5% / FCM 18% / non-GTA), goods category | UAE subcontracted leg of an export → 5%, not 0% |
| 9 | **Reverse charge** on sales (show 0 tax, keep the notional amount for returns) and **self-assessment** on purchases | India GTA under RCM; EU cross-border B2B |
| 10 | **Withholding** as a separate engine (TDS / WHT / VAT withholding): base, trigger, single and yearly thresholds, payee-type rates, certificates (e.g. India ≤10-truck declaration), receivable and payable tracking, old + new India section numbers | Client deducts 2% TDS; we deduct TDS from a market-truck supplier |
| 11 | **Tax on advances** where the country requires it (receipt voucher with tax, adjusted on the final invoice) | India forward-charge advances (verify) |
| 12 | **Multi-currency**: document currency and local tax currency both stored; official rate source per country | "VAT in AED" on a USD invoice |
| 13 | **Evidence gating** for zero-rated and exempt lines: CMR, bill of lading, exit/customs document, declarations | UAE export 0% only with exit proof |
| 14 | **Registration-threshold monitor** and a "should we charge tax?" check; **bill of supply / no-tax invoice** when the seller is not registered or the supply is exempt | Small Indian owner-driver without GST |
| 15 | **Return-box mapping** and exports | GSTR-1/3B, UAE VAT201, Saudi VAT return, UK VAT100, EC Sales List |
| 16 | **External calculator hook** for the USA and other many-jurisdiction markets (Avalara / Stripe Tax), results stored in the same tables | US sales tax (we do not build US rates ourselves) |
| 17 | **Immutable tax snapshot + calculation trace** on every document; append-only audit log; retention per country (longest applies) | Shows exactly why a rate was chosen |

---

## 2. Freight line types (service classes)

Tax rules look at the **kind of charge**, not at free text:

| Class | Usual treatment |
|---|---|
| `FREIGHT_ROAD` | Main supply; country rule decides |
| `VEHICLE_HIRE_WITH_DRIVER` / `WITHOUT_DRIVER` | Country decides (India SAC 9966) |
| `LOADING_UNLOADING`, `DETENTION`, `DEMURRAGE`, `FUEL_SURCHARGE`, `PACKING` | **Follow the main freight line** when billed together (verify per country) |
| `TOLL_RECHARGE` | Usually part of the freight price, so follows it |
| `DISBURSEMENT` (paid as pure agent) | Outside scope, with evidence |
| `DAMAGE_CLAIM`, `PENALTY`, `LATE_PAYMENT_INTEREST` | Usually outside scope / exempt (verify) |
| `WAREHOUSING`, `CUSTOMS_CLEARANCE` | Own rules |
| `ASSET_SALE` (old truck), `SCRAP_SALE` | Goods, standard rate |

---

## 3. How a tax is calculated (step by step)

1. **Collect facts:** document type, dates, tax-point date (per country rule),
   our registration, the buyer's tax profile, trip facts (legs, carrier role,
   origin, destination, evidence), currency.
2. **Pick the regime** from our registrations. Not registered → no-tax document.
   Warn when turnover nears the registration threshold.
3. **Resolve place of supply** with the country's resolver.
4. **Classify each line** (service class); add-on charges follow the main freight
   line; disbursements are outside scope.
5. **Match the rule:** most specific matching rule wins, then priority. **No match
   → stop with a clear error** (never fall back to 0%).
6. **Apply exceptions:** customer-type mapping (e.g. SEZ → zero-rated), a
   certificate, or a manual override (permission + reason, logged).
7. **Check evidence** for zero-rated/exempt lines; block or mark "pending
   evidence" per company setting.
8. **Expand the tax** into its parts with the rates valid on the tax-point date;
   calculate on the net (or back-calculate from tax-inclusive prices).
9. **Reverse charge:** charge 0, keep notional amounts; self-assess on purchases.
10. **Round** per line or per document category; spread any difference back to
    lines so trip/truck cost centres still balance; separate round-off line.
11. **Convert currency** with the country's official rate source and date.
12. **Withholding:** check payee type, certificates and thresholds (year-to-date
    per payee); record expected TDS (sales) or TDS payable (purchases).
13. **Validate** mandatory fields per country, simplified-invoice limits,
    numbering rules, credit-note references, e-invoice schema rules.
14. **Save** the tax lines, the calculation trace and the accounting entries.
15. **E-invoice** (where required): build the standard invoice model and send it
    through the country adapter and provider; clearance countries are not
    "issued" until cleared.
16. **Returns:** period close adds up tax lines into return boxes and exports
    the files.

### Worked examples

| Case | Result |
|---|---|
| India, GTA on 18% forward charge, same state, ₹50,000 | CGST 9% ₹4,500 + SGST 9% ₹4,500 = ₹59,000. Client deducts TDS 2% (₹1,000) and pays ₹58,000; we book TDS receivable ₹1,000 |
| India, GTA default 5% reverse charge, other state, ₹50,000 | Tax charged ₹0; "Tax payable on reverse charge: Yes"; notional IGST ₹2,500 in GSTR-1 |
| UAE, Dubai → Riyadh export by us as main carrier, AED 8,000, CMR + exit doc attached | 0% (zero-rated); posts because evidence is present |
| UAE, Jebel Ali → Abu Dhabi leg done by us as subcontractor, AED 1,200 | 5% = AED 60 |
| EU, German haulier bills a French company, €3,000 | No German VAT; "Reverse charge"; both VAT IDs; EC Sales List |

---

## 4. Tables (summary; added to `06-data-model.md`)

**Global masters (presets, copied and overridable per company):**
`countries`, `tax_jurisdictions` (states, provinces, emirates), `tax_regimes`
(display name GST/VAT/SST, place-of-supply resolver, rounding, tax on advance,
e-invoice scheme, retention, registration threshold), `tax_types` (CGST, SGST,
IGST, VAT, HST…; ledger accounts), `tax_codes` (category, reverse charge,
exemption reason, legal text, evidence needed), `tax_rates` (dated, never edited
once used), `tax_group_members`, `service_classes`, `commodity_codes`
(HSN/SAC…), `service_class_codes`, `tax_rules` (conditions as columns +
outcome), `fiscal_positions` + maps, `withholding_codes` + `withholding_rates`,
`return_box_mappings`, `exchange_rates`.

**Company and party setup:** `tax_registrations` (our GSTINs/TRNs/VAT IDs, India
GTA option per year with Annexure V reference, e-invoice go-live and provider),
`party_tax_profiles` (tax ID and validation date, status, category, PAN/TIN,
Peppol ID), `tax_certificates` (TDS declarations, lower-deduction certificates,
LUT, SEZ…), trip tax facts (leg, carrier role, origin, destination, evidence).

**Document side:** tax fields on invoice lines, `document_tax_lines` (rate
snapshot, base and tax in document and local currency, category, reverse-charge
flags, rule id, return tags), `document_withholding_lines`,
`document_tax_traces` (append-only), number series with max length / allowed
characters / gap-free flag, `e_invoice_submissions` (status, external id such as
IRN/UUID, QR payload, hash chain where required), `e_invoice_providers`.

Money columns in the tax tables use `DECIMAL(19,4)`, rates `DECIMAL(9,6)`,
exchange rates `DECIMAL(19,8)`, so 3-decimal currencies and per-unit taxes work.

---

## 5. Invoice requirements by country (handled by templates + rules)

- **Document types:** tax invoice, simplified invoice, bill of supply / no-tax
  invoice, export invoice, credit note, debit note, receipt voucher (advance),
  refund voucher, self-billed invoice, proforma.
- **Mandatory field sets** per country (India Rule 46; UAE Art. 59; EU Art. 226;
  UK; Saudi Arabic + national address + QR; Canada / Australia / NZ / South
  Africa amount tiers).
- **Languages:** Arabic + English (Saudi, UAE, Oman, Bahrain, Egypt), French
  (France, Quebec); tax shown in local currency; optional dual dates (Nepal BS,
  Hijri display).
- **QR codes** where required (Saudi, Malaysia, Pakistan, Kenya, Nigeria, India
  e-invoices, Poland, Spain from 2027, Egypt e-receipts) and the UPI pay QR.
- **Freight references** on every invoice: LR / consignment note / CMR / e-way
  bill numbers, vehicle, origin → destination, trip.
- **E-invoice layer:** one standard (EN 16931) invoice model + one adapter per
  scheme + provider connectors; retry queue; cancellation windows (India 24 h);
  **inbound** e-invoices for purchases.
- **PDF + structured file** archived together for the retention period; in-country
  archive where required.

---

## 6. Country presets (status as of Oct 2026 — verify before go-live)

| Country | Tax | Standard rate | Road-freight treatment | E-invoicing | QR | Keep records (yrs) |
|---|---|---|---|---|---|---|
| **India** | GST (CGST+SGST / IGST) | 18% (slabs 5/18/40) | GTA: 5% RCM default, or 5% / 18% forward by option; non-GTA exempt | IRN above ₹5 cr turnover; **GTA freight exempt** | Only with IRN | 6 (GST), 8 (companies) |
| **UAE** | VAT | 5% | Domestic 5%; international 0%; subcontracted UAE leg 5% | Via accredited provider: **1 Jan 2027** (≥ AED 50m), **1 Jul 2027** (others) | No (verify) | 5–7 (verify) |
| Saudi Arabia | VAT | 15% | Domestic 15%; international 0% | FATOORA live; wave 25 (> SAR 187.5k) by **1 Feb 2027** | Yes | 6 |
| Oman | VAT | 5% | Domestic 5%; international 0% (verify) | Pilot 2026; mandatory 1 Apr / 1 Oct 2027 (verify) | (verify) | 10 (verify) |
| Bahrain | VAT | 10% | Domestic 10%; international 0% (verify) | Not announced | No | 5 (verify) |
| Qatar, Kuwait | none | — | Outside scope | None / draft | No | (verify) |
| EU (general) | VAT | ≥ 15% by state | Domestic standard; cross-border B2B reverse charge | Intra-EU B2B **1 Jul 2030** (ViDA); Belgium, Poland live; France Sep 2026/2027; Germany 2027/2028; Spain 2027 | Some states | Per state |
| UK | VAT | 20% | Domestic 20%; international 0% | B2B from Apr 2029 | No | 6 |
| USA | Sales tax | 0–~10% by state | Mostly not taxed; varies | None | No | 3–7 |
| Canada | GST/HST (+PST/QST) | 5% / 13–15% | Taxable by province; continuous international movement 0% | None | No | 6 |
| Australia | GST | 10% | Domestic 10%; international GST-free | Peppol voluntary | No | 5 |
| New Zealand | GST | 15% | Domestic 15%; international 0% (domestic leg only if same supplier) | Peppol voluntary | No | 7 |
| Singapore | GST | 9% | Domestic 9%; international 0% | InvoiceNow phased 2028–31 | No | 5 |
| Malaysia | SST | 8% (logistics 6%) | Logistics 6% (verify) | MyInvois live; < RM 3m exempt | Yes | 7 |
| Sri Lanka, Bangladesh, Nepal, Pakistan | VAT / sales tax | 18% / 15% / 13% / 15–18% | (verify each); withholding on transport common | Varies (Pakistan FBR real-time) | Pakistan yes | 5–6 (verify) |
| Kenya, Nigeria, South Africa, Egypt | VAT | 16% / 7.5% / 15% / 14% | Mostly standard domestic, 0% international (verify) | Kenya eTIMS, Nigeria MBS, Egypt ETA live; South Africa consultation | Mostly yes | 5–6 |

---

## 7. Build order

1. **Core engine + India + UAE presets** (rules, dated rates, groups, RCM, TDS,
   evidence gating, returns, standard invoice model) — with phase 1 accounting.
2. **UAE e-invoice provider adapter** — before the UAE launch (1 Jan 2027 for
   companies ≥ AED 50m; 1 Jul 2027 for the rest).
3. **Saudi FATOORA adapter** (wave 25 deadline 1 Feb 2027), then **Oman**, if
   we sell there.
4. **EU / UK base** (reverse charge, VIES check, EC Sales List, Peppol) and
   country adapters only when a customer needs them.
5. **Rest-of-world presets as data**; external calculator for the USA; local
   real-time systems (Malaysia, Kenya, Pakistan, Nigeria, Egypt) only when that
   market opens.

---

## 8. Reused from Clinic Ledger

Clinic Ledger already has **country packs for 52 countries**, named tax rates
with components (CGST+SGST / IGST by state; fixed splits like GST+QST),
inclusive/exclusive prices, exempt/zero, a global and per-invoice tax switch, and
the rule that the tax *name* follows the country ("GST rates" in India, "VAT
rates" in the UK). We port that and add what it lacks: per-line categories,
reverse charge and bill of supply, withholding, multiple registrations per
company, dated rates, mandatory place of supply, tax in local currency,
3-decimal currencies, amount in words in more languages, and e-invoice adapters.
