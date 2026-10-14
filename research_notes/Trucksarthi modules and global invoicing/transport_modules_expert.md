# Transport ledgers, documents and invoicing: what an experienced Indian transporter expects (as of 14 Oct 2026)

Written from the view of a long-time Indian road-transport operator and ERP
analyst. It covers the seven modules the founder requires (decision D13):

1. Driver Ledger
2. Client Ledger
3. Truck Ledger
4. Supplier Ledger
5. Vendor Ledger
6. Document Manager
7. Invoices & LR, with invoice templates and a template editor (D14)

**What this note does and does not do**
- It **extends** our blueprint (`docs/blueprint/03`, `06`, `07`). It does not repeat what is already planned there, such as trip settlement, automatic accounting entries, the GST/TDS basics and blocking dispatch on expired documents. Where a feature already exists in the blueprint, the table says "exists (03 §8)" and only adds what is missing.
- Another agent is studying Trucksarthi itself. This note covers the wider market and expert practice.

**How to read the Source column**
- `S1`…`S48` refer to the source list in section 1.
- **[Practice — unverified]** means the point comes from practitioner knowledge, not from a source found during this research. Check it with real transporters, or with a CA for tax points, before building.
- **(verify)** marks a legal or tax point that needs a CA or tax-agent check.

---

## Contents

1. Sources and limits
2. Features every ledger needs (shared rules)
3. Driver Ledger
4. Client Ledger
5. Truck Ledger
6. Supplier Ledger (market vehicles / lorry hire)
7. Vendor Ledger (goods and services we buy)
8. Document Manager
9. Invoices & LR
10. Terminology (including **Supplier vs Vendor**)
11. Document formats: field lists
12. Template-editor feature checklist
13. Spec-ready summary (what we must build), with data-model additions

---

## 1. Sources and limits

### 1.1 Limits of this research

- **Tools.** Only WebSearch was available, so search summaries were the only source. No pages were opened in full and no apps were installed. Most product claims come from app-store listings, vendor pages or help pages, so they are **vendor-stated**.
- **Products not found.** Searches returned nothing usable for **TransportBook, Truck Khata, Truck Trip Hisab, FleetHisab, VCS transport software or Logic ERP**.
  - "Fleetbooks" matched only a US product, Truckrs.io Fleet Books.
  - **BharatFleet** and **Marg** were not searched, because the search budget ran out.
  - These products may still exist, but their names did not index. Indian products that *were* found and used instead: **MyBilty, ONLINELR, AIM Transport, LogixERP, Fleetx Transporter ERP, Freight Tiger, FR8, Smart Bahi Khata, LocoKhata, BUSY (transport template)**.
- **Legal points.** These were taken from secondary sources unless an official page appears in the results (CBIC Rule 48, India Code for the Carriage by Road Act, the Tally help pages and the Income-tax FAQ). Everything about the new **Income-tax Act 2025** section numbers should be re-checked with a CA.
- **Weak areas.** Sources were thinnest on:
  - copy labels on LR books;
  - the Paid / To-Pay / TBB / FOC conventions;
  - the fields on lorry hire challans and money receipts;
  - fuel-pump credit statements.

  For these I relied on practice, and each such point is labelled.

### 1.2 Source list

