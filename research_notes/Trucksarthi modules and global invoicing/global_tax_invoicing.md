# Global tax engine and invoicing for road-freight businesses: country presets and engine design (as of 14 Oct 2026)

**Purpose.** The founder wants "full functional invoicing modules with GST/tax rules (globally)". This note gives:

- **Part A:** a preset for each target country, covering tax, how road freight is treated, withholding, invoice rules, e-invoicing, retention and thresholds.
- **Part B:** how leading systems model tax, so that one engine can serve every country.
- A **data model and calculation algorithm** for our Laravel/MySQL app.
- A **spec-ready summary** at the end.

India and UAE are covered in depth in `research_notes/Truck fleet app research India UAE/india_legal_accounting.md`, `uae_legal_accounting.md` and `docs/blueprint/07-accounting.md`. This note only summarises them and extends the picture to other countries.

---

## 1. Sources and limits

**How this was researched.** WebFetch and curl were not available, so every fact comes from web-search result summaries (about 47 searches). No primary statute text was opened. Where possible I used tax-authority pages (ZATCA, CRA, HMRC, ATO, IRD NZ, SARS) and Big-4 or e-invoicing trackers (KPMG, PwC, EY, Deloitte, Sovos, VATCalc, Fiscal-Requirements, Comarch, VATupdate). Vendor blogs were used when nothing better came up, and they are named as such.

**Labels used in this note.**

- **(verify)**: the fact was not confirmed by a source in this round, sources disagreed, or it comes from my background knowledge. Check it with a local adviser or the official text before the preset goes live.
- **Long-standing standard rates** (UK 20%, Germany 19%, Australia 10% and so on) are well known. They are not cited one by one. They are stored as **data rows with effective dates**, not code, so a wrong or changed rate is a data fix.
- **"As of Oct 2026"** applies to every date. E-invoicing timelines move often. In 2026 alone, the following changed: UAE ASP deadline, Oman Fawtara reset, Malaysia threshold, Poland penalties, Spain Verifactu, Singapore phases and the KSA waves.

**Biggest open questions.** Each must be resolved by a local adviser before that country's preset ships:

1. Domestic road-freight VAT treatment in Sri Lanka, Bangladesh, Nepal, Pakistan (each province), Kenya, Nigeria and Egypt.
2. Withholding rates on transport payments in Pakistan, Nepal, Bangladesh, Kenya, Nigeria, Sri Lanka and Egypt.
3. Final e-invoicing dates for Oman, Spain (B2B), South Africa, Bahrain and Qatar.
4. Retention periods for Oman, Bahrain, Germany (8 or 10 years), France (6 or 10 years), Poland, Belgium and the South Asian countries.

---

## 2. Part A: country presets

### 2.1 Legend: tax categories used in the tables

The categories follow the EU/Peppol **UNCL5305** subset. Our engine uses them for every country, because UAE PINT-AE, KSA ZATCA, Malaysia, Singapore, Australia, Belgium and Germany all use EN 16931 or Peppol-style category codes.

| Code | Meaning | Freight example |
|---|---|---|
| **S** | Standard rate (any non-zero rate, including reduced rates) | Domestic haulage in UAE at 5% |
| **Z** | Zero-rated: taxable at 0%, input tax is recoverable | UAE→KSA truck move (UAE carrier) |
| **E** | Exempt: no tax, input tax usually not recoverable | India non-GTA truck owner's freight |
| **AE** | Reverse charge: the customer self-assesses | EU B2B cross-border freight; India GTA RCM 5% (our internal mapping) |
| **K** | Intra-EU (EEA) exempt supply of goods/services | Rare for freight; mostly goods |
| **G** | Free export item, tax not charged | Export-related lines |
| **O** | Outside the scope of tax | Disbursements paid as agent, damage claims, Qatar/Kuwait (no VAT) |
| **L / M** | Canary Islands IGIC / Ceuta-Melilla IPSI | Spain special territories |
| **B** | Transferred VAT (Italy split payment) | Italian public-sector customers (verify) |

