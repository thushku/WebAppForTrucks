# India: legal, tax, accounting, labour and data-protection requirements for a transporter accounting + trip-management SaaS (as of October 2026)

> **How these notes were gathered, and their limits (please read first).**
> - Research date: 13 October 2026. All findings come from **web-search result summaries** (about 27 searches). **WebFetch failed with DNS errors (`getaddrinfo ENOTFOUND`) for every domain tried**, including cbic-gst.gov.in, taxinformation.cbic.gov.in, pib.gov.in, uidai.gov.in, incometaxindia.gov.in, taxguru.in, cleartax.in and taxtmi.com. The **shared web-search budget ran out** before the last batch of queries (OSH Central Rules 2026 text, Code on Wages Central Rules, 2026 DPDP updates, Annexure V deadline, MoRTH driver-hours/AC-cabin rule). So **no primary text (notification, Act or Rule) was read in full**. Most citations are secondary commentary, as summarised by the search engine.
> - Each **Gaps** subsection has a list marked **"Background knowledge, NOT verified in this session"**. These are well-known statutory points I am fairly confident of but could not source here. The report writer should present them as "to be verified" or check them against the primary source before relying on them.
> - Legend: **[NEW 2025-26]** = changed in 2025 or 2026; **[UNCERTAIN]** = sources conflict or are thin.

---

## 1. GST on road transport: GTA definition, rates and options after GST 2.0 (22 Sep 2025), RCM vs FCM, ITC, Annexure V, exemptions, SAC codes, place of supply

### Takeaway
A **GTA** is a road transporter that **issues a consignment note** ("bilty"/LR). Since **22 September 2025** a GTA has three options. **(a) 5% under reverse charge (RCM)**, paid by specified recipients; this is the default. **(b) 5% forward charge (FCM) without ITC.** **(c) 18% forward charge with full ITC**, which replaced the old 12% option **[NEW 2025-26]**. A GTA chooses FCM by filing **Annexure V** before the financial year starts, and the choice is locked for the whole year. An **individual truck owner who does not issue a consignment note is not a GTA**, and their road-transport service is exempt (Notification 12/2017-CT(R) entry 18). Hiring a vehicle out to a GTA is also exempt (entry 22). The software must carry a per-company, per-financial-year "GTA tax regime" setting that drives the tax computation, the invoice wording and the return mapping.