| Code | Source | What it supports |
|---|---|---|
| S1 | MyBilty app listings: [chrome-stats](https://chrome-stats.com/d/com.my_bilty), [APKPure](https://apkpure.net/mybilty/com.my_bilty) | Party, Broker, Vehicle, Driver and **Supplier-wise** ledgers; Truck Hire Challan; Loading Slip; TDS declaration; credit/debit notes; multi-branch bilty book; WhatsApp/email sharing |
| S2 | ONLINELR: [app listing](https://iphoneaddict.fr/apps/economie-entreprise/online-lr-transport-lr-bilty.html), [YourStory profile](https://yourstory.com/companies/onlinelr) | Party-wise, **supplier-wise**, driver and broker ledgers; LR, Goods Receipt Note, Transit Memo, Loading Slip, Quotation, Payment Slip, Delivery Slip, Truck Hiring Note, credit/debit notes |
| S3 | Fleetx Transporter ERP: [product page](https://www.Fleetx.io/transporter-erp), [blog: unified platform](https://blog.fleetx.io/transporter-erp-a-unified-platform-for-fleet-operations), [blog: freight settlement](https://blog.fleetx.io/automating-freight-settlement-for-transporters) | **Market Vehicle Management** (hiring, advances, detention, balance payments); Sales Bills (freight bill, **supplementary bills**, pending payments); freight from rate charts; custom PDF printing; **Purchase Bills with vendor-wise tracking** (spares, batteries, tyres) |
| S4 | [Freight Tiger marketplace](https://freighttiger.com/marketplace) | Calls fleet owners who supply hired trucks **"truck suppliers"**; the platform pays them advance and balance |
| S5 | FR8 [partner halting terms](https://www.fr8.in/partnerHaltingTerms.html), [special halting terms](https://www.fr8.in/specialHaltingTerms.html), [truck-owner page](https://fr8.in/truck-owner) | Halting is free for the first 24 h, then a per-day slab rate by truck type; halting and loading/unloading are paid only if recorded on the POD; the shipper's shortage, damage, late-POD and late-delivery deductions are taken from the balance payment |
| S6 | [FR8 blog: Overview of the Lorry Receipt](https://fr8.in/blog/?p=1826) | LR number as the unique shipment ID; driver and consignee get copies of the LR |
| S7 | Carriage by Road Act 2007: [India Code PDF](https://www.indiacode.nic.in/bitstream/123456789/19858/1/the-carriage-by-road-act-2007.pdf), [s.9 goods receipt](https://www.advocatekhoj.com/library/bareacts/carriagebyroadact/9.php); [Carriage by Road Rules 2011](https://www.legitquest.com/act/carriage-by-road-rules-2011/8E26) | Goods forwarding note by the consignor, with declared value (s.8); **goods receipt in triplicate, original to consignor** (s.9); liability limit unless a higher risk rate is agreed (s.10/11); Rules: liability up to 10× freight, capped at declared value; goods receipt in Form 8 (one source) |
| S8 | [CBIC: CGST Rule 48](https://taxinformation.cbic.gov.in/content/html/tax_repository/gst/rules/cgst_rules/active/chapter6/rule48_v1.00.html) | Goods invoices are made in triplicate (Original for Recipient / Duplicate for Transporter / Triplicate for Supplier). **Services invoices are made in duplicate** (Original for Recipient / Duplicate for Supplier). |
| S9 | Tally help: [printing multiple copies](https://help.tallysolutions.com/printing-multi-account-reports/), [configure printing](https://help.tallysolutions.com/configure-printing-for-invoices-reports/) | Number of copies; copy type labels (Original, Duplicate, Triplicate, Quadruplicate, Extra Copy); GST-aware labels; title and subtitle |
| S10 | Tally: [bill-wise details](https://tallysolutions.com/tally/bill-wise-details-report-in-tally-prime/), [receivables ageing and reminders](https://tallysolutions.com/business-guides/how-to-track-receivables-aging-and-send-payment-reminders-in-tallyprime/); [Teachoo ageing](https://teachoo.com/753/231/Debtor-Aging-Report/category/Important-Tally-Reports-/) | New Ref / Agst Ref / Advance / On Account; credit period per party, so due dates are worked out automatically; ageing by bill date or by due date; custom ageing slabs |
| S11 | [Tally help: TDS on transport expenses](https://help.tallysolutions.com/tally/tds-on-transport-expenses/) | Transporter TDS rates; nil TDS when the transporter owns ≤10 goods carriages and gives a declaration with PAN; 20% when there is no PAN |
| S12 | TallyAtCloud: [truck operators](https://www.tallyatcloud.com/articles/how-truck-operators-and-transport-contractors-can-manage-accounts-gst-payments-easily), [freight billing](https://www.tallyatcloud.com/articles/freight-billing-software-explained-how-to-manage-transport-freight-charges-in-tallyprime) | Income by party, trip or vehicle; vehicle-wise fuel cost; cost centres by vehicle, route and branch; LR-based billing |
| S13 | [Vikash Amit Tech: custom Tally TDL transport modules](https://vikashamittech.odoo.com) | A market for TDL add-ons exists, because Tally does not do vehicle-wise P&L out of the box |
| S14 | Indian TMS vendor pages from the search results (vendor not named in the summary; for example [Techjockey: Truckers Adobe](https://www.techjockey.com/detail/truckersadobe), [IndiaMART: Sat Software](https://m.indiamart.com/sat-software-solution/other-services.html)) | GR types **TBB / TP / Paid**; GST set by who pays; manual GR numbers; **one challan covering many GRs**, with TDS, driver payments and shortage; hired-vehicle billing |
| S15 | [eresource 3GL ERP for transportation (SlideShare)](https://www.slideshare.net/slideshow/eresource-3gl-erp-erp-for-transportation-business-operation-module/55730374) | Freight memo with LR details, booking details, **advance details** and charge details; LR records hired-vehicle details; bills generated against LRs; receipted-LR entry with arrival and unloading times |
| S16 | [AIM Transport (Techjockey)](https://techjockey.com/detail/aim-transport) | Delivery and received challan management; ledger book |
| S17 | [LogixERP (SlideShare)](https://www.slideshare.net/slideshow/logistics-on-cloud/41632155) | Manifest; **Lorry Hire Sheet**; Trip Hire Sheet; vendor management |
| S18 | LawInsider freight clauses: [freight payment](https://www.lawinsider.com/clause/freight-payment/_1), [despatch documents](https://www.lawinsider.com/clause/despatch-documents/_2); [BHEL tender](https://tenders.bhel.com/sites/default/files/pg-28-29-1507875368.pdf) | "To Pay" (consignee pays at destination) vs "To be billed" (billed after delivery proof); freight bills paid 45–60 days after presentation with the LR/GR acknowledged by the consignee; TDS deducted at payment; shortage or damage holds payment; a **"Driver/Lorry/Destination" copy** |
| S19 | [Form 15J (Taxmann PDF)](https://support.taxmann.com/pdf/tds-forms/Form15J.pdf) | Old annual return of payments to truck owners: owner name, PAN, address, amount paid, **vehicle registration number** |
| S20 | [CAclubindia: transporter declaration](https://www.caclubindia.com/experts/transporter-declaration-form-2959954.asp), [TaxGuru](https://taxguru.in/?p=1039051), [Terra Insight on s.393](https://www.terra-insight.com/insights/tds-on-freight-transport-auto-component-section-194c-india/), [Income-tax FAQ on s.397](https://www.incometaxindia.gov.in/documents/d/guest/faqs-on-higher-rate-of-tds-under-section-397) | ≤10 goods carriages declaration under **s.393(4)** of the 2025 Act (Table Sl. 8, verify); **a fresh declaration each tax year**; stamp paper not required (disputed); higher no-PAN rate now under **s.397** |
| S21 | [TaxGuru: Finance Ministry on 43B(h)](https://taxguru.in/income-tax/finance-ministry-clarifies-section-43bh-45-day-msme-payment-rule.html), [BUSY: 43B(h)](https://busy.in/tds/section-43bh-msme-payment-rule-and-45-day-limit-explained/) | MSME rule: **15 days** with no written agreement, **45 days** maximum with one; now **s.37(2)(g)** of the 2025 Act; traders not covered |
| S22 | [CA Rajput: GTA](https://carajput.com/blog/taxability-of-gst-on-gta-services/), [Disytax GTA 2026](https://disytax.com/?p=24594) | GTA forward-charge invoice declaration (**Annexure III**); Annexure V option by **31 March** (deemed continuing); RCM reported in 3B Table 3.1(d) |
| S23 | Vahan/RC APIs: [Surepass](https://surepass.io/vahan-verification-api/), [Gridlines RC API](https://gridlines.io/products/rc-api), [Cashfree RC](https://www.cashfree.com/rc-verification), [Cashfree RC OCR docs](https://www.cashfree.com/docs/secure-id/ovd/vehicle-rc.md), [IDfy on RapidAPI](https://rapidapi.com/uk/organization/idfy) | RC lookup from the Vahan database: owner, chassis, insurance number or status, PUC, fuel; Gridlines lists **₹3.99 per call** and a variant with challans; Cashfree "Smart OCR" of RC images |
| S24 | [MoRTH SOP](https://morth.gov.in/sites/default/files/circulars_document/Standard_Operating_Procedure.pdf), [Cashfree DigiLocker API](https://cashfree.com/digilocker-api), [Gridlines DigiLocker use cases](https://gridlines.io/blogs/?p=5299) | DigiLocker and mParivahan DL/RC are valid as originals; only **licensed DigiLocker requesters** can pull documents, with user consent (OTP) |
| S25 | [VahanScan](https://apps.apple.com/app/id1450485590), [RichAutomate: WhatsApp for fleets](https://richautomate.in/blog/whatsapp-commercial-vehicle-fleet-telematics-amc-india-2026), [Odoo fleet documents with expiry](https://apps.odoo.com/apps/modules/16.0/fleet_documents_with_expiry), [Uffizio reminders](https://uffizio.com/wp-content/uploads/sites/2/2023/06/Fleet-Reminders.pdf), [Tribune: Punjab SMS alerts](https://www.tribuneindia.com/news/punjab/punjab-transport-department-to-introduce-sms-alerts-for-vehicle-owners) | Expiry reminders by push, SMS, email or WhatsApp; VahanScan alerts insurance 30 and 7 days ahead and PUC 14 and 3 days ahead; permit, fitness, insurance and PUC renewal reminders over the WhatsApp Business API |
| S26 | [DocuBot Fleet (GetApp)](https://www.getapp.com.au/software/2168248/docubot-fleet), [HyperVerge DL OCR](https://documentation.hyperverge.co/api-reference/india_api/OCR%20APIs/driving_licence_ocr_api) | OCR extraction of expiry dates, with a **human-review queue for low-confidence reads**; Indian DL OCR returns the expiry date with a confidence score |
| S27 | Vyapar: [customise invoice](https://vyaparapp.in/guides/how-to-customize-your-invoice-in-vyapar), [thermal printing](https://vyaparapp.in/videos/how-to-quickly-print-bills-with-thermal-printer), [connect printer](https://vyaparapp.in/guides/how-to-connect-my-printer-with-vyapar), [themes blog](https://vyaparapp.in/blog/professional-invoice-themes-in-vyapar/) | Separate set-ups for regular (A4/A5) and **thermal 2"/58 mm and 3"/80 mm** printers; themes; show or hide fields (HSN, MRP, unit); terms; payment mode; signatory; custom fields |
| S28 | Vyapar: [e-way bill video](https://vyaparapp.in/videos/how-to-generate-e-way-bill), [TaxOne e-way bill](https://taxone.vyapar.com/post/eway-bill-system-rules-process-everything) | Transport details on the invoice (transporter, vehicle, delivery date and place, distance) feed the e-way bill |
| S29 | Vyapar: [credit limit](https://vyaparapp.in/videos/set-credit-limit-for-customer), [payment reminders](https://vyaparapp.in/guides/how-to-send-payment-reminders-in-vyapar) | Credit limit per party, with a warning on the invoice; WhatsApp/SMS reminders, editable template, scheduled reminders |
| S30 | [Vyapar: bank details on invoice](https://vyaparapp.in/videos/add-bank-account-details-on-invoice), [GST Circular 156 on dynamic QR](https://gstcouncil.gov.in/sites/default/files/2024-06/156-12-2021_20gst_20circular.pdf) | Bank details and a **UPI QR for the exact pending amount**; after a part payment the QR may show the balance, with the cross-reference printed |
| S31 | myBillBook: [transport bill format](https://mybillbook.in/s/transport-bill-format), [custom invoice](https://mybillbook.in/s/how-to-create-custom-invoice-in-mybillbook/), [custom theme](https://knowledge.mybillbook.in/articles/3286289-how-to-set-up-custom-invoice-theme-in-mybillbook), [party statements](https://knowledge.mybillbook.in/articles/1989927-how-to-view-and-share-party-statements-on-mybillbook), [language](https://knowledge.mybillbook.in/articles/7601339-how-to-setchange-language-in-mybillbook-mobile-app) | Transport bill with freight, loading, unloading, fuel surcharge and detention lines, custom fields for vehicle and LR; 80+ themes; **prompt-based custom-theme editor**; add, remove, show or hide fields, logo, watermark, tagline; party statement PDF shared over WhatsApp/SMS; app in English, Hinglish, Hindi and Gujarati |
| S32 | Zoho: [Invoice: edit template](https://www.zoho.com/invoice/help/pdf-templates/edit-template.html), [Books: template language](https://www.zoho.com/in/books/kb/templates/customize-template-language.md), [Books Jan 2026 update](https://help.zoho.com/portal/en/community/topic/zoho-books-product-updates-january-2026) | Template name, orientation, font, background, paper (A5/A4/Letter), header/footer (first page or all pages), header image, colours, field font size and colour, column widths, "attention content"; label language per template; **bilingual Arabic templates (standard and compact) for GCC** |
| S33 | QuickBooks: [customise forms](https://quickbooks.intuit.com/learn-support/en-us/help-article/customize-forms/customize-invoices-estimates-sales-receipts-online/L57kKA6b6_US_en_US), [import custom form styles](https://quickbooks.intuit.com/learn-support/en-us/getting-started/import-custom-form-styles-for-invoices-or-estimates/00/263153), [Satva guide](https://satvasolutions.com/blog/customize-quickbooks-online-invoices) | Several templates and a default per form; Design / Content / Emails tabs; show or hide fields; import a Word layout; limits on custom fields and layout |
| S34 | Odoo add-ons: [Invoice Format Editor (Cybrosys)](https://apps.odoo.com/apps/modules/18.0/invoice_format_editor), [Invoice Custom Format](https://apps.odoo.com/apps/modules/18.0/invoice_custom_format), [ECOSIRE Invoice Layout Pro](https://ecosire.com/fr/apps/odoo/invoice-layout-pro) | Standard layouts (Light / Boxed / Bold / Striped) plus logo and paper format; add-ons for field positions, colours, hiding fields, bank details, **dynamic QR, signature/stamp upload**; QWeb edits can be lost on upgrade |
| S35 | [Swipe: invoices in Hindi](https://community.getswipe.in/t/how-to-create-invoices-bills-in-hindi/3123), [Tally multi-language printing](https://help.tallysolutions.com/docs/te9rel49/07_MULTI-LINGUAL_FEATURES/Display_Print_in_Multiple_Language.htm) | A "Language in PDF" setting translates labels, not data; data must be typed in the target language |
| S36 | [Velmont Crest: UAE bilingual e-invoices](https://velmontcrest.ae/insights/e-invoicing-uae-arabic-bilingual-format/) | Arabic on UAE tax invoices only on request (secondary source); RTL rework often needed in PDFs |
| S37 | [Patron Accounting: trip-wise profitability](https://www.patronaccounting.com/blog/trip-wise-profitability-logistics) | Direct trip costs vs per-km provisions for tyres and maintenance; fixed costs (insurance, EMI, road tax, permits, depreciation) spread per km |
| S38 | Trucksarthi store listing, as summarised in search ([chrome-stats](https://chrome-stats.com/d/id6758569173)) | Driver ledger (expenses, advances, settlements, balances), client ledger, invoicing, fuel and expenses, driver app |
| S39 | [Kladana: transport bill template](https://www.kladana.com/blog/templates/transport-bill/) | Bill number, date, LR/waybill reference on transport bills |
| S40 | [TransportOne glossary](https://transportone.getos1.com/en/article/glossary) | Detention for loading and unloading charged in time-bracket **slabs** |
| S41 | [BUSY transport template](https://busy.in/download/transport-bill-template/) | General accounting packages ship transport sample data (booking tracking, freight analysis), not a true transport module |
| S42 | US driver-settlement practice: [TruckLogics](https://www.trucklogics.com/driver-settlement-software), [TruckingOffice](https://help.truckingoffice.com/hc/en-us/articles/360000505883), [Truckpedia](https://truckpedia.io/guides/where-can-i-review-and-manage-trip-financials-in-truckpedia) | Recurring deductions; "deduct from driver pay" flag on an expense; payment items **read-only after settlement** |
| S43 | [Smart Bahi Khata](https://apkcombo.com/smart-bahi-khata/com.smartbahikhata.smart_bahikhata/), [LocoKhata](https://apkpure.com/locokhata/com.develop.khataapp) | Khata-style transporter logs (diesel, driver advance, tolls); a **driver-side khata app that shares reports with the transporter** |
| S44 | [Scribd: consignment note format](https://www.scribd.com/document/916030451/Consignment-Note-Format) | Copy printed as **"CONSIGNOR COPY – AT OWNER'S RISK"** |
| S45 | [YRC Canada BOL](https://my.yrc.com/national/pdf/Canada-BOL-3.2023.pdf) (benchmark only) | "Said to contain"; declared value or "NVD" (no value declared) limiting carrier liability |
| S46 | [GoFrugal: dynamic QR in print design](https://community.gofrugal.com/portal/en/kb/articles/creating-and-mapping-dynamic-qr-codes-in-sales-print-design) | QR placed in a print template as a mapped field carrying the amount and UPI ID |
| S47 | [Ohio fleet card reconciliation](https://dam.assets.ohio.gov/image/upload/das.ohio.gov/property-services/fleet%20management/OFMGUD0006_FLEET_CARD_RECONCILIATION_PROCESS_Rev_12_19.pdf), [ECOSIRE ERPNext petrol pump](https://ecosire.com/es/apps/erpnext/erpnext-petrol-pump-fuel-station) | Monthly fuel transactions matched to driver receipts; pump-side **credit-outstanding statements** for fleet accounts |
| S48 | Existing research note: `research_notes/Truck fleet app research India UAE/operations_field_catalogue.md` §2 | Rule 4B consignment-note particulars; proposed LR and POD fields (not repeated here) |

---

## 2. Features every ledger needs (shared rules)

All five ledgers (Driver, Client, Truck, Supplier, Vendor) are **views over the one
accounting book** (blueprint 06 §9: "one source of truth for money"). They are not separate
money stores. Build these shared rules once and reuse them in every ledger.

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Plain-language balance | Each balance is shown as **Dr/Cr** for the accountant and in plain words for everyone else: "Ravi will give you ₹18,750" / "You will give ACC Ltd ₹4,200". The colour also flips with the direction: red for "they owe", green for "we owe". | Most small owners do not read Dr/Cr; khata apps trained them on "you will get / you will give". | [Practice — unverified] |
| Running balance | Statement rows: date, voucher type, number, reference (trip / LR / bill), narration, debit, credit, running balance after each row | The standard ledger statement every Indian accountant expects (Tally style) | S10, [Practice — unverified] |
| Opening balance | Total opening, or **bill-wise opening** (each old unpaid bill with its date, so ageing is correct from day one); as-on date; Dr/Cr | Customers move over from Tally, Excel or paper books mid-year | S10 |
| Closing / carry forward | Year-end closing balances carried forward automatically; bill-wise items carried with their original dates | Keeps ageing and outstanding correct across years | exists (07 §1 year-end) — extend to bill-wise |
| Bill-wise references | Every money line carries a reference type: **New Ref** (bill), **Against Ref** (payment against that bill), **Advance**, **On Account** (not yet matched); a later "match" screen allocates on-account money to bills | Part payments, TDS and deductions can only be tracked per bill | S10 |
| Filters | Date range or financial year; voucher type; trip; truck; LR; branch; status (pending / approved / cancelled); amount range; "only unsettled" | Daily use | [Practice — unverified] |
| Statements and sharing | PDF (company letterhead), Excel, CSV; **share by WhatsApp** (PDF or a secure link), SMS link, email; "statement for period" with opening and closing; Hindi or regional labels | Owners settle disputes on WhatsApp | S31, S29 |
| Reminders | Per ledger type (sections 3–7); WhatsApp/SMS/email templates with placeholders; scheduled and auto rules | Collections and compliance | S29 |
| Attachments | Photos or PDFs on any entry (receipt, signed voucher, UPI screenshot, POD, bill), compressed to the upload limit | Proof in disputes and audits | blueprint (D1, 06 files) |
| Approvals (maker–checker) | Owner-defined: payments above ₹X, write-offs, deductions, back-dated entries and edits in a locked period need approval; approval history kept | Stops staff fraud; very common owner fear | [Practice — unverified] |
| Acknowledgement | The counter-party confirms receipt in the app (driver taps "received", OTP) or by a signed photo | Ends "I never got that advance" disputes | [Practice — unverified] |
| Audit | No delete, only cancel with a reason; old and new values; user, IP, device, time | Legal requirement (07 §1) | exists (07) |
| Period lock | Entries before the lock date cannot be added or changed | Accounts closed with the CA stay closed | exists (07) |
| Bulk import / export | Excel import of opening balances and masters; Tally XML export | Migration | exists (07) — extend to ledgers |
| Search everywhere | One search box: LR no., bill no., truck no., UTR, cheque no., party name, mobile | Owners search by whatever number they have | S16-style "centralised search"; [Practice — unverified] |
| Language | Ledger screens and statements in English, Hindi and regional languages; amount in words in Indian style (lakh/crore) | Many owners and drivers read Hindi or regional languages only | S31, Q1 in decisions |
| Party with many roles | One party can be a client **and** a supplier or vendor (for example, a transporter who gives us loads and also hires our trucks). Each role has its own ledger view, and a **set-off (contra) entry** nets them with approval. | Very common in transport; avoids two names for one firm | [Practice — unverified] |

---

## 3. Driver Ledger

**Extends:** blueprint 03 §8 (trip settlement), 06 (`crew_cash_moves`, `trip_settlements`,
`salary_structures`) and 07 §2.6 (payroll). The driver ledger is the driver's **personal
account** (khata). It combines company cash the driver is holding, what the company owes
him (salary, bata, incentives) and any loan.

### 3.1 Key design point: three buckets inside one ledger

| Bucket | Meaning | Example |
|---|---|---|
| **Trip cash (imprest)** | Company money in the driver's hands for trip expenses | Advance ₹20,000 − approved spends ₹16,250 = ₹3,750 still with Ravi |
| **Earnings** | Salary, bata, incentives, overtime and reimbursements owed to the driver, less deductions | Salary ₹18,000 + bata ₹4,200 − fine recovery ₹500 |
| **Loan** | Personal loan or salary advance being recovered in instalments | ₹30,000 loan, ₹3,000 a month |

The ledger shows each bucket and a **net position**. Mixing trip cash with salary in one
number is the most common cause of driver disputes, so the screen keeps them apart and
offers an explicit "adjust trip cash against salary" entry. [Practice — unverified]

### 3.2 Entry types

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Opening balance | Amount, as-on date, bucket (cash / earnings / loan), direction, note | Migration from a paper khata | [Practice — unverified] |
| Advance (trip or general) | Date, amount, mode (cash / UPI / bank / fuel-card load / FASTag recharge), trip (optional), paid from (cash book / bank / branch), UTR, purpose, **driver acknowledgement** (tap or OTP), photo of signed voucher | Most-used entry; main dispute area | S38, S1, exists (03 §8) — add acknowledgement |
| Trip expense (from road events) | Auto-posted when the manager approves a road event; links to the event and photos; rejected events post nothing | Already designed | exists (03 §5–6) |
| Cash collected for the company | Freight collected on a **To-Pay LR**, an advance collected from the consignor, or scrap sale; LR no., party, amount, mode. Debits the driver, credits the client. | To-Pay collections are often taken by the driver | [Practice — unverified] |
| Cash deposited / returned | Office cash, or a bank deposit with a slip photo or UPI reference | Closes the imprest | exists (03 §8) |
| Crew-to-crew handover | Driver A hands cash to driver B on the road; one entry, two ledgers, both confirm | Two-driver trips; avoids double counting | [Practice — unverified] |
| Salary accrual | Month, days present / leave / absent, fixed pay, earned pay, statutory deductions | Payroll output posts here | exists (07 §2.6) |
| Bata / bhatta / khuraki accrual | Rule: per day / per trip / per km / route table; driver and helper rates differ; auto-posted at trip close | Daily allowance is separate from trip cash | exists (03 §8) — add a separate rate for each role |
| Incentives and bonus | Trip incentive (per trip or per km), **mileage (diesel-saving) incentive**, on-time bonus, festival bonus (Diwali, Onam, Eid), statutory bonus | Owners use incentives to control diesel and delays | [Practice — unverified] |
| Overtime | From driving hours (driver-change log) | Labour Codes | exists (07 §2.6) |
| Reimbursement | Driver spent his own money (for example a repair); credits earnings or cash bucket | Fair settlement | exists (03 §8) |
| **Recoveries / deductions** | Types: fine recovery (e-challan where the driver is at fault, challan no.), cargo shortage or damage passed on from the client, **diesel shortage** (actual km/l below the truck's standard → shortfall litres × rate), missing tools / tarpaulin / stepney, rejected expense, advance recovery. Fields: basis document, calculation shown, approval, driver notified, **legal cap check** (India Code on Wages (verify); UAE: total ≤50%, damage ≤5 days' wage per month, 07 §3.4) | Recoveries are where drivers quit or dispute; they must be explained and capped | [Practice — unverified]; caps: 07 |
| Loan and EMI recovery | Principal, interest (India allowed; **UAE: no interest, written consent**), instalment, start month, schedule, auto-deduct from salary, prepayment, waiver | Common driver need; must be tracked apart from trip cash | S42, exists (07: instalment plans) — add loan sub-ledger |
| Salary / earnings payout | Mode, UTR, bank batch file; WPS SIF (UAE) | Closes the earnings bucket | exists (07) |
| Trip settlement | Settlement voucher per trip (received, spent, balance) and the result: returned / paid / **carried forward** / adjusted against salary | Already designed; the ledger shows the link | exists (03 §8) |
| Carry forward line | "Brought from trip 261013-ACC-COK-BOM-01: ₹3,750" shown as a reference line, with no cash movement | Drivers understand balances by trip | [Practice — unverified] |
| Security deposit | Some owners hold a deposit from the driver; kept as a liability and refunded at exit | Exists in practice; keep it separate from salary | [Practice — unverified] |
| Full & final settlement | Net of all buckets + gratuity + leave encashment − pending recoveries; settlement letter; clearance (keys, phone, documents returned) | Driver exit is frequent; disputes go to the labour office | [Practice — unverified]; UAE gratuity in 07 |
| Write-off / waiver | Owner only, with a reason | Small balances or goodwill | [Practice — unverified] |

### 3.3 Balances, views and reports

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Driver statement | Running balance per bucket and net; filter by trip, month, type | Monthly settlement talk | S38 |
| "My Khata" in the crew app | The driver sees his own ledger and statement in his language, can **flag** an entry ("not received" / "wrong amount"), which opens a query thread | Trust and fewer phone calls | S43 (driver-side khata), [Practice — unverified] |
| All-drivers cash position | Who holds how much company cash, and for how many days | Cash exposure control | [Practice — unverified] |
| Advance ageing | Advances not settled for more than N days | Spots leakage | [Practice — unverified] |
| Registers | Recoveries, loans, bata, incentives, salary register, wage slips | Payroll and labour-law records | exists (07) — add recoveries and loans |
| Reminders | To the manager: unsettled trip cash > N days, cash with a driver > ₹X, loan instalment due; to the driver: monthly statement on WhatsApp | Discipline | [Practice — unverified] |
| Settlement lock | After settlement, entries in that trip's settlement are read-only | Stops back-dated changes | S42, exists (03) |

---

## 4. Client Ledger (customer / party / billing party)

**Extends:** 06 `parties`, `route_rates` and `consignments`; 07 (sales invoice, receipt,
TDS receivable, outstanding and ageing). On one LR there can be three different parties:
**consignor** (who sends), **consignee** (who receives) and **billing party** (who pays,
which can be either of them or a third party such as a 3PL). Only the billing party carries
a ledger balance. Consignor and consignee are address-book roles.

### 4.1 Client set-up

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Client profile | Legal and trade name, short code, **group / parent** (for example "UltraTech – all plants"), several **GSTINs with state and address** (plant-wise billing), PAN, TAN, contacts (dispatch, accounts payable, bill submission), invoice email, vendor code at the client, client portal URL | Large shippers have many plants and pay from different GSTINs | [Practice — unverified] |
| Credit limit and credit days | Limit ₹; days; due-date basis (**invoice date** or **bill submission date**); action on breach: warn / block new LR or trip / need manager override with a reason | Cash-flow safety | S29, S10 |
| Billing rules | Billing cycle (per LR / weekly / fortnightly / monthly); bill-with-POD rule (original POD, e-POD or none); number of copies; annexure format; reference numbers the client needs on bills (PO / indent / shipment / delivery no.) | Each big client has its own bill-submission rules; a wrong format means the bill is rejected and paid late | S18, [Practice — unverified] |
| Tax behaviour | GST mode for this client (RCM / FCM 5% / FCM 18% / exempt or bill of supply); does the client deduct TDS (rate, section)? Has **our** ≤10-trucks declaration been given to this client for this FY (copy stored, see 4.2)? | Correct invoice wording and correct receivable | S22, S20 |
| Rate contracts | Route (from–to), material, truck type / body / size, basis (per trip / per MT / per kg / per km / per package), rate, **minimum guaranteed weight**, validity, version, approval; **fuel-escalation clause** (base diesel price, ±% step, rate change formula); detention (free hours, **slab rates per day**); loading/unloading and multi-drop charges; penalties (late delivery per day, how shortage is valued); payment terms. The rate is auto-filled on the LR. | Freight disputes are mostly about rates; most contracts link rates to diesel | S3 (freight from rate charts), S5, S40, extends 06 `route_rates` |
| Interest on late payment | % per year, after the due date; optional interest debit note | Usually printed on bills and rarely charged, but must be possible | [Practice — unverified] |

### 4.2 Entry types

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| LR booked (unbilled) | Not a money entry until billed (for TBB); shown in the "Unbilled LRs" list with its value | Revenue is earned but not yet billed; must be visible | S14, [Practice — unverified] |
| Advance received | At loading or before the trip; against a trip or LR (ref type **Advance**); mode; UTR; auto-adjusted on the bill | Market loads often pay an advance at loading | S10, S15 |
| Freight invoice (single LR) | See 9.2 and 11.2 | Basic bill | 07 |
| **Consolidated bill (multi-LR)** | Pick client, period, GSTIN, filters (POD received only, route, truck); one invoice with a **LR annexure**; optional POD scans attached as one PDF bundle | Monthly billing is the norm for contract clients | [Practice — unverified] |
| **Supplementary bill** | Detention / halting, extra loading or unloading, multi-point delivery, rate difference (diesel escalation), weight difference; linked to the original LR or bill | Detention is often found after the main bill | S3 |
| Receipt | Date, mode (NEFT / RTGS / UPI / cheque / cash), UTR or cheque no., bank, amount; **split across bills** (Agst Ref), Advance, On Account | Clients pay lump sums against many bills | S10 |
| TDS deducted by client | Per bill: section (old 194C / new 393(1)), rate, amount, quarter; posts Dr TDS receivable; matched later with **26AS / AIS** and Form 16A | The client pays net; without this the bill looks short-paid forever | exists (07 TDS receivable) — add per-bill tracking and Form 16A collection |
| Deductions by client | Shortage, damage, late delivery, **late POD submission**, rate difference, unloading charges deducted; evidence; posted as a **credit note** (GST adjusted if FCM) or as a claim / discount; option to **recover from driver or supplier** (linked recovery) | Shippers deduct claims from payments; the transporter passes them on | S5, S18 |
| Credit / debit note | Linked to the invoice; reason codes | GST and outstanding accuracy | exists (07) |
| Cheque bounce | Reverses the receipt; bank charges debited to the client; reminder for the legal notice window | Real risk with small parties | [Practice — unverified] |
| Write-off / bad debt | Owner only, with a reason and approval | Year-end clean-up | [Practice — unverified] |
| Refund / excess receipt | Pay back or keep as on-account | Over-payments happen | [Practice — unverified] |
| Set-off (contra) | Net against the same firm's supplier or vendor ledger, with approval | Common between transporters | [Practice — unverified] |
| Our ≤10-truck declaration to client | If our customer (the transporter) owns ≤10 trucks, they give each client a declaration with PAN each FY so the client deducts no TDS; store the signed copy, print it, remind before the FY starts | Saves the small owner from TDS cash blockage; very relevant to our small-fleet users | S20, S11 |

### 4.3 Expert workflow: LR to cash

```
LR booked ─► Loaded ─► Delivered ─► POD received ─► POD verified ─► Billed
                                     (photo/e-POD     (qty, shortage,   (single or
                                      + original)      detention dates)   consolidated)
   ─► Bill submitted (date, mode, acknowledgement no. or portal ref, courier docket)
   ─► Due date (from invoice or submission date, as per the client's terms)
   ─► Part payments + TDS + deductions (bill-wise)  ─► Bill closed
```

| Queue (dashboard tile) | What it lists | Why it matters | Source |
|---|---|---|---|
| POD pending | Delivered LRs without a POD, aged by days since delivery | POD is the bottleneck for billing and supplier payment | S18, S48 |
| Ready to bill | POD verified, not billed | Revenue leakage check | [Practice — unverified] |
| Billed, not submitted | Bills not yet handed to the client | Due date often starts at submission | S18 |
| Submitted, not due / overdue | With ageing | Collections | S10 |
| Short-paid bills | Paid minus (TDS + deductions) ≠ bill | Finds unexplained deductions | [Practice — unverified] |

### 4.4 Reports

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Client statement | Opening, bills, receipts, TDS, notes, closing; shareable | Monthly reconciliation with the client | S31 |
| Bill-wise outstanding | Each open bill: date, due date, amount, received, TDS, deductions, balance, days overdue | Collections calls | S10 |
| Ageing | Slabs editable (0–30 / 31–60 / 61–90 / 90+), by bill date or due date; per client, group or branch | Credit control | S10 |
| Unbilled revenue | LRs delivered but not billed, with value | Month-end accruals | [Practice — unverified] |
| TDS receivable reconciliation | Books vs 26AS / AIS per client per quarter; missing Form 16A | Tax credit lost if not matched | exists (07) — add client view |
| Deductions analysis | Shortage and damage by client, route, driver, supplier | Finds bad routes or drivers | [Practice — unverified] |
| Client profitability | Revenue minus trip costs for that client's trips | Pricing decisions | [Practice — unverified] |
| DSO and collection forecast | Days sales outstanding; expected receipts by week | Cash planning | [Practice — unverified] |
| Balance confirmation | Yearly letter or email asking the client to confirm the balance as of 31 March | Auditors ask for it | [Practice — unverified] |

### 4.5 Reminders and sharing

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Payment reminders | Before due, on due, every N days after; WhatsApp / SMS / email; editable template with placeholders (party, amount, bill nos., due date, pay link) | Collections | S29 |
| Invoice share | PDF or link with **UPI pay QR** for the balance amount | Faster payment | S30 |
| Client portal (later) | Client views bills, PODs, statement, downloads PDFs | Fewer calls; big clients like self-service | [Practice — unverified] |

---

## 5. Truck Ledger (per-vehicle income, costs and profit)

**Extends:** 03 §9 (trip profit and the shared-cost owner setting), 06 (`trucks`,
`truck_documents`, `tyres`, `service_jobs`) and 07 (cost centres, fixed assets). The truck
ledger is a **cost-centre view**: every voucher line tagged with the truck adds up here.
It is not a separate account.

### 5.1 What goes in

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Income lines | Freight from own trips; detention; loading/unloading recovered; other charges; **hire income** when our truck is given to another transporter; **commission income** from an attached truck | Revenue per truck | S12, [Practice — unverified] |
| Running costs | Fuel and AdBlue (bought, adjusted for tank level), tolls / FASTag (matched to trips), bata, driver and helper wage share, loading/unloading, weighbridge, parking, fines, payments without receipt | Variable cost per km | exists (03 §9) |
| Maintenance | Service jobs, breakdown repairs, accident repairs, lubricants, batteries, spares; **insurance claim receipts** reduce the accident cost | Truck health and cost | exists (06) |
| Tyres | Cost by **cost-per-km method** (tyre cost ÷ expected km × km run) or as purchased; retreading; scrap value | Avoids one month looking bad because tyres were bought | S37 |
| Fixed costs | **EMI split into principal and interest** (only interest is an expense; principal reduces the loan), insurance (paid yearly, spread monthly), road tax (quarterly/yearly), permits (national permit authorisation, state permits), fitness, PUC, GPS / AIS-140 subscription, FASTag fees, parking or yard rent | Monthly P&L must spread yearly payments | S37, [Practice — unverified] |
| Depreciation | Book method (Companies Act) and tax method shown separately (verify rates) | True profit vs cash profit | exists (07 phase 2) |
| Overhead allocation | Office costs allocated by days / km / trips / revenue (owner chooses) | "Full" profit | exists (03 §9) — add the method choice |

### 5.2 Views and KPIs

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Truck P&L per month | Income − variable costs = **contribution**; − fixed costs = **operating profit**; − depreciation and interest = **net profit**; cash view vs accrual view | The number owners care about most | S12, S37 |
| Per-km figures | km run (loaded / empty), revenue per km, variable cost per km, fixed cost per km, profit per km, **diesel km/l** vs standard | Comparison across trucks of different use | S37 |
| Utilisation | Days on trip / idle / in workshop / breakdown; trips per month; empty-km % | Idle trucks still pay EMI | [Practice — unverified] |
| Break-even | Fixed cost per month ÷ contribution per km = km needed to break even | Pricing and dispatch decisions | [Practice — unverified] |
| Truck comparison | All trucks side by side for the period; ranking; same-model averages | Finds problem trucks or drivers | [Practice — unverified] |
| Life-to-date | Since purchase: total revenue, total cost, profit, loan paid, book value; **profit or loss on sale** when sold | Decides when to sell or replace | [Practice — unverified] |
| Budget vs actual | Monthly budget per truck (fuel, tolls, maintenance) | Cost control | extends 03 §4 budgets |
| Truck register | Trip list for the truck with income, cost and profit per trip; drill down to vouchers | Audit trail per vehicle | S12 |
| Attached / market trucks | For attached trucks: income, commission and payable to the owner (links to Supplier Ledger) | Many fleets mix own and attached trucks | [Practice — unverified] |
| Loan sub-view | Lender, loan account, EMI schedule, principal / interest paid, outstanding, foreclosure amount, **hypothecation on RC** (NOC after closure) | Owners track EMIs per truck | [Practice — unverified] |
| Export | PDF / Excel / WhatsApp; "truck report card" one-pager | Shared with partners and lenders | [Practice — unverified] |

---

## 6. Supplier Ledger (market vehicles / lorry hire / "bhada")

**Convention used in this note (evidence in 10.1):** a **Supplier** is someone who
**supplies a vehicle** for our load: a market truck owner, a fleet operator, or a vehicle
broker. The money owed to them is **lorry hire / bhada**. Our blueprint (A3) puts
hired / market trucks in a later phase. D13 now requires this module, so the phase should
be reviewed.

### 6.1 Supplier set-up

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Supplier profile | Type: owner-operator / fleet operator / **vehicle broker (agent)**; name; mobile; address; **PAN** (verified); GSTIN (if they are a GTA); Aadhaar (masked, optional); bank account (verified by **penny-drop**); UPI; preferred routes; rating; **blacklist flag** with reason | Paying the wrong person or a fake owner is a real fraud | [Practice — unverified] |
| Supplier vehicles | Reg no., type / body / size, RC copy, **RC owner name** (from a Vahan check, S23) vs supplier PAN name, insurance / permit / fitness validity (from Vahan), usual driver name, mobile and DL | TDS declaration and fraud checks need vehicle-to-owner proof | S19, S23 |
| ≤10 trucks declaration | Per supplier **per FY**: signed declaration (upload), number of goods carriages owned, PAN, date received; **valid only for that tax year**; reminder in March to collect next year's | Decides nil TDS; auditors check it | S20, S11 |
| Default terms | Advance % at loading, balance days after POD, who pays tolls / loading / unloading, detention terms, penalty terms, munshiana / office charge, commission | Speeds up the hire challan | [Practice — unverified] |

### 6.2 Entry types

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| **Lorry hire challan / hire memo / truck hire note** | Credits the supplier with the agreed hire. Links trip, LR(s) and vehicle. Fields in 11.3. | The base document for every market-vehicle payment | S1, S2, S15, S17, S3 |
| Advance at loading | One or more: cash / bank / UPI / **cash to the market driver**; UTR; acknowledgement | Market trucks will not move without an advance | S3, S15 |
| Fuel given on supplier's account | Diesel from our credit pump (vendor slip) charged to the supplier and deducted from hire | Common practice; links Vendor and Supplier ledgers | [Practice — unverified] |
| Detention / halting payable | Per POD dates and agreed slabs | Fair payment; often back-to-back with client detention | S5 |
| Extra charges payable | Loading/unloading paid by the supplier's driver, two-point delivery, extra km | Real-world extras | S5 (only if recorded on the POD) |
| Deductions | Shortage, damage, late delivery, **late POD submission fee**, claim passed on from the client (back-to-back), munshiana / office charges, advance recovered, fuel given | Shippers deduct from transporters, who pass it on to the supplier | S5, [Practice — unverified] |
| Broker commission | Commission / dalali: deducted from hire (vehicle broker) or paid to the broker separately; % or fixed | Brokers arrange most market trucks | S1, S2 (broker ledger) |
| **TDS on hire** | Automatic decision: valid PAN + declaration on file for the FY → **nil TDS, but still reported** in the TDS return; otherwise 1% (individual/HUF) or 2% (others) once over ₹30,000 per payment or ₹1,00,000 a year; **no PAN → higher rate (20%)**; TDS certificate to the supplier; old/new section shown (194C → 393(1) / 393(4); 206AA → 397) (verify) | Legal duty of the payer; mistakes mean interest and disallowance | S11, S20, 07 §2.3 |
| Balance payment | Released only when: POD received and verified, deductions entered, TDS applied; due on agreed days after POD; batch payment file | Paying before POD is the classic loss | S3, S5 |
| GST on hire | Hiring a goods vehicle to a GTA is exempt (no GST); if the supplier is itself a GTA issuing its own LR, special rules apply (verify with CA) | Avoids wrong tax | 07 §2.1 (verify) |

### 6.3 Reports and controls

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Supplier statement | Per supplier and per vehicle; hire, advances, deductions, TDS, payments, balance | Settlement talks with truck owners | S1, S2 |
| Hire register | All hire challans: date, vehicle, route, client freight vs hire = **margin per load** | Shows broker or market margin | [Practice — unverified] |
| Pending balance | Split: POD not received / POD received, not paid / paid | Workload and cash planning | S3 |
| **Advance without POD ageing** | Advances paid where the POD is still missing after N days | Biggest loss risk with market trucks | [Practice — unverified] |
| TDS register | Per supplier: payments, TDS, declaration status, PAN status | Quarterly return | S19, S11 |
| Supplier share | Hire challan and statement over WhatsApp; link for the supplier to upload a POD photo | Faster POD | [Practice — unverified] |

---

## 7. Vendor Ledger (goods and services we buy)

**Convention:** a **Vendor** sells us goods or services we use to run our own trucks and
office: fuel pumps, workshops, tyre dealers, spare parts, batteries, lubricants, insurance
companies and agents, RTO agents, GPS / FASTag providers, loading labour, weighbridges,
towing, rent, CA and legal.

### 7.1 Vendor set-up

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Vendor profile | Category (list above), GSTIN, PAN, **Udyam no. and type** (micro / small / medium) with check date, payment terms (credit days; **written agreement yes/no**), bank / UPI, default TDS section, default ITC-eligible flag, contact; for pumps: **location** (to match fuel events by GPS) | MSME due dates, TDS and ITC depend on these | S21, 07 |
| Price agreements | Pump discount per litre vs retail price; tyre and parts rate lists; workshop labour rates | Detects overbilling | [Practice — unverified] |

### 7.2 Entry types and workflows

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Purchase / expense bill | Bill no., date, GSTIN, items or services, HSN/SAC, taxable value, GST, **ITC eligible / blocked**, truck / trip / branch cost centre, attachment, **due date** | Base entry | exists (07) |
| **Fuel pump credit** | Issue a numbered **fuel slip / indent** to the driver (slip no., truck, driver, litres or amount, pump); the driver's fuel event links the slip; the pump's **fortnightly/monthly statement** is imported (Excel/CSV/PDF) and **auto-matched** to slips and events; exceptions: missing slip, extra litres, rate above agreed, wrong truck; disputes; payment against the statement | Diesel is 40–60% of trip cost; slip fraud is common | S47, exists (03 fuel event), [Practice — unverified] |
| Workshop | Estimate → owner approval → job card → bill (parts + labour) → warranty; links `service_jobs` | Stops unapproved repairs | exists (06) — add the estimate approval |
| Tyre dealer | Tyre serial nos. on the bill auto-create tyre records; retread bills; **warranty claims as credit notes** | Tyre life tracking | exists (06 tyres) |
| Insurance | Premium bill → Document Manager policy record → expense spread monthly to the truck; claim receivable and settlement | Biggest yearly cost per truck | [Practice — unverified] |
| RTO agent | Government fee (with receipt) + agent service charge, per truck and per document; links the renewal in the Document Manager | Common, often poorly documented | [Practice — unverified] |
| Payments | Bill-wise (Agst Ref), advance, on-account; batch payment run (select bills due → bank file); cheque printing; **payment advice** to the vendor (bills paid, TDS) by WhatsApp or email | Vendor relations | S10, [Practice — unverified] |
| Debit notes | Returns, short supply, rate dispute | GST and payable accuracy | exists (07) |
| TDS on vendors | Contracts such as repair labour and loading labour (old 194C → new 393); professional fees (old 194J); rent (old 194I); purchase of goods (old 194Q, only above the turnover limit) (verify all mappings) | Legal duty | 07 (verify) |
| **MSME due-date control** | Due = agreed days (max 45) or 15 days if no written agreement; alerts at 30 / 40 / 45 days; year-end list of unpaid MSME dues past due (tax disallowance under **s.37(2)(g)**); interest liability note; companies: half-yearly MSME return data (verify) | Tax cost and legal interest | S21, 07 |
| GST input credit checks | Purchase register vs GSTR-2B / IMS; vendor not filing; **ITC reversal if the vendor is unpaid after 180 days** (verify) | Lost credit is a direct cost | 07, [Practice — unverified] |
| Fuel and tax note | India: diesel is outside GST, so there is no ITC on diesel (lubricants and AdBlue carry GST). UAE: fuel VAT is recoverable with the supplier's TRN. | Correct costing per country | 03 §5.2 (UAE TRN), [Practice — unverified] |
| Vendor statement and ageing | Payables ageing; statement reconciliation (vendor's statement vs our books); balance confirmation | Month-end close | S10 |

---

## 8. Document Manager

**Extends:** 06 (`truck_documents`, `person_documents`, `document_types`) and 03 §4
(dispatch blocked on expired documents). Here the Document Manager becomes one module for
**all** owners of documents: trucks, drivers and crew, company, clients, suppliers and
vendors.

### 8.1 Document types (defaults per country; company can add more)

| Owner | India | UAE |
|---|---|---|
| Truck | RC (with hypothecation), insurance (policy, period, IDV, insurer), **fitness**, **national permit / state permit + NP authorisation**, PUC, road tax receipt, FASTag, **AIS-140 tracker certificate**, speed governor certificate, loan papers / NOC, invoice of purchase, body-builder certificate, CNG/LNG cylinder test (if any) | Mulkiya, insurance, RTA / ADNOC permits, Salik tag, tracker, trade-plate papers |
| Driver / crew | Driving licence (transport endorsement, **hazardous endorsement**), badge, **Aadhaar (masked: first 8 digits hidden)**, PAN, bank passbook or cancelled cheque, photo, address proof, medical fitness / eye test, police verification, HAZMAT training, appointment letter, nominee form | Licence, Emirates ID (encrypted), passport, visa, labour card, medical, ILOE policy |
| Company | GST certificate(s), PAN, TAN, Udyam, shop act / trade licence, **Carriage by Road Act registration certificate**, bank letters, ISO, GTA Annexure V filing | Trade licence, VAT TRN certificate, chamber membership, insurance |
| Client | Contract, **rate agreement**, GST certificate, PAN, credit approval, NDA, **TDS certificates (Form 16A) received each quarter**, our ≤10 truck declaration given | Contract, TRN certificate |
| Supplier / vendor | PAN, ≤10 truck declaration per FY, RC copies, cancelled cheque, owner ID (masked), Udyam certificate, GST certificate | TRN certificate, trade licence |

Sources: 06 and the existing research note S48 §9; the company and client rows are
[Practice — unverified]; masking of Aadhaar and Emirates ID is in 07 §4.

### 8.2 Features

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Document record | Type, owner (truck / person / company / client / supplier / vendor), number, issuing authority, issue date, valid from, **expiry date**, amount paid, vendor (insurer, agent), files (front, back, multi-page PDF), notes, **where the original is kept** (office / financer / in the truck) | One place for everything | 06, [Practice — unverified] |
| Expiry reminders | Per type: reminder days (for example 30 / 15 / 7 / 1) and **repeat** after expiry (daily or weekly until renewed); to whom (owner, manager, driver); channels (push, SMS, email, **WhatsApp template**); snooze with a reason | Missed renewals mean fines and blocked trips | S25 |
| Compliance dashboard | Counts: expired / due in 7 / 15 / 30 days; calendar view; per-truck "document health" (green / amber / red); export | Owners check this every morning | S25, [Practice — unverified] |
| Renewal workflow | "Renew" creates a **new version**; the old one is archived (kept, read-only); renewal history per document; who renewed it and via which agent | Audit and resale history | [Practice — unverified] |
| Cost posting | Renewal cost posts as a vendor bill or cash expense to the **truck ledger**; insurance and road tax can be spread over the validity period (prepaid) | Truck P&L accuracy | [Practice — unverified] |
| Dispatch gate | Expired document blocks dispatch; manager override with a reason | exists (03 §4) | exists |
| Share | Share one document or a **document pack** (for example "all papers of KL-07-AB-1234") by WhatsApp: as PDF, or as a **secure link that expires**; watermark "Copy shared on <date> for <purpose>" | Police checks, client vehicle on-boarding and lenders ask for papers on WhatsApp | [Practice — unverified] |
| Driver access in the app | The crew app shows the current truck's documents offline (RC, insurance, permit, fitness, PUC) | Roadside checks; DigiLocker / mParivahan copies are legally valid | S24 |
| Bulk upload | Many files at once; auto-link by file name (for example `KL07AB1234_insurance.pdf`); drag-and-drop; from the phone gallery or scanner | First-time set-up of a 50-truck fleet | [Practice — unverified] |
| OCR auto-fill | Read number and dates from the image (RC, DL, insurance); **show confidence; low-confidence fields go to a review queue** | Saves typing; avoids wrong expiry dates | S26, S23 (Cashfree OCR) |
| **Vahan auto-fetch (RC)** | By reg no.: owner name, chassis / engine (masked), class, fuel, norms, **insurance company and validity, PUC validity, fitness validity, permit validity, tax validity**, financer, blacklist status (fields vary by provider) | Fills the truck master and checks expiries without typing | S23 |
| Sarathi auto-fetch (DL) | By DL no. + DOB: name, validity (non-transport / transport), classes of vehicle, endorsements | Hiring checks; fake-licence fraud | S26, [Practice — unverified] |
| DigiLocker pull | With the driver's consent (OTP): DL, RC, Aadhaar (masked), PAN; requires a **licensed DigiLocker requester partner** (we would use an aggregator) | Verified documents with no fake uploads | S24 |
| e-Challan check | Pending traffic challans per vehicle (some RC APIs include them) | Catch fines before they block renewals | S23 (Gridlines variant) |
| API providers | Aggregators found: **Surepass, Gridlines (₹3.99/call listed), Cashfree, IDfy**; also known in practice: Signzy, HyperVerge (DL OCR), Perfios-Karza. Direct government (Vahan / Sarathi) access is limited to authorised entities, so use an aggregator behind a provider interface so we can switch. | Cost and continuity | S23, S26; [Practice — unverified] for Signzy / Karza and for official access rules |
| Storage limits | Images compressed on upload (250–500 KB rule from D1); PDFs up to a size cap; **storage quota per plan** (for example X GB per truck), shown in settings; old versions can be moved to cold storage but never silently deleted while legally needed | Cost control for a SaaS | D1, [Practice — unverified] |
| Privacy | Role-based access (drivers' ID documents visible only to HR / owner); masked Aadhaar; every view and download is logged | DPDP / PDPL | 07 §4 |
| Insurance claim tracking | Claim no., date, surveyor, estimate, approved, received, linked to the accident and repair | Recovers money owners often lose | [Practice — unverified] |

---

## 9. Invoices & LR

**Extends:** 07 §2.2 (LR rules, Rule 46 invoice, bill of supply, credit notes, e-way
bill) and 06 `consignments`, `number_series` and `invoices`. LR fields already proposed
in S48 §2 are not repeated. Full field lists are in section 11.

### 9.1 LR / bilty / GR / consignment note

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Legal basis | Under GST, **issuing a consignment note is what makes the transporter a GTA**. Particulars from old Rule 4B: serial no., consignor, consignee, vehicle no., goods, origin, destination, **person liable to pay GST**. Carriage by Road Act: the consignor gives a **goods forwarding note with declared value**, and the carrier issues a **goods receipt in triplicate (original to the consignor)**. | Minimum content and copies | S48, S7 |
| Copies | Configurable copy set with printed labels. **Default 4:** CONSIGNOR COPY, CONSIGNEE COPY, DRIVER / LORRY COPY, OFFICE COPY. Optional: ACCOUNTS COPY, POD COPY, BRANCH COPY. The law requires at least 3; traditional books are often colour-coded per copy. | Copy labels guide who gets which paper; the signed consignee/POD copy proves delivery | S7, S18, S44, [Practice — unverified] for 4 copies and colours |
| Numbering | Series **per branch per FY** with prefix (for example `COK/26-27/000123`); auto or manual | Branch control | 07, S1 (multi-branch bilty book) |
| **LR book control (manual books)** | Book register: book no., number range from–to, issued to (branch / agent / driver), issue date; each leaf: used / unused / **cancelled** / lost; typed number accepted only if it falls in an issued book and is not used; gap and duplicate report | Many transporters still use pre-printed books; missing leaves mean hidden bookings | S14 (manual GR no.), [Practice — unverified] |
| Payment type | **Paid** (consignor pays at booking), **To-Pay** (consignee pays at delivery; driver or branch collects), **TBB / To Be Billed** (billed later to a contract party), **FOC** (free of charge, for own goods or samples; still needs an LR for movement) | Decides who is debited and when | S14, S18, [Practice — unverified] for FOC |
| Risk | **Owner's risk (OR)** or **Carrier's risk (CR)**; CR adds a **risk charge** (% of declared value); declared value; Act liability limit (10× freight, capped at declared value) printed in terms | Liability in claims | S7, S44 |
| Insurance | Insured by (consignor / transporter / none), insurer, policy no., amount, date | Claims | S48 |
| GST payable by | Consignor / consignee / transporter (GTA forward charge); drives the invoice declaration | GST correctness | S48, S22 |
| E-way bills | One LR can carry **several invoices and several EWB numbers**; EWB validity countdown; Part-B update on vehicle change | Compliance | exists (07 §2.2) |
| Charges on LR | Freight, **LR / bilty charge (statistical)**, hamali (loading / unloading), door collection, door delivery, risk charge, detention (later), other; GST line | Paid and To-Pay LRs are themselves the money document | S31, [Practice — unverified] |
| POD | e-POD: receiver name, mobile **OTP**, signature on the phone, stamp photo, GPS and time, packages received, shortage / damage remarks, unloading start and end (for detention). Physical POD: date the original LR copy came back to the office; dispatched to the client with the bill (courier docket). | Billing and supplier balance payment depend on it | S48, S5, [Practice — unverified] |
| Back-side terms | Printed terms and conditions (liability, demurrage after N days, jurisdiction, claims within N days) | Standard on Indian LRs | S7, [Practice — unverified] |
| Part-load (PTL) extras (later) | Booking branch → **manifest / challan / transit memo** listing LRs on a vehicle → arrival → godown → delivery memo / gate pass | Needed if customers run parcel/PTL | S2, S14, S16, S17 |

### 9.2 Freight invoice / bill

| Feature | Fields / details | Why it matters | Source |
|---|---|---|---|
| Single-LR invoice | One bill per LR; can be made automatically when the POD is verified | Spot / market clients | 07 |
| Multi-LR consolidated invoice | Period, client GSTIN, LR selection (POD-verified only), **summary invoice + LR annexure** (LR no., date, vehicle, from–to, material, weight, rate, freight, detention, other, total), optional POD images bundled | Contract clients | [Practice — unverified] |
| Supplementary invoice | Detention / halting, extra loading, rate difference (diesel escalation), linked to the original | Detention found late | S3 |
| Charge lines | Freight (by basis), detention / halting (days × slab rate), loading / unloading, multi-point, toll (if billable), **fuel surcharge**, other charges | Transport bill lines | S31, S40 |
| Less: advance | "Less advance received on <date>" to show the **net receivable** (GST stays on the full value) | Clients pay net | S15, [Practice — unverified] |
| TDS line | Not on the tax invoice itself; optionally "TDS @1%/2% will be deducted by you" note or a "net payable after TDS" helper line | Avoids confusion | [Practice — unverified] |
| GTA declarations | Auto-printed by the client's GST mode: RCM: "Tax payable on reverse charge: **Yes**"; FCM 5%: no-ITC condition text; FCM 18%: **Annexure III** declaration; exempt: "Bill of Supply". Exact wording held in a dated, editable table. | Legal wording changes; CA must approve | S22, 07 (verify) |
| Round-off, amount in words | Round to rupee; words in **Indian format** ("Rupees One Lakh Twenty Thousand Only"); AED "Dirhams … and … Fils" | Expected on every Indian bill | [Practice — unverified] |
| Bank, UPI QR, pay link | Bank name, account, IFSC, branch; **dynamic UPI QR for the balance amount** | Faster payment | S30, S46 |
| Terms | Payment terms, interest on late payment, jurisdiction, claims window, "subject to … jurisdiction" | Standard | [Practice — unverified] |
| Signature / stamp | Uploaded signature and company stamp images; "For <Company> – Authorised Signatory"; digital signature (DSC) option later | Rule 46 needs a signature or digital signature | 07, S34 |
| **Copies** | Services invoice (GTA freight is a service) = **2 copies**: ORIGINAL FOR RECIPIENT, DUPLICATE FOR SUPPLIER (Rule 48). The 3-copy set (… DUPLICATE FOR TRANSPORTER, TRIPLICATE FOR SUPPLIER) applies to **goods** invoices, which a transporter issues only when selling goods such as scrap. Extra copies allowed (EXTRA COPY / OFFICE COPY), as Tally does. | Correct legal labels; the founder's brief assumed triplicate | S8, S9 |
| Bill submission tracking | Submitted on, to whom, mode (hand / courier / email / client portal), acknowledgement no., portal invoice ref; due date can start here | Large clients count credit days from submission | S18 |
| UAE | "Tax Invoice" title, TRN, VAT category per line, AED, optional **Arabic bilingual** template | Country pack | 07 §3.3, S32, S36 |

### 9.3 Other transport documents (all from the same template engine)

| Document | Purpose | Key fields | Source |
|---|---|---|---|
| Lorry hire challan / truck hire note / freight memo | Contract with a market-truck supplier | See 11.3 | S1, S2, S15, S17 |
| Loading slip / loading memo | Tells the loading point (or driver) what to load and for whom | Slip no., date, truck, driver and mobile, consignor, loading point, material, qty / weight expected, LR no. (if known), advance given, instructions | S1, S2, [Practice — unverified] |
| Trip sheet | One sheet per trip: route, km, expenses, advances, settlement | Exists in operations (03); printable | S48 |
| Money receipt | Proof of money received (freight, advance, To-Pay collection) | See 11.4 | [Practice — unverified] |
| Payment voucher / payment slip | Proof of payment to a driver, supplier or vendor, with the receiver's signature | Voucher no., date, payee, amount, words, mode / UTR, against (trip / hire challan / bill), TDS, prepared / approved / received signatures | S2, [Practice — unverified] |
| Credit / debit note | Adjust an invoice | Original invoice no. and date, reason, taxable value, tax, total | 07 |
| Quotation / rate confirmation | Offer to a client; confirmation of agreed rate for a load | Route, truck type, rate basis, rate, validity, detention terms, payment terms, acceptance signature | S2, [Practice — unverified] |
| Delivery challan / delivery slip | Movement of goods that is not a sale (own tyres, spares between branches) or the PTL delivery memo | Challan no., from / to, goods, qty, value, vehicle, reason | S2, S16 |
| Manifest / transit memo (PTL) | List of LRs on one vehicle between branches | Manifest no., vehicle, driver, from and to branch, LR list with packages and weight, seal no. | S2, S17 |
| Driver salary slip, settlement statement | Payroll and trip hisaab | From payroll and settlement | 07, 03 |
| Statement of account | Ledger statement for any party | Section 2 | S31 |

---

## 10. Terminology

### 10.1 Supplier vs Vendor: the convention, with evidence

| Evidence | What it shows |
|---|---|
| MyBilty lists **Party, Broker, Vehicle, Driver and "Supplier-wise" ledgers**, plus separate "direct & indirect expenses" (S1) | "Supplier" sits beside broker and vehicle, on the hired-vehicle side; running expenses are kept elsewhere |
| ONLINELR tracks **party-wise, supplier-wise, driver and broker** ledgers (S2) | Same pattern |
| Freight Tiger calls fleet owners who give trucks for loads **"truck suppliers"** and pays them advance and balance (S4) | In Indian freight, "supplier" commonly means **vehicle supplier** |
| Fleetx keeps **Market Vehicle Management** (hire, advance, detention, balance) apart from **Purchase Bills "with vendor-wise tracking"** of spares, batteries and tyres (S3) | "Vendor" means a goods or services seller for the fleet |
| LogixERP lists "**vendor management**" in its accounting back-bone and a separate **Lorry Hire Sheet** (S17) | Same split |
| General accounting (Tally) uses "Sundry Creditors" for both, and the two words are synonyms there (S10) | The split is a **transport-industry habit**, not an accounting rule |

**Conclusion and our rule:**
- **Supplier** = a person or firm that **supplies a vehicle** (market or hired truck, attached truck owner, fleet operator, or vehicle broker). Money flow: lorry hire / bhada, with advance, balance, TDS under the ≤10-truck rule and POD-based deductions.
- **Vendor** = a person or firm that **sells goods or services** we consume (fuel, repairs, tyres, spares, insurance, RTO work, GPS, rent, labour). Money flow: purchase bills, GST input credit, MSME due dates, TDS by nature of payment.
- Show this definition on the screens: "Suppliers = truck owners and brokers who give you vehicles" and "Vendors = pumps, workshops, shops and service providers". The words differ between apps, so users need the hint.
- One firm can hold both roles (section 2, "party with many roles").

### 10.2 Glossary

| Term | Meaning |
|---|---|
| LR / GR / bilty / CN / consignment note / goods receipt | The transporter's receipt for goods taken for carriage. LR = lorry receipt; GR = goods receipt; bilty is the Hindi word. |
| Goods forwarding note (GFN) | The consignor's note to the carrier, declaring the goods and their value (Carriage by Road Act) |
| POD / acknowledgement copy | The LR copy signed and stamped by the consignee on delivery; proof of delivery |
| Consignor / consignee / billing party | Sender / receiver / the one who pays the freight |
| Paid / To-Pay (TP) / TBB / FOC | Freight paid at booking / paid by the consignee at delivery / billed later to a contract party / no charge |
| Bhada / bhaada | Freight or lorry hire (Hindi) |
| Lorry hire challan / hire memo / truck hire note / freight memo / hire slip | The document agreeing hire with a market-truck supplier, with advance and balance |
| Advance / balance | Part of freight or hire paid at loading / the rest paid after delivery and POD |
| Market vehicle / hired vehicle | A truck from another owner, hired for one load |
| Attached vehicle | A truck owned by someone else that runs regularly under our company, usually for a commission |
| Own vehicle | A truck in our fleet |
| Broker / agent / dalal; dalali | Middleman who arranges loads or trucks; his commission |
| Munshiana | Office or clerical charge deducted from hire by the booking office [Practice — unverified] |
| Hamali | Loading or unloading labour charge |
| Bata / bhatta / khuraki | Driver's daily allowance; khuraki = food allowance |
| Detention / halting / demurrage | Waiting charge for the truck beyond free time (detention, halting); demurrage on LRs often means a charge for goods not collected from the godown |
| Shortage / damage (kami) | Goods short or damaged on delivery; usually deducted from freight |
| Dharam kanta | Weighbridge |
| FTL / PTL | Full truck load / part truck load (parcel) |
| Manifest / challan / transit memo | List of LRs loaded on a vehicle between branches (PTL) |
| Owner's risk / carrier's risk | Who bears the risk of loss; carrier's risk costs extra (risk charge) |
| GTA / RCM / FCM | Goods Transport Agency; reverse charge (client pays GST); forward charge (GTA charges GST) |
| Annexure III / Annexure V | GTA forward-charge invoice declaration / yearly option to pay under forward charge |
| New Ref / Agst Ref / Advance / On Account | Tally bill-wise reference types |
| 26AS / AIS / Form 16A | Tax-credit statements and TDS certificate used to match TDS deducted by clients |
| ≤10 truck declaration | Transporter's declaration (with PAN) that he owns no more than 10 goods carriages, giving nil TDS (old 194C(6) → new s.393(4), verify) |
| Udyam | MSME registration number; triggers the 15/45-day payment rule |
| Vahan / Sarathi | National vehicle and driving-licence databases (MoRTH / NIC) |
| e-POD | Electronic proof of delivery (OTP, signature, photo, GPS) |

---

## 11. Document formats: field lists

Field order follows common Indian layouts. **M** = must have (law or universal
practice); **C** = common; **O** = optional or configurable.

### 11.1 Lorry receipt (LR / bilty / consignment note)

| Block | Fields |
|---|---|
| Header | M: transporter name, address, phone, **GSTIN**, PAN; C: logo, **Carriage by Road Act registration no.** [Practice — unverified], branch name and address; O: tagline, website |
| Title and copy | M: "CONSIGNMENT NOTE / LORRY RECEIPT"; **copy label** (CONSIGNOR / CONSIGNEE / DRIVER-LORRY / OFFICE); C: risk banner ("AT OWNER'S RISK" / "AT CARRIER'S RISK") |
| Identity | M: **LR no.** (series), LR date; C: booking branch, delivery branch, book no. (manual books); O: trip no., client order / indent / shipment no. |
| Route and vehicle | M: from (station), to (station), **vehicle no.**; C: driver name and mobile, vehicle type; O: via points, ETA |
| Parties | M: consignor name and address, consignee name and address; C: GSTINs, phones, **billing party** (if different); O: notify party |
| Goods | M: **description ("said to contain")**; C: no. of packages and type (bags, boxes, drums), private marks, actual weight, charged weight, HSN (optional); O: dimensions (ODC), hazardous class, seal / container no. |
| Commercial references | C: consignor invoice no(s)., date(s), **value(s)**; **e-way bill no(s).** and validity; O: PO no., ASN |
| Value and risk | C: declared value (from the GFN); risk (OR / CR); insurance (by, insurer, policy no., amount) |
| Charges | C: freight basis and rate, freight, LR / bilty charge, hamali, door collection / delivery, risk charge, other; GST (if FCM on LR); total; O: advance received, balance |
| Payment | M: **Paid / To-Pay / TBB / FOC**; M: **GST payable by: consignor / consignee / transporter** |
| Delivery | O: delivery type (godown / door), demurrage free days |
| Footer | C: remarks; terms (often on the back); signature of the booking clerk and company stamp; consignor's signature; **POD box**: received in good condition, packages received, shortage or damage, receiver name, signature, stamp, date and time |
| Digital extras | O: QR code to track or view the e-POD; generated-by / time; page "1 of N" |

### 11.2 Freight invoice / freight bill (single or consolidated)

| Block | Fields |
|---|---|
| Header | M: supplier name, address, **GSTIN**, state and code; C: PAN, logo, phone, email, branch; O: CIN, Udyam no. |
| Title | M: "TAX INVOICE" (or "BILL OF SUPPLY" if exempt); M: **copy label** (ORIGINAL FOR RECIPIENT / DUPLICATE FOR SUPPLIER) |
| Invoice identity | M: invoice no. (≤16 chars, unique per FY), date; M: **place of supply** (state); M: **"Tax payable on reverse charge: Yes/No"**; C: due date, credit days; O: PO / contract ref, bill period (consolidated), vendor code at client |
| Recipient | M: name, address, **GSTIN**, state and code; C: contact, billing GSTIN plant name |
| Line table (single LR) | M: description of service ("Transportation of goods by road"), **SAC (9965 / 996511)**; C: LR no., LR date, vehicle no., from–to, material, packages, weight (actual / charged), rate and basis, freight, detention, loading / unloading, other charges, line total |
| Line table (consolidated) | C: summary lines by charge type, plus **LR annexure** (one row per LR with the columns above), annexure totals tying to the invoice |
| Totals | M: taxable value; M: tax rate and amount (CGST / SGST or IGST, or "nil — RCM"); C: round-off, **grand total**, **amount in words**; O: less advance received → net receivable |
| Declarations | M (as applicable): RCM / FCM / Annexure III text (dated table); C: ≤10-truck TDS declaration note (if our user is a small owner); O: "E. & O.E." |
| Payment | C: bank name, account no., IFSC, branch; **UPI QR (dynamic)**; pay link |
| Terms | C: payment terms, interest on late payment, claims period, jurisdiction |
| Sign-off | M: signature or digital signature; C: "For <Company> – Authorised Signatory", stamp image |
| Attachments | O: POD copies, annexure, detention calculation sheet |

### 11.3 Lorry hire challan (truck hire note / freight memo)

| Block | Fields |
|---|---|
| Header | Company, branch, GSTIN, PAN; title "LORRY HIRE CHALLAN"; copy label (SUPPLIER COPY / OFFICE COPY) |
| Identity | Challan no., date, linked trip no., **linked LR no(s).** |
| Supplier | Supplier / owner name, mobile, address, **PAN**, ≤10-truck declaration status for the FY (Yes / No + date), GSTIN (if any), bank or UPI |
| Broker | Broker name, mobile, commission (₹ or %) |
| Vehicle and driver | Vehicle no., type / size, **RC owner name** (from Vahan), driver name, mobile, DL no. |
| Load | From, to, consignor, consignee, material, packages, weight (loaded), freight basis |
| Money | **Hire amount** (fixed or rate × weight); add: detention terms (free time, rate per day), loading / unloading if payable; less: **advance(s)** (date, mode, UTR, to whom), fuel given (slip nos.), commission, munshiana / office charge, TDS (section, rate, amount; or "nil — declaration on file"); **balance payable** |
| Terms | Balance paid N days after POD receipt; POD late fee; shortage or damage deducted at actual / contract value; late delivery penalty; supplier responsible for goods in transit; jurisdiction |
| Sign-off | Prepared by, approved by, **supplier / driver signature** (or in-app acceptance with OTP), date |

Sources: S1, S2, S15 (freight memo with advance and charge details), S17, S19 (owner,
PAN, vehicle), S5 (POD-based deductions and halting). Layout and terms are
[Practice — unverified].

### 11.4 Money receipt

| Block | Fields |
|---|---|
| Header | Company, branch, GSTIN; title "MONEY RECEIPT" / "RECEIPT VOUCHER" (a GST receipt voucher applies when an advance is received for a taxable FCM supply (verify)) |
| Identity | Receipt no. (own series), date |
| From | Received from (party name, GSTIN), on behalf of (if paid by someone else) |
| Amount | Amount in figures and **in words**; mode (cash / cheque / NEFT / RTGS / UPI); cheque no. and date, bank, **UTR** |
| Against | Bill-wise list: bill no., bill date, bill amount, amount adjusted; or "Advance against LR / trip"; or "On account"; **TDS deducted by party** (amount, section) shown so gross = cash + TDS; deductions (shortage etc.) if settled in this receipt |
| Balance | Outstanding after this receipt (optional) |
| Sign-off | Received by (name, signature or e-sign); note "Subject to realisation" for cheques |
| Driver collection variant | LR no., To-Pay amount, collected by driver X, vehicle, place, time and GPS (from the crew app) |

Source: [Practice — unverified]; the bill-wise logic follows S10 and the TDS treatment
follows S11.

---

## 12. Template-editor feature checklist

What Indian billing apps and invoice tools offer, and what we need. "Have" columns show
where each feature was seen (V = Vyapar, M = myBillBook, Z = Zoho, Q = QuickBooks,
O = Odoo with add-ons, T = Tally). A blank means not seen in this research, not
confirmed absent.

| # | Feature | Seen in | Must build? | Notes |
|---|---|---|---|---|
| 1 | Ready themes / layouts (several per document type) | V, M (80+), Z, Q, O (Light / Boxed / Bold / Striped) | Yes | Ship 4–6 transport-specific themes: Classic LR (boxed, Indian book look), Modern invoice, Compact A5, Thermal, Bilingual |
| 2 | Logo, colours, fonts | V, M, Z, Q, O | Yes | Font list must include **Indic and Arabic fonts (Noto family)** |
| 3 | Show / hide fields | V (HSN, MRP, unit), M, Q, O | Yes | Per template |
| 4 | Custom fields (add own fields) | V, M, Q (limited), Z | Yes | Typed (text / number / date / list); per document type; show-on-print flag |
| 5 | Rename labels | Z (labels per template), T | Yes | Also the route to other languages |
| 6 | Column order and width in item tables | Z (widths), O add-on (positions) | Yes | Drag to reorder; LR annexure columns too |
| 7 | Header / footer control (first page or all pages) | Z | Yes | Repeat the header on every page of long annexures |
| 8 | Header image / background / letterhead | Z (1 MB image), M | Yes | Option to print on **pre-printed letterhead** (blank header space) |
| 9 | Terms and declarations library | V, Z, Q | Yes | Reusable blocks; GTA declarations from a dated table |
| 10 | Signature and stamp image | V (signatory), O add-on | Yes | Per user or per branch |
| 11 | Watermark | M | Yes | "DRAFT", "CANCELLED", "COPY", "PAID" automatic watermarks |
| 12 | Tagline / attention box | M (tagline), Z (attention content) | Optional | |
| 13 | Bank details + **dynamic UPI QR** | V, O add-on, S46 | Yes | QR amount = balance due |
| 14 | Multiple copies with labels | T (Original … Extra copy) | Yes | Copy set per document type (LR 4 copies; invoice 2); print all copies in one PDF |
| 15 | Paper sizes A4 / A5 / Letter, portrait / landscape | V (A4 / A5), Z (A5 / A4 / Letter) | Yes | Add **Legal** and custom sizes for LR books |
| 16 | Thermal 2" (58 mm) and 3" (80 mm) | V (separate thermal set-up) | Yes | For phone Bluetooth printers in the B4A app (money receipts, LR on the road) |
| 17 | Language of labels (Hindi, regional) | Z, T, Swipe (labels only) | Yes | Labels translated; data typed in that language |
| 18 | **Bilingual** (English + Arabic or Hindi side by side) | Z (GCC bilingual, compact bilingual) | Yes (UAE) | Arabic needs **RTL** layout |
| 19 | Live preview with sample or real data | Z, Q, M | Yes | Preview each copy and each paper size |
| 20 | Default template per document type | Q, Z | Yes | |
| 21 | Default per **branch / GSTIN / client** | — (asked for in Zoho community; not seen) | Yes | Big clients want their own format; each branch has its own address |
| 22 | Duplicate a template | Q, Z | Yes | Then edit the copy |
| 23 | Template versions and rollback | — | Yes | An invoice prints with the template version used when it was issued |
| 24 | Prompt / AI-assisted editing | M (prompt-based custom theme) | Later | "Make the header blue and add our PAN" |
| 25 | Import a layout (Word) | Q | Later | |
| 26 | Email / WhatsApp message templates | Q (Emails tab), V (reminder template) | Yes | Placeholders: party, doc no., amount, link |
| 27 | Field positioning for **pre-printed stationery** | O add-on (positions) | Yes | Print only values at mm offsets onto old LR books and pre-printed bills [Practice — unverified need] |
| 28 | Number and date formats | — | Yes | Indian grouping (1,20,000.00); amount in words (lakh / crore); AED fils; date formats |
| 29 | Per-document-type blocks | — | Yes | LR: POD box, risk banner, copy label; invoice: GST summary by rate, RCM line; hire challan: advance table |
| 30 | Permissions | — | Yes | Only owner or admin edits templates; changes logged |
| 31 | Safe templating | O warns that QWeb edits break on upgrade | Yes | Store templates as data (JSON layout + **sandboxed** placeholders), never raw PHP or Blade code from users |
| 32 | Image / QR / barcode elements | O add-on, S46 | Yes | LR barcode or QR for scanning at the godown |
| 33 | Page break and "continued" rules | — | Yes | Long consolidated bills |
| 34 | Output formats | All (PDF) | Yes | PDF (A4 / A5), thermal text (ESC/POS), image (PNG for WhatsApp preview) |

**Rendering notes for our stack** [Practice — unverified]:
- Indic scripts (Hindi, Malayalam, Tamil) and Arabic need correct glyph shaping. Simple PHP PDF libraries often break conjuncts, so render HTML templates with a **Chromium-based** engine (for example a headless-Chrome service).
- On cheap thermal printers, Indic text usually has to be sent as a **raster image**, not as ESC/POS text. Plan for this in the B4A app.
- Per D14, the founder's Clinic Ledger invoicing and template editor should be studied first. Map its editor to the rows above and add the transport-only rows: copy sets, LR blocks, pre-printed positioning, bilingual layout and thermal output.

---

## 13. Spec-ready summary (what we must build)

### 13.1 Shared ledger engine (used by all five ledgers)
- Ledger view over the accounting book, with Dr/Cr and plain words ("will give / will get"), running balance, opening (total or bill-wise), closing and carry forward.
- Bill-wise references: New Ref, Against Ref, Advance, On Account; allocation and matching screen.
- Filters (date / FY, type, trip, truck, LR, branch, status); global search by LR, bill, truck, UTR or cheque.
- Statement PDF / Excel / CSV; share by WhatsApp (PDF or expiring link), SMS and email; regional-language labels.
- Reminders engine with templates and schedules.
- Attachments on every entry.
- Approvals (maker–checker by amount and type).
- Counter-party acknowledgement (OTP / tap).
- Cancel-not-delete, audit log, period lock.
- Excel import of openings; Tally export.
- One party can hold several roles, with contra set-off.

### 13.2 Driver Ledger
- Three buckets (trip cash, earnings, loan) plus net, in one personal ledger.
- Entries:
  - opening balance;
  - advances (all modes) with acknowledgement;
  - auto-posted approved trip expenses;
  - cash collected for the company (To-Pay);
  - deposits and returns;
  - crew-to-crew handover;
  - salary, bata/bhatta/khuraki (by role), incentives (trip, per km, diesel-saving), bonus, overtime, reimbursement;
  - recoveries (fines, cargo shortage, diesel shortage, missing items, rejected expenses) with basis, approval, driver notice and legal caps;
  - loans with EMI schedule (UAE: no interest, written consent);
  - payouts (bank batch, WPS);
  - trip settlement links and carry-forward lines;
  - security deposit, full & final settlement, write-off.
- Reports:
  - driver statement;
  - "My Khata" in the crew app with an entry-dispute flag;
  - all-drivers cash position;
  - advance ageing;
  - recoveries, loans, bata and salary registers.
- Reminders for unsettled cash and instalments; monthly statement on WhatsApp.

### 13.3 Client Ledger
- Client set-up:
  - group / parent;
  - several GSTINs and addresses;
  - contacts by role;
  - credit limit and credit days (warn / block / override) with due-date basis (invoice or submission);
  - billing cycle and bill-submission rules;
  - GST mode;
  - TDS behaviour;
  - our ≤10-truck declaration per FY.
- Rate contracts: route, material, truck type, basis, minimum weight, validity, versions, diesel escalation, detention slabs, extra charges, penalties; auto-fill on the LR.
- Entries:
  - unbilled LRs;
  - advances against LR or trip;
  - single and consolidated invoices;
  - supplementary bills;
  - bill-wise receipts;
  - per-bill TDS (26AS / AIS match, Form 16A tracking);
  - client deductions as credit notes or claims, linked to recovery from the driver or supplier;
  - credit / debit notes;
  - cheque bounce;
  - write-off, refund, contra.
- Pipeline queues: POD pending → ready to bill → billed not submitted → submitted / overdue → short-paid.
- Reports:
  - statement and bill-wise outstanding;
  - ageing by bill or due date with editable slabs;
  - unbilled revenue;
  - TDS reconciliation;
  - deductions analysis;
  - client profitability;
  - DSO and forecast;
  - yearly balance confirmation.
- Reminders (WhatsApp / SMS / email) and invoice share with a dynamic UPI QR. Client portal later.

### 13.4 Truck Ledger
- Cost-centre P&L per truck per month:
  - income (own freight, detention, recoveries, hire-out, attached-truck commission);
  - variable costs (fuel, AdBlue, tolls, bata, wages share, loading, fines);
  - maintenance (net of insurance claims);
  - tyres by cost per km;
  - fixed costs spread monthly (EMI interest, insurance, road tax, permits, fitness, PUC, GPS, FASTag, parking);
  - depreciation (book and tax);
  - overhead allocation (days / km / trips / revenue).
- KPIs:
  - km loaded and empty;
  - km/l vs standard;
  - revenue, cost and profit per km;
  - utilisation and idle days;
  - break-even km;
  - budget vs actual;
  - comparison and ranking;
  - life-to-date and profit on sale.
- Truck loan view: EMI schedule, principal and interest, outstanding, hypothecation and NOC.
- Truck register with drill-down; "truck report card" PDF.

### 13.5 Supplier Ledger (market vehicles)
- Supplier set-up:
  - type (owner, fleet operator, broker);
  - PAN verification;
  - penny-drop bank check;
  - vehicles with Vahan-checked RC owner;
  - ≤10-truck declaration per FY with upload and March reminder;
  - blacklist and rating;
  - default terms.
- Entries:
  - lorry hire challan (credits hire);
  - one or more advances (including cash to the market driver);
  - fuel given (linked to a vendor slip);
  - detention and extras payable;
  - deductions (shortage, damage, late delivery, late POD, back-to-back client claims, munshiana, commission);
  - broker commission;
  - automatic TDS decision (nil with declaration but still reported; 1% / 2% over thresholds; higher no-PAN rate; certificates);
  - balance payment gated on a verified POD.
- Reports: supplier and vehicle statements; hire register with margin per load; pending balance by POD status; advance-without-POD ageing; TDS register.
- Sharing: hire challan and statement over WhatsApp; POD photo upload link for suppliers.

### 13.6 Vendor Ledger
- Vendor set-up:
  - category;
  - GSTIN, PAN;
  - Udyam no. and type;
  - payment terms with written-agreement flag;
  - default TDS section and ITC flag;
  - pump location;
  - price agreements.
- Entries:
  - purchase or expense bills with ITC flag and cost centres;
  - **fuel slip book + pump statement import with auto-match and exception list**;
  - workshop estimate approval → job card → bill;
  - tyre bills creating tyre records, with warranty credit notes;
  - insurance premium → policy + prepaid spread + claim tracking;
  - RTO agent fees linked to document renewals;
  - payments (bill-wise, advance, batch bank file, cheque, payment advice);
  - debit notes;
  - TDS by nature (verify new-Act mapping).
- Controls:
  - MSME due dates (15 / 45 days) with alerts and the year-end disallowance list;
  - GSTR-2B / IMS matching;
  - 180-day unpaid ITC reversal alert (verify);
  - country fuel-tax rules.
- Reports: payables ageing; vendor statement reconciliation; balance confirmation.

### 13.7 Document Manager
- One module for documents of trucks, crew, company, clients, suppliers and vendors, with country default types (8.1) and company-defined types.
- Record fields: number, authority, dates, cost, vendor, files (multi-page), location of the original.
- Reminders: days-before list, repeat after expiry, channels including WhatsApp templates, snooze with a reason.
- Compliance dashboard and calendar; per-truck document health.
- Renewal creates a new version, with history; renewal cost posts to the truck ledger (prepaid spread). Dispatch gate already exists.
- Share one document or a pack by WhatsApp (PDF or expiring link, with a watermark); offline documents in the crew app.
- Bulk upload with file-name linking; OCR auto-fill with confidence and a review queue.
- Vahan RC fetch, Sarathi DL fetch, DigiLocker pull (consent) and e-challan check, all through an aggregator interface (Surepass / Gridlines / Cashfree / IDfy) that can be switched.
- Storage: quota per plan, compression, versions kept; role-based access, masked Aadhaar, view and download log.
- Insurance claim tracker.

### 13.8 Invoices & LR
- **LR:**
  - fields (11.1);
  - configurable copy set (default 4 labelled copies);
  - series per branch per FY;
  - auto or manual numbering with an LR book register (ranges, issued-to, leaf status, gap and duplicate report);
  - Paid / To-Pay / TBB / FOC;
  - OR / CR with risk charge and declared value;
  - insurance;
  - GST payable by;
  - several invoices and EWBs per LR;
  - LR charges;
  - e-POD (OTP, signature, stamp photo, GPS, shortage, unloading times) and physical POD tracking;
  - back-side terms;
  - PTL manifest later.
- **Freight invoice:**
  - single-LR, consolidated multi-LR with annexure and POD bundle, supplementary bills;
  - charge lines;
  - less-advance net line;
  - dated GTA declaration table (RCM / FCM 5% / FCM 18% Annexure III / bill of supply);
  - Indian-format amount in words;
  - bank and dynamic UPI QR;
  - terms, signature and stamp;
  - **2 legal copies for services (Original for Recipient / Duplicate for Supplier)** plus optional extra copies;
  - bill submission tracking;
  - UAE tax invoice, optionally bilingual.
- **Other documents:** lorry hire challan, loading slip, trip sheet, money receipt (bill-wise, with TDS), payment voucher, credit / debit notes, quotation / rate confirmation, delivery challan, manifest, statement of account, salary slip and settlement statement.
- **Template editor:** every "Yes" row in section 12, including:
  - themes;
  - logo, colours and Indic / Arabic fonts;
  - show / hide;
  - custom fields;
  - label renaming and language;
  - bilingual RTL;
  - column order and width;
  - header / footer rules;
  - letterhead mode;
  - terms library;
  - signature and stamp;
  - watermarks;
  - UPI QR;
  - copy sets;
  - A4 / A5 / Letter / Legal / custom and thermal 58 / 80 mm;
  - live preview;
  - defaults per document type, branch, GSTIN and client;
  - duplicate, versions and rollback;
  - message templates;
  - pre-printed stationery positioning;
  - Indian number formats;
  - sandboxed templates;
  - barcode / QR;
  - page-break rules;
  - PDF, ESC/POS and PNG output.

### 13.9 Data-model additions (extend `06-data-model.md`)

| Table | Key columns |
|---|---|
| `party_roles` | party, role (client / supplier / vendor / broker / consignor / consignee), active; replaces the single `type` on `parties` |
| `party_gstins` | party, GSTIN, state, address, default flag |
| `party_credit_terms` | party, credit limit, credit days, due-date basis (invoice / submission), breach action |
| `rate_contracts`, `rate_contract_lines` | party, version, validity, approval; route, material, truck type, basis, rate, minimum weight, detention slabs JSON, escalation JSON, penalties JSON (extends `route_rates`) |
| `tds_declarations` | party (supplier, or our company towards a client), FY, trucks owned, PAN, file, received_on, direction (received / given) |
| `bill_refs` | voucher line, ref type (new / against / advance / on_account), ref no., due date, amount; the basis of bill-wise outstanding |
| `bill_submissions` | invoice, submitted_on, mode, to, ack no., portal ref, courier docket |
| `client_deductions` | invoice / receipt, type (shortage / damage / late / POD late / rate), amount, evidence, credit_note_id, recover_from (driver / supplier), recovery_id |
| `lr_books`, `lr_book_leaves` | branch, book no., from–to, issued to, issued on; leaf no., status (unused / used / cancelled / lost), consignment_id |
| `lr_copy_sets` | document type, copy order, label, colour hint |
| `hire_challans` | number, date, trip, supplier, vehicle, driver, hire basis and amount, terms JSON, broker, commission, TDS decision, status |
| `hire_challan_lines` | challan, type (advance / fuel / detention / extra / deduction / commission / TDS / balance), amount, voucher_id |
| `supplier_vehicles` | supplier, reg no., type, RC owner name, Vahan check JSON, checked_at |
| `driver_ledger_buckets` (or a `bucket` column on voucher lines for person accounts) | cash / earnings / loan |
| `person_loans`, `person_loan_installments` | person, principal, interest, consent file; due month, amount, paid voucher |
| `recoveries` | person or supplier, type, basis, amount, approved_by, notice_sent_at, cap check result |
| `fuel_slips` | slip no., book, truck, driver, pump vendor, litres / amount, issued_at, trip_event_id, statement_line_id, status |
| `vendor_statements`, `vendor_statement_lines` | vendor, period, file; line date, slip no., vehicle, litres, rate, amount, match status |
| `documents`, `document_versions` | one generic table replacing `truck_documents` and `person_documents`: owner type and id, type, number, authority, dates, cost, vendor, original location; versions with files and renewal voucher |
| `document_reminder_rules` | document type, days-before list, repeat rule, channels, recipients |
| `verification_checks` | entity, provider, check type (RC / DL / PAN / bank / DigiLocker / challan), request, response JSON, cost, at |
| `print_templates`, `print_template_versions` | document type, name, scope (company / branch / GSTIN / client), default flag, paper, copy set, layout JSON, language(s), version, created_by |
| `message_templates` | channel (WhatsApp / SMS / email), purpose, language, body with placeholders, provider template id |
| `gst_declaration_texts` | country, mode (RCM / FCM5 / FCM18 / exempt), text, valid_from, valid_to |