Source: [Peppol BIS Billing 3.0 code lists](https://docs.peppol.eu/poacc/billing/3.0/codelist/). VATEX exemption-reason pairing (for example VATEX-EU-AE with AE) is described in [Vertex: Peppol tax exemptions](https://developer.vertexinc.com/einvoicing/docs/peppol-taxes). KSA uses its own reason codes, such as VATEX-SA-34-1 "international transport of goods" (verify exact code list).

### 2.2 Table A1: tax, road-freight treatment and withholding

"RC" = reverse charge. "WHT" = withholding tax deducted by the payer.

| Country | Tax and rates | Structure | Domestic road freight | Cross-border road freight | WHT on transport payments |
|---|---|---|---|---|---|
| **India** | GST: 5% / 18% / 40% (demerit) slabs from 22 Sep 2025, plus 0% and special 0.25%/3% (verify) | CGST + SGST (intra-state) or IGST (inter-state); UTGST in UTs; compensation cess ended 22 Sep 2025 except tobacco | **GTA** (issues consignment note): **5% RCM (default)**, or opt via Annexure V for **5% FCM (no ITC)** or **18% FCM (full ITC)**, locked per FY. Non-GTA truck owner: **exempt**. Hire of a goods carriage to a GTA: **exempt**. Some goods and unregistered recipients: exempt | Place of supply is the destination when supplier or recipient is outside India (IGST s.13(9)), so export of service at **0% under LUT** if the conditions are met. An Indian recipient is taxed as domestic (s.12(8)) (verify) | **TDS s.393(1)** Income-tax Act 2025 (old 194C): 1% individual/HUF, 2% others, above ₹30k per payment / ₹1 lakh per year. **Nil** if the payee owns ≤10 goods carriages and gives a PAN declaration (s.393(4)). **GST-TDS 2%** by government buyers (s.51 CGST) (verify) |
| **UAE** | VAT 5% | Single | 5% (S) | **0% (Z)** if the move starts or ends outside UAE. The UAE leg is 0% **only if the same supplier carries the international leg**; a subcontractor charges 5% (VATP040) | None (0%) |
| **Saudi Arabia** | VAT 15% | Single | 15% (S) (verify; general rule) | **0% (Z)** for international transport of goods (Implementing Regs, amended Dec 2022) | Only on payments to **non-residents**: 5–20% by payment type; road freight probably "other" 15% (verify) |
| **Oman** | VAT 5% | Single | 5% | 0% international transport (verify article) | 10% WHT on certain payments to non-residents (verify whether transport is covered) |
| **Bahrain** | VAT 10% (since 1 Jan 2022) | Single | 10% | 0% international transport (verify) | None |
| **Qatar** | **No VAT** (5% planned, no date) | n/a | Out of scope (O) | Out of scope | 5% WHT on service fees paid to non-residents (verify) |
| **Kuwait** | **No VAT** (not before 2028 per fiscal-plan reports) | n/a | Out of scope (O) | Out of scope | 5% retention on contract payments until tax clearance (verify) |
| **EU (general)** | VAT set per member state; standard rate ≥15%, reduced ≥5% | Single per member state; special territories (L/M) | Standard rate of the member state where transport happens (B2C) or of the customer's state (B2B) | **B2B:** place of supply is the customer's country (Art. 44), so no VAT is charged, the invoice says "Reverse charge" and the customer self-assesses (AE). **B2C intra-EU:** place of departure (Art. 50). **Linked to export/import of goods:** exempt with credit (Art. 146/144) (verify) | None generally |
| Germany | 19% / 7% | | 19% | As EU; export-linked transport exempt (§4 Nr.3 UStG) (verify) | None |
| France | 20% / 10% / 5.5% / 2.1% | | 20% | As EU | None |
| Italy | 22% / 10% / 5% / 4% | Plus €2 stamp duty on non-VAT invoices >€77.47 (verify) | 22% (verify) | As EU; international transport "non imponibile" (art. 9 DPR 633/72) (verify) | None for hauliers (verify) |
| Spain | 21% / 10% / 4%; IGIC (Canaries), IPSI (Ceuta/Melilla) | | 21% | As EU | None (verify) |
| Poland | 23% / 8% / 5% | | 23% | As EU | None |
| Belgium | 21% / 12% / 6% | | 21% | As EU | None |
| **UK** | VAT 20% / 5% / 0% | Single | 20% | **0%** when goods move to or from a place outside the UK, **including UK legs of such a journey**, with evidence (VATA Sch 8 Grp 8 item 5). B2B to a customer belonging outside the UK is outside scope | None (verify) |
| **USA** | State and local sales/use tax (0–~10%+ combined); no federal VAT | Thousands of jurisdictions | A carrier's freight service is **generally not taxed** by most states. Exceptions are gross-receipts-type taxes such as HI GET, NM GRT and WA B&O (verify per state). A **seller's** delivery charges are taxable in some states | Interstate transport service generally not taxed | None on domestic carriers. Form 1099 reporting; 24% backup withholding if no TIN (verify) |
| **Canada** | GST 5%; HST 13% (ON), 14% (NS from 1 Apr 2025), 15% (NB, NL, PE); PST BC 7%, SK 6%, MB 7%; QST 9.975% | GST + PST/QST (separate, **not compounded**) or HST (single) | GST/HST at the rate of the **province of supply** (freight has its own place-of-supply rule, verify). PST generally does not apply to freight (verify) | **0%** when part of a **continuous inbound/outbound freight movement** (Sch VI Part VII), or supplied to another carrier under interlining | 15% Reg. 105 withholding on non-residents' services in Canada (verify) |
| **Australia** | GST 10% | Single | 10% | **GST-free** (s.38-355), including some domestic legs (verify conditions) | **No-ABN withholding 47%**; TPAR reporting of road-freight contractor payments (verify) |
| **New Zealand** | GST 15% | Single | 15% | **0%** (s.11A). The domestic leg is 0% **only if the same supplier provides the international transport**; a subcontractor charges 15% | NRCT for non-resident contractors (verify) |
| **Singapore** | GST 9% | Single | 9% | 0% international transport (s.21(3)) (verify conditions) | s.45 WHT for non-residents (not usual for freight) (verify) |
| **Malaysia** | SST: sales tax 5%/10% (goods); service tax 8% standard, **6% for logistics**, F&B, telecom, parking | One tax per transaction (sales tax **or** service tax) | **6% service tax** on logistics, including domestic transport/delivery, if the provider is above RM500k; B2B reliefs may apply (verify) | International, transshipment and transit movements can be exempt under RMCD conditions (verify) | None for residents. Non-resident WHT s.107A/109B (verify) |
| **Sri Lanka** | VAT 18%; **SSCL 2.5%** turnover levy | VAT + SSCL | (verify against VAT exemption schedule) | Zero-rated export of services (verify) | WHT on service fees (verify) |
| **Bangladesh** | VAT 15%; reduced 5% / 7.5% / 10%; supplementary duty | VAT + SD; **VDS** (VAT deducted at source) by withholding entities | "Transport contractor" 15% (petroleum transport 5%) (verify) | (verify) | VDS plus income-tax deduction at source (ITA 2023 s.89/90) (verify rates) |
| **Nepal** | VAT 13% (single rate) | Single | In VAT scope (IRD transport VAT guidelines 2023) (verify) | Export services zero-rated (verify) | **TDS 1.5%** (payee VAT-registered) / **2.5%** (not registered) on goods transport and vehicle hire (verify against Finance Act 2083) |
| **Pakistan** | Sales tax on goods 18% (federal, FBR); **sales tax on services is provincial**: Sindh 15%, Punjab 16%, KP 15%, Balochistan 15% (verify) | Goods (FBR) vs services (SRB, PRA, KPRA, BRA, ICT) | Provincial services tax; goods transport may be exempt or reduced in some provinces (verify each) | (verify) | **s.153(1)(b)** WHT on services, about 6% for specified services for companies in FY2025-26 and doubled for non-ATL payees (verify). s.234 advance tax on goods vehicles |
| **Kenya** | VAT 16% (8% fuel) | Single | 16% (verify) | International transport services zero-rated (KRA list), but haulage of **transit goods** is disputed (2021 High Court held it taxable) | 2% VAT withholding by appointed agents (verify); WHT on non-residents |
| **Nigeria** | VAT 7.5% (Nigeria Tax Act 2025, from 1 Jan 2026) | Single | 7.5% (verify); shared passenger road transport exempt | (verify) | WHT **2% resident / 5% non-resident** on general services (Deduction at Source Regs 2024) (verify haulage line) |
| **South Africa** | VAT 15% | Single | 15% | **0%** international transport of goods, including local legs that are part of it (s.11(2)) (verify conditions) | None for residents |
| **Egypt** | VAT 14% (5% machinery; table tax on some items) | VAT + table tax | 14% (verify). Freight for imported beans, grains and salt is exempt | Zero-rated export services (verify) | Income-tax withholding (advance-payment system) (verify rates) |

### 2.3 Table A2: invoicing, e-invoicing, QR, retention and threshold

"CTC" = continuous transaction control (real-time clearance or reporting).

| Country | E-invoicing model | Status and dates (Oct 2026) | Must use certified provider? | QR on invoice | Retention | Registration threshold |
|---|---|---|---|---|---|---|
| **India** | IRP registration (IRN) for B2B; e-way bill for goods movement | Mandatory for AATO > ₹5 cr. 30-day reporting limit for AATO ≥ ₹10 cr. **GTA road-freight services exempt** (Notif. 13/2020) | Direct IRP API (large taxpayers) or via GSP/ASP | Yes when an IRN is issued; B2C dynamic QR for AATO > ₹500 cr (verify) | GST: 72 months from annual-return due date (~6 yrs); Companies Act: **8 yrs** | ₹20 lakh services (₹10 lakh special-category states). A GTA making only RCM supplies need not register (verify) |
| **UAE** | Peppol **5-corner DCTCE** (PINT-AE) | Revenue ≥ AED 50m: appoint ASP by **30 Oct 2026**, live **1 Jan 2027**. Others: appoint by 31 Mar 2027, live **1 Jul 2027**. Government: 1 Oct 2027 | **Yes**: MoF-accredited ASP | No (verify) | 5 yrs VAT (verify) | AED 375k mandatory / AED 187.5k voluntary |
| **Saudi Arabia** | **FATOORA**: B2B **clearance** (real time), B2C **reporting within 24h**; UBL 2.1 + cryptographic stamp, ICV, PIH | Phase 1 since Dec 2021. Phase 2 waves: Wave 24 (> SAR 375k), Wave 25 (> SAR 187.5k in any year 2022–25) integrate by **1 Feb 2027** | No intermediary needed, but each solution/unit must be onboarded (CSID) and compliant | **Yes** (all invoices in Phase 2) | **6 yrs** (11 for real estate) | SAR 375k / 187.5k |
| **Oman** | **Fawtara**: Peppol-style 5-corner via accredited providers (verify) | Decision 189/2026 (reported): voluntary pilot from late Aug 2026; **mandatory 1 Apr 2027** (supplies > OMR 5m), **1 Oct 2027** (others) (verify) | Likely yes (verify) | (verify) | 10 yrs (verify) | OMR 38.5k / 19.25k |
| **Bahrain** | None yet; e-invoices allowed without approval since 16 Nov 2023 | No go-live date announced (verify) | n/a | No | 5 yrs (verify) | BHD 37.5k / 18.75k |
| **Qatar** | Draft e-invoicing law approved May 2026; no VAT | Not live | n/a | No | (verify) | n/a |
| **Kuwait** | None | n/a | n/a | No | (verify) | n/a |
| **EU (ViDA)** | Member states may mandate domestic e-invoicing **without derogation** (since 2025). **1 Jul 2030**: mandatory e-invoicing + digital reporting for **intra-EU B2B**, EN 16931 | Adopted Mar 2025 | Per member state | Per member state | Per member state | Per member state; EU SME scheme from 2025 |
| Germany | Post-audit, decentralised (XRechnung/ZUGFeRD, EDI) | Receive since 1 Jan 2025. **Issue: 1 Jan 2027** (prior-year turnover > €800k), **1 Jan 2028** all. Exempt: < €250 and transport tickets | No (email, Peppol or EDI) | No | 8 yrs vouchers from 2025 / 10 yrs books (verify) | Small business €25k prior / €100k current (verify) |
| France | Decentralised CTC via **Plateformes Agréées (PA)** + e-reporting (B2C and international) | **1 Sep 2026**: all must receive; large companies and ETIs issue (reported live with graduated enforcement). **1 Sep 2027**: SMEs and micro issue | **Yes**: PA | No (verify) | 6 yrs tax / 10 yrs commercial; reported extension to 10 yrs tax (Law 2026-534) (verify) | Franchise thresholds (verify) |
| Italy | **SDI clearance** (FatturaPA XML), B2B + B2C + cross-border data | Live since 2019 | SDI channel (direct or intermediary) | No | **10 yrs** (digital preservation) | None general (flat-rate regime €85k) (verify) |
| Spain | SII (large companies) + **Verifactu** (software-sealed records) + future B2B e-invoice (Crea y Crece) | Verifactu: **1 Jan 2027** (corporate taxpayers), **1 Jul 2027** (others), per RDL 15/2025. B2B order still pending (reported proposal: Oct 2027 / Oct 2028) (verify) | Certified (Verifactu-compliant) software | **Yes, Verifactu QR from 2027** | 4 yrs tax / 6 yrs commercial | None |
| Poland | **KSeF clearance** (FA(3) XML) | Mandatory **1 Feb 2026** (sales > PLN 200m), **1 Apr 2026** (others), **1 Jan 2027** (micro ≤ PLN 10k/month). Penalties deferred to end-2027 or 2028 (bill UD477) (verify) | No (direct API with token/certificate) | **Yes** on printed or offline invoices (verify) | 5 yrs (verify) | PLN 200k (240k from 2026) (verify) |
| Belgium | **Peppol 4-corner** (BIS 3.0); **5-corner e-reporting 1 Jan 2028** | **Mandatory B2B since 1 Jan 2026** (grace period Q1 2026) | Yes: Peppol access point | No | 10 yrs (verify) | €25k franchise |
| **UK** | MTD for VAT now; **B2B/B2G e-invoicing from Apr 2029** (Peppol, no clearance at first) | Announced in Budget 2025 | Expected: Peppol access point | No | **6 yrs** | £90k |
| **USA** | None | n/a | n/a | No | 3–7 yrs (IRS) + state rules (verify) | State economic nexus, typically $100k (verify) |
| **Canada** | None | n/a | n/a | No | **6 yrs** | CAD 30k (small supplier) |
| **Australia** | Peppol PINT A-NZ, voluntary B2B; government targets 2026 | No B2B mandate | n/a | No | **5 yrs** | AUD 75k |
| **New Zealand** | Peppol, voluntary B2B (government receiving targets) (verify) | No B2B mandate | n/a | No | **7 yrs** | NZD 60k |
| **Singapore** | **InvoiceNow** (Peppol 5-corner, IRAS as corner 5) | New voluntary registrants: 1 Nov 2025 / **1 Apr 2026**. Others phased: **1 Apr 2028** (new compulsory and ≤ SGD 200k) to **1 Apr 2031** (verify) | Yes: InvoiceNow-ready solution / Peppol access point | No | **5 yrs** | SGD 1m |
| **Malaysia** | **MyInvois** (IRBM validation, UIN + QR) | Phased 2024–2026. **Threshold raised from RM1m to RM3m** (Aug/Sep 2026). Penalty relaxation for late cohorts reported to 31 Dec 2027 (verify) | No (portal or direct API); Peppol optional | **Yes** | **7 yrs** | RM500k (services) |
| **Sri Lanka** | None for domestic suppliers (verify) | VAT (Amendment) Act No. 14 of 2026 tightened invoicing (verify) | n/a | No (verify) | 5 yrs (verify) | LKR 60m/yr or LKR 15m/quarter (cut to 36m abandoned Jun 2026) |
| **Bangladesh** | No B2B mandate; EFD/SDC for retail; e-VAT returns; Mushak 6.3 invoice | n/a | EFD/SDC devices for retail | Retail devices only | 6 yrs (verify) | (verify) |
| **Nepal** | IRD-approved billing software; real-time reporting (CBMS) above set turnover (verify) | Transport providers above NPR 10m: continuous reporting (2023 guideline) (verify) | IRD-approved software | (verify) | 6 yrs (verify) | NPR 5m (freight counted on goods side) / NPR 3m services (verify) |
| **Pakistan** | **FBR Digital Invoicing**: real-time, every registered person | SRO 709(I)/2025 then phased Oct–Dec 2025; fines from 2025 (verify current status) | **Yes**: PRAL or licensed integrator | **Yes** (FBR invoice number QR) | 6 yrs (verify) | Provincial services: generally none (verify) |
| **Kenya** | **eTIMS** real-time; all businesses incl. non-VAT-registered | Live; expenses without an eTIMS invoice are not deductible (verify) | eTIMS OSCU/VSCU or KRA-approved integrator | **Yes** | **5 yrs** | KES 5m (verify) |
| **Nigeria** | **NRS Merchant-Buyer Solution (MBS)**: IRN + QR | Large: live Nov 2025. Medium (₦1–5bn): **1 Jul 2026**, enforcement Jan 2027. Emerging (< ₦1bn): **1 Jul 2027** | Yes: accredited System Integrator / Access Point Provider (verify) | **Yes** | 6 yrs (verify) | Small-company exemption under NTA 2025 (verify amount) |
| **South Africa** | Proposed **DCTCE 5-corner** (VAT Modernisation paper, 17 Aug 2026; comments due 16 Oct 2026) | No mandate yet; large B2B first, about 2028–2030 (sources differ) | (proposed) yes | No | **5 yrs** | **R2.3m** from 1 Apr 2026 (voluntary R120k) |
| **Egypt** | **ETA clearance** (signed JSON/XML) + **e-receipt** for B2C | E-invoice mandatory for all registered taxpayers (2020–2023 phases); e-receipt waves continue | Direct API with e-seal/token, or intermediary | Yes on e-receipts | **5 yrs**, archive stored in Egypt | EGP 500k (one source says 250k from 2026) (verify) |

### 2.4 Per-country notes

Each note covers only what the engine needs. "Engine:" lines say what the preset must configure.

#### South Asia

**India (summary of existing notes).**

- **Rates and structure.** GST 2.0 from 22 Sep 2025: slabs 5% / 18% / 40%. Compensation cess was discontinued for all goods except tobacco items ([India Briefing](https://www.india-briefing.com/news/gst-cess-removal-india-2025-business-benefits-39879.html/); [Baker McKenzie](https://resourcehub.bakermckenzie.com/en/resources/vatgst-rates--thresholds/asia-pacific/india/topics/vat--gst)). Tobacco cess was reportedly replaced by new levies from Feb 2026 (verify; [Taxo](https://taxo.online/latest-news/01-12-2025-government-to-bring-two-bills-in-lok-sabha-to-replace-gst-cess-on-tobacco-pan-masala-with-new-levies/)). Freight businesses rarely need cess, but the engine keeps a **cess component type** (ad valorem or per-unit) because history and amendments need it.
- **GTA regime.** The GTA regime (RCM 5% / FCM 5% / FCM 18%), the exemptions (entries 18, 21, 21A, 22), Rule 46 invoice fields, the 16-character number per FY, e-invoicing exemption for GTA, e-way bill and TDS s.393 are all in `india_legal_accounting.md`.
- **Place of supply for international road freight.** Where supplier or recipient is outside India, place of supply for goods transport is the **destination of the goods** (IGST s.13(9)). Freight to Nepal or Bangladesh billed to a foreign customer can therefore be an **export of service** (0% under LUT, or paid and refunded). Where both are in India, s.12(8) applies (recipient's location) and the supply is taxed as domestic (verify with CA).
- **Advances.** GST is due on **advances for services** at receipt (receipt voucher with tax, adjusted on the invoice). Under RCM, the recipient handles it.
- **Engine.**
  - Regime `IN_GST`.
  - Intra/inter-state resolver by state code (the first 2 digits of GSTIN).
  - Seller registration options per GSTIN per FY (GTA RCM / FCM5 / FCM18 / exempt non-GTA).
  - Buyer categories: registered, unregistered, factory, body corporate, government, SEZ, overseas.
  - Goods category on the consignment (for exemptions).
  - HSN/SAC master (9965/996511, 9966/996601, 996791).
  - TDS s.393 with old and new section numbers.
  - GST-TDS for government buyers.
  - IRN/QR only for non-GTA taxable supplies above the threshold.

**Sri Lanka.**

- **Rates and threshold.** VAT 18% since 1 Jan 2024. Registration above **LKR 60m a year or LKR 15m a quarter**. A proposed cut to LKR 36m was **abandoned on 23 Jun 2026**. **SSCL** (Social Security Contribution Levy) stays at **2.5% of liable turnover** ([Beancount, Sep 2026](https://beancount.io/blog/2026/09/24/sri-lanka-vat-threshold-reversal-60-million-registration-guide); [Kintsugi](https://trykintsugi.com/sales-tax-guides/apac/sri-lanka)).
- **2026 amendment.** VAT (Amendment) Act No. 14 of 2026 brought non-resident digital services into VAT from 1 Jul 2026 and tightened invoicing ([LookupTax](https://lookuptax.com/docs/country/sri-lanka-vat-guidelines-indirect-tax)). The financial-services rate change in the same source is single-sourced (verify).
- **Freight.** Domestic freight treatment (taxable or exempt) was **not confirmed** (verify).
- **Engine.** VAT 18% + SSCL as a separate levy type. SSCL is a turnover levy; whether it is shown on the invoice is a preset option (verify practice). Tax invoice vs simplified invoice by buyer registration.

**Bangladesh.**

- **Rates.** VAT 15% standard, with reduced 5% / 7.5% / 10% ([PwC tax summaries](https://taxsummaries.pwc.com/bangladesh/corporate/other-taxes)). A secondary table lists "transport contractor" at 15% and petroleum transport contractor at 5% ([LookupTax](https://lookuptax.com/docs/country/bangladesh-vat-guidelines-indirect-tax)) (verify).
- **E-invoicing.** No mandatory B2B or B2G e-invoicing. The model is post-audit with EFD/SDC devices for retail and the e-VAT online return. The statutory invoice is **Mushak 6.3**, which can be issued electronically ([VATupdate country booklet, Aug 2026](https://www.vatupdate.com/2026/08/28/bangladesh-e-invoicing-e-reporting-country-booklet/)).
- **Withholding.** Income-tax deduction at source under ITA 2023 s.89 (contractors) and s.90 (services) ([secondary guide](https://rakibhassan.eu/withholding-tax-bangladesh-complete-compliance-guide-2025/)). Rates for road transport were not confirmed (verify).
- **Engine.**
  - VAT + supplementary duty components.
  - **VDS** (VAT deducted at source by specified buyers) as a withholding code that reduces the cash received, not the VAT charged.
  - Mushak 6.3 / 6.7 (credit note) / 6.8 (debit note) templates (verify form numbers).
  - BIN on invoices.

**Nepal.**

- **Rate and transport guidelines.** VAT 13%, single rate. In 2023 the IRD issued **VAT guidelines for transport services**, covering invoicing, quarterly payment for providers with turnover up to NPR 10m, and continuous transaction reporting above that ([Bloomberg Tax](https://news.bloombergtax.com/daily-tax-report-international/nepal-tax-agency-posts-vat-implementation-guidelines-for-transport-services)).
- **Threshold.** Freight and hire of freight vehicles are tested against the **NPR 5m (goods-side)** threshold, not NPR 3m ([LookupTax](https://lookuptax.com/docs/country/nepal-vat-guidelines-indirect-tax)) (verify).
- **TDS.** Goods transport / vehicle hire is **1.5%** when paid to a VAT-registered provider and **2.5%** when not registered ([Law Bhandari](https://lawbhandari.com/publication/understanding-tax-law-for-local-and-fdi-companies-in-nepal); [NBSM newsletter](https://www.nbsm.com.np/uploads/large/162133181028077.pdf)). Verify against the current Finance Act.
- **Rideshare (not freight).** A July 2026 notice set 5% VAT on rideshare fares ([LookupTax](https://lookuptax.com/tax-changes/nepal/ride-sharing-platform-vat-2026)).
- **Engine.**
  - VAT 13%.
  - TDS rate chosen by the payee's VAT status.
  - Fiscal year mid-July to mid-July.
  - Invoices may need **Bikram Sambat dates** alongside Gregorian (verify).
  - IRD-approved billing software certification is required to sell into Nepal (verify).

**Pakistan.**

- **Split of tax powers.** Sales tax on **goods** is federal (FBR, Sales Tax Act 1990). Sales tax on **services** is **provincial**: Sindh (SRB) 15% from FY2024-25 ([EY](https://www.ey.com/en_gl/technical/tax-alerts/2024-1353-pakistan-adopts-changes-to-provincial-sales-tax-laws)); Punjab (PRA) 16%, moved to a negative list in 2025 ([Kintsugi](https://trykintsugi.com/sales-tax-guides/apac/pakistan)); KP and Balochistan about 15% (verify).
- **Freight.** Road freight treatment differs by province (verify each).
- **Income-tax WHT, s.153(1)(b).** The Finance Act 2025 raised specified-service rates (reported 6%) and unspecified services to 15%; IT services stay at 4% ([Pakistan Today](https://profit.pakistantoday.com.pk/?p=208309); [ETTC table: freight/courier companies 6% filer / 12% non-filer](https://ettc.pk/blog/fbr-withholding-tax-rates-pakistan)). One tax-office form shows 9%/18% for companies in general ([ICAP form](https://www.icap.org.pk/files/per/downloads/tax/ExemptionCertificateUs153.pdf)) (verify).
- **E-invoicing.** **FBR Digital Invoicing** requires every sales-tax-registered person to integrate in real time via **PRAL or a licensed integrator**. Each invoice carries an FBR invoice number in a **QR code**. Rules came via SRO 709(I)/2025, phased Oct–Dec 2025, with fines (for example PKR 500k first violation) ([Comarch](https://www.comarch.com/trade-and-services/data-management/legal-regulation-changes/tight-deadlines-for-nationwide-e-invoicing-integration-in-pakistan/); [VATCalc](https://www.vatcalc.com/pakistan/pakistan-readies-centralised-e-invoicing-and-fiscal-systems/); [Zameen](https://www.zameen.com/news/fbr-warns-of-fines-for-missing-e-invoicing.html)) (verify current status).
- **Engine.**
  - Two regimes: `PK_ST_GOODS` (federal) and `PK_ST_SERVICES_<province>`.
  - The jurisdiction resolver picks the province of service.
  - WHT codes per section, with rates by **ATL status** (Active Taxpayer List: filer / non-filer).
  - NTN/STRN/CNIC fields.
  - FBR integrator adapter.

#### GCC

**UAE (summary of existing notes).**

- **VAT.** 5%. International transport is **Z**. The domestic leg is Z only for the principal carrier; a subcontractor charges S (VATP040).
- **Tolls and parking.** Salik and Parkin carry 5% VAT from 1 Jun 2026 (a purchase-side tax code with an effective date).
- **2026 amendments.** Reverse charge with no self-invoice from 2026. 5-year input-tax limit.
- **Invoices and e-invoicing.** Invoices follow Art. 59; simplified invoice up to AED 10k. E-invoicing via ASP (PINT-AE): **1 Jan 2027** for ≥ AED 50m, **1 Jul 2027** for others.
- **Engine.**
  - Trip flags `transport_leg` (domestic / international / domestic leg of international) and `carrier_role` (principal / subcontractor).
  - Zero-rating evidence fields.
  - AED amounts with exchange rate.
  - ASP adapter.

**Saudi Arabia.**

- **Rate and zero-rating.** VAT 15%. Zero rating covers international transport of goods and passengers, means of transport and services connected with international transport. The Implementing Regulations (Arts. 33, 34, 36) were amended in Dec 2022 to clarify zero-rating for exports of services and international transport ([PwC 2022](https://pwc.com/m1/en/tax/documents/2022/saudi-arabia-approved-amendments-chapter-six-zero-rated-supplies.pdf); [ClearTax KSA](https://cleartax.com/sa/zero-rated-supplies-under-saudi-arabia-vat)). Domestic road freight follows the general 15% rule (verify with ZATCA guide).
- **E-invoicing (FATOORA).** Two invoice types:
  - **Standard tax invoice (B2B):** must be **cleared** by ZATCA before it is given to the buyer.
  - **Simplified tax invoice (B2C):** given immediately, then **reported within 24 hours**.

  Each invoice carries a cryptographic stamp, **QR code**, Invoice Counter Value (ICV) and Previous Invoice Hash (PIH), in UBL 2.1 with KSA extensions ([Dariba](https://www.dariba.co/e-invoice-clearance-vs-reporting-in-saudi-arabia-a-plain-english-guide/); [Invopop](https://docs.invopop.com/compliance/saudi-arabia.md)).
- **Waves.** Phase 2 runs in waves. **Wave 25** covers everyone with VAT-able revenue > **SAR 187,500** in any of 2022–2025, with integration due by **1 Feb 2027** ([KPMG, Jul 2026](https://kpmg.com/us/en/taxnewsflash/news/2026/07/saudi-arabia-e-invoicing-mandatory-for-taxpayers-with-revenue-above-sar-187500.html); [ZATCA Wave 25](https://zatca.gov.sa/en/MediaCenter/News/Pages/Wave25-E-invoicing.aspx); [Qoyod](https://www.qoyod.com/en/knowledge-base/e-invoicing/e-invoicing-phase-2-waves/)). In practice almost every VAT-registered KSA fleet will be in scope by Feb 2027.
- **Language, currency and retention.** **Arabic is required** on invoices; other languages may be added (verify exact rule). VAT is in SAR. **Retention 6 years** from the end of the tax period, 11 years for real estate ([Qoyod](https://www.qoyod.com/en/learn/e-invoice-retention/)).
- **Engine.**
  - Bilingual (Arabic/English) invoice template.
  - Buyer VAT number and national address for B2B.
  - UUID, ICV and PIH hash chain per device/branch (**strictly sequential, no gaps**).
  - CSID onboarding per EGS unit.
  - Clearance API before release (B2B); queue with 24h SLA (B2C).
  - Credit/debit notes must cite the original invoice and a reason.
  - VATEX-SA reason codes for zero-rated and exempt lines.

**Oman.**

- **Rate.** VAT 5% since April 2021.
- **Fawtara timeline.** Sources conflict. Early 2026 reports gave Phase 1 in **Aug 2026** for about 100 large taxpayers, then Feb 2027 and Aug 2027 ([A&M](https://www.alvarezandmarsal.com/node/95831); [Oman Observer](https://www.omanobserver.om/ampArticle/1194559)). The latest reports (Aug 2026) say **Tax Authority Decision 189/2026** reset this: a voluntary pilot from late Aug 2026 (about 100 companies), mandatory from **1 Apr 2027** for supplies > OMR 5m and **1 Oct 2027** for the rest ([Fonoa](https://www.fonoa.com/resources/blog/oman-e-invoicing)). Mandatory fields: [ClearTax Oman](https://www.cleartax.com/om/mandatory-data-fields-fawtara-e-invoicing). (verify with OTA)
- **Engine.** Same canonical invoice as UAE (Peppol-style), OMR with 3 decimals (verify), Arabic support.

**Bahrain.**

- **Rate.** VAT 10% since 1 Jan 2022.
- **E-invoicing.** Not mandatory; **no go-live date announced** as of mid-2026. Invoices may be issued and kept electronically without NBR approval since 16 Nov 2023. Unofficial expectations mention large taxpayers first ([ClearTax](https://www.cleartax.com/sa/e-invoicing-in-bahrain); [VATCalc](https://www.vatcalc.com/bahrain/bahrain-progresses-vat-e-invoicing-launch/); [Flick](https://www.flick.network/en-bh/e-invoicing-bahrain); [VATupdate booklet](https://www.vatupdate.com/2026/08/10/bahrain-e-invoicing-e-reporting-country-booklet/)).
- **Engine.** BHD with 3 decimals (verify); international transport Z (verify).

**Qatar and Kuwait.**

- **Status.** Neither has VAT. Qatar approved a draft e-invoicing law in May 2026, and VAT timing is unconfirmed ([PwC GCC Q2 2026](https://www.pwc.com/m1/en/services/tax/middle-east-tax-news-alerts/2026/gcc-indirect-tax-news-roundup-q2-2026.html); [Fiscal-Requirements](https://www.fiscal-requirements.com/news/5769-qatar-vat-status)). Kuwait's 2026–30 fiscal plan leaves out VAT ([VATCalc](https://www.vatcalc.com/kuwait/kuwait-may-abandon-2023-vat-implementation/); [Baker Tilly Kuwait](https://www.bakertilly.com.kw/en/services/tax-services/value-added-tax-services/)).
- **Engine.** Preset "no indirect tax". Every line gets category **O**, so switching VAT on later is just new data rows with an effective date. Keep withholding/retention codes for non-resident and contract payments (verify).

#### Europe

**EU general rules (all EU presets inherit these).**

- **Place of supply for goods transport.**
  - **B2B:** general rule, the customer's place of establishment (Art. 44) ([EU Customs & Tax e-learning](https://customs-taxation.learning.europa.eu/mod/resource/view.php?id=8960)). A haulier billing a business in another member state charges **no VAT**, writes **"Reverse charge"** (category AE), shows both VAT IDs, and reports the sale in its **EC Sales List**. The customer self-assesses.
  - **B2C intra-Community transport:** taxed where transport **departs** (Art. 50; definitions in Art. 51) ([Directive text](https://www.legislation.gov.uk/eudr/2006/112/article/51/2014-01-01/data.html)).
  - **B2C domestic transport:** taxed where it takes place, in proportion to distances (Art. 49).
  - **Transport linked to export or import of goods:** exempt with credit (Arts. 146(1)(e), 144) (verify wording on EUR-Lex).
- **Invoice content (Art. 226).** Date; sequential number; supplier and (for RC/intra-EU) customer VAT IDs; names and addresses; quantity and nature; date of supply; taxable amount per rate; unit price; VAT rate; VAT amount; exemption reference or "Reverse charge" text.
- **Currency, simplified invoices and credit notes.** The VAT amount must be expressible in the member state's currency (Art. 230). Simplified invoices are allowed up to €100, and some member states set higher limits, for example Germany €250 (verify). Credit notes must clearly refer to the original invoice.
- **ViDA.** Adopted March 2025. Since 2025 member states can impose domestic e-invoicing **without EU derogation**. From **1 Jul 2030** intra-EU B2B must be e-invoiced (EN 16931) with near-real-time digital reporting, replacing EC Sales Lists. Existing pre-2024 domestic systems must converge by **2035** ([PwC CH](https://www.pwc.ch/en/insights/tax/vida-revolutionising-eu-vat-for-the-digital-era.html); [Grant Thornton IE](https://www.grantthornton.ie/insights/factsheets/vida-digital-reporting-e-invoicing/); [Sovos](https://sovos.com/en-gb/blog/vat/vida-e-invoicing-and-digital-reporting-requirements/); [eConnect timeline](https://econnect.eu/en/docs/knowledge/regulations/europe/vida/timeline)).
- **Engine.** VIES VAT-ID validation, an "EU B2B services" rule that yields AE plus the legal text, an EC Sales List export, and an **EN 16931 canonical invoice model** (the core of every EU and Peppol country adapter).

**Germany.**

- **Mandate.** Receiving EN 16931 e-invoices is mandatory since 1 Jan 2025 (an email inbox is enough). Issuing is mandatory from **1 Jan 2027** if prior-year turnover is above **€800k**, and from **1 Jan 2028** for all. Accepted formats are XRechnung (XML) and ZUGFeRD 2.x (hybrid PDF+XML). Invoices under €250 and transport tickets are exempt ([SoftCo 2026](https://softco.com/blog/germany-e-invoicing-mandate-2026-update/); [EC eInvoicing Germany](https://ec.europa.eu/digital-building-blocks/sites/display/DIGITAL/eInvoicing%2Bin%2BGermany); [Fiscal-Requirements](https://www.fiscal-requirements.com/news/4525-germanys-road-to-full-b2b-e-invoicing-by-2028)).
- **Pending pushback.** The trades association (ZDH) wants 2027 moved to 2028; this was not adopted as of the sources.
- **Model.** Post-audit, so no clearance and no certified provider.
- **Engine.** ZUGFeRD/XRechnung generator and an inbound XML parser.

**France.**

- **Mandate.** From **1 Sep 2026** every VAT-registered business must **receive** e-invoices via a **Plateforme Agréée (PA)**, and large companies and ETIs must **issue**. SMEs and micro-businesses issue from **1 Sep 2027**. E-reporting covers B2C and international transactions. A 14 Aug 2026 source says go-live happened with no formal delay and with graduated enforcement ([Tungsten](https://tungstenautomation.com/compliance-overview/country-updates/country/france/september-2026-go-live-of-e-invoicing-and-e-reporting-mandate-confirmed); [CMS](https://cms.law/en/fra/news-information/electronic-invoicing-what-does-the-reform-entail); [IEDI](https://iedi.com/e-invoicing-france-2026)) (verify).
- **Engine.** A French tenant needs a **PA partner**. New mandatory mentions: customer SIREN, delivery address, goods/services/mixed flag, VAT on debits option (verify list).

**Italy.**

- **Mandate.** All invoices (B2B, B2C, B2G) pass through **SDI** in FatturaPA XML; SDI rejects invalid files.
- **Coding.** Non-taxable and exempt lines carry **"Natura" codes** (N1–N7; e.g. N3.x non-taxable, N6.x reverse charge).
- **Cross-border data.** Cross-border data goes through SDI (TD17/18/19 document types) ([vendor guide](https://ecosire.com/ar/blog/odoo-italy-localization-guide-2026)) (verify).
- **Engine.** Natura code on each tax code, stamp-duty (bollo) line type, split-payment (B) for public bodies, and an SDI intermediary.

**Spain.**

- **Verifactu.** Postponed by **RDL 15/2025** to **1 Jan 2027** (corporate taxpayers) and **1 Jul 2027** (others). Invoicing software must create chained, hashed records and print a **QR code** ([VATCalc](https://www.vatcalc.com/spain/spain-verifactu-delay-till-jan-2027-for-certified-e-invoicing/); [Comarch](https://www.comarch.com/trade-and-services/data-management/legal-regulation-changes/spain-postpones-verifactu-implementation-to-2027/)).
- **B2B e-invoicing (Crea y Crece).** The ministerial order activating dates was still pending in mid-2026. A proposal reports Oct 2027 (large) and Oct 2028 (SMEs) ([Tradeshift](https://tradeshift.com/?p=71104)) (verify).
- **Engine.** IGIC (L) and IPSI (M) regimes, a Verifactu record chain, and QR.

**Poland.**

- **KSeF dates.** Mandatory **1 Feb 2026** for taxpayers with sales > PLN 200m and **1 Apr 2026** for the rest. Micro businesses (≤ PLN 10k invoiced per month) join **1 Jan 2027**.
- **Penalties.** The Ministry extended penalty relief to end-2027, and a 22 Sep 2026 bill (UD477) would move it to **1 Jan 2028** ([Sovos](https://sovos.com/regulatory-updates/vat/poland-ministry-of-finance-extends-ksef-penalty-relief-until-end-of-2027/); [Fiscal-Requirements](https://www.fiscal-requirements.com/news/6020-e-invoicing-in-poland-penalty-deadline-proposed-to-be-extended-until-2028); [Fintua](https://fintua.com/blog/poland-b2b-e-invoicing/)).
- **Model.** Clearance: the invoice exists legally only with a KSeF number.
- **Engine.** FA(3) XML adapter, KSeF number stored, QR codes on paper/PDF copies and offline mode (verify).

**Belgium.**

- **Mandate.** Structured B2B e-invoicing via **Peppol BIS 3.0** has been mandatory since **1 Jan 2026** (Royal Decree of 14 Jul 2025), with a conditional grace period for Q1 2026. **Near-real-time e-reporting (5-corner) is due 1 Jan 2028** ([TrueCommerce](https://www.truecommerce.com/blog/e-invoicing-in-belgium-your-guide-to-the-2026-mandate/); [Comarch](https://abc.comarch.com/trade-and-services/data-management/legal-regulation-changes/belgium-confirms-2026-peppol-e-invoicing-mandate-with-2028-near-real-time-reporting-to-follow)).
- **Engine.** Peppol access point partner.

**UK.**

- **Freight.** Freight transport means a vehicle supplied with a driver. A vehicle without a driver is a means-of-transport hire, which is a different rule ([VAT Notice 744B](https://www.gov.uk/government/publications/vat-notice-744b-freight-transport-and-associated-services)).
- **Zero rating.** Freight to or from a place outside the UK is zero-rated (Group 8 item 5), **including legs that are part of such a journey**, if the supplier holds evidence ([HMRC VTRANS060200](https://www.gov.uk/hmrc-internal-manuals/vat-transport/vtrans060200)). Purely domestic haulage to a UK customer is 20%.
- **E-invoicing.** **Mandatory B2B/B2G e-invoicing from April 2029**, Peppol-based, with no clearance at first ([Avalara](https://www.avalara.com/us/en/blog/uk-mandatory-e-invoicing-2029.html); [Comarch](https://www.comarch.com/trade-and-services/data-management/legal-regulation-changes/uk-budget-2025-confirms-mandatory-b2b-e-invoicing-for-2029/)).
- **Invoices and records.** Simplified invoice up to £250 (verify). VAT shown in GBP. Records kept 6 years ([retention table](https://invoicedataextraction.com/blog/invoice-retention-requirements-by-country)).
- **Engine.** MTD-compatible VAT return export (boxes 1–9).

#### Americas

**USA.**

- **Sales tax structure.** No VAT. Sales tax is state and local. Taxability of a seller's shipping or delivery charges varies by state. Separately stated common-carrier charges are often exempt, combined shipping-and-handling is often taxable, and some states (for example Connecticut) tax shipping on taxable sales either way ([Avalara state guide](https://www.avalara.com/us/en/blog/how-to-handle-sales-tax-on-shipping-a-state-by-state-guide.html); [Numeral](https://www.numeral.com/blog/sales-tax-on-shipping)).
- **Freight services.** A **trucking company's own freight service** is generally a non-taxable service in most states, but gross-receipts taxes can apply (verify per state).
- **Trucking-specific taxes outside invoicing.** IFTA fuel tax, IRP apportioned registration and HVUT Form 2290 (out of scope here).
- **Engine.** Do not build US jurisdiction rates ourselves. **Integrate Avalara, Stripe Tax or a similar provider** for the US. Our engine keeps "US sales tax" as an external-calculation regime and stores the returned jurisdiction lines. Also needed: 1099 vendor reporting and W-9/TIN capture.

**Canada.**

- **Rates.** GST 5%. HST 13% (ON), **14% (NS since 1 Apr 2025)**, 15% (NB, NL, PE). BC and MB 7% PST, SK 6% PST, QC 9.975% QST ([TaxTips 2026](https://www.taxtips.ca/salestaxes/sales-tax-rates-2026.htm)). The GST/HST rate generally follows the place of supply (customer or destination).
- **Freight zero rating.** Domestic freight is **zero-rated when part of a continuous inbound or outbound freight movement** or supplied to another carrier under interlining. A shipper's declaration is needed. Processing of the goods in Canada breaks zero-rating ([CRA freight carriers](https://www.canada.ca/en/revenue-agency/services/tax/businesses/topics/gst-hst-businesses/charge-collect-specific-situations/freight-carriers.html); [CRA Memo 28-2](https://www.canada.ca/en/revenue-agency/services/forms-publications/publications/28-2/freight-transportation-services.html)).
- **Engine.** Tax group GST + PST/QST (two components, not compound). Province resolver. Continuous-movement declaration as zero-rating evidence. Invoice detail tiers by amount (under CAD 100; 100–499.99; 500+) (verify). Quebec French-language rules (verify).

#### Asia-Pacific

**Australia.**

- **Freight.** GST 10%. International transport of goods is GST-free (s.38-355). The 2010 changes extended GST-free treatment to some domestic legs ([ATO](https://www.ato.gov.au/Business/GST/In-detail/Rules-for-specific-transactions/International-transactions/GST-and-international-freight-transport/); [CGW](https://cgw.com.au/publications/gst-new-rules-for-supplies-of-international-transport-and-insurance/)) (verify current items).
- **E-invoicing.** Peppol **PINT A-NZ** only since 15 May 2025. B2B is **voluntary**: the proposed business e-invoicing right was not enacted. Commonwealth agencies must receive 30% via Peppol by 1 Jul 2026 ([Zone & Co](https://www.zoneandco.com/articles/e-invoicing-in-australia-compliance-guide); [Avalara](https://www.avalara.com/us/en/blog/australia-e-invoicing-2026-deadlines-government-adoption.html)).
- **Engine.** Tax invoice for sales ≥ AUD 82.50 when asked; buyer identity/ABN if ≥ AUD 1,000 (verify). No-ABN withholding. TPAR export.

**New Zealand.**

- **Freight.** GST 15%. International transport is zero-rated. **The domestic leg is zero-rated only if the same supplier provides the international transport.** An NZ subcontractor charges GST. IRD found widespread wrong zero-rating ([IRD 2020 issues paper ch.5](https://taxpolicy.ird.govt.nz/publications/2020/2020-ip-gst-issues/chapter-5)). A 2021 proposal to fix cascades has unknown enactment status ([RIS 2021](https://www.regulation.govt.nz/assets/RIS-Documents/ria-ird-dts-may21.pdf)) (verify).
- **Engine.** The same `carrier_role` flag as UAE. Taxable supply information (TSI) tiers (≤ NZD 200 / ≤ 1,000 / > 1,000) (verify).

**Singapore.**

- **Rate.** GST 9% since 2024.
- **InvoiceNow (Peppol, IRAS as corner 5).**
  - Mandatory for newly incorporated voluntary registrants from **1 Nov 2025**, and for **all new voluntary registrants from 1 Apr 2026**.
  - IRAS (Feb 2026) phased in existing businesses: **1 Apr 2028** (new compulsory registrants, and existing with supplies ≤ SGD 200k), **2029** (≤ SGD 1m), **2030** (≤ SGD 4m), **2031** (rest).

  Sources: [Sovos](https://sovos.com/regulatory-updates/vat/singapore-gst-invoicenow-expansion/); [Fiscal-Requirements](https://www.fiscal-requirements.com/news/4534-singapore-mandatory-invoicenow-e-invoicing-for-gst-registrants-starts-november-2025); [LeftRight](https://leftrightcorp.com/guides/news-updates/mandatory-invoicenow-transition/) (verify on IRAS).
- **Engine.** Peppol access point. Simplified invoice ≤ SGD 1,000. Tax invoice within 30 days (verify).

**Malaysia.**

- **SST.** Logistics stayed at **6%** when general service tax went to 8%. The logistics category covers freight forwarding, customs agency, courier, transportation and delivery ([LookupTax](https://lookuptax.com/docs/country/malaysia-sst-guidelines-indirect-tax); [KPMG MY](https://kpmg.com/my/en/home/insights/2024/01/proposed-increase-in-service-tax-rate-and-expansion-of-taxable-services.html); [Maersk notice](https://www.maersk.com/news/articles/2024/02/13/notice-of-service-tax-rate-changes-on-logistics-service-in-malaysia)). RMCD's June 2025 logistics guide reportedly treats domestic deliveries as taxable and international door-to-door, transit and transshipment as exempt under conditions. Registration threshold RM500k ([ParcelDaily](https://parceldaily.com/blog/do-businesses-pay-sst-on-delivery-services/)) (verify against RMCD).
- **MyInvois.** Phased since Aug 2024. In late Aug 2026 the exemption threshold was **raised from RM1m to RM3m** ([Bernama](https://www.bernama.com/en/region/news.php?id=2601063); [NST](https://www.nst.com.my/amp/news/nation/2025/06/1226501/lhdn-e-invoice-implementation-revenue-below-rm5mil-deferred-jan-1-2026); [Fiscal-Requirements](https://www.fiscal-requirements.com/news/4986-malaysia-postpones-mandatory-e-invoicing-to-2027)). Each validated e-invoice gets a UIN and a **QR code**. Penalties are RM200–20,000 per offence (verify).
- **Engine.** SST is "one tax per line" (service tax 6%/8% or sales tax), not VAT-style input credit. MyInvois API adapter. Consolidated B2C e-invoice monthly (verify).

#### Africa and Middle East

**Kenya.**

- **Rate.** VAT 16%.
- **eTIMS.** Since the Finance Act 2023, **all businesses**, including those not VAT-registered, must issue invoices through **eTIMS**. A missing e-invoice can cost a penalty of 2× the tax ([ACHI Systems](https://achisystems.co.ke/directory/etims-tims-compliance-requirements-in-kenya/); [Kintsugi](https://trykintsugi.com/sales-tax-guides/africa/kenya)).
- **Transit haulage.** International transportation services remain zero-rated on KRA's list ([Kenyans.co.ke](https://www.kenyans.co.ke/news/125329-kra-releases-updated-list-vat-exempt-zero-rated-goods)). However, a 2021 **High Court decision held road transport of transit goods taxable** ([EY](https://taxnews.ey.com/news/2021-0392); [Global Law Experts](https://globallawexperts.com/kenyas-court-appeal-rules-exportlogistics-services/)). Treat transit haulage as **S (16%) by default, Z only with adviser sign-off** (verify).
- **Engine.** eTIMS integration (OSCU/VSCU or approved integrator), KRA PIN, CU invoice number + QR, VAT withholding 2% by agents (verify).

**Nigeria.**

- **Rate.** The Nigeria Tax Act 2025 and Nigeria Tax Administration Act 2025 apply from 1 Jan 2026. VAT stays at **7.5%** ([Forvis Mazars](https://www.forvismazars.com/ng/en/services/tax/nigeria-s-tax-reform-in-focus/2025-nta-vat-key-business-changes)).
- **E-invoicing (NRS MBS).** Large taxpayers went live in Nov 2025. Medium (₦1–5bn) went live **1 Jul 2026**, with enforcement from Jan 2027. Emerging (< ₦1bn) go live **1 Jul 2027**, with enforcement from Jan 2028 ([Sovos](https://sovos.com/regulatory-updates/vat/nigeria-nrs-publishes-e-invoicing-timeline/); [TechCabal](https://techcabal.com/2026/04/16/all-you-need-to-know-about-e-invoicing-in-nigeria/); [SSL](https://sharedserviceslink.com/news/nigeria-enters-next-e-invoicing-phase-on-1st-july)).
- **Penalties.** Reported ₦200k per invoice + 100% of VAT due. An invoice without an IRN cannot support input VAT (verify).
- **WHT.** Deduction at Source Regulations 2024: **2% resident / 5% non-resident** for services not specifically listed, which is probably where haulage falls (inference) ([PwC NG](https://pwc.com/ng/en/assets/pdf/highlights-of-the-deduction-at-source-regulations-2024.pdf); [BusinessDay transport review](https://businessday.ng/news/legal-business/article/what-transportation-stakeholders-must-know-a-review-of-the-provisions-of-nigerias-tax-reform-acts-2025/)) (verify).

**South Africa.**

- **Rate and threshold.** VAT **15%** (proposed increases reversed). Compulsory registration threshold **R2.3m from 1 Apr 2026** (voluntary R120k) ([KPMG](https://kpmg.com/us/en/taxnewsflash/news/2026/02/south-africa-increases-vat-registration-thresholds-carbon-taxes.html); [CDH](https://www.cliffedekkerhofmeyr.com/en/news/publications/2026/South-Africa/Tax-Exchange-Control/tax-and-exchange-control-alert-19-march-VAT-threshold-increased-Should-SMEs-remain-registered)).
- **E-invoicing proposal.** SARS's **VAT Modernisation Consultation Paper (17 Aug 2026)** proposes a decentralised 5-corner CTC model ("Digital VAT Model"): pilot, then voluntary, then mandatory, large B2B first. Comments are due **16 Oct 2026**. Mandate dates are not set; sources say anything from 2028 to 2030+ ([SARS](https://www.sars.gov.za/vat-modernisation-consultation-paper-august-2026/); [KPMG](https://kpmg.com/us/en/taxnewsflash/news/2026/08/tnf-south-africa-public-consultation-on-e-invoicing-and-digital-reporting.html); [VATCalc](https://vatcalc.com/south-africa/south-africa-vat-digital-reporting-consultation)).
- **Transport.** SARS has a draft interpretation note on the VAT treatment of transport services ([SARS draft IN](https://www.sars.gov.za/lapd-lprep-draft-2014-22-draft-in-vat-treatment-supply-transport-services)) (verify the final version).
- **Engine.** Full tax invoice above R5,000 (buyer name, address and VAT number); abbreviated invoice up to R5,000 (verify).

**Egypt.**

- **Rates.** VAT 14% (Law 67/2016), 5% for machinery. Exports of goods and qualifying services are 0% ([PwC tax summaries](https://taxsummaries.pwc.com/Egypt/Corporate/Other-taxes); [Kintsugi](https://trykintsugi.com/sales-tax-guides/middle-east/egypt.md)). Law 3/2022 exempted **freight for imported beans, grains and salt** ([PwC 2022](https://pwc.com/m1/en/tax/documents/2022/egypt-issuance-of-law-number-three-to-update-vat-and-stamp-tax-laws.pdf)). The general domestic-freight treatment was not confirmed (verify).
- **E-invoicing.** Mandatory **ETA clearance** for all registered taxpayers. **E-receipts** for B2C are rolling out in waves (Resolution 405/2024). Records are kept **5 years** and the archive must be **stored in Egypt** ([VAT IT](https://vatit.com/e-invoicing-guide/egypt/); [ClearTax EG](https://cleartax.com/eg/en/e-invoicing-egypt)).
- **Engine.** ETA JSON with digital signature (HSM/e-seal), activity code, GS1/EGS item codes, Egypt data-residency flag.

### 2.5 Freight-specific line types (all countries)

Tax rules key on the **line's service class**, not on free text. Recommended classes:

| Service class | Typical treatment | Notes |
|---|---|---|
| `FREIGHT_ROAD` | Main supply; country rule | India GTA vs non-GTA; leg and role flags |
| `VEHICLE_HIRE_WITH_DRIVER` | Often the same as freight (UK) or different (India SAC 9966) | Country decides |
| `VEHICLE_HIRE_WITHOUT_DRIVER` | Leasing/rental of goods | UK: not freight; India 9966 |
| `LOADING_UNLOADING`, `DETENTION`, `DEMURRAGE`, `FUEL_SURCHARGE`, `PACKING` | **Ancillary: follows the principal supply** when billed with it (composite supply) | Flag `follows_principal = true` (verify per country) |
| `TOLL_RECHARGE` | Usually part of the freight consideration, so it follows the principal | Not a disbursement unless paid in the customer's name |
| `DISBURSEMENT` (pure agent) | **O: outside scope** | India Rule 33; EU Art. 79(c); UAE disbursement rules. Needs evidence |
| `DAMAGE_CLAIM`, `PENALTY_COMPENSATION` | Usually O | Verify per country |
| `WAREHOUSING`, `CUSTOMS_CLEARANCE` | Separate service rules | |
| `ASSET_SALE` (old truck), `SCRAP_SALE` | Goods; standard rate | India: may need IRN even for a GTA |
| `LATE_PAYMENT_INTEREST` | Often exempt or outside scope | Verify |

---

## 3. Part B: how leading systems model tax

### 3.1 Product-by-product

| Product | Core model | What we copy |
|---|---|---|
| **Odoo** | `account.tax` records with: amount type (percent, fixed, "percentage of price tax included", **group**); **Included in price**; **Affects base of subsequent taxes**; **Base affected by preceding taxes**; sequence; tax group (for display); **repartition lines** (invoice vs refund, each with account and **tax grid tags** that feed return boxes); tax exigibility (on invoice or on payment, i.e. cash basis). **Fiscal positions** map default taxes to other taxes, and accounts to other accounts, by customer type or country (e.g. foreign customer 15% becomes 0%). Company setting: round per line or round globally | Groups, compound flags, the order of application, fiscal positions, repartition with return tags, the cash-basis flag, the rounding setting ([Odoo tax computation](https://www.odoo.com/documentation/master/applications/finance/accounting/taxes/tax_computation.html); [Odoo 16 taxes](https://www.odoo.com/documentation/16.0/applications/finance/accounting/taxes.html); [Odoo fiscal positions](https://www.odoo.com/documentation/user/12.0/accounting/others/taxes/application.html)) |
| **Zoho Books** | Taxes; **tax groups** (independent taxes on the same base, summed); **compound tax** (a tax on top of another, e.g. older Canadian PST on GST); **exemption reasons** per customer or item, shown on the invoice. India edition: GST treatment of the customer (registered, unregistered, consumer, overseas, SEZ, etc.), place of supply, reverse-charge flag, TDS/TCS | Explicit difference between group and compound; mandatory exemption reason; GST treatment on the party ([Zoho: compound vs group](https://www.zoho.com/en-de/books/kb/taxes/diff-btwn-compound-tax-and-tax-group.md); [Zoho Books taxes (CA)](https://www.zoho.com/ca/books/help/settings/taxes.html)) |
| **QuickBooks Online** | US: **automated sales tax** from ship-to address + product category, with agencies and combined rates. Elsewhere: tax codes containing one or more rates, plus agency mapping for returns (verify) | US is outsourced to a rate service; tax agency is a first-class object |
| **Xero** | **Tax rates built from components** (each with its own rate, optional compound flag); a **tax type** per rate drives return boxes (output, input, exempt, zero-rated, EC, reverse charge); line amounts "Tax exclusive / Tax inclusive / No tax" (verify details) | Components + report-box type on every rate; three-way inclusive switch on the document |
| **Avalara AvaTax** | **Transaction API**: send addresses (ship from/to), lines with **ItemCode + TaxCode** (8-character codes, e.g. `FR…` for freight, where FR020100 and FR020400 behave differently by state), customer code, **exemption certificates** and **entity use codes**. AvaTax returns jurisdiction-level tax lines; "commit" feeds returns. Nexus set per company | Our engine treats an external calculator as one "regime provider" and stores what it returns; freight tax code per line; exemption certificates linked to the customer ([Zenskar on AvaTax codes](https://docs.zenskar.com/docs/avatax); [Educative item tax codes](https://www.educative.io/courses/calculating-sales-tax-using-avalara-avatax-api-in-python/item-tax-codes); [EPI users: freight codes](https://www.epiusers.help/t/freight-and-non-taxable-parts-with-avalara-and-streamlines-salestax-states/124947)) |
| **Stripe Tax** | **Product tax codes** (`txcd_…`) that are the same everywhere while the treatment differs by location; a preset default code and a separate **shipping** code; **tax_behavior inclusive/exclusive set on the price and immutable**; collection only where the merchant has **registrations**; results carry a taxability reason (reverse charge, not collecting, exempt, etc.) | A global **service class** independent of country; registrations drive whether we charge at all; a reason code stored on every tax line ([Stripe: tax codes and tax behavior](https://docs.stripe.com/tax/products-prices-tax-codes-tax-behavior)) |
| **SAP Business One** | Tax codes / VAT groups with **rate rows effective from a date**; determination from business partner, item and ship-to; **withholding tax codes are a separate master** (base = net or gross, deducted on invoice or payment); India: determination table by state, HSN and BP (verify) | Dated rate rows; WHT as a separate master with base type and timing |
| **Tally Prime** | GST details set at company, ledger, stock group or stock item level, with **"Applicable from" rate history**; HSN/SAC; nature of transaction (local, interstate, export, SEZ, RCM); precedence (transaction > item > group > ledger > company); TDS "nature of payment" with section, rate and thresholds | Precedence chain for defaults; rate history; nature-of-transaction label shown to accountants (Indian users expect it) |

### 3.2 Design patterns, one by one

1. **Tax codes vs tax rates vs effective dates.** A *tax code* is a stable meaning, for example "UAE VAT standard" or "India GST 18% intra-state". *Rates* are child rows with `effective_from` / `effective_to`. Never edit a rate in place; close it and add a new row. Calculation uses the rate valid on the **tax point date**. Examples: India GST 2.0 on 22 Sep 2025, NS HST cut on 1 Apr 2025, Salik VAT on 1 Jun 2026. Credit notes reuse the **original invoice's** rate snapshot.
2. **Tax groups (multi-component).** One code can expand into components applied to the **same base**, for example India 5% = CGST 2.5% + SGST 2.5%, or Canada BC = GST 5% + PST 7%. Each component posts to its own ledger and return box.
3. **Compound or cascading tax.** A component whose base includes earlier components, for example historic PEI/QC compounding or some cess designs. Odoo uses `include_base_amount` and `is_base_affected`; Zoho uses its compound flag. Keep the flag even though few current regimes need it.
4. **Cess and fixed-amount taxes.** Component `rate_type` can be `percent`, `per_unit` (amount per unit, kg or litre) or `percent_plus_per_unit`. Examples: Italian stamp duty, India cess history.
5. **Inclusive vs exclusive prices.** This is a document-level setting, overridable per line. For inclusive prices, back-calculate: `net = gross / (1 + Σ non-compound rates)`, with compound and fixed parts handled in order (Odoo's "percentage of price tax included" example: 1,000 inclusive at 10% gives 909.09 + 90.91). Fix the mode per price list, as Stripe does, so inclusive/exclusive doesn't flip silently.
6. **Rounding.** Two different things:
   - **Tax rounding:** per line, or per document per tax category/rate. EN 16931 and Peppol compute VAT per category on the summed net, so "per document" is safest for e-invoices. Spread the rounding difference back to lines so that posting by trip, truck or branch still balances.
   - **Cash rounding:** for example the India "Round off" line to the nearest rupee, or 0.05 rounding. Keep it as a separate non-tax line.

   Settings per regime: precision (2 decimals; 3 for OMR, BHD and KWD), method (half-up by default), mode.
7. **Tax categories and e-invoice codes.** Every tax code carries a **UNCL5305 category** and, for E/Z/AE/O/G/K, an **exemption reason code and text**. Use VATEX for the EU/Peppol, VATEX-SA for KSA, Natura N-codes for Italy, and the India supply type (B2B/SEZWP/SEZWOP/EXPWP/EXPWOP/DEXP) plus RCM flag. The same code prints the legal wording on the PDF, for example "Reverse charge" or "Tax payable on reverse charge: Yes".
8. **Place of supply and jurisdiction.** This is a per-regime **resolver** in code, not table data, because the rules are logic. Resolvers needed:
   - India: s.10/12/13 IGST, intra- vs inter-state.
   - EU: Arts. 44/49/50.
   - UK.
   - GCC: domestic vs international; principal vs subcontractor.
   - Canada: province.
   - US: delegate to the provider.
   - Generic: seller country = buyer country, otherwise export.

   Output: `place_of_supply_jurisdiction`, `relation` (same state / other state / same country / cross-border), `b2b|b2c`.
9. **Reverse charge.** The supplier invoice shows **0 tax charged** with an AE (or India RCM) code and a note. The engine still computes the **notional tax** (for GSTR-1 RCM tables, EC Sales List and the customer's view). On purchases, **self-assessment** posts output VAT payable and input VAT recoverable in one step, with no self-invoice in UAE from 2026. Also handle domestic RCM, such as an India GTA bill received by our company.
10. **Withholding (TDS/TCS/WHT/VDS) is separate from VAT.** It does not change the invoice tax. It changes the **amount paid**. Model it as `withholding_codes`, each with:
    - base type (net of GST or gross; India TDS is on the amount excluding GST when GST is shown separately (verify));
    - trigger (on invoice, or on payment, whichever is earlier);
    - thresholds (single payment and yearly aggregate per payee);
    - payee-type rates (individual/HUF vs others; ATL filer vs non-filer; VAT-registered vs not);
    - certificate overrides (India s.393(4) declaration, lower-deduction certificate).

    Sales side: track "TDS receivable" when the customer deducts. Purchase side: compute TDS payable to sub-hired truck owners.
11. **Tax on advances.** Some regimes tax at the **earlier of invoice or payment**: India services, EU Art. 65, UAE/KSA date-of-supply rules. Receiving an advance can therefore create a **receipt voucher with tax**, which is reversed when the final invoice is adjusted. Make it a per-regime flag `tax_on_advance`.
12. **Multi-currency.** Store document currency, local (tax) currency, rate, rate source and rate date. The rule differs by country: EU Art. 91 (ECB or national bank), Poland NBP rate of the previous working day, UAE CBUAE, KSA SAMA (verify each). Store tax amounts in **both currencies on every tax line**. Print "VAT in AED/SAR/GBP/EUR" when the document currency differs (EU Art. 230; UAE; KSA; UK).
13. **Commodity codes.** Service class → country code mapping: India SAC 9965/996511 and 9966; Malaysia classification codes; Egypt GS1/EGS; UNSPSC for Peppol where wanted. Use dated rows.
14. **Fiscal positions (mapping by customer type).** A small **override layer** after rule matching: for example "SEZ unit → zero-rated with LUT", "Diplomatic → exempt", "Export customer → Z". Rules cover most needs. Fiscal positions are for tenant-level exceptions without code.
15. **Exemption certificates and evidence.** Store certificates with scope, validity and file: US resale or exemption, India TDS declaration, Kenya exemption. Store **zero-rating evidence** per trip: CMR or bill of lading, exit/customs document, border-crossing proof, shipper's continuous-movement declaration (Canada). Block posting a Z/exempt line if required evidence is missing, with a role-based override.
16. **Return-box mapping.** Each tax code component and direction (sale or purchase; invoice or credit note) maps to **return boxes with sign and amount kind** (base or tax), with effective dates: Odoo tax grids, Xero tax types. Examples: GSTR-1 tables and GSTR-3B 3.1(d); UAE VAT201 box per emirate; KSA VAT return; UK VAT100 boxes 1–9; EC Sales List.
17. **Audit trail.** Store a calculation **trace** (inputs, matched rule id, rate ids, rounding steps, engine version) on each document. Tax lines are immutable once posted; changes only via credit or debit note. The general append-only audit log (India Companies Act) also covers tax master changes.

---

## 4. Recommended data model (Laravel/MySQL)

**Conventions.**

- Amounts are `DECIMAL(19,4)`, rates `DECIMAL(9,6)` and exchange rates `DECIMAL(19,8)`.
- Every master has `effective_from`/`effective_to` where relevant, `created_by`, timestamps and soft-delete **only for masters, never for posted documents**.
- `tenant_id` sits on tenant-owned rows. Global presets have `tenant_id = NULL`; tenants can clone and override them.
- Use PHP backed enums for categories and types.

### 4.1 Masters (global presets, overridable per tenant)

| Table | Key columns | Notes |
|---|---|---|
| `countries` | `iso2` PK, `name`, `currency_code`, `currency_decimals`, `default_regime_code`, `fiscal_year_start_mmdd`, `calendar` (gregorian / bikram_sambat / hijri-display), `data_residency_required` | Egypt archive in-country; India company data in India |
| `tax_jurisdictions` | `id`, `country_iso2`, `parent_id`, `level` (country/state/province/emirate/county/city), `code` (e.g. `IN-29`, `CA-ON`, `AE-DU`), `gst_state_code`, `name` | Needed for India states, Canadian provinces, UAE emirate return boxes, Pakistan provinces |
| `tax_regimes` | `id`, `code` (`IN_GST`, `AE_VAT`, `SA_VAT`, `EU_VAT_DE`, `CA_GST_HST`, `US_SALES_EXT`, `PK_ST_SVC_SINDH` …), `country_iso2`, `jurisdiction_id`, `tax_display_name` (GST/VAT/SST/HST), `pos_resolver` (PHP class key), `calc_provider` (`internal` / `avalara` / `stripe`), `rounding_mode` (`per_line` / `per_document_category`), `rounding_precision`, `rounding_method`, `tax_on_advance` bool, `default_price_mode` (incl/excl), `simplified_invoice_limit`, `invoice_issue_days`, `e_invoice_scheme` (IRP, PINT_AE, ZATCA, FAWTARA, KSEF, SDI, PEPPOL_BIS, XRECHNUNG, FR_PA, VERIFACTU, MYINVOIS, INVOICENOW, FBR_DI, ETIMS, NRS_MBS, ETA, null), `retention_years`, `reg_threshold_amount`, `reg_threshold_currency`, `reg_threshold_rule`, `effective_from/to`, `notes_ref` | One country can have several regimes (Pakistan; Canada federal + provincial; Spain IGIC) |
| `tax_types` | `id`, `regime_id`, `code` (CGST, SGST, UTGST, IGST, CESS, VAT, GST, HST, PST, QST, SST_SVC, SST_SALES, SSCL, STAMP), `jurisdiction_level`, `gl_output_account_id`, `gl_input_account_id`, `gl_rcm_payable_account_id`, `gl_rcm_input_account_id` | Atomic tax kinds; where money goes |
| `tax_codes` | `id`, `regime_id`, `code` (`IN_GST18_INTRA`, `AE_S5`, `AE_Z_INTL_TRANSPORT`, `EU_AE_SERVICES`, `SA_Z_34_1` …), `name`, `is_group` bool, `category` (S/Z/E/AE/K/G/O/L/M/B), `reverse_charge` bool, `self_assess_on_purchase` bool, `exemption_reason_code` (VATEX…, Natura…), `legal_text` (translatable JSON), `direction` (sale/purchase/both), `input_recoverable_pct` (default 100), `requires_evidence` JSON (e.g. `["cmr","exit_doc"]`), `print_label`, `active` | What a document line references |
| `tax_rates` | `id`, `tax_code_id` (atomic code) **or** `tax_type_id`, `jurisdiction_id` NULL, `rate_type` (percent / per_unit / percent_plus_unit), `rate`, `unit_amount`, `unit_code`, `effective_from`, `effective_to`, `legal_reference` (e.g. "Notif. 9/2025-CT(R)"), `source_url` | Dated; never edited after use |
| `tax_group_members` | `group_code_id`, `member_code_id`, `sequence`, `base_includes_previous` bool (compound), `base_pct` (default 100) | CGST+SGST; GST+PST; GST+QST |
| `service_classes` | `code` (FREIGHT_ROAD, DETENTION, DISBURSEMENT …), `name`, `follows_principal` bool, `default_category_hint` | Global list (§2.5) |
| `commodity_codes` | `id`, `scheme` (HSN, SAC, UNSPSC, EGS, MY_CLASS, AVALARA, STRIPE_TXCD), `code`, `description`, `country_iso2`, `effective_from/to` | |
| `service_class_codes` | `service_class_code`, `country_iso2`, `commodity_code_id` | e.g. FREIGHT_ROAD + IN → SAC 996511 |
| `tax_rules` | `id`, `regime_id`, `priority`, `name`; **condition columns (NULL = any):** `service_class`, `seller_registration_type` (e.g. `GTA_RCM`, `GTA_FCM5`, `GTA_FCM18`, `NON_GTA`, `REGULAR`, `COMPOSITION`), `buyer_tax_status` (registered/unregistered/consumer/government/SEZ/overseas/exempt_org), `buyer_category` (factory, body_corporate, partnership …), `supply_relation` (same_state/other_state/domestic/export/import/intra_eu/cross_border), `transport_leg` (domestic/international/domestic_leg_of_intl), `carrier_role` (principal/subcontractor), `goods_category` (agri_produce, foodgrain, relief …), `origin_country`, `destination_country`, `pos_jurisdiction_id`, `min_amount`, `max_amount`, `extra_conditions` JSON; **outcome:** `tax_code_id`, `legal_text_override`, `evidence_override` JSON, `effective_from/to`, `tenant_id` NULL | Most-specific match wins, then priority. Keep conditions as columns for indexing and readability |
| `fiscal_positions` | `id`, `tenant_id`, `regime_id`, `name`, `auto_apply` conditions JSON (buyer status, country group) | Tenant-level exceptions |
| `fiscal_position_maps` | `fiscal_position_id`, `from_tax_code_id`, `to_tax_code_id`, `from_account_id`, `to_account_id` | |
| `withholding_codes` | `id`, `country_iso2`, `code` (`IN_TDS_393_1_CONTRACT`, `NP_TDS_TRANSPORT`, `PK_153_1B_SVC`, `NG_WHT_SVC`, `BD_VDS`, `KE_VAT_WH`, `IN_GST_TDS_51`), `section_new`, `section_old`, `name`, `kind` (income_tax / vat_withholding / retention), `base_type` (net / gross / vat_amount), `trigger` (invoice / payment / earlier_of), `threshold_single`, `threshold_aggregate`, `aggregate_period` (FY), `return_form`, `gl_payable_account_id`, `gl_receivable_account_id` | Separate from VAT |
| `withholding_rates` | `withholding_code_id`, `payee_type` (individual_huf / company / any), `payee_flag` (pan_available / atl_filer / vat_registered …), `rate`, `effective_from/to` | |
| `return_box_mappings` | `id`, `regime_id`, `return_form` (GSTR1, GSTR3B, AE_VAT201, SA_VAT, UK_VAT100, EU_ESL …), `box_code`, `tax_code_id` or `category`, `tax_type_id` NULL, `jurisdiction_id` NULL (e.g. emirate), `doc_kind` (invoice/credit_note/debit_note/receipt_advance), `direction`, `amount_kind` (base/tax), `sign`, `effective_from/to` | Drives return exports |
| `exchange_rates` | `currency_from`, `currency_to`, `rate_date`, `rate`, `source` (RBI, CBUAE, SAMA, ECB, NBP, manual) | Per-regime rule picks source and date |

### 4.2 Party and company tax setup (tenant data)

| Table | Key columns |
|---|---|
| `tax_registrations` (our company) | `tenant_id`, `company_id`, `regime_id`, `jurisdiction_id`, `registration_number` (GSTIN/TRN/VAT ID/NTN-STRN), `registration_type` (see rules), `effective_from/to`, `option_year` + `option_ack_ref` (e.g. India Annexure V), `turnover_band`, `e_invoice_applicable` bool, `e_invoice_go_live`, `e_invoice_provider_id`, `simplified_allowed` |
| `party_tax_profiles` | `party_id`, `country_iso2`, `jurisdiction_id`, `tax_id`, `tax_id_validated_at` (VIES/GST portal), `tax_status`, `buyer_category`, `entity_use_code`, `pan` / `tin`, `atl_status`, `vat_registered` bool, `default_fiscal_position_id`, `peppol_participant_id`, `national_address` JSON (KSA) |
| `tax_certificates` | `party_id`, `regime_id` or `withholding_code_id`, `type` (TDS_393_4_DECL, LOWER_DEDUCTION, RESALE, EXEMPT_ORG, LUT, SEZ_ENDORSEMENT), `number`, `valid_from`, `valid_to`, `scope` JSON, `file_id`, `status` |
| `trip_tax_facts` (or columns on consignment/trip) | `trip_id`, `transport_leg`, `carrier_role`, `origin_country`, `destination_country`, `origin_jurisdiction_id`, `destination_jurisdiction_id`, `goods_category`, `evidence` JSON (CMR no., BoL no., exit doc, border crossing time, shipper declaration file) |

### 4.3 Document side

| Table | Key columns |
|---|---|
| `invoice_lines` (existing) + tax fields | `service_class`, `commodity_code_id`, `qty`, `unit`, `unit_price`, `discount`, `price_includes_tax`, `line_net` (computed), `trip_id`, `truck_id`, `branch_id`, `tax_code_id` (resolved), `tax_override_reason` |
| `document_tax_lines` | `id`, `document_type`, `document_id`, `document_line_id` NULL (NULL = document-level category total), `tax_code_id`, `tax_type_id`, `tax_rate_id` (snapshot), `jurisdiction_id`, `category`, `exemption_reason_code`, `rate`, `base_amount_doc`, `tax_amount_doc`, `base_amount_local`, `tax_amount_local`, `exchange_rate`, `rate_source`, `is_reverse_charge`, `is_self_assessed`, `recoverable_amount`, `non_recoverable_amount`, `rounding_adjustment`, `rule_id`, `return_tags` JSON (snapshot), `calc_version`, `posted_at` |
| `document_withholding_lines` | `document_type`, `document_id`, `withholding_code_id`, `rate`, `base_amount`, `amount`, `certificate_id`, `period`, `deposit_ref` (challan / CPR), `status` (expected / deducted / certified) |
| `document_tax_traces` | `document_id`, `inputs` JSON, `steps` JSON, `engine_version`, `created_at` (append-only) |
| `number_series` | `tenant_id`, `registration_id`, `doc_type`, `fiscal_year`, `prefix`, `next_no`, `max_length` (India 16), `allowed_chars_regex`, `gapless` bool (KSA ICV, Poland, Italy) |
| `e_invoice_submissions` | `document_id`, `scheme`, `provider` (ASP/GSP/PA/APP/direct), `payload_hash`, `prev_hash` (KSA PIH, Verifactu chain), `counter` (KSA ICV), `status` (draft/queued/submitted/cleared/reported/rejected/cancelled), `external_id` (IRN, UUID, KSeF no., SDI id, MyInvois UIN, CU no.), `qr_payload`, `signed_xml_file_id`, `submitted_at`, `cleared_at`, `cancel_deadline_at`, `response` JSON, `attempts` |
| `e_invoice_providers` | `tenant_id`, `scheme`, `vendor` (e.g. ASP name), `credentials_ref` (secret store), `environment` (sandbox/prod) |

---

## 5. Calculation algorithm

### 5.1 Steps

1. **Collect context.**
   - Document type; issue date; supply date or period; **tax point date**, computed per regime (earliest of invoice, supply and payment where the regime says so).
   - Seller registration for the regime; buyer party profile.
   - Trip facts (legs, role, origin, destination, evidence); currency.
2. **Pick regime(s).** From the seller's registrations and the place where the supply is made. Then run the **"should we charge tax?" check**:
   - Seller not registered → every line O or E (bill of supply / no-tax invoice).
   - Show a **threshold monitor** warning when rolling turnover approaches `reg_threshold_amount`.
   - For India, a GTA with only RCM supplies may be unregistered (verify).
3. **Resolve place of supply** with the regime's resolver. Output: POS jurisdiction, `supply_relation`, B2B/B2C.
4. **Classify each line**: service class, `follows_principal` (inherit the principal line's tax code), disbursement → O.
5. **Match rules.**
   - Filter `tax_rules` by regime, tenant (tenant rules first), and effective on the tax point date.
   - Keep rules whose non-NULL conditions all match. Choose the most specific (most non-NULL conditions), then the highest priority.
   - Outcome: `tax_code`. Record `rule_id`. **No match → stop with an error.** Never fall back silently to 0%.
6. **Apply overrides** in order: fiscal position map, then certificate (e.g. LUT → export Z), then manual override (permission + reason, logged).
7. **Check evidence.** If `tax_code.requires_evidence` is missing → block posting, or allow posting with a "pending evidence" flag per tenant setting.
8. **Expand and price.**
   - Group → components (sequence order); load the `tax_rate` for each component valid on the tax point date.
   - **Base:** exclusive price → `net = qty × unit_price − discount`. Inclusive price → back-solve net from gross across all components, honouring compound and per-unit parts.
   - **Component tax:** `base_i = net (+ Σ previous taxes if compound)`, `tax_i = base_i × rate_i (+ qty × unit_amount)`.
9. **Reverse charge.** If the code is RC: tax charged = 0. Still compute the **notional** amounts and flag `is_reverse_charge`. On purchase documents with `self_assess_on_purchase`, create paired output and input lines.
10. **Round.**
    - `per_line`: round each component per line.
    - `per_document_category`: group unrounded lines by (tax code component, rate, category), compute and round once, then spread the difference to the lines (largest remainder) for cost-centre posting.
    - Then apply cash rounding (separate line), if the tenant uses it.
11. **Convert currency.** Pick the rate per regime rule (source and date); compute `*_local` amounts; round in local currency precision.
12. **Withholding.** For each applicable `withholding_code`:
    - Check payee type and flags, certificates, and single plus aggregate thresholds (query year-to-date per payee).
    - Base type (net vs gross); create `document_withholding_lines`.
    - Sales side: record "expected TDS by customer". Purchase side: record "TDS payable" at the trigger event.
13. **Validate.**
    - Mandatory fields per regime (buyer tax ID for B2B, legal texts).
    - Simplified-invoice limits; numbering rules (length, characters, gapless).
    - Credit note references the original and uses its rate snapshot.
    - E-invoice schema pre-validation (EN 16931 business rules; ZATCA/KSeF/IRP rules).
14. **Persist.** Snapshot `document_tax_lines` (with `return_tags` copied from `return_box_mappings`), the trace, and the GL postings (output, input, RCM, non-recoverable to expense with trip/truck cost centres).
15. **E-invoice** (if applicable). Build the **canonical model** (EN 16931 semantic fields plus extensions), run the scheme adapter (IRP JSON, PINT-AE, ZATCA UBL, KSeF FA(3), FatturaPA, MyInvois, eTIMS, ETA, MBS, Peppol BIS, XRechnung/ZUGFeRD, Verifactu record), and submit via the provider.
    - **Clearance schemes:** the document is not "issued" until cleared. Store IRN, UUID or QR, then lock.
    - **Reporting schemes:** issue, then report within the SLA (KSA 24h).
16. **Returns.** Period close aggregates `document_tax_lines.return_tags` into return boxes and exports the files (GSTR-1 JSON, VAT201 sheet, VAT100 via MTD API, EC Sales List, etc.).

### 5.2 Worked examples (all amounts illustrative)

| Case | Inputs | Result |
|---|---|---|
| **India, GTA on FCM 18%, intra-state** | Freight ₹50,000; seller and buyer both in Karnataka; buyer is a company | Group `IN_GST18_INTRA`: CGST 9% ₹4,500 + SGST 9% ₹4,500, invoice ₹59,000. Customer deducts TDS 2% on ₹50,000 = ₹1,000 and pays ₹58,000. We book "TDS receivable ₹1,000" |
| **India, GTA default RCM 5%, inter-state** | Freight ₹50,000; buyer is a registered company in Maharashtra | Code `IN_GTA_RCM5_INTER` (category AE-like): tax charged ₹0; invoice says "Tax payable on reverse charge: Yes"; notional IGST 5% ₹2,500 reported in the GSTR-1 RCM table; customer self-assesses |
| **UAE, export trip by principal carrier** | Dubai → Riyadh, AED 8,000; `carrier_role = principal`; CMR + exit doc attached | `AE_Z_INTL_TRANSPORT` 0%. Evidence present, so the invoice posts |
| **UAE, subcontracted UAE leg** | Jebel Ali → Abu Dhabi leg of an export, AED 1,200; `carrier_role = subcontractor` | `AE_S5`: 5% = AED 60 (VATP040) |
| **Canada, Ontario domestic** | Toronto → Ottawa, CAD 2,000 | HST 13% = CAD 260 (verify freight place-of-supply rule) |
| **Canada, continuous outbound** | Toronto → port, export shipment, shipper declaration on file | Zero-rated (Sch VI Part VII) |
| **EU, cross-border B2B** | German haulier bills a French company for Lyon → Munich, €3,000; valid FR VAT ID | `EU_AE_SERVICES`: no German VAT; "Reverse charge"; both VAT IDs; listed in the German EC Sales List; customer self-assesses 20% in France |
| **Nepal, purchase from a sub-hired truck owner** | Hire NPR 100,000; payee VAT-registered | VAT 13% on the bill; TDS 1.5% = NPR 1,500 withheld (verify rate) |

---

## 6. Spec-ready summary

### 6.1 The tax engine must support

1. **Data-driven presets** per country (regimes, tax types, codes, dated rates, groups, rules, return boxes) that can be cloned and overridden per tenant. **No rates in code.**
2. **Effective-dated rates**, with selection by tax point date and credit notes reusing the original rates.
3. **Multi-component groups** (CGST+SGST / IGST, GST+PST/QST, VAT + levy), with an optional compound flag and per-unit or fixed components (cess, stamp duty).
4. **Inclusive and exclusive pricing**, back-calculation, and configurable rounding: per line or per document category, precision 2 or 3, half-up. Cash rounding is kept separate.
5. **Tax categories** S/Z/E/AE/K/G/O/L/M/B with exemption reason codes (VATEX, VATEX-SA, Italian Natura, India supply types) and printable legal texts.
6. **Place-of-supply resolvers** per regime: India intra/inter-state and s.12/13, EU Arts. 44/49/50, UK, GCC domestic vs international, Canada province, generic. A US external provider.
7. **Rule engine** keyed on freight facts: service class, transport leg, **carrier role (principal/subcontractor)**, origin/destination, buyer status and category, seller registration option (India GTA RCM/FCM5/FCM18/non-GTA), goods category.
8. **Reverse charge** on sales (notional tax and legend) and **self-assessment** on purchases. Domestic RCM (India GTA bills received).
9. **Withholding** as a separate engine: TDS/WHT/VDS/VAT-withholding/retention, with base type, trigger, single and annual thresholds, payee-type rates, certificates, and receivable/payable tracking. India old and new section numbers.
10. **Tax on advances** where the regime requires it (receipt voucher with tax, then adjustment).
11. **Multi-currency**: rate source and date per regime, tax shown in local currency, both amounts stored.
12. **Evidence gating** for zero-rated and exempt lines (CMR, BoL, exit doc, continuous-movement declaration, LUT).
13. **Registration-threshold monitor** (rolling 12 months + next 30 days for GCC), with the "should we charge tax?" check and a no-tax invoice (bill of supply) when the seller is unregistered or exempt.
14. **Return-box mapping** and exports (GSTR-1/3B, VAT201, KSA VAT, UK VAT100/MTD, EC Sales List), plus input-tax recoverability (full, partial, blocked; for example India FCM-5% ITC block).
15. **External calculator hook** (Avalara/Stripe Tax) for the US and other high-jurisdiction markets, storing the returned lines in the same tables.
16. **Immutable tax snapshots + calculation trace + append-only audit log.** Retention per country (longest applicable; India company 8 years).

### 6.2 The invoice module must support

1. **Document types:** tax invoice, simplified invoice, bill of supply / no-tax invoice, export invoice, credit note, debit note, receipt voucher (advance), refund voucher, self-billed invoice (configurable), proforma (non-tax).
2. **Number series** per registration, document type and fiscal year, with max length and allowed characters (India 16, `-` and `/`), **gapless** where needed (KSA ICV, Poland, Italy), and FY reset per country.
3. **Mandatory field sets** per regime: India Rule 46 (place of supply, HSN/SAC, RCM yes/no); UAE Art. 59; EU Art. 226; UK; KSA (Arabic, national address, QR); Canada amount tiers; Australia/NZ tiers; South Africa R5,000 tier.
4. **Multilingual templates:** Arabic/English (KSA, UAE, Oman, Bahrain, Egypt), French (France, Quebec), plus local-currency tax display and dual dates (Nepal BS, Hijri display optional).
5. **QR codes:** KSA (all), Malaysia, Pakistan, Kenya, Nigeria, India (IRN), Poland (KSeF copies), Spain (Verifactu 2027), Egypt (e-receipts).
6. **Freight references** on each invoice: LR / consignment note / CMR / e-way bill numbers, vehicle, origin to destination, trip id.
7. **Credit/debit notes** linked to the original invoice, with reason codes and deadlines (India 30 Nov of the next FY).
8. **E-invoice layer:** canonical EN 16931 model + scheme adapters + provider connectors; clearance vs reporting flows; retry queue; cancellation windows (India 24h); inbound e-invoice receipt for purchases (Peppol, UAE ASP, Germany XRechnung, France PA).
9. **PDF + structured XML/JSON** archived together for the retention period, with an in-country archive option (Egypt, India).

### 6.3 Country preset table (for the spec)

| Country | Tax name | Standard rate | Road-freight treatment | E-invoicing status/date (Oct 2026) | QR needed | Retention (yrs) |
|---|---|---|---|---|---|---|
| India | GST (CGST+SGST / IGST) | 18% (slabs 5/18/40) | GTA: 5% RCM default, or 5% FCM / 18% FCM by option; non-GTA exempt | IRN for AATO > ₹5 cr; **GTA freight exempt** | Only with IRN | 6 (GST); 8 (companies) |
| UAE | VAT | 5% | Domestic 5%; international 0% (subcontracted UAE leg 5%) | ASP/PINT-AE: **1 Jan 2027** (≥ AED 50m), **1 Jul 2027** (others) | No (verify) | 5 (verify) |
| Saudi Arabia | VAT | 15% | Domestic 15%; international 0% | FATOORA live; Wave 25 (> SAR 187.5k) by **1 Feb 2027** | Yes | 6 |
| Oman | VAT | 5% | Domestic 5%; international 0% (verify) | Fawtara pilot 2026; mandatory **1 Apr 2027** / **1 Oct 2027** (verify) | (verify) | 10 (verify) |
| Bahrain | VAT | 10% | Domestic 10%; international 0% (verify) | Not announced | No | 5 (verify) |
| Qatar | none | n/a | Out of scope | Draft law only | No | (verify) |
| Kuwait | none | n/a | Out of scope | None | No | (verify) |
| EU (general) | VAT | per state (≥15%) | Domestic standard; cross-border B2B reverse charge; export-linked exempt | ViDA: intra-EU B2B **1 Jul 2030** | Per state | per state |
| Germany | USt (VAT) | 19% | 19% domestic | Issue **2027** (> €800k) / **2028** all | No | 8 vouchers / 10 books (verify) |
| France | TVA (VAT) | 20% | 20% domestic | Receive all + large/ETI issue **1 Sep 2026**; SMEs **1 Sep 2027** (via PA) | No | 6 → 10 (verify) |
| Italy | IVA (VAT) | 22% | 22% domestic (verify); intl non-taxable | SDI clearance live | No | 10 |
| Spain | IVA (VAT) | 21% | 21% domestic | Verifactu **2027**; B2B order pending | Yes (Verifactu) | 4 tax / 6 commercial |
| Poland | VAT | 23% | 23% domestic | KSeF live **Feb/Apr 2026**; micro **Jan 2027** | Yes (copies) (verify) | 5 (verify) |
| Belgium | VAT | 21% | 21% domestic | Peppol B2B live **1 Jan 2026**; e-reporting **2028** | No | 10 (verify) |
| UK | VAT | 20% | Domestic 20%; international incl. UK leg 0% | B2B e-invoicing **Apr 2029** | No | 6 |
| USA | Sales tax (state/local) | varies (0–~10%) | Carrier freight mostly not taxed; varies by state | None | No | 3–7 (verify) |
| Canada | GST/HST (+PST/QST) | 5% GST; HST 13–15% | Taxable by province; continuous intl movement 0% | None | No | 6 |
| Australia | GST | 10% | Domestic 10%; international GST-free | Peppol voluntary | No | 5 |
| New Zealand | GST | 15% | Domestic 15%; intl 0% (domestic leg only if same supplier) | Peppol voluntary | No | 7 |
| Singapore | GST | 9% | Domestic 9%; international 0% | InvoiceNow: new voluntary registrants now; others **2028–2031** | No | 5 |
| Malaysia | SST (service tax) | 8% (logistics 6%) | Logistics 6%; international/transit exempt (verify) | MyInvois live; < RM3m exempt | Yes | 7 |
| Sri Lanka | VAT (+SSCL 2.5%) | 18% | (verify) | None (verify) | No (verify) | 5 (verify) |
| Bangladesh | VAT | 15% | Transport contractor 15% (verify) | None (EFD/SDC retail) | Retail devices | 6 (verify) |
| Nepal | VAT | 13% | In scope (verify); TDS 1.5%/2.5% | IRD billing software / CBMS (verify) | (verify) | 6 (verify) |
| Pakistan | Sales tax (federal goods; provincial services) | 18% goods; 15–16% services | Provincial; may be exempt or reduced (verify) | FBR Digital Invoicing (real time) | Yes | 6 (verify) |
| Kenya | VAT | 16% | 16%; intl transport 0%; transit haulage disputed | eTIMS live (all businesses) | Yes | 5 |
| Nigeria | VAT | 7.5% | 7.5% (verify) | MBS: large live; medium **Jul 2026**; small **Jul 2027** | Yes | 6 (verify) |
| South Africa | VAT | 15% | Domestic 15%; international 0% | Consultation; no date (≈2028–30) | No | 5 |
| Egypt | VAT | 14% | 14% (verify) | ETA clearance live; e-receipts rolling | Yes (e-receipts) | 5 (archive in Egypt) |

### 6.4 Build order suggestion (tax engine)

1. **Core engine + India + UAE** (already-researched packs): rules, dated rates, groups, RCM, TDS, evidence gating, returns, canonical invoice model.
2. **UAE ASP adapter** (needed by **1 Jan 2027** for tenants ≥ AED 50m; 1 Jul 2027 for the rest).
3. **KSA FATOORA adapter** (Wave 25 deadline **1 Feb 2027**), then Oman (Apr/Oct 2027) on the same Peppol/UBL base.
4. **EU/UK base** (reverse charge, VIES, EC Sales List, EN 16931/Peppol). Add country adapters only when a tenant needs one: Belgium Peppol now; Poland KSeF now; France PA now; Germany 2027; Spain 2027.
5. **Rest-of-world presets as data**, plus external calculator for the US. Integrate local CTC systems (Malaysia, Kenya, Pakistan, Nigeria, Egypt) only when a market is opened.

### 6.5 Must-verify list before each preset goes live

- **India.** Annexure V deadline; scope of the GTA e-invoice exemption; TDS base excluding GST; export of freight services to Nepal/Bangladesh.
- **UAE.** Article 59 fields; whether Arabic is required; retention years.
- **KSA.** Domestic road-freight rate; exact VATEX-SA codes; non-resident WHT on road freight.
- **Oman.** Decision 189/2026 dates; Fawtara model and provider rule; retention.
- **Bahrain, Qatar, Kuwait.** Status rechecked each quarter.
- **EU.** Each state's retention years, simplified-invoice limits, the France PA list, Germany 2027 postponement debate, Spain B2B order, Poland UD477.
- **Canada.** Freight place-of-supply rule; PST on freight in BC, SK and MB; Quebec language rules.
- **South Asia and Africa.** Domestic road-freight VAT status and withholding rates for every country listed with (verify) in Table A1.