### Cited Findings
- **Rate change [NEW 2025-26]:** Under GST 2.0, effective **22 September 2025**, the old **12% forward-charge option for GTAs was replaced by 18% with full ITC**. The **5% option without ITC continues**. If the GTA has not opted for forward charge through Annexure V, the recipient pays **5% under RCM**. — [Busy: GST on freight 2026](https://busy.in/gst-rates/freight-charges/); [TaxClue: GST on Freight & GTA 2025-26](https://taxclue.in/guide/gst-on-freight); [TaxGarden GTA RCM 2026](https://taxgarden.in/blog/gst-on-transportation-freight-gta-rcm-india-2026)
- **Official confirmation:** The PIB FAQ on the 56th GST Council decisions states that the "GTA will have the option of charging GST at the standard rate of 18% with full ITC". It also says **multimodal transport of goods is taxed at 5% with restricted ITC**, provided no transport by air is involved. — [PIB FAQs on 56th GST Council decisions](https://www.pib.gov.in/PressReleasePage.aspx?PRID=2163560&reg=3&lang=2) (seen as a search snippet only; the page could not be fetched)
- **Notification number:** The search results never named the notification that implements the 18% GTA option. I believe it is Notification 15/2025-CT(Rate) dated 17.09.2025, but this was **not confirmed**. — (no source found; see Gaps)
- **How the option works:** The option is **locked for the full financial year** once Annexure V is filed. **If Annexure V is not filed by the deadline, the GTA defaults to RCM for that year.** — [Patron Accounting: GST returns for GTA 2026](https://www.patronaccounting.com/gst-returns-for-goods-transport-agency-gta); [IndiaFilings: Annexure V declaration](https://www.indiafilings.com/learn/filing-of-annexure-v-declaration-by-goods-transport-agency)
- **Annexure V deadline [UNCERTAIN]:** One guide says the deadline is **31 March of the preceding FY**, extended from 15 March after the **50th GST Council meeting (11 July 2023)**. Several other sources still say **15 March**. — [Patron Accounting](https://www.patronaccounting.com/gst-returns-for-goods-transport-agency-gta); contradicted by [IndiaFilings](https://www.indiafilings.com/learn/filing-of-annexure-v-declaration-by-goods-transport-agency) and [TaxGuru: Advisory on opting for FCM by GTA](https://taxguru.in/goods-and-service-tax/advisory-opting-gst-payment-charge-mechanism-gta.html)
- **GTA definition:** A GTA is a person who provides road transport of goods **and issues a consignment note**. **Individual transporters who do not issue a consignment note fall under entry 18 of Notification 12/2017 and are exempt from GST.** — [TaxGuru: GST implication on goods transport by road](https://taxguru.in/goods-and-service-tax/gst-implication-goods-transport-road-gta.html); [West Bengal Commercial Taxes FAQ on GTA](https://comtax.wb.gov.in/GST/GST_FAQ/GOODS_TRANSPORT_AGENCY_UNDER_GST.pdf); [GST Council FAQ: Transport & Logistics](https://www.gstcouncil.gov.in/sites/default/files/2024-02/27122017faq_transportandlogistic.pdf)
- **Entry 18:** Road transport of goods is exempt when the provider is **neither a GTA nor a courier agency**. — [TaxGuru: GST on transport of goods by road other than GTA](https://taxguru.in/goods-and-service-tax/gst-transport-goods-road-service-gta.html)
- **Entry 21:** This entry exempts GTA freight for specified goods, such as **agricultural produce**. **Notification 04/2022-CT(Rate) dated 13.07.2022 omitted clauses (b) and (c) of entry 21.** — [TaxGuru: GST implications on transportation of goods by road and GTA](https://taxguru.in/goods-and-service-tax/gst-implications-transportation-goods-road-gta.html)
- **Entry 21A:** This entry was inserted by **Notification 32/2017-CT(Rate) dated 13.10.2017**. — [TaxGuru](https://taxguru.in/goods-and-service-tax/gst-implications-transportation-goods-road-gta.html)
- **Entry 22:** Giving a means of road transport **on hire to a GTA is exempt** under entry 22 of Notification 12/2017. — [TaxGuru: GTA under GST](https://taxguru.in/goods-and-service-tax/goods-transport-agency-gta-gst.html)
- **Renting goods carriages (SAC 9966), earlier position:** Renting a vehicle with operator for goods transport on a time basis falls under **Heading 9966 at 18%**. Where the **fuel cost is included**, the rate was **12%**, a change made at the **47th GST Council**. — [CBIC Circular 177/09/2022-TRU](https://cbic-gst.gov.in/pdf/cir-177-08-2022-cgst.pdf); [PIB: 47th GST Council recommendations](https://www.pib.gov.in/PressReleasePage.aspx?PRID=1838020&reg=48&lang=2)
- **Renting goods carriages, after GST 2.0 [UNCERTAIN]:** Secondary sources say the 12% entry for renting goods carriages with fuel **moved to 18% with ITC, with a 5% alternative (ITC of input services only)**. This is **not confirmed by the PIB FAQ snippet**. — [Busy: SAC 9966](https://busy.in/sac-code-9966/); [A&M: 56th GST Council alert](https://www.alvarezandmarsal.com/thought-leadership/56th-gst-council-meeting-alert)
- **SAC 9965:** This heading covers "Goods transport services". — [Busy: SAC 9965](https://busy.in/sac-code-9965/) (secondary source)
- **SAC 9966:** This heading covers "Rental services of transport vehicles with or without operators". — [IncorpX: SAC heading 9966](https://www.incorpx.io/sac-codes/heading-9966) (secondary source)

### Inferences
- The software needs a **"GST regime for GTA services" master per GSTIN per FY**, with the values `RCM-5%`, `FCM-5% (no ITC)` and `FCM-18% (full ITC)`, plus a stored Annexure V filing date and acknowledgement. When the regime is FCM-5%, the purchase module should **block or flag ITC**, except on input services in the same line of business (see Gaps).
- **Per-invoice tax determination** has to look at the recipient type. Use RCM if the recipient is one of the specified categories (registered person, factory, body corporate, firm and so on) and the GTA has not opted for FCM. Use exempt if the recipient is unregistered under entry 21A, or if the goods are exempt (for example agricultural produce). Otherwise use FCM. The UI needs a **"goods category" field on the consignment** (agricultural produce, milk/salt/food grain, relief material and so on) so exemptions can be applied.
- **Owner-operators** who do not issue an LR, and who only rent trucks to GTAs, are exempt (entries 18 and 22). The app should support an **"unregistered / exempt transporter" profile**: no GST, but still double-entry books and TDS-declaration handling.
- Keep an **SAC master** that includes 9965/996511, 996791 and 9966/996601. Each code should be **effective-dated** so that rates for documents before and after 22.09.2025 compute correctly, for example on credit notes and amendments to pre-September 2025 invoices.

### Gaps
- I could not read the notification text that implements the 18% option (believed to be Notification 15/2025-CT(Rate)), and the PIB FAQ could not be fetched.
- **The current Annexure V deadline (15 March or 31 March) is unresolved**, as is the rule for new GTAs.
- I could not confirm which clauses were omitted from entry 21 by Notification 04/2022. I believe they were the ₹1,500 single-carriage and ₹750 single-consignee thresholds, which would mean that exemption **no longer exists**. The research brief assumed it still applies, so this must be checked.
- **Background knowledge, NOT verified in this session:**
  - *GTA definition:* Explanation to Notification 11/2017 and 12/2017 (as amended by Notification 03/2022-CT(R), effective 18.07.2022): "goods transport agency means any person who provides service in relation to transport of goods by road and issues consignment note, by whatever name called".
  - *Who pays under RCM:* RCM recipients under Notification 13/2017-CT(R) entry 1 are any factory under the Factories Act, any society under the Societies Registration Act, any co-operative society, any person registered under CGST/SGST/UTGST, any body corporate, any partnership firm or AOP, and any registered casual taxable person. The recipient pays 5% under RCM and can generally claim that tax as ITC under section 16. Since 18.07.2022, RCM does not apply when the GTA has opted for FCM.
  - *ITC under the 5% option:* The condition for the 5% option (Notification 11/2017 entry 9(iii)) is that no ITC is taken on goods and services used, **except input services in the same line of business**, that is, GTA services bought from another GTA.
  - *Exemption entries:* Entry 21A exempts GTA services to **unregistered persons** (including unregistered casual taxable persons) other than the specified categories. Entry 21B exempts GTA services to **government departments or local authorities registered only for TDS**. Entry 21 still covers agricultural produce; milk, salt and food grain including flour, pulses and rice; organic manure; newspapers and magazines registered with RNI; relief material; and defence or military equipment.
  - *Forms:* Annexure V is the option to pay under FCM, filed online on the GST portal since 2023. Annexure VI is the declaration to revert to RCM.
  - *Place of supply:* For transport of goods, place of supply is governed by **IGST Act section 12(8)**. It is the **location of the recipient if registered**, otherwise the **location where the goods are handed over for transport**. Inter-state or intra-state status, and therefore IGST or CGST+SGST, follows from this.
  - *SAC codes:* **996511** covers road transport of goods (including by trucks and trailers), **996791** covers GTA services for road transport, and **996601** covers rental of road vehicles for goods with operator.

---

## 2. Tax invoice contents (Rule 46 CGST Rules), invoice numbering, credit/debit notes, consignment note

### Takeaway
Rule 46(b) requires a **consecutive serial number of at most 16 characters**, using letters, digits, "-" and "/" only, in one or more series, and **unique within the financial year**. An invoice must also show whether **tax is payable on reverse charge**, along with GSTINs, HSN/SAC, place of supply, signature, and QR/IRN where applicable. The software must enforce the numbering pattern and the RCM declaration, and must emit a GTA consignment note (LR) for every trip.

### Cited Findings
- **Rule 46(b):** The serial number may not exceed **sixteen characters**, may use "alphabets or numerals or special characters hyphen or dash and slash", may run in **one or multiple series**, and must be **unique for a financial year**. — [CBIC: Rule 46 text](https://taxinformation.cbic.gov.in/content/html/tax_repository/gst/rules/cgst_rules/active/chapter6/rule46_v1.00.html) (via search snippet); [TaxGuru: Commentary on tax invoice number](https://taxguru.in/goods-and-service-tax/commentary-tax-invoice-number-gst.html)
- **Format consistency:** Mixing formats such as INV-1 and INV-001 in one series is not a breach of Rule 46(b), but it complicates sorting and reporting. Numbering does **not** have to restart each year, as long as numbers are unique within the FY. — [Mavoin: Changing invoice series mid-year](https://www.mavoin.com/blog/change-invoice-number-series-mid-year-gst); [TaxReply: restarting invoice number each FY](https://taxreply.com/gst/Is_it_mandatory_to_restart_GST_Invoice_Number_from_1_in_every_Financial_Year_-1535.html)
- **Mandatory contents:** The required particulars include supplier and recipient GSTINs, serial number, date, HSN/SAC, quantity, value, tax details, **place of supply**, **reverse charge**, **signature**, and **QR/IRN** where e-invoicing applies. — [IndiaFilings: GST invoice format](https://www.indiafilings.com/learn/gst-invoice-format-rules); [Fintaxblog: Rule 46](https://fintaxblog.com/rule-46-of-cgst-rules-2017-tax-invoice/)
- **GTA status depends on the LR:** A GTA is defined by **issuing a consignment note**, so the LR/bilty is the document that makes a transporter a GTA. — [TaxGuru](https://taxguru.in/goods-and-service-tax/gst-implication-goods-transport-road-gta.html)

### Inferences
- **Invoice numbering engine:**
  - Configurable prefix per series, for example per branch or per GSTIN.
  - **Hard validation of the 16-character limit** and the allowed character set.
  - A uniqueness check per GSTIN per FY.
  - No gaps or reuse once a number is issued. Cancelled numbers stay as "cancelled" records.
  - Separate series for tax invoices, bills of supply (exempt), credit notes, debit notes, receipt and refund vouchers, self-invoices for RCM purchases from unregistered persons, and LRs.
- **Invoice template:**
  - A mandatory "Whether tax is payable on reverse charge: Yes/No" field.
  - An auto-generated RCM declaration when the regime is RCM-5%.
  - The **FCM declaration text** when the GTA has opted for FCM.
  - Place-of-supply state name and code.
  - Print of IRN and QR when e-invoicing applies.
- **LR (consignment note) module:** Every trip or consignment generates a numbered LR. LR data (consignor, consignee, vehicle number, origin, destination, goods description, weight, freight, who pays GST) should flow into the freight invoice. One invoice can cover many LRs (monthly billing).

### Gaps
- The full clause-by-clause text of Rule 46, and the current amendment history, was not read.
- **Background knowledge, NOT verified in this session:**
  - *Rule 46 particulars (a)-(s):*
    - Supplier name, address and GSTIN.
    - Serial number and date.
    - Recipient name, address and GSTIN.
    - For unregistered recipients with value above ₹50,000: name, address, delivery address, and state name and code.
    - HSN/SAC and description.
    - Quantity and unit.
    - Total value and taxable value after discounts.
    - Tax rate and amount, split into CGST, SGST/UTGST, IGST and cess.
    - Place of supply with state name for inter-state supplies.
    - Delivery address if different.
    - Whether tax is payable on reverse charge.
    - Signature or digital signature.
    - QR code for e-invoices.
  - *Credit and debit notes:* These are governed by **section 34 and Rule 53**. A credit note must be declared **no later than 30 November following the end of the FY**, or the date of the annual return if earlier.
  - *LR contents:* There is **no statutory GST format for the consignment note**. The old Service Tax Rule 4B listed: serial number, names of consignor and consignee, registration number of the goods carriage, details of goods, origin, destination, and the person liable to pay tax. This is still standard practice.

---

## 3. E-invoicing (IRN/QR): threshold, 30-day reporting limit, GTA status

### Takeaway
E-invoicing applies to B2B supplies by taxpayers whose **aggregate annual turnover (AATO) exceeded ₹5 crore** in any FY since 2017-18, effective **1 August 2023**. Taxpayers with **AATO ≥ ₹10 crore cannot report invoices older than 30 days** to the IRP, effective **1 April 2025** **[NEW 2025-26]**. However, a **GTA supplying road transport services is an exempt class under Notification 13/2020-CT**, so a pure GTA does **not** need IRNs at any turnover. It is disputed whether the exemption covers a GTA's other supplies, such as vehicle rental or warehousing.

### Cited Findings
- **The ₹5 crore threshold:** E-invoicing is compulsory for registered businesses whose **AATO crossed ₹5 crore in any FY from 2017-18**, effective **1 August 2023**. — [Accountune: e-invoice limit FY 2026-27](https://accountune.com/e-invoicing-compulsory-india-small-business-2026); [AI Accountant applicability checker FY 2026-27](https://www.aiaccountant.com/resources/e-invoice-applicability-checker); [GSTSafar](https://gstsafar.com/e-invoice/)
- **The 30-day limit:** GSTN's advisory of **5 November 2024** lowered the 30-day reporting limit from AATO ≥ ₹100 crore (in force since the 13 September 2023 advisory) to **AATO ≥ ₹10 crore, from 1 April 2025**. It applies to **invoices, credit notes and debit notes**. A late document is **rejected by the IRP**, so no IRN is issued. — [Taxscan](https://www.taxscan.in/e-invoices-30-days-not-allowed-on-irp-portals-taxpayers-10-crore-aato-gstn/454070); [TaxReply: GSTN advisory](https://taxreply.com/gst/GSTN_Advisory__Time_Limit_for_Reporting_e-Invoice_on_the_IRP_Portal_-_Lowering_of_Threshold_to_AATO_10_Crores_and_Above-1455.html); [SAG Infotech: Advisory 543](https://blog.saginfotech.com/gst-advisory-no-543-no-permission-report-older-e-invoices-businesses-aato-10-crores)
- **Still current:** Sources dated mid-2026 to late September 2026 report **no further change** to the ₹5 crore threshold or the ₹10 crore band for the 30-day rule. — [AI Accountant](https://www.aiaccountant.com/resources/e-invoice-applicability-checker); [GSTIN API blog](https://gstinapi.com/blog/e-invoice-30-day-reporting-limit)
- **GTA exemption:** **Notification 13/2020-CT dated 21.03.2020**, issued under Rule 48(4), exempts several classes from e-invoicing **irrespective of turnover**. These include insurers, banks, financial institutions, NBFCs, **a goods transport agency supplying services in relation to transportation of goods by road in a goods carriage**, and passenger transport service suppliers. — [TaxGuru: e-invoice exemption notified](https://taxguru.in/goods-and-service-tax/electronic-invoice-gst-cbic-notifies-exemption.html); [CGST Hyderabad Zone e-invoice FAQ](https://cgsthyderabadzone.gov.in/pages/news/1248einvoicefaq.pdf)
- **Scope of the GTA exemption [UNCERTAIN]:** A clarification quoted on a forum says the exemption applies "for the entity as a whole and is not restricted by the nature of supply". A summary site says it covers "only GTA service". — [TaxTMI forum](https://www.taxtmi.com/forum/issue?id=118308); contradicted by [GSTSafar](https://gstsafar.com/e-invoice/)
- **Exemption declaration:** There is a facility for exempt entities to file an **e-invoice exemption declaration** on the portal. — [West Bengal CT: Manual on e-invoice exemption declaration](https://comtax.wb.gov.in/GST/e-invoicing/Manual%20on%20e-Invocie%20Exemption.pdf)

### Inferences
- The software needs an **e-invoice applicability flag** per GSTIN, computed from AATO history and an "exempt category" setting such as "GTA (Notif. 13/2020)".
- **For GTA freight invoices, IRN is not required** whether the GTA is on RCM or FCM. The research brief asked whether a GTA on RCM must e-invoice; the answer is no.
- Non-GTA entities still need IRP integration. Examples:
  - A fleet owner company above ₹5 crore that rents trucks to a GTA (exempt supply, so no IRN) or **sells scrap or old trucks** (taxable goods, B2B).
  - Mixed-activity companies, depending on the disputed scope of the exemption.
- For those entities, provide IRP integration (direct API or through a GSP), QR printing, cancellation within 24 hours, and a **30-day ageing alert when AATO ≥ ₹10 crore**.

### Gaps
- The consolidated current text of Notification 13/2020-CT (amended many times, last confirmed amendment 10/2023-CT) and official CBIC wording on the GTA exemption could not be fetched.
- **Background knowledge, NOT verified in this session:** IRN cancellation is allowed within 24 hours on the IRP. E-invoicing applies to B2B and export supplies, not B2C.

---

## 4. E-way bill rules relevant to transporters

### Takeaway
Goods consignments **above ₹50,000** need an e-way bill (EWB). **Validity is 1 day per 200 km**, or 1 day per 20 km for over-dimensional cargo (ODC). **Part B (vehicle number) is mandatory for road movement**, except intra-state movement of 50 km or less from the consignor to the transporter's place of business. EWBs can be consolidated into **EWB-02**. **From 1 January 2025, an EWB cannot be generated for documents older than 180 days, and total extension is capped at 360 days** **[NEW 2025-26]**. Detention under **section 129** carries a penalty of **200% of tax** when the owner comes forward. The transporter's software must track EWB numbers per LR and vehicle, update Part B on vehicle change, and raise alerts before expiry.

### Cited Findings
- **Threshold:** **₹50,000** consignment value, with inter-state and intra-state variations. — [IndiaPost.org: e-way bill limit and rules 2026](https://indiapost.org/e-way-bill-limit-and-rules)
- **Validity:** **1 day per 200 km** for regular cargo and **1 day per 20 km for ODC**. For example, 280 km gives 2 days. Validity starts when **Part B is first entered**, though sources disagree on whether later Part B updates recalculate it. — [Busy: EWB distance and validity](https://busy.in/gst/computing-distance-covered-determining-eway-bill-validity-its-extension/); [ClearTax: EWB rules](https://cleartax.in/s/eway-bill-gst-rules-compliance)
- **Part B:** Under Rule 138, an EWB is **not valid for road movement unless Part B has been furnished**. The exception is intra-state movement of **50 km or less from the consignor's place of business to the transporter's place of business** (raised from 10 km). Part B then has to be updated before onward movement. — [Busy](https://busy.in/gst/computing-distance-covered-determining-eway-bill-validity-its-extension/); [IndiaPost.org](https://indiapost.org/e-way-bill); [Legalsuvidha](https://legalsuvidha.com/blog/e-way-bill-transporters-using-common-enrollment-number-for-generation-of-e-way-bill)
- **Extension:** The transporter can extend validity **within 8 hours before or after expiry**, giving a reason and updated Part B details. — [Busy](https://busy.in/gst/computing-distance-covered-determining-eway-bill-validity-its-extension/)
- **2025 limits [NEW 2025-26]:** From **1 January 2025**, the e-way bill system **does not allow generation for documents dated more than 180 days earlier**, and **extension is capped at 360 days from the original generation date**. — [Busy](https://busy.in/gst/computing-distance-covered-determining-eway-bill-validity-its-extension/); [Roado: EWB rules for transporters 2026](https://www.roado.tech/blog/e-way-bill-rules-in-india-a-complete-compliance-guide-for-transporters-2026/); [Accountune: EWB 2026 guide](https://accountune.com/e-way-bill-2026-complete-guide/)
- **Consolidated EWB-02:** Multiple consignments in **one vehicle** can be grouped into a consolidated **EWB-02**. Each consignment above ₹50,000 still needs its own EWB-01, and all must carry the same Transporter ID. A **multi-vehicle** facility exists for splitting a consignment across vehicles. — [TaxGST.in: multi-vehicle and consolidated EWB-02](https://taxgst.in/how-to-generate-multi-vehicle-consolidated-e-way-bills-form-gst-ewb-02/); [ICAI Handbook on e-way bill](https://idtc-icai.s3.ap-southeast-1.amazonaws.com/download/pdf20/Handbook-on-E-way-Bill-under-GST.pdf)
- **Common Enrolment Number:** Transporters registered in multiple states can obtain a single **Common Enrolment Number** to use as their transporter ID. — [Legalsuvidha: CEN for transporters](https://legalsuvidha.com/blog/e-way-bill-transporters-using-common-enrollment-number-for-generation-of-e-way-bill)
- **Section 129:** Goods and the conveyance can be **detained**. For taxable goods the penalty is **200% of tax payable** where the owner comes forward. Separately, transporting goods without the required documents attracts a penalty of **₹10,000 or the tax evaded, whichever is higher** (a different provision, section 122). — [Bajaj Finserv: EWB penalty](https://www.bajajfinserv.in/eway-bill-penalty); [Telangana CT training deck on e-way bills](https://www.tgct.gov.in/tgportal/staffcollege/DR%20ACTOs%2017.01.2020%20to%2018.02.2020/February%20-%202020%20%20PDF's/01.02.2020,%202.%20R.Yedukondalu%20Sir,%20Deputy%20Commissioner%20(ST),%20E-Way%20Bills.pdf)

### Inferences
- **Trip and LR records:**
  - Store each **EWB number, generation date, validity end, Part A/Part B status and vehicle number**, with multiple EWBs per trip and a consolidated EWB-02 number.
  - **Auto-compute validity** from distance (200 km per day, 20 km per day for ODC) and alert the dispatcher and driver app before expiry. The extension window is ±8 hours.
- **Vehicle change:** On transhipment or breakdown, require a **Part B update** and keep the history.
- **Transporter profile:** Store the **transporter ID (TRANSIN/GSTIN) and Common Enrolment Number**. For unregistered owner-operators, the transporter ID comes from enrolment on the EWB portal.
- **Integration:** Provide EWB API integration (via GSP or NIC) for **Part B update, consolidated EWB generation, extension and rejection**. Block EWB generation for documents older than 180 days.
- **Driver app:** Show the EWB PDF or number for roadside checks, since the conveyance can be detained.

### Gaps
- I did not read the Rule 138 text or the EWB portal release notes.
- **Background knowledge, NOT verified in this session:**
  - *Who generates:* Under Rule 138(1)-(3), the **registered consignor or consignee generates Part A**. **The transporter must generate the EWB if neither has**, where value exceeds ₹50,000. The transporter updates Part B.
  - *Two-factor authentication:* **2FA became mandatory for EWB/e-invoice portal login** in 2025, phased by AATO (₹20 crore+ from 1 February 2025, then all taxpayers from 1 April 2025).
  - *Section 129 when the owner does not come forward:* The penalty is **50% of the value of goods or 200% of tax, whichever is higher**.
  - Penalty for not carrying an EWB under section 122(1)(xiv).
  - Exact current exemption lists (Rule 138(14)) and state-specific intra-state thresholds, including the definition of consignment value.

---

## 5. GST returns a transporter files (GSTR-1, GSTR-3B, IMS) and data the software should export; GST record retention

### Takeaway
A registered transporter files **GSTR-1/IFF** (outward supplies), **GSTR-3B** (summary return with tax payment) and the annual **GSTR-9/9C** where applicable. It also uses the **Invoice Management System (IMS)** to accept or reject inward invoices before ITC flows into GSTR-3B. **From the July 2025 tax period, outward-liability Tables 3.1 and 3.2 of GSTR-3B are hard-locked**, auto-filled from GSTR-1/1A/IFF and not editable **[NEW 2025-26]**. Corrections must go through **GSTR-1A** before GSTR-3B is filed. RCM inward liability (Table 3.1(d)) stays editable. The software must produce GSTR-1-ready JSON, reconcile against GSTR-2B and IMS, and post RCM liability and ITC correctly.

### Cited Findings
- **Hard lock [NEW 2025-26]:** GSTN's advisory of **7 June 2025** made the auto-populated outward liability in **GSTR-3B Tables 3.1 and 3.2 non-editable from the July 2025 tax period**. Earlier proposals for January 2025 were deferred. — [TaxGuru: hard-locking GSTR-3B](https://taxguru.in/goods-and-service-tax/hard-locking-auto-populated-data-gstr-3b-paradigm-shift-gst-return-filing-compliance.html); [CA Rajput](https://carajput.com/blog/advisory-on-hard-locking-of-auto-populated-values-in-gstr-3b/); [IndiaFilings](https://www.indiafilings.com/learn/hard-locking-in-gstr-3b); [LKS Law](https://lkslaw.com/insights/articles/hard-locking-gstr-3b-a-new-compliance-milestone-and-its-pitfalls)
- **Correction routes:** Liability corrections go **only through GSTR-1A**, which can be filed **once per period, before that period's GSTR-3B**. **ITC changes are handled through the IMS dashboard.** — [IndiaFilings](https://www.indiafilings.com/learn/hard-locking-in-gstr-3b); [TaxUpdate.in](https://taxupdate.in/gst/783/gstr-3b-hard-locking-ims-gstr-1a-input-tax-credit-table-4-july-2026/)
- **IMS and credit notes:** When a recipient rejects a credit note in IMS, the tax is automatically added back to the supplier's liability. This is a known pain point. — [IndiaFilings](https://www.indiafilings.com/learn/hard-locking-in-gstr-3b)
- **RCM table:** **Table 3.1(d) (inward supplies liable to reverse charge) is excluded** from the auto-locked outward liability. — [TaxGuru](https://taxguru.in/goods-and-service-tax/hard-locking-auto-populated-data-gstr-3b-paradigm-shift-gst-return-filing-compliance.html)
- **Table 4A from July 2026 [UNCERTAIN]:** One 2026 source claims hard-locking extends to **Table 4A ITC from July 2026**. No other source corroborates this. — [TaxUpdate.in](https://taxupdate.in/gst/783/gstr-3b-hard-locking-ims-gstr-1a-input-tax-credit-table-4-july-2026/); [GSTRecoPro](https://gstrecopro.in/blogs/gstr-3b-hard-locking-2026-guide); [ASBanka (June 2026)](https://asbanka.com/2026/06/23/gstr-3b-hard-locking-gstr-1a-ims-itc/)

### Inferences
- **Exports and reports:**
  - **GSTR-1 JSON**: B2B, B2CL, B2CS, CDNR, CDNUR, HSN summary (Table 12) and documents issued (Table 13), with a separate LR or invoice series.
  - **GSTR-3B working sheet**: FCM output, RCM inward liability in Table 3.1(d), ITC in Table 4 including RCM ITC, exempt or nil supplies in Table 3.1(c)/(e), and inter-state supplies to unregistered persons in Table 3.2.
  - **GSTR-2B/IMS reconciliation**: import the 2B or IMS JSON and match it against purchase bills (fuel, tyres, repairs, hire charges).
  - **GSTR-9/9C** annual data.
- **Because GSTR-3B is now locked**, the software must make **GSTR-1/1A accuracy** the control point. Provide pre-filing validation for duplicate invoice numbers, the RCM flag, place of supply and GSTIN validity.
- **RCM purchases** (for example GTA services received, or legal services) need **self-invoice and payment-voucher generation** and a separate RCM liability ledger, so that the cash payment of RCM and the ITC claim match.

### Gaps
- **Background knowledge, NOT verified in this session:**
  - *Retention:* **CGST Act section 36** requires accounts and records to be kept for **72 months from the due date of the annual return** for the year, and longer if an appeal or proceeding is pending.
  - *Rule 56(8):* No entry may be erased or overwritten. Electronic records must keep a **log of every entry edited or deleted**, which is effectively a GST audit trail.
  - *Rule 57:* Rule 57 covers electronic records and backups.
  - *Time bar:* A **three-year time bar** applies to filing GSTR-1/3B/9 after the due date (Finance Act 2023; GSTN enforced it from the July 2025 period).
  - *RCM ITC statement:* An **RCM liability/ITC statement** has existed on the portal since August 2024.
  - *HSN in GSTR-1:* HSN Table 12 requires a drop-down selection and is split into B2B and B2C (from 2025).
- No source confirmed the current GSTR-9 or 9C turnover thresholds for FY 2025-26.

---

## 6. Income tax: TDS on transport payments (old 194C, new section 393), transporter exemption, presumptive taxation (old 44AE, new section 58), tax audit (old 44AB, new section 63), and the Income-tax Act 2025 renumbering

### Takeaway
The **Income-tax Act, 2025 applies from tax year 2026-27 (1 April 2026)** **[NEW 2025-26]**, and FY 2025-26 is still governed by the 1961 Act. Contractor TDS moves to **section 393(1), Table S.No. 6(i)**: 1% for individual/HUF payees and 2% for others, with thresholds of ₹30,000 per payment and ₹1,00,000 aggregate per year. The **transporter exemption (old 194C(6))** moves to **section 393(4), Table S.No. 8**: no TDS if the payee owns **≤10 goods carriages** and gives a **declaration with PAN**, and the payer must report these payments. **44AE (presumptive income per goods carriage)** is merged into **section 58**: ₹1,000 per tonne of gross vehicle weight per month for heavy vehicles, ₹7,500 per month for others, for owners of ≤10 vehicles. Tax audit moves from 44AB to **section 63** (new audit report **Form 26**).

### Cited Findings
- **Contractor TDS:** Under the Income-tax Act 2025, **section 393(1), Table S.No. 6(i)**, the rate is **1% where the contractor is an individual or HUF and 2% otherwise**. No TDS applies if a single payment is **≤ ₹30,000** and the FY aggregate is **≤ ₹1,00,000**. — [TDSMan blog (June 2026)](https://blog.tdsman.com/2026/06/tds-on-payments-to-contractors-section-3931-section-194c/); [India Briefing: Section 393 FAQs](https://www.india-briefing.com/news/section-393-income-tax-act-2025-tds-rules-rates-compliance-guide-44450.html/); [Incometaxindia: Section 393](https://www.incometaxindia.gov.in/w/section-393-5) (snippet only)
- **Individual/HUF payers:** They must deduct contractor TDS only if they were **liable to tax audit (section 63) in the preceding FY**. Otherwise they deduct **2% on contract payments above ₹50 lakh per year** (old 194M). — [Shahi & Co: Section 393 guide](https://shahiandco.in/blog-tds-new-income-tax-act-2025.html); [CalcGuru: Section 393 TDS chart FY 2026-27](https://calcguru.in/section-393-tds-fy-2026-27-transition-guide/)
- **Transporter exemption:** No TDS applies on payments for **plying, hiring or leasing goods carriages** to a payee who **owns not more than ten goods carriages** at any time in the year and gives a **declaration with PAN**, under **section 393(4), Table S.No. 8**. This continues **194C(6)**. The **payer must furnish particulars of these exempted payments** in the prescribed form. — [TaxTMI notes: 393(1)/393(4) vs 194C](https://www.taxtmi.com/tmi_notes?id=1858); [TaxGuru: no TDS on transport payments, declaration format](https://taxguru.in/income-tax/tds-transport-payments-declaration-format-income-tax-act-2025.html); [CAclubindia: section 393(4) declaration format](https://www.caclubindia.com/articles/tds-on-transporters-declaration-format-under-section-3934-for-ay-202627-55097.asp); [TaxTMI article](https://www.taxtmi.com/article/detailed?id=16255)
- **Section 58:** Old **44AD, 44ADA and 44AE are merged into section 58** of the Income-tax Act 2025, which applies to tax year 2026-27. Rates and limits are **largely unchanged** and are set out in a serial-number table. — [TaxGuru: Section 58 presumptive taxation](https://taxguru.in/income-tax/section-58-presumptive-taxation-income-tax-act-2025.html); [TaxGuru: Act merges 44AD/44ADA/44AE](https://taxguru.in/income-tax/presumptive-taxation-simplified-income-tax-act-2025-merges-44ad-44ada-44ae.html); [SKM & Co](https://skmca.co.in/section-58-presumptive-taxation-rules-2025/)
- **Goods carriage rates:** **₹1,000 per ton of gross vehicle weight per month** for a heavy goods vehicle and **₹7,500 per vehicle per month** for others. The scheme is open to owners of **not more than 10 goods carriages** at any time in the year. — [Incometaxindia: Section 44AE](https://www.incometaxindia.gov.in/w/section-44ae-31) (snippet only); [ClearTax: 44AE](https://cleartax.in/s/section-44ae-of-the-income-tax-act); [TaxBuddy](https://www.taxbuddy.com/blog/section-44ae)
- **44AE eligibility [UNCERTAIN]:** One search summary said 44AE eligibility is limited to "individuals, HUFs and partnership firms (not LLPs)". The 1961 statutory wording ("an assessee who owns not more than ten goods carriages") is wider, so this conflicts. — [AngelOne](https://www.angelone.in/knowledge-center/income-tax/section-44ae-of-income-tax)
- **Tax audit:** The audit provision moves to **section 63**, with audit report **Form 26**, for years under the new Act. **For FY 2025-26 (AY 2026-27), 44AB and Forms 3CA/3CB/3CD still apply.** — [TaxGuru: Section 58](https://taxguru.in/income-tax/section-58-presumptive-taxation-income-tax-act-2025.html); [TaxQueries: 44AD(4) & 44AB(e)](https://www.taxqueries.in/2024/08/decoding-sections-44abe-and-44ad4-of.html)

### Inferences
- **TDS on hire payments:**
  - **Vendor (truck-owner) master** fields: PAN, "owns ≤10 goods carriages" declaration (store the signed declaration document and its FY), payee type (individual/HUF or other), and a TDS applicability switch.
  - **Auto-deduct 1% or 2%** on hire or lorry-hire payments **unless a valid declaration exists**. Check the ₹30,000 per-payment and ₹1,00,000 per-year thresholds.
  - The quarterly TDS statement export (Form 26Q equivalent under the new Act) must **still list exempted transporter payments**.
  - Track whether the payer (an individual/HUF owner) was audited in the previous year, since that decides whether TDS applies at all.
- **TDS suffered by the transporter:** Customers deduct TDS on freight, so keep a **TDS-receivable ledger** reconciled with Form 26AS/AIS (or the new-Act equivalent) by customer, invoice and quarter.
- **Presumptive scheme:** Provide a **per-vehicle register** (registration number, GVW, ownership dates, months owned) and an automatic **section 58 / 44AE presumptive-income calculator** (months or part-months × rate) for small owners. Section 58 users still need books for GST, loans and credit.
- **Dual statute labelling:** Reports must show **both old and new section numbers**, for example "194C / 393(1) S.No.6(i)" and "44AE / 58", because FY 2025-26 filings during 2026 use the 1961 Act while FY 2026-27 transactions use the 2025 Act.

### Gaps
- **Tax audit thresholds under section 63 were not verified** for FY 2026-27. Under 44AB, as I understand it, the threshold is ₹1 crore turnover, or ₹10 crore if cash receipts and payments are ≤5% each. Audit is also triggered if a 44AE/section 58 user declares lower income and total income exceeds the basic exemption limit.
- **Exact current text of section 58 for goods carriages was not verified.** Points to check: whether the per-tonne and per-vehicle figures are unchanged, the "heavy goods vehicle" definition (GVW > 12,000 kg), and whether eligibility is limited to any type of assessee.
- I did not confirm the form numbers for the transporter declaration or the quarterly statement under the new Income-tax Rules 2026.
- **Background knowledge, NOT verified in this session:**
  - *194C(6) reporting under the 1961 Act:* The payer reports exempted transporter payments in Form 26Q under 194C(7).
  - *TDS deposit and filing:* TDS is deposited by the **7th of the next month** (30 April for March). Quarterly statements are due 31 July, 31 October, 31 January and 31 May.
  - *Interest on late deposit:* 1% per month for late deduction and 1.5% per month for late payment.
  - *Late-filing fee:* ₹200 per day (old section 234E).
  - *Higher TDS without PAN:* TDS at 20% where the payee has no PAN (old 206AA).
  - *Books under the new Act:* The new-Act section that replaces 44AA (maintenance of books) was not identified. I believe it is section 62, but this is unverified.

---

## 7. Books of account: Companies Act s.128 and Companies (Accounts) Rules (audit trail, retention, India backup); non-company businesses (s.44AA, Rule 6F); GST record retention

### Takeaway
**Every company**, including small companies, OPCs and section 8 companies, must use accounting software that **records an audit trail (edit log) of every change with the date, which cannot be disabled**. This applies from FY 2023-24 under the proviso to Rule 3(1) of the Companies (Accounts) Rules 2014. Auditors report on it under Rule 11(g). Books and the audit trail must be kept **8 years**. Where books are kept electronically, a **daily backup on servers physically located in India** is required under Rule 3(5), as amended in 2022. For a SaaS, these are **hard product requirements**: an immutable change log, no admin switch to turn it off, India-region daily backups, and 8+ years of retention. GST requires 72 months and income-tax rules require roughly 6-8 years. Designing for **8 years of immutable retention** satisfies all of them.

### Cited Findings
- **Audit trail:** The proviso to **Rule 3(1)** of the Companies (Accounts) Rules 2014 requires companies, for **FYs starting on or after 1 April 2023**, to use accounting software that **records an audit trail of each and every transaction**, creates an **edit log of each change with the date**, and ensures the **audit trail cannot be disabled**. **No exemption applies** for small companies, OPCs or section 8 companies. This sits under **section 128** of the Companies Act 2013. — [India Briefing: audit trail for all companies](https://www.india-briefing.com/news/india-mandates-audit-trail-compliance-for-all-companies-explainer-key-obligations-34837.html/); [TaxGuru: audit trail w.e.f. 01.04.2023](https://taxguru.in/company-law/audit-trail-companies-act-2013-wef-01-04-2023.html); [CAP India: audit trail mandatory incl. section 8](https://capindia.in/audit-trail-edit-log-mandatory-for-all-companies-including-section-8-companies/); [TaxGuru: practitioner's guide, Rule 3(1) & 11(g)](https://taxguru.in/company-law/audit-trail-compliance-rule-3-1-rule-11-practitioners-guide.html)
- **Retention and auditor reporting:** The audit trail must be **preserved for at least eight years**, and auditors report on it under **Rule 11(g)** of the Companies (Audit and Auditors) Rules. — [TaxGuru: reporting on audit trail](https://taxguru.in/company-law/reporting-audit-trail-companies-act-2013.html); [MBG Corp: Rule 11(g)](https://www.mbgcorp.com/in/insights/auditors-reporting-for-audit-trail/); [SR Batliboi (EY): audit trail learnings](https://www.srbatliboi.in/article/audit-trail-implementation-challenges-and-learnings-from-first-year)
- **India backup:** **Rule 3(5)**, as amended by the Companies (Accounts) Fourth Amendment Rules 2022, requires a **daily backup of electronically kept books and papers on servers physically located in India**. — [TaxGuru: demystifying audit trail](https://taxguru.in/company-law/demystifying-audit-trail-edit-log.html); [CAclubindia: everything about audit trail](https://www.caclubindia.com/articles/audit-trail-49593.asp)
- **Tally's definition:** Tally describes the audit trail as capturing who changed what and when. — [Tally Solutions glossary](https://tallysolutions.com/glossary/audit-trail/)

### Inferences
- **Product requirements:**
  - An **append-only, tamper-evident audit log** at transaction and master level, recording who, when, the before and after values, the action (create, edit, delete or cancel) and the source (web, app, API, import).
  - The log is **always on**, with **no tenant-level toggle**.
  - Deletions are soft deletes with a log entry.
  - An "Audit trail report" export for statutory auditors, covering Rule 11(g) and CARO-style review.
- **Data residency:**
  - **Primary data plus a daily backup in an Indian cloud region**, for example AWS Mumbai/Hyderabad, Azure Pune/Chennai or GCP Mumbai/Delhi.
  - Document the service provider's name, address and server location, because companies must disclose this (see Gaps).
- **Retention:** **8 financial years of retention** for books, vouchers, attachments and audit trail, with legal hold where proceedings are pending. Purge only after that period and with the tenant's consent. This needs to be **reconciled with DPDP erasure duties** (section 10 below). Accounting records are retained under a legal obligation, which overrides an erasure request.
- **Year-end:** Period lock and year-end close, with any edit to a closed period logged and needing elevated rights.

### Gaps
- The text of Rule 3(5) and Rule 3(6) was not read.
- **Background knowledge, NOT verified in this session:**
  - *Rule 3(6):* Companies must **inform the Registrar every year**, at the time of filing financial statements, of the name, address and location of the **service provider and servers** where electronic books are kept.
  - *Section 128(5):* Books of account must be preserved for **8 years immediately preceding the FY**.
  - *Section 44AA and Rule 6F:* Under the 1961 Act, section 44AA(2) requires non-professional businesses to keep books if income exceeds ₹2.5 lakh or turnover exceeds ₹25 lakh in any of the 3 preceding years (individual/HUF; ₹1.2 lakh / ₹10 lakh for others). Books are also required when the presumptive scheme is claimed and lower income is declared. **Rule 6F** prescribes books and a **6-year retention** for specified professions, not for transporters.
  - *Retention under the new Act:* The new-Act equivalent and retention rule were not confirmed.
  - *GST:* **CGST section 36** requires **72 months** retention, and **Rule 56(8)** requires an edit log for electronic records.

---

## 8. Financial statement formats (Schedule III), double-entry ledger, TDS/TCS ledgers, Tally compatibility

### Takeaway
The searches in this session found **no sources** on Schedule III or Tally export formats. The points below are inferences and background knowledge. A compliant system needs a full double-entry general ledger, Schedule III (Division I) balance sheet and P&L for companies, separate statutory ledgers (GST input/output by tax head, RCM, TDS payable and receivable), and import/export interoperability with Tally, which most Indian CAs expect.

### Cited Findings
- The only related finding is that **Tally** markets an audit-trail feature aligned with the Companies Act requirement. CAs therefore expect any alternative accounting software to provide the same edit-log capability. — [Tally Solutions glossary](https://tallysolutions.com/glossary/audit-trail/)

### Inferences
- **Chart of accounts template** for transporters:
  - Freight income, split into GTA FCM, GTA RCM and exempt.
  - Hire charges paid (lorry hire) with TDS.
  - Diesel, tolls/FASTag, tyres, repairs, driver bata, loading/unloading, detention, permits, insurance, road tax, EMI interest and depreciation per vehicle.
  - **Cost centres per vehicle and per trip**.
- **GST ledgers:** separate CGST, SGST/UTGST and IGST input and output ledgers; an RCM payable ledger; an RCM ITC ledger; and an ITC-reversal ledger.
- **TDS ledgers:** a **TDS payable** ledger by section (393/194C, 194J and so on) and a **TDS receivable** ledger by customer.
- **Statements and Tally:** Schedule III reports, cash flow and a fixed-asset register with both Companies Act and IT Act depreciation. **Tally XML/Excel import-export** of masters and vouchers.

### Gaps
- **No sources found in this session**; the search budget was exhausted.
- **Background knowledge, NOT verified in this session:**
  - Schedule III Division I (non-Ind AS) was amended on 24 March 2021, effective FY 2021-22. It requires **ageing schedules for trade receivables and payables**, **MSME dues disclosure**, promoter shareholding, ratios and other items.
  - Non-corporate entities follow ICAI formats.
  - Tally Prime supports XML/JSON import and export through its TDL/XML interface.

---

## 9. Labour and payroll for drivers and helpers: Motor Transport Workers Act 1961 and the new Labour Codes (in force 21 Nov 2025)

### Takeaway
**All four Labour Codes came into force on 21 November 2025** **[NEW 2025-26]**: the Code on Wages 2019, the Industrial Relations Code 2020, the Social Security Code 2020 and the OSH Code 2020. The **Motor Transport Workers (MTW) Act 1961 is subsumed into the OSH Code**, and motor transport workers, including drivers, are explicitly covered. The core norms are **8 hours a day and 48 hours a week**, with **overtime at twice the ordinary wage and only with the worker's consent**. Under the old MTW Act section 13, long-distance routes could go up to **10 hours a day and 54 hours a week with approval**; whether the new rules keep this was not confirmed. Under the Code on Wages, **"wages" (basic + DA + retaining allowance) must be at least 50% of total remuneration** for statutory calculations, which raises PF and gratuity bases. **Gratuity for fixed-term employees now vests after 1 year** (5 years for permanent staff). Detailed central and state rules are still being notified: draft **MTW Rules 2025** were issued on 22 September 2025, and the **OSH (Central) Rules 2026** were reportedly notified on 8 May 2026. Payroll-lite features must therefore be **state-configurable**.

### Cited Findings
- **Commencement and the OSH Code [NEW 2025-26]:** The OSH Code (Act 37 of 2020) came into force on **21 November 2025** and consolidates **13 central labour laws, including the MTW Act 1961**. — [DLA Piper: Labour Codes notified](https://knowledge.dlapiper.com/dlapiperknowledge/globalemploymentlatestdevelopments/2025/government-of-india-notifies-the-labour-codes-ushers-a-new-era-of-compliances); [Wikipedia: OSH Code](https://en.wikipedia.org/wiki/Occupational_Safety,_Health_and_Working_Conditions_Code,_2020); [Cyril Amarchand Mangaldas: Guide to the Labour Codes (Dec 2025)](https://www.cyrilshroff.com/wp-content/uploads/2025/12/Guide-to-the-Labour-Codes.pdf); [Simpliance: Labour Codes](https://www.simpliance.in/India/LEI/labour_code)
- **Coverage of drivers:** Motor transport workers, including drivers, are covered by the OSH Code. Its safety, health, welfare, **working hours and rest interval** provisions apply to them. **Weekly-holiday rules have an exception for motor transport undertakings.** The Code counts **running time, subsidiary work and short periods of attendance as working hours** for motor transport workers. — [Counselence: OSH Code overview (28 Nov 2025)](https://counselence.com/wp-content/uploads/2025/11/CEC-Labour-Code-Overview-of-the-OSH-Code-November-28-2025.pdf); [PIB: OSH Code explainer (Nov 2025)](https://static.pib.gov.in/WriteReadData/specificdocs/documents/2025/nov/doc20251122702801.pdf)
- **Hours and overtime:** The limits are **8 hours a day and 48 hours a week**, with **overtime at twice the wage rate** and **worker consent** required. With consent, other daily patterns are allowed: 12 hours a day in a 4-day week, 9.5 hours in a 5-day week, and 8 hours in a 6-day week (a general provision). — [Counselence](https://counselence.com/wp-content/uploads/2025/11/CEC-Labour-Code-Overview-of-the-OSH-Code-November-28-2025.pdf); [PIB OSH explainer](https://static.pib.gov.in/WriteReadData/specificdocs/documents/2025/nov/doc20251122702801.pdf); [Timesheet.io](https://timesheet.io/en/blog/india-labour-codes-working-hours)
- **Old MTW Act section 13:** Adult motor transport workers may work **8 hours a day and 48 hours a week**. On **long-distance routes** or notified festive occasions, the employer may exceed this **with approval of the prescribed authority, up to 10 hours a day and 54 hours a week**. — [India Code: MTW Act 1961](https://www.indiacode.nic.in/bitstream/123456789/19026/1/motor_transport_workers_act_1961.pdf); [Indian Kanoon: MTW Act](https://indiankanoon.org/doc/1276952/)
- **Rest break [UNCERTAIN]:** An HR guide mentions a **30-minute break after 5 hours of continuous work** under the MTW Act. This was not confirmed against the statute. — [Keka: MTW Act guide](https://www.keka.com/compliance/acts/motor-transport-workers-act)
- **Draft MTW Rules [NEW 2025-26]:** The **Draft Motor Transport Workers Rules 2025** were issued by the Ministry of Labour on **22 September 2025** under **sections 23 and 24 of the OSH Code**, with comments due by **6 November 2025**. — [IndiaLaw: Draft MTW Rules 2025](https://www.indialaw.in/blog/labour/draft-motor-transport-workers-rules-2025/)
- **OSH (Central) Rules [NEW 2025-26]:** Draft **OSH (Central) Rules 2025** were published on **30 December 2025** as G.S.R. 934(E), with comments due by 28 January 2026. One source says the **final OSH (Central) Rules 2026 were notified on 8 May 2026** and cover motor transport. The power to fix rest intervals and spread-over lies with the appropriate government, and **for motor transport undertakings that is generally the State Government**. **States are notifying their own OSH rules**, for example Andhra Pradesh OSH Rules 2026. — [Lexplosion](https://lexplosion.in/fresh-draft-on-occupational-safety-health-and-working-conditions-central-rules-2025-published-by-ministry-of-labour-invites-comments-by-28th-january-2026/); [Legal500: notified OSH (Central) Rules 2026](https://www.legal500.com/intelligence/india/employment-and-hr/notified-occupational-safety-health-and-working-conditions-(central)-rules-2026-what-employers-need-to-know); [iPleaders: OSH Central Rules 2026](https://blog.ipleaders.in/osh-central-rules-2026-registration-hours-contract-labour/); [AP OSH Rules 2026 (Lawrbit copy)](https://www.lawrbit.com/wp-content/uploads/2026/04/occupational-safety-health-and-working-conditions-andhra-pradesh-rules-2026.pdf)
- **Code on Wages [NEW 2025-26]:** The Code came into force on 21 November 2025. **"Wages" (basic + DA + retaining allowance) must be ≥50% of total remuneration.** Excluded components such as HRA, conveyance and bonus above 50% are added back to wages for statutory calculations. — [Numerica: Labour Code FAQs and the 50% rule](https://numericaconsulting.com/blog/new-labour-code-faqs-employers-india-2025/); [LegalServiceIndia: 50% wage rule](https://www.legalserviceindia.com/Legal-Articles/50-percent-wage-rule-code-on-wages-2019-salary-pf-gratuity/); [Labour Law Reporter: salary structure](https://labourlawreporter.com/salarystructure.asp)
- **Gratuity [NEW 2025-26]:** Under the Social Security Code, effective 21 November 2025 and prospective, **fixed-term employees qualify for gratuity after 1 year** of continuous service. **Permanent employees still need 5 years**, except on death or disablement. The formula is **15/26 × last drawn wage × completed years**. — [Fisher Phillips](https://www.fisherphillips.com/en/insights/insights/indias-new-labor-codes-expand-gratuity-payment-rules); [BusinessToday](https://www.businesstoday.in/personal-finance/news/story/labour-codes-how-updated-gratuity-framework-would-change-things-for-fixed-term-contract-staff-504180-2025-11-28); [PeopleMatters](https://www.peoplematters.in/news/economy-policy/indias-new-gratuity-rules-2025-what-employers-and-employees-should-know-47753); [Numerica: gratuity rules](https://numericaconsulting.com/blog/gratuity-rules-india-2025-labour-code/)
- **Partial implementation:** **Some Social Security Code provisions await central rules.** — [BDO: Labour Codes alert](https://www.bdo.in/en-gb/insights/alerts-updates/alert-implementation-of-labour-codes-key-provisions-notified-effective-21-november-2025); [Fisher Phillips](https://www.fisherphillips.com/en/insights/insights/indias-new-labor-codes-expand-gratuity-payment-rules)
- **Case law:** The Supreme Court held that drivers who had **resigned** were not "employed" for MTW Act purposes, reading "employed" as being in service. This concerns employment status, not hours. — [The Law Observer](https://www.thelawobserver.in/supreme-court/can-contract-systems-for-truck-drivers-violate-labour-rights-supreme-court-clarifies)

### Inferences
- **Payroll-lite data model:**
  - **Employee master:** category (driver, helper/cleaner, mechanic, office); employment type (permanent, fixed-term, casual, contract-labour via contractor); state of employment; licence details; and UAN, ESIC and PAN numbers.
  - **Wage structure:** components of basic, DA, HRA, **bata/daily allowance** (trip-based), overtime, incentives, bonus and reimbursements. Run a **50% wage test** and add back any excess for PF and gratuity bases.
  - **Attendance and hours:** trip-linked duty time, with running time, loading/unloading and waiting counted as working time. Raise **alerts above 8 hours a day or 48 hours a week**, compute **overtime at 2×**, record **OT consent**, and track weekly rest days. Keep state-specific settings for spread-over and long-route extensions.
  - **Leave:** earned or annual leave accrual, carry-forward and encashment, plus sick and casual leave, all configurable by state.
  - **Advances and deductions:** driver advances (trip cash and salary advance), recoveries and fines. Limit total deductions per the Code on Wages, which I believe is 50% of wages (see Gaps).
  - **Bonus:** statutory bonus calculation (8.33%-20%, see Gaps) and **gratuity accrual** (1 year for fixed-term).
  - **PF/ESI:** contribution computation and **ECR/ESIC challan export**.
  - **Professional tax:** state slabs.
  - **Records:** **wage slips** issued electronically, a **register of employees and wages** (electronic, in the formats prescribed by the Central or State rules), and a full and final settlement workflow.
- **Driver advances** link to the accounting module as "driver imprest" ledgers per driver, and cash expenses settled against them have to respect the section 40A(3) limits (section 12).

### Gaps
- I could not read the **final OSH (Central) Rules 2026**, the **MTW Rules** (final status unknown), or the **Code on Wages and Social Security Central Rules**. Their spread-over, rest-interval, register-format and long-route-hours provisions are therefore **unconfirmed**.
- **Background knowledge, NOT verified in this session:**
  - *EPF:* Applies to establishments with **20+ employees**. Contributions are 12% + 12%, on a **wage ceiling of ₹15,000** for mandatory coverage.
  - *ESI:* Applies to establishments with **10+ employees** and employees with wages **≤ ₹21,000 a month** (₹25,000 for persons with disability). Contributions are 3.25% (employer) and 0.75% (employee).
  - *Bonus:* Under the Code on Wages, the statutory bonus is **minimum 8.33%, maximum 20%**, for employees earning up to the notified ceiling (₹21,000 under the old Act) with ≥30 days worked in the year.
  - *Annual leave:* Under OSH Code section 32, a worker who has worked **180+ days in a calendar year** earns **1 day of leave per 20 days worked**. The research brief's "20 days" refers to the accrual rate, not eligibility.
  - *Deductions:* Total deductions are capped at **50% of wages** (Code on Wages).
  - *National floor wage:* The Code on Wages provides for a **national floor wage**. The Centre had not notified it as of my knowledge; states set minimum wages, including for drivers in "transport" scheduled employments.
  - *Professional tax:* Levied by states, with a constitutional cap of **₹2,500 per person per year** (Article 276(2)). Not all states levy it.
  - *Contract labour:* The OSH Code's contract-labour threshold is **50 workers**.
- No source covered **minimum wage rates for truck drivers by state**. They differ by state and are revised twice a year through VDA.

---

## 10. Driving-hours rules for transport vehicle drivers (Motor Vehicles Act 1988, MTW Act/OSH Code) and the 2024-2026 push on driver rest

### Takeaway
India has **no standalone hours-of-service regime with electronic logging** like the US FMCSA rules. Driver hours come from the **labour law** that MV Act section 91 points to (formerly the MTW Act, now the OSH Code). That means **8 hours a day and 48 hours a week**, with limited long-route extensions under state rules. In this session I could **not find or verify** any 2025-2026 MoRTH rule mandating driver rest hours, or a Supreme Court direction on truck-driver hours. The software should still record duty and driving time per trip and warn on fatigue, as best practice and to show labour-law compliance.

### Cited Findings
- **MTW Act section 13 (the hours that MV Act section 91 points to):** 8 hours a day and 48 a week, extendable on long routes with approval to **10 hours a day and 54 a week**. — [India Code: MTW Act 1961](https://www.indiacode.nic.in/bitstream/123456789/19026/1/motor_transport_workers_act_1961.pdf)
- **Under the OSH Code:** Hours and rest intervals now apply through the OSH Code, and running time counts as working time. — [Counselence OSH overview](https://counselence.com/wp-content/uploads/2025/11/CEC-Labour-Code-Overview-of-the-OSH-Code-November-28-2025.pdf)
- **No 2025 driving-hours notification found:** A targeted search for a 2025 MoRTH driving-hours rule or Supreme Court enforcement order returned only US FMCSA material and a Quora post claiming India has no limits, which is not reliable. — [Quora (unreliable)](https://www.quora.com/How-many-hours-straight-can-a-truck-driver-drive-in-India-Why-is-there-no-limit); [Wikipedia: Drivers' working hours](https://en.wikipedia.org/wiki/Drivers%27_working_hours)

### Inferences
- **Trip duty log:** Record a **trip duty log** for each trip: start and end of duty, driving periods from GPS ignition or motion, breaks, and loading or waiting time.
- **Configurable rule engine:** Defaults of 8 hours a day and 48 a week, a rest break after 5 hours (to be verified), and a long-route 10-hour/54-hour profile. Alerts go to the fleet manager.
- **Positioning:** Present this as **labour-law and road-safety support**, not as a statutory HOS logbook, since none exists in India.

### Gaps
- **Background knowledge, NOT verified in this session:**
  - *MV Act section 91:* Section 91 of the Motor Vehicles Act 1988 ("Restriction of hours of work of drivers") provides that a transport-vehicle driver's hours of work are as set under the MTW Act 1961, now read with the OSH Code.
  - *AC cabins:* MoRTH notified (around December 2023) that **N2 and N3 trucks manufactured on or after 1 October 2025 must have air-conditioned cabins** (IS 14618), to reduce driver fatigue.
  - *Other initiatives:* There have also been rest-area ("Apna Ghar") initiatives for truckers. Whether any **2026 draft on driver rest hours, tachograph or driver-monitoring** exists could not be checked.
- **This whole topic needs a follow-up search** on morth.gov.in, egazette.gov.in and Supreme Court orders.

---

## 11. Data protection: DPDP Act 2023 and DPDP Rules 2025 (GPS tracking of staff phones, photos, ID documents), and Aadhaar storage restrictions

### Takeaway
The **DPDP Rules 2025 were notified on 13/14 November 2025 (G.S.R. 846(E))** **[NEW 2025-26]** and phase in over 18 months. Board and procedural provisions apply immediately. **Consent Manager provisions apply after about 12 months (around November 2026).** The **core obligations apply after 18 months (around 13 May 2027)**: notice, security safeguards, **breach notification to the Board and affected people with a detailed report within 72 hours**, retention and erasure (48-hour pre-erasure notice), and data-principal rights. So in October 2026, most fiduciary duties are **not yet enforceable but are about 7 months away**, and a SaaS launched now should be built compliant. Employers can process employee data **without consent for employment purposes under section 7(i)** ("legitimate uses"). However, commentators say this is **not a blanket licence for surveillance**: data minimisation, purpose limitation and transparency still apply. Off-duty GPS tracking is high-risk. For **Aadhaar**, physical or scanned copies must have the **first 8 digits redacted** before storage. Full Aadhaar numbers may be stored only in an **Aadhaar Data Vault**, an obligation that applies to Aadhaar "requesting entities" (UIDAI Circular 14/2025).

### Cited Findings
- **Notification [NEW 2025-26]:** The DPDP Rules 2025 were notified in **November 2025**. Sources give **13 November 2025 (G.S.R. 846(E))**, and PIB gives 14 November. — [PIB: DPDP Rules 2025 notified (PDF)](https://static.pib.gov.in/WriteReadData/specificdocs/documents/2025/nov/doc20251117695301.pdf); [EY India: DPDP Rules 2025 guide](https://www.ey.com/en_in/insights/cybersecurity/transforming-data-privacy-digital-personal-data-protection-rules-2025); [Wikipedia: DPDP Rules 2025](https://en.wikipedia.org/wiki/Digital_Personal_Data_Protection_Rules,_2025)
- **Phasing:** Commencement is phased over **18 months**: **13 November 2025** (immediate: definitions, Board), **about 13/14 November 2026** (Consent Managers), and **13/14 May 2027** (substantive obligations, **including breach notification**). One guide wrongly says the Rules were notified in January 2025; this is an error. — [DComply: DPDP Rules effective dates](https://dpdpa.dcomply.in/rules/); [TCSA: implementation roadmap](https://www.tcsa.in/resources/dpdp-rules-2025-implementation-roadmap); [CY5: 12/18-month rollout](https://www.cy5.io/blog/dpdp-rules-2025-complete-compliance-guide-cloud-security/); contradicted on the notification date by [Glocert](https://www.glocertinternational.com/resources/guides/dpdp-rules-2025-compliance-timeline/)
- **Breach notification:** Notify the **Data Protection Board and affected Data Principals without delay** on becoming aware of a breach, then file a **detailed report within 72 hours**. — [Legal500: DPDP data breach notification](https://www.legal500.com/intelligence/india/privacy/dpdp-data-breach-notification-in-india-timelines-requirements-and-key-compliance-considerations); [CY5](https://www.cy5.io/blog/dpdp-rules-2025-complete-compliance-guide-cloud-security/)
- **Retention and erasure:** Retention must be **purpose-limited**. Data Principals must be **informed at least 48 hours before erasure**. A **3-year deletion rule after last interaction** applies only to specified large platform classes (e-commerce, gaming and social media), not to ordinary employers. — [DPDP-compliance.in: rule-by-rule guide](https://dpdp-compliance.in/dpdp-rules); [TCSA](https://www.tcsa.in/resources/dpdp-rules-2025-implementation-roadmap)
- **Employment legitimate use:** **Section 7(i)** allows processing **without consent for employment purposes** and to safeguard the employer from loss or liability. Commentators warn it **is not a blanket authorisation for unrestricted surveillance**: **data minimisation and purpose limitation apply**, and **covert monitoring is hard to defend**. — [Chambers: legitimate-use exemption for employee data](https://chambers.com/articles/navigating-the-legitimate-use-exemption-for-employee-data-under-the-digital-personal-data-protection); [Legal500: can employers monitor devices under DPDP](https://www.legal500.com/intelligence/india/privacy/can-employers-monitor-employee-devices-emails-and-communications-under-indias-dpdp-act); [Lexology: is consent required for employee data](https://www.lexology.com/library/detail.aspx?g=5b0947a9-4f71-4c61-bccc-c2818ef43922); [LawCodeHub: DPDP section 7](https://www.lawcodehub.com/dpdp/7)
- **Consent for monitoring [UNCERTAIN]:** One compliance table claims employee monitoring (email/CCTV) needs **consent plus notice**, which conflicts with the section 7(i) view. Two sources mis-cite the employment ground as 7(f). — [Consently: employee data under DPDP](https://www.consently.in/blog/employee-data-dpdp-act-hr-compliance-india); [ProdView: DPDP and employee monitoring](https://prodview.app/blog/dpdp-act-employee-monitoring-india); [ComplyDP](https://www.complydp.com/articles/do-we-need-consent-for-employee-data-dpdp)
- **Aadhaar Data Vault [NEW 2025-26]:** **UIDAI Circular No. 14 of 2025** requires **requesting entities (REs)**, meaning AUAs and KUAs, to store Aadhaar numbers and connected data **only in a separate secure Aadhaar Data Vault**, using reference keys or tokens elsewhere. The 2025 Circular and FAQs clarify that **ADV requirements apply only to REs, not to other entities that merely collect Aadhaar information**. The 2017 FAQs had pulled in employers storing Aadhaar digitally; the 2022 FAQs dropped that. — [UIDAI Circular 14/2025](https://uidai.gov.in/images/Circular-No.14_of-2025.pdf); [Lexology: UIDAI clarifications on ADV](https://www.lexology.com/library/detail.aspx?g=391c2c88-0ab3-4d29-b88d-1a53c68e45c9); [Mondaq](https://www.mondaq.com/india/privacy-protection/1719124/vaulting-to-safety-uidai-issues-clarifications-on-applicability-of-aadhaar-data-vault-requirements); [Finahub (Aug 2026)](https://blog.finahub.com/2026/08/aadhaar-data-vault-compliance-uidai-circular-14.html)
- **Masking and storage rules:** The **Aadhaar (Authentication and Offline Verification) Regulations 2021** (clean copy updated 30.12.2025) require an entity to **redact the first 8 digits of the Aadhaar number before storing physical copies**. Storing Aadhaar numbers for non-authentication purposes **needs specific permission from UIDAI and an agreement**. — [UIDAI: Authentication & Offline Verification Regulations 2021](https://old.uidai.gov.in/images/The_Aadhaar_Authentication_and_Offline_Verifications_Regulations_2021-_Clean_copy-30122025.pdf)
- **Proposed photocopy ban [UNCERTAIN]:** UIDAI reportedly **planned to bar private entities from storing Aadhaar photocopies** and to push registered offline verification instead. It is **not confirmed as notified**. — [Deccan Herald](https://www.deccanherald.com/amp/story/india%2Fuidai-plans-to-ban-private-entities-form-storing-aadhaar-photocopies-here-is-the-alternative-3826236)
- **Offline e-KYC:** Offline e-KYC (signed XML or QR) lets a verifier confirm identity **without handling the full Aadhaar number**. — [Befisc: offline e-KYC guide](https://www.befisc.com/fintechsherlock/aadhaar-offline-ekyc-xml-guide/)

### Inferences
- **Roles:** The transport business (tenant) is the **Data Fiduciary** for its drivers' and helpers' data. **The SaaS vendor is a Data Processor** and needs a **data processing agreement** with each tenant. This becomes contractually important before May 2027.
- **GPS tracking of staff phones:**
  - Track **only during duty or trip**, with an **auto-off outside duty hours** and a visible in-app indicator.
  - Give a **clear notice**: purpose (trip safety, ETA, proof of delivery, fuel and route audit), data collected, retention and rights. Notice is good practice even where section 7(i) applies.
  - Offer **consent capture** as a fallback for non-employees such as hired owner-drivers and contract drivers, who are **not "employees"**. **Section 7(i) likely does not cover hired owner-operators**, so consent is needed.
  - **Limit location retention** (for example 6-12 months of raw points), keep aggregates longer, and keep **trip-level summaries for 8 years** where they support accounting vouchers.
- **Photos and ID documents** (driving licence, Aadhaar, PAN, selfies for attendance):
  - **Never store full Aadhaar numbers**, and never store unmasked Aadhaar images. Enforce **masking of the first 8 digits** on upload.
  - Prefer **DigiLocker or offline e-KYC**, or use DL or PAN as the primary ID.
  - Encrypt at rest, restrict access by role, and log every access.
- **Security and breach readiness** (Rule 6 safeguards; see Gaps for log retention): encryption, access control, **access logs kept ≥1 year**, a breach-detection and **72-hour report workflow**, and a tenant notification SLA.
- **Data-principal rights:** In-app requests for access, correction and erasure, with erasure **subject to legal-retention overrides** such as wage registers, accounting vouchers and GST records.
- **Data residency:** The DPDP Act has **no general data-localisation mandate** (see Gaps). The **Companies Act daily India backup** (section 7) means primary and backup data in Indian regions is the safe default anyway.

### Gaps
- I could not read the Rules text directly; PIB and MeitY could not be fetched. Rule numbers below are from background knowledge.
- **Background knowledge, NOT verified in this session:**
  - *Rule 3:* Notice must be standalone, clear and itemised, with a link to withdraw consent.
  - *Rule 6:* Security safeguards, including **logs kept for 1 year**.
  - *Rule 7:* Breach notification within **72 hours**.
  - *Rule 8:* Erasure, with **48-hour notice** and the Third Schedule classes. **Rule 8(3)** requires retaining personal data, traffic data and logs for **1 year** for Seventh Schedule purposes.
  - *Rule 14:* Response to data-principal rights requests within **90 days**.
  - *Rule 15:* Cross-border transfers are permitted subject to Government restrictions. The Act's section 16 allows a negative list; there is **no blanket localisation**.
  - *Penalties (Schedule to the Act):* up to **₹250 crore** for failing to take reasonable security safeguards, and up to **₹200 crore** for failing to notify a breach or for breaches of children's-data duties.
  - *Aadhaar Act section 29:* Restricts sharing and display of core biometric information and Aadhaar numbers.
- No source found addressing **GPS tracking proportionality** specifically. No 2026 amendment to the DPDP timeline could be checked.

---

## 12. Other: MSME payment rule (s.43B(h), new s.37(2)(g)) and cash transaction limits (s.40A(3), s.269ST) for driver cash expenses

### Takeaway
**Cash payments above ₹10,000 per person per day are disallowed in full**, except that the limit is **₹35,000 for payments to persons plying, hiring or leasing goods carriages** (40A(3), reportedly section 36(4) of the 2025 Act). **Cash receipts of ₹2 lakh or more** from one person, in a day or for one transaction or event, are prohibited (269ST), with a penalty on the receiver equal to the amount. **Payments to micro and small (Udyam-registered) suppliers** must be made within **15 days, or up to 45 days with a written agreement**. Otherwise the deduction is deferred to the year of payment (43B(h), moved to **section 37(2)(g)** of the 2025 Act). The software needs cash-limit validations on payment vouchers and an MSME-ageing report.

### Cited Findings
- **Section 40A(3):** The general limit is **₹10,000 per person per day**. For payments to persons **plying, hiring or leasing goods carriages** it is **₹35,000**; passenger transport does not qualify. **The whole payment is disallowed**, not just the excess. — [TaxGuru: 40A(3)/(3A)](https://taxguru.in/income-tax/section-40a3-3a-restrictions-cash-expenditure-capital-revenue.html); [BankBazaar: 40A(3)](https://www.bankbazaar.com/tax/section-40a3.html); [TaxGarden: 40A(3)](https://taxgarden.in/blog/section-40a-3-cash-payment-disallowance-rs-10000-rules-exceptions-india)
- **Section 269ST:** This restricts **receipts**: no person may receive **₹2 lakh or more in cash**. The **penalty under section 271DA falls on the receiver**. — [MAS LLP: 269ST](https://masllp.com/cash-receipts-payments-under-income-tax-act/); [CalcGuru: cash limits](https://calcguru.in/cash-transaction-limits-269st-40a3-penalty/)
- **New-Act mapping [UNCERTAIN]:** Secondary sources map 269SS to **section 185**, 269T to **section 188**, 269ST to **section 186**, and **40A(3) to section 36(4)** of the 2025 Act, from tax year 2026-27. No official mapping was found. — [TaxAJ: cash transaction limits 2026](https://www.taxaj.com/learn/cash-transaction-limits-income-tax-india/); [TaxGarden: 269SS/269T/269ST](https://taxgarden.in/blog/cash-transaction-limits-269ss-269t-269st-penalty-india)
- **Section 43B(h):** Payments to **micro and small enterprises** (Udyam-registered; **medium enterprises are excluded**) must be made within **15 days without a written agreement, or the agreed period up to a maximum of 45 days**, as defined in MSMED Act section 15. **Otherwise the deduction is allowed only in the year of actual payment**, and paying before the return-filing date does not help. The rule **moves to section 37(2)(g)** of the 2025 Act; 43B(h) still governs FY 2025-26 and earlier. — [TaxUpdate: 43B(h) to section 37](https://taxupdate.in/income-tax/893/section-43bh-msme-payment-disallowance-tax-audit-2026-section-37-income-tax-act-2025/); [TaxUpdate: CBDT FAQ series #7](https://taxupdate.in/income-tax/771/cbdt-faq-deep-dive-7-section-43b-43bh-msme-section-37-income-tax-act-2025-actual-payment-tax-audit/); [ClearTax: 43B(h)](https://cleartax.in/s/section-43bh-of-income-tax-act)
- **Transporter treatment:** No source discussed transporter-specific treatment under 43B(h). Whether freight or hire payments to an MSE truck owner are covered depends on the supplier's Udyam classification. — (gap noted in [TaxGarden 43B(h) guide](https://taxgarden.in/blog/section-43b-h-msme-payment-compliance-guide) search summary)

### Inferences
- **Cash validations on payment and expense vouchers:**
  - **Warn or block cash payments above ₹10,000 per payee per day**, aggregated across vouchers.
  - Apply a **₹35,000 limit when the payee is tagged "goods carriage operator"** (for example lorry hire paid to a truck owner).
  - For **driver trip expenses** (diesel, tolls, repairs, loading), the limit applies **per ultimate payee**, such as a fuel pump or mechanic, not to the driver advance itself.
  - Encourage FASTag, fuel cards and UPI, and capture the **payee name and amount per bill** in the driver app.
- **Receipt validations:** **Block cash receipts of ₹2 lakh or more** per customer per day, per transaction or per event.
- **MSME:** Vendor master fields for **Udyam number and category (micro, small or medium)**. An **MSME payables ageing report** with 15-day and 45-day buckets, and a year-end disallowance computation for the tax audit (Form 3CD clause 22 or the new Form 26 equivalent). The same data feeds the **Schedule III MSME disclosure** and the **MSME-1 half-yearly return** for companies.

### Gaps
- No official 1961→2025 section concordance was read, so the new section numbers (36(4), 186, 37(2)(g)) are **from secondary sources**.
- **Background knowledge, NOT verified in this session:**
  - *Rule 6DD:* Rule 6DD exceptions to 40A(3) include payments on bank holidays and payments in villages without banks.
  - *MSME-1:* Companies must file **MSME-1** with the ROC half-yearly for dues to MSEs outstanding more than 45 days.

---

## 13. Consolidated "software must do" checklist (synthesis for the report writer)

### Takeaway
The items below bring together the inferences above into a build checklist. Every item traces back to cited findings in sections 1-12; anything resting on unverified background is marked "(verify)".

### Cited Findings
- See sections 1-12 for sources. Key cited anchors:
  - GTA 5% RCM / 5% FCM / 18% FCM with ITC from 22.09.2025 ([PIB FAQ](https://www.pib.gov.in/PressReleasePage.aspx?PRID=2163560&reg=3&lang=2)).
  - Rule 46(b) 16-character unique-per-FY numbering ([CBIC Rule 46](https://taxinformation.cbic.gov.in/content/html/tax_repository/gst/rules/cgst_rules/active/chapter6/rule46_v1.00.html)).
  - GTA exempt from e-invoicing ([TaxGuru on Notification 13/2020](https://taxguru.in/goods-and-service-tax/electronic-invoice-gst-cbic-notifies-exemption.html)).
  - E-way bill 200 km per day, 180-day and 360-day limits ([Busy](https://busy.in/gst/computing-distance-covered-determining-eway-bill-validity-its-extension/)).
  - GSTR-3B hard-lock from July 2025 ([TaxGuru](https://taxguru.in/goods-and-service-tax/hard-locking-auto-populated-data-gstr-3b-paradigm-shift-gst-return-filing-compliance.html)).
  - Section 393(4) transporter TDS exemption ([TaxTMI](https://www.taxtmi.com/tmi_notes?id=1858)).
  - Section 58 presumptive ([TaxGuru](https://taxguru.in/income-tax/section-58-presumptive-taxation-income-tax-act-2025.html)).
  - Non-disableable audit trail, 8 years, daily India backup ([India Briefing](https://www.india-briefing.com/news/india-mandates-audit-trail-compliance-for-all-companies-explainer-key-obligations-34837.html/); [TaxGuru](https://taxguru.in/company-law/demystifying-audit-trail-edit-log.html)).
  - Labour Codes from 21.11.2025 ([DLA Piper](https://knowledge.dlapiper.com/dlapiperknowledge/globalemploymentlatestdevelopments/2025/government-of-india-notifies-the-labour-codes-ushers-a-new-era-of-compliances)).
  - DPDP Rules phasing to May 2027 ([DComply](https://dpdpa.dcomply.in/rules/)).
  - Aadhaar masking ([UIDAI Regulations 2021](https://old.uidai.gov.in/images/The_Aadhaar_Authentication_and_Offline_Verifications_Regulations_2021-_Clean_copy-30122025.pdf)).
  - 40A(3) ₹35,000 limit for goods-carriage operators ([TaxGuru](https://taxguru.in/income-tax/section-40a3-3a-restrictions-cash-expenditure-capital-revenue.html)).

### Inferences
**A. Entity and tax setup**
1. Entity types: owner-driver (individual, often unregistered for GST), proprietorship, firm/LLP, company (Companies Act features switched on), and multi-GSTIN multi-state set-ups.
2. Per GSTIN per FY: GTA regime (RCM-5% / FCM-5% / FCM-18%), Annexure V reference, e-invoice applicability (AATO, GTA exemption), EWB transporter ID or Common Enrolment Number, and AATO tracking.
3. Effective-dated rate and SAC master (9965/996511/996791/9966) covering the change on 22.09.2025.

**B. Sales and operations documents**
4. LR/consignment note per consignment, then freight invoice. Rule 46 fields, RCM Yes/No, place of supply (IGST s.12(8), verify), and a 16-character unique-per-FY number series.
5. Credit and debit notes linked to the original invoice. Bills of supply for exempt services. RCM self-invoices.
6. E-way bill tracking per trip: Part B updates, consolidation, validity countdown, extension window, and a block on documents older than 180 days.
7. Optional IRP integration for non-GTA taxable supplies above ₹5 crore AATO, with a 30-day ageing guard at ₹10 crore or more.

**C. Purchases and payables**
8. Lorry-hire and owner-operator payments: TDS at 1% or 2% (393(1) / 194C), the ≤10-carriage PAN declaration (393(4) / 194C(6)), and reporting of exempt payments.
9. ITC eligibility tied to the GTA regime (no ITC on 5% FCM except same-line input services, verify). IMS/2B reconciliation.
10. Udyam/MSME tagging, 15/45-day ageing, and 43B(h) / 37(2)(g) disallowance report.
11. Cash-limit validation: ₹10,000 per day, or ₹35,000 for goods-carriage operators; cash receipts of ₹2 lakh or more blocked.

**D. Books, statements, retention**
12. Double-entry general ledger, cost centres per vehicle and trip, and separate GST and TDS ledgers.
13. Schedule III statements for companies (verify), fixed-asset register, and Tally XML export (verify).
14. Immutable audit trail (no off switch), 8-year retention, daily backup in an Indian region, server-location disclosure data (verify), and period lock.
15. GSTR-1/1A JSON export (hard-lock aware), GSTR-3B working sheet (RCM in Table 3.1(d)), GSTR-9 data, TDS return export, and Form 26AS/AIS reconciliation.
16. Presumptive-income calculator per vehicle (s.58 / 44AE) and dual old/new section labelling for 2026-27.

**E. Payroll-lite (drivers and helpers)**
17. State-configurable wage components (basic, DA, bata, OT, bonus), a 50% wage test, OT at 2× with consent, the 8-hour/48-hour hours monitor from trip logs, a leave engine (verify 1 day per 20 days), advances and deductions (50% cap, verify), PF/ESI/PT calculations and exports (verify thresholds), gratuity accrual (1 year for fixed-term), wage slips and electronic registers in the state-rule formats (verify).

**F. Privacy and security**
18. DPDP-ready by May 2027:
   - Notice templates, a consent flow for non-employee drivers, and GPS on duty only.
   - Retention schedules with legal-hold override, Aadhaar masking (no full numbers), encryption, access logs for at least 1 year (verify), a 72-hour breach workflow, a rights-request portal, and DPAs with tenants.

### Gaps
- All items marked "(verify)" depend on background knowledge that could not be sourced in this session, because the search budget ran out and WebFetch failed on DNS.
- **Priority follow-ups:**
  - (1) Text of the GST 2.0 GTA notification and the current Annexure V deadline.
  - (2) The status of entry 21 clauses (b) and (c), i.e. the ₹1,500/₹750 exemption.
  - (3) Section 63 tax-audit thresholds and section 58 text.
  - (4) OSH (Central) Rules 2026 and state MTW rules on hours, spread-over and registers.
  - (5) Code on Wages and Social Security central rules: registers, bonus ceiling, floor wage.
  - (6) Any 2026 DPDP timeline change.
  - (7) MoRTH 2025-26 driver-fatigue measures (AC cabins from 1 October 2025; any rest-hour rules).
