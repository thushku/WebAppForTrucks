# Truck transport operating practice and record-keeping in India and the UAE: field catalogue for a trip-management app (as of Oct 2026)

> **Method and reliability caveat (read first).** These notes come only from web-search summaries, run on 13 Oct 2026. Every WebFetch attempt failed with DNS errors, including gstcouncil.gov.in, comtax.wb.gov.in, blog.fleetx.io, wam.ae, gulfnews.com, trucksbuses.com and mybillbook.in. The shared web-search budget then ran out, so no full page was read. Each cited fact is therefore what a search summary said that page contained.
>
> Many sources are vendor blogs, such as Fleetx, FR8, TrucksBuses and 91trucks. Treat their numbers as indicative only.
>
> Much of what a seasoned transporter records was not documented in any source I could reach. This includes freight rate bases, the mechanics of the driver "hisaab", hamali rates, "mamool", tyre position codes and LR book numbering. Those items appear under **Inferences**, labelled *[Inference, practitioner knowledge, unverified]*, so the report writer can tell them apart from cited facts. They should be validated with real transporters during discovery interviews.

---

## 1. Trip sheet / trip register: what fields transporters record per trip

### Takeaway
No statute prescribes a "trip sheet" for Indian goods transport; it is an internal operating record. What it must capture is pushed by the documents and charges around it:
- the consignment note (LR), e-way bill and POD;
- detention or halting claims, which need dated POD evidence;
- freight billing: advance, balance, detention, handling.

In the UAE, trip planning must also handle emirate-level truck movement bans and permits. Dubai introduced new bans on 1 Oct 2026, and Abu Dhabi has its own road bans and Darb timings.

### Cited Findings
- Trip sheets are described as a record of each trip a commercial vehicle makes, which also serves as a record for audits and disputes. Templates suggest adding cargo-specific fields, such as temperature readings and reefer unit status for temperature-sensitive loads. These are template sites of low authority. — [Truck Driver Trip Sheet Template (amuselabs mirror)](https://cdn-fill.amuselabs.com/ProductPdf/mL06B4/600233/Truck%20Driver%20Trip%20Sheet%20Template.pdf); [Lorry Trip Sheet Format (lunchbox mirror)](https://wildcard.lunchbox.io/index.jsp/details/mL295C601077/Lorry%20Trip%20Sheet%20Format.pdf)
- A transport bill template includes consignor/consignee, HSN/SAC, freight, handling/detention charges, e-way bill references and payment terms. — [myBillBook transport bill format](https://mybillbook.in/s/transport-bill-format)
- **Detention/halting charges (India), with FR8's published terms as an example:**
  - The first 24 hours are free (₹0). A 32 ft MXL truck then pays ₹1,000/day for days 1–2, rising in steps to ₹3,000/day from day 7.
  - 20 ft / 6-wheeler trucks pay ₹600–₹1,800/day.
  - Halting is paid only for waiting at loading or unloading as recorded in the POD, the date must appear on the POD, and the amount is added to the **balance payment**.
  
  — [FR8 Special Halting Terms](https://www.fr8.in/specialHaltingTerms.html)
- FR8's blog gives a different MXL slab: ₹1,500/day for days 1–2, rising to ₹3,000 from day 7. It says ₹3,000/day better reflects a truck owner's fixed costs, while "many shippers and companies pay only ₹1,000 per day". This conflicts with FR8's own terms page above. — [FR8 blog: Why halting/detention charges should be higher](https://www.fr8.in/blog/why-should-halting-or-detention-charges-be-higher)
- An all-India transport rate-contract clause sets detention at ₹1,500 per axle per day, payable only after the first 3 days. — [LawInsider: Loading Point clause](https://www.lawinsider.com/clause/loading-point/_3)
- A vendor glossary cites carrier detention of "Rs 500–2,000 per hour" depending on vehicle and distance. This is likely not typical of Indian truck practice; flag it as dubious. — [Fretron glossary](https://www.fretron.com/blog/glossary-detention-demurrage/)
- A long-haul Indian driving day can run 10–14 hours. One source says each driver may drive at most 600 km/day on line-haul work, which is useful for planned-duration and ETA fields. — [91trucks: A day in the life of a long-haul truck driver](https://www.91trucks.com/news/a-day-in-the-life-of-a-long-haul-truck-driver-in-india)
- **E-way bill fields relevant to a trip (India):**
  - Part B holds the conveyance details, i.e. the vehicle number for road transport. After Part B is submitted, only the vehicle number can be updated. Generating for road needs a transporter ID (GSTIN or TRANSIN) or the vehicle number. — [ClearTax e-way bill rules](https://cleartax.in/s//eway-bill-gst-rules-compliance)
  - If the vehicle changes in transit (transhipment or breakdown), the transporter must update Part B. — [Digit: e-way bill requirement](https://www.godigit.com/gst/eway-bill-requirement-for-transportation-of-goods.amp.html)
  - Validity is 1 day per 200 km or part thereof for normal cargo and 1 day per 20 km for over-dimensional cargo (ODC). — [Tally e-way bill FAQ](https://tallysolutions.com/gst/frequently-asked-questions-about-the-e-way-bill-answered/)
  - The bill can be extended within 8 hours before or after expiry, up to 360 days from generation, for example after a breakdown. — [Tally e-way bill FAQ](https://tallysolutions.com/gst/frequently-asked-questions-about-the-e-way-bill-answered/)
  - Sources conflict on whether validity starts at Part B entry or at generation. — [Open Money](https://open.money/blog/e-way-bill-validity-explained/) vs [Razorpay](https://razorpay.com/learn/e-way-bill-validity/)
- **Dubai truck movement restrictions from 1 Oct 2026:**
  - Emirates Road is exempt.
  - Al Qudra St, Al Meydan St, all crossings and the Airport Tunnel are banned round the clock.
  - Urban roads (Airport St, Oman St, Damascus St) are banned in three peak windows: 06:30–08:30, 13:00–15:00 and 17:30–20:00.
  - Sources conflict on Sheikh Zayed Road and Beirut St. Khaleej Times says a 16-hour ban (06:00–22:00); Gulf News puts them in the peak-window group.
  - Exceptions require the RTA "Mobility Permit for Heavy Vehicles in Banned Times and Roads".
  
  — [WAM](https://www.wam.ae/en/article/c2fwakt-rta-dubai-police-announce-truck-movement); [Khaleej Times](https://www.khaleejtimes.com/uae/transport/trucks-movement-restriction-dubai-roads-from-october-1); [Gulf News](https://gulfnews.com/uae/transport/dubai-to-introduce-truck-movement-restrictions-from-october-1-with-emirates-road-exempt-1.500688610); [Gulf Today](https://www.gulftoday.ae/news/2026/09/26/rta-dubai-police-announce-truck-movement-restrictions-on-roads-across-dubai-from-oct1); [Emirates 24|7](https://www.emirates247.com/uae/dubai-to-enforce-truck-traffic-curbs-from-oct-1-2026-rta-says-emirates-road-exempt/6059)
- Abu Dhabi banned trucks from Sheikh Maktoum bin Rashid Road (E11) and Al Raha Beach Road (E10) from 1 Dec 2025, with peak-hour limits in Musaffah. — [Khaleej Times: UAE traffic law changes](https://www.khaleejtimes.com/uae/transport/uae-traffic-laws-changes-residents-must-know?amp=1)
- In March 2026 the Integrated Transport Centre (Abu Dhabi Mobility) and Abu Dhabi Police announced 24/7 processing of heavy-vehicle permits via TAMM for specific routes, such as the E20 Abu Dhabi–Sweihan Road. — [Aletihad](https://en.aletihad.ae/news/uae/4654283/javasxript:)

### Inferences
*[Inference, practitioner knowledge, unverified: a proposed per-trip field set to validate in interviews]*

- **Header:**
  - Trip no.
  - Trip type: own load / market (broker) load / dedicated contract / return load.
  - Status: planned → loading → in transit → unloading → POD pending → billed → settled.
  - Created by, created at.
- **Assets and crew:**
  - Truck registration no.
  - Trailer no. (for a tractor-trailer).
  - Driver (primary), co-driver, helper/cleaner ("khalasi").
  - Each crew member's role and start/end date-time on the trip, so ledgers can be posted per person.
- **Parties:**
  - Billing party (customer).
  - Consignor (name, GSTIN, address, contact).
  - Consignee (name, GSTIN, address, contact).
  - Broker/agent (name, phone, commission).
  - Loading supervisor contact.
- **Route:**
  - Loading point (name, address, PIN, GPS geofence).
  - Unloading point(s); multi-drop needs an ordered list.
  - Via points or route preference.
  - Planned km and actual km (odometer start/end with photo, and GPS km).
  - Planned and actual times: reporting time at loading, loading start/end, departure, arrival at unloading, unloading start/end.
- **Cargo:**
  - Material/commodity.
  - Packages and type (bags, boxes, loose, coils).
  - Weights: declared weight, loaded weight (weighbridge slip no. and weight), unloaded weight, shortage.
  - Consignor's invoice no(s). and value.
  - E-way bill no(s). and expiry.
  - LR no(s).
  - Hazardous flag (UN class).
  - ODC flag.
  - Seal no(s). for containers.
  - Container no(s). (20/40 ft).
- **Freight:**
  - Rate basis: per tonne / per trip (fixed) / per km / per kg / per package / per container. Per-tonne rates often apply to the higher of actual or "guaranteed minimum" weight.
  - Rate, freight amount.
  - Advance received from the party (amount, date, mode, reference).
  - TDS deducted by the party.
  - Balance due.
  - Detention days × rate, from POD-stamped dates.
  - Loading/unloading charges and who bears them.
  - Extra-point charges, other deductions (shortage, damage, late delivery).
  - Commission/brokerage.
  - Freight bill no. and date.
  - Payment receipts.
- **Return leg:** empty return flag, empty km, and a link to the next or return trip. Empty running matters to the owner's per-km profitability.
- **UAE-specific:**
  - Emirate(s) traversed.
  - Whether a banned road/time permit is needed, with the permit no.
  - Border crossing (Ghuwaifat, Hatta/Oman) and customs declaration no. (see §2).

### Gaps
- I found no authoritative Indian source describing a "typical" trip register layout. Templates found were low-quality mirror sites.
- I found no data on the share of trips priced per tonne vs per trip vs per km, on typical "guaranteed minimum weight" clauses, or on standard broker commission. The planned search on broker commission did not run because the budget was exhausted.
- I found no UAE source on domestic trip documentation practice, or on whether UAE fleets bill per trip, per tonne or per hour.

---

## 2. Lorry Receipt (LR / bilty / GR / consignment note) and Proof of Delivery (POD); UAE equivalents

### Takeaway
**India.** In GST law, issuing a consignment note is what makes a road transporter a "Goods Transport Agency" (GTA). GST does not define the note's particulars, so practitioners borrow the old Service Tax Rule 4B list:
- serially numbered;
- consignor and consignee names;
- vehicle registration number;
- description of goods;
- origin and destination;
- the person liable to pay tax.

Commercial LR/bilty formats add GSTINs, weight and packages, freight and payment terms. POD is the acknowledged LR (or delivery challan) copy. It gates the balance or freight payment, which typically comes 30–45 days after the bill or POD is submitted.

**UAE.** The only UAE-specific trip documents I found are the customs and transit papers for GCC cross-border movement. Domestic delivery-note and waybill practice is a gap.

### Cited Findings
- **GTA definition and consignment note (India):**
  - The GTA definition in clause (ze) of Notification 12/2017-Central Tax (Rate) hinges on issuing a consignment note. Without one, the operator falls outside GTA.
  - Neither the GST Act nor the notification defines the consignment note. Practitioners take guidance from the Explanation to Rule 4B of the Service Tax Rules, 1994.
  
  — [TaxGuru: GTA under GST](https://taxguru.in/goods-and-service-tax/gta-goods-transport-agency-gst.html); [Taxo.online (CA Deepa Saluja)](https://taxo.online/latest-articles/comprehensive-review-around-gst-on-gta-service/); [GST Council GTA flyer, Sept 2017](https://gstcouncil.gov.in/sites/default/files/e-version-gst-flyers/Goods%20Transport%20Agency%20in%20GST_11%20September%202017.pdf) (the flyer could not be fetched)
- One secondary source summarises the Rule 4B particulars as:
  - a **serially numbered** document;
  - consignor and consignee names;
  - goods carriage registration number;
  - description of goods;
  - origin and destination;
  - person liable to pay tax (consignor, consignee or GTA).
  
  I did not verify this against the rule text. — [TaxGuru](https://taxguru.in/goods-and-service-tax/gta-goods-transport-agency-gst.html); [Kanakkupillai](https://www.kanakkupillai.com/learn/wp-json/wp/v2/posts/3757)
- A 2026 GTA compliance guide lists consignor details (with GSTIN if registered), consignee details, goods description, vehicle number, origin and destination, and freight charges. It is a commercial guide whose rate claims were not verified. — [Disytax GTA guide 2026](https://disytax.com/?p=24594)
- **Common bilty/LR fields:**
  - Transporter details including GSTIN.
  - Consignor and consignee details.
  - Pickup and delivery addresses.
  - Vehicle number.
  - LR number and issue date.
  - Goods description, quantity, weight and packaging.
  - Freight amount, payment terms, GST details.
  - Transporter's signature or stamp.
  
  The LR is the proof that the transporter has taken charge of the goods, and the main consignment record for disputes and audits. — [Fleetx: What is Bilty?](https://blog.fleetx.io/what-is-bilty-a-simple-explanation-for-transporters-shippers)
- The LR number is described as a unique identifier per shipment, and the goods section covers description, quantity and dimensions. — [FR8: Overview of the Lorry Receipt](https://fr8.in/blog/?p=1826)
- A spreadsheet-style bilty keeps invoice and freight amounts alongside consignor and consignee data. This is a vendor description, not a regulatory requirement. — [SignNow bilty format](https://www.signnow.com/features/bilty-format-in-excel-for-rd)
- **POD and payment:**
  - A public-sector freight contract says payment is due within 45 days of receipt of the bill, with consignee-accepted delivery challans attached. — [LawInsider: freight payment clause](https://www.lawinsider.com/clause/freight-payment/_4)
  - Another clause requires freight bills to be submitted with the original LR/GR copy acknowledged by the consignee. — [LawInsider: freight payment clause](https://www.lawinsider.com/clause/freight-payment/_4)
- A Government of India (BIS) tender pays 100% at month-end, only after the bill and a copy of the POD are submitted in duplicate. — [BIS courier tender](https://www.bis.gov.in/wp-content/themes/bisnew/page-templates/uploads/pdf/Tender/CourierServices.pdf)
- Dalmia Cement cut POD submission time from 7 days to 3 days with digital ePODs. This shows that physical POD return is a real bottleneck. — [Freight Tiger case study](https://blog.freighttiger.com/a-leading-cement-manufacturer-dalmia-saw-a-57-reduction-in-time-for-proof-of-delivery-pod-submissions-with-freight-tiger-platform/)
- Sennder, a European platform used here only as a benchmark, pays within 30 days of an approved POD. It notes that shippers and forwarders commonly take up to 60 days. — [Sennder](https://www.sennder.com/blog/streamline-your-payments-with-sennder-s-digital-tools)
- Halting/detention is paid only when the waiting is recorded in the POD with dates. — [FR8 Special Halting Terms](https://www.fr8.in/specialHaltingTerms.html)
- **UAE–Saudi cross-border at Ghuwaifat (Abu Dhabi Customs, from H2 2023):**
  - Advance (pre-arrival) customs declarations are mandatory.
  - Exiting loaded requires a "loaded truck crossing permit". That permit needs the customs declaration number, the Saudi "Fasah" clearance permit information and the expected arrival date.
  - Entering, the transit permit is issued automatically once the customs declaration is completed.
  
  — [Abu Dhabi Customs news](https://www.adcustoms.gov.ae/en/Media-Centre/News/New-improvements-to-the-Pre-Arrival-Customs-Clearance-System-to-facilitate-trade-movement); [Abu Dhabi Media Office](https://www.mediaoffice.abudhabi/en/economy/abu-dhabi-customs-to-implement-new-measures-to-facilitate-growth-of-trade-movement-from-h2-2023/)
- A truck pile-up at Ghuwaifat was blamed on truckers lacking transit customs documents. Customs told drivers to also carry the certificates for the materials transported. — [Gulf News](https://gulfnews.com/uae/transport/lack-of-transit-papers-behind-truck-pile-up-at-ghuwaifat-border-checkpoint-customs-sources-1.608119)
- **Bahrain–Saudi causeway (Khalifa Bin Salman Port advisory, 1 Apr 2026):**
  - GCC-registered trucks may transit the King Fahd Causeway directly, 24×7.
  - They need an IM8 "Transfer Authorization" customs declaration.
  
  — [APM Terminals Bahrain](https://www.apmterminals.com/en/bahrain/investors/investor-news/2026/260401-Procedure-for-facilitating-Entry-of-GCC-Based-Trucks-at-KBSP)
- Overland border charges and paperwork differ by country. The source is a generic logistics blog. — [Al Sharqi: Overland border charges across UAE](https://www.alsharqi.co/blog/article/overland-transportation-border-charges-across-uae/)

### Inferences
*[Inference, practitioner knowledge, unverified]*

- **Proposed LR fields:**
  - LR/GR no.; LR date; branch of issue.
  - From/to (station names).
  - Truck no.
  - Consignor: name, address, GSTIN, phone.
  - Consignee: name, address, GSTIN, phone.
  - Billing party, if different.
  - Consignor invoice no., date and value.
  - E-way bill no.
  - No. of packages and type.
  - "Said to contain" description.
  - Actual weight and charged weight.
  - Private marks.
  - Freight basis and rate; freight, plus hamali, collection/delivery, statistical/LR charges and other charges; total.
  - Payment type:
    - **Paid** (consignor pays at booking);
    - **To-Pay** (consignee pays on delivery);
    - **TBB / To Be Billed** (billed later to a contract party).
  - GST payable by (consignor / consignee / GTA under forward charge).
  - Insurance (owner's risk / carrier's risk; policy no.).
  - Remarks.
  - Signature or stamp of the booking clerk.
- Indian LRs are traditionally issued in multiple copies: consignor, consignee, driver/lorry and office/record. The **consignee copy signed and stamped on delivery** becomes the POD.
- **Digital POD should capture:**
  - receiver name and signature;
  - stamp photo;
  - GPS position and time;
  - packages received and shortage/damage remarks;
  - unloading start/end, to support detention.
- **UAE domestic practice is probably:**
  - a shipper's delivery note signed by the receiver, plus the transport company's own job or waybill reference;
  - for GCC cross-border, the customs declaration and transit permit numbers;
  - possibly a CMR or TIR carnet on some lanes (unverified).

### Gaps
- I found no primary-text confirmation of the Rule 4B particulars, and no CBIC circular listing consignment-note fields. GST portal pages were unreachable.
- I found no source on LR copy conventions (no. of copies, colours) or on "Paid / To-Pay / TBB" conventions. The planned search did not run.
- **UAE:** I found nothing on domestic consignment-note or waybill law. I also could not check whether the CMR Convention or TIR carnets apply to UAE GCC trucking, or what fields UAE delivery notes carry. Searching for these was planned, but the budget ran out.

---

## 3. Numbering conventions for trips, LRs and invoices (incl. GST invoice serial rules)

### Takeaway
GST Rule 46(b) governs the **tax invoice / freight bill** in India:
- consecutive serial, max **16 characters**;
- letters, digits, "-" and "/" only;
- **unique per financial year**;
- a new series each 1 April;
- no daily resets.

Consignment notes are expected to be **serially numbered**, per Rule 4B as summarised by secondary sources. Trip numbers have no legal format.

UAE e-invoicing is phasing in from a July 2026 pilot, with go-live from **1 Jan 2027** for large businesses. Invoice numbering design for the UAE should therefore be ready for accredited-provider (ASP) e-invoice exchange.

### Cited Findings
- **Rule 46(b) serial number:**
  - It must be consecutive, no more than 16 characters, and unique for the financial year.
  - Allowed characters are letters, numerals, "-" and "/".
  - The 16-character limit counts the full string: prefix, FY code, separators and running digits.
  
  — [BUSY: invoice numbering mistakes under GST](https://busy.in/gst/invoice-numbering-mistakes-under-gst-causes-corrections-and-prevention/); [ClearTax: invoice number](https://cleartax.in/s/invoice-number); [ICMAI: Invoice rules](https://icmai.in/upload/Taxation/Rule_Invoice_GST.pdf.filepart)
- Taxpayers should start a new series for FY 2026-27 from 01/04/2026. — [IRIS GST: reset invoice series from 1 April 2026](https://irisgst.com/effective-1st-april-2026-reset-the-invoice-number-series/)
- GSTN's 2019 advisory says:
  - restarting the serial daily (e.g. "20170701-001") is not appropriate, because the number must be consecutive and unique for the FY;
  - non-compliance can cause problems with e-way bill generation, GSTR-1 filing and refunds.
  
  — [Taxscan: GST portal advisory on invoice series](https://www.taxscan.in/?p=34898)
- A secondary source says the e-invoice Document Number field takes 1–16 characters and cannot start with "/" or "-". I did not verify this against the IRP spec. — [AI Accountant: GST invoice format](https://www.aiaccountant.com/blog/gst-invoice-format); [ClearTax](https://cleartax.in/s/invoice-number)
- Consignment notes are described as serially numbered documents (Rule 4B guidance). — [TaxGuru](https://taxguru.in/goods-and-service-tax/gta-goods-transport-agency-gst.html)
- **UAE e-invoicing:**
  - The Ministry of Finance issued e-invoicing guidelines v1.1 on 1 June 2026.
  - The pilot started 1 July 2026, with voluntary adoption.
  - **Phase 1** (revenue ≥ AED 50m): appoint an Accredited Service Provider by 31 July 2026; go live 1 Jan 2027.
  - **Phase 2**: ASP by 31 Mar 2027; go live 1 July 2027.
  - **Government entities**: go live 1 Oct 2027.
  - The regime applies to all businesses regardless of VAT registration, with limited exclusions.
  - Penalties under Cabinet Decision No. 106 of 2025: AED 5,000 per month for failing to implement or appoint an ASP; AED 100 per late e-invoice, capped at AED 5,000 per month.
  - One VAT guide gives a conflicting go-live of 31 July 2026.
  
  — [Fiscal Requirements: UAE e-invoicing v1.1 June 2026](https://www.fiscal-requirements.com/news/5572-uae-issues-updated-e-invoicing-rules-version-11-june-2026); [VATit UAE e-invoicing guide](https://vatit.com/e-invoicing-guide/united-arab-emirates/); [KPI: UAE 2026–2027 rollout](https://kpi.co/blog/regulatory-compliance/e-invoicing-in-the-uae-how-to-prepare-for-the-2026-2027-phased-rollout); [ATOZ ME Alert](https://www.atoz.lu/sites/default/files/media/file/260107-ATOZ%20ME%20Alert_3.pdf)

### Inferences
*[Inference, design recommendation]*

Use configurable series per document type, each unique per entity (GSTIN) per FY.

| Document | Suggested pattern | Example | Length |
|---|---|---|---|
| Freight invoice | `{BR}/{YY-YY}/{NNNNN}` | `MUM/26-27/00045` | 15 chars, fits 16 |
| LR | `{BR}{NNNNNN}` (pre-printed LR books often use this) | `MUM004512` | — |
| Trip | `T-{YYMM}-{NNNN}` | — | — |

- Trip numbers are internal, so they are free-form.
- The app should:
  - reject invoice numbers longer than 16 characters;
  - block duplicates within an FY;
  - auto-reset on 1 April;
  - support a separate series per branch or GSTIN;
  - keep cancelled numbers (void, never reuse).
- *[Inference, practitioner knowledge, unverified]* Many small Indian transporters use pre-printed LR books with running serial numbers. The app should allow manual LR number entry from a book, as well as auto-generated numbers.
- For the UAE, a sequential tax-invoice number is expected under the VAT rules (unverified; see Gaps). Plan for ASP integration rather than local PDF-only invoices.

### Gaps
- I did not verify the UAE VAT tax-invoice mandatory particulars, including the sequential numbering requirement; the search returned only the e-invoicing timeline.
- I found no source on how Indian fleets number trips or LRs in practice (branch codes, book series). The planned search did not run.

---

## 4. Driver cash advance and trip settlement ("hisaab"), bata/allowances, salaries (India & UAE), helper pay

### Takeaway
Indian long-haul driver pay is commonly a monthly salary plus trip-linked allowances and incentives:
- trip incentive;
- fuel allowance;
- overnight halt charge.

Cash advances are reconciled against expenses after the trip, and the difference is adjusted in pay.

Typical Indian heavy-truck driver pay in 2026:
- about ₹19,500–₹41,500/month (WageIndicator, majority band);
- ₹30,000–₹50,000 on interstate long-haul (blog estimate).

UAE heavy-truck driver pay ranges from a basic of AED 1,500 plus trip allowance up to AED 6,000–8,000/month. Aggregator mid-points are about AED 3,000–3,700, usually with visa, medical insurance and sometimes accommodation provided.

I found no reliable published source for standard bata (daily allowance) amounts or helper (khalasi) pay.

### Cited Findings
- **India pay estimates from a 2026 blog guide:**
  - By experience: entry level (0–2 yrs) ₹15,000–20,000; mid (3–9 yrs) ₹25,000–35,000; experienced (10–20 yrs) ₹35,000–50,000; 20+ yrs ₹50,000–70,000.
  - By route: long-distance/interstate ₹30,000–50,000; international ₹50,000–80,000.
  - By vehicle: container/trailer ₹35,000–60,000; hazardous ₹50,000–80,000.
  
  — [TrucksBuses: Truck driver salary trends 2026](https://www.trucksbuses.com/blog/truck-driver-salary-trends-in-india-for-2026)
- WageIndicator (2026): most heavy truck and lorry drivers earn ₹19,500–₹41,506/month. Starting net pay ranges ₹19,500–₹59,744. — [WageIndicator India: heavy truck and lorry drivers](https://wageindicator.org/en-in/work-in-india/role-and-pay/india-heavy-truck-and-lorry-drivers)
- An older FR8 article cites ₹10,000–15,000/month as the "average". This is probably outdated. — [FR8: Truck driver salary in India 2023](https://www.fr8.in/blog/truck-driver-salary-in-india-2023/)
- A payroll vendor for the logistics industry lists driver settlement components on a monthly cycle:
  - trip-based incentives;
  - fuel allowances;
  - overnight halt charges;
  - advance settlements.
  
  This is vendor marketing. — [Patron Accounting: payroll for travel and logistics](https://www.patronaccounting.com/payroll-services-for-travel-and-logistics-industry)
- Advance reconciliation workflow: after the trip, cash advances are matched to actual expenses, and unused funds or discrepancies are adjusted in the driver's pay settlement. This is a US product's help page, cited only as the generic pattern. — [Datatruck help center](https://support.datatruck.io/hc/en-us/articles/40199221682323)
- Raddar, an Indian product, lets drivers log trips and expenses over WhatsApp. It supports English, Hindi, Tamil, Telugu, Kannada and Malayalam, which signals that low-friction vernacular input matters. — [Product Hunt: Raddar](https://www.producthunt.com/products/raddar)
- TCI (Transport Corporation of India), with about 10,000 trucks on the road at any time, uses a DBS/TCIL phone-based payment system so drivers get paid without visiting head office. — [The Corporate Treasurer](https://thecorporatetreasurer.com/article/the-payments-solution-that-is-getting-indias-truck-drivers-paid-on-time/461439)
- A 2020 Statista survey found:
  - most drivers got no customer incentive for on-time trip completion;
  - drivers in northern cities were more often paid late and earned less than those in southern cities.
  
  The page was accessed via a university proxy link. — [Statista (via proxy)](https://www-statista-com.ezproxy.canberra.edu.au/statistics/1110222/india-opinion-on-incentives-received-by-truck-drivers-upon-trip-completion-by-city)
- "Driver's Night Batta per day (11 PM to 5 AM)" appears as a separate line item in an Indian institutional vehicle-hire tender. The amount was blank, and the context is taxi hire, not trucking. — [IISc taxi tender](https://www.iisc.ac.in/wp-content/uploads/2021/01/CMC_FinBid_Taxi_Tender_local-1.pdf)
- HPCL's DriveTrack Plus tie-up with TrucksUp gives drivers and helpers personal accident cover of up to ₹5 lakh each. This is evidence that helpers are a recognised crew role. — [AJOT: TrucksUp–HPCL](https://www.ajot.com/news/trucksup-partners-with-hpcl-to-transform-fuel-management-for-truck-owners)
- **UAE pay:**
  - A recruitment blog puts heavy truck drivers at about AED 4,000–7,000/month, with bonuses on long-distance routes. — [Two Continents](https://twocontinents.com/blog/how-much-does-a-driver-earn-in-dubai)
  - 2026 job postings range from a basic AED 1,500 plus trip allowance, through AED 2,300–2,500/month, to AED 6,000–8,000/month. The AED 1,500 posting includes free visa, medical insurance, one month's paid leave a year and one day off a week. The search summary did not pin each figure to a specific posting. — [Gulf News Classifieds: heavy vehicle drivers](https://classifieds.gulfnews.com/job/heavy-vehicle-drivers); [Qureos: truck driver](https://app.qureos.com/jobs/truck-driver-1781703474252); [Tiger Recruitment: Heavy Truck Driver](https://portal.tiger-recruitment.com/job/162858)
  - Jooble (July 2026) estimates "heavy drivers" at about AED 3,692/month (AED 44,304/yr). — [Jooble UAE: heavy drivers](https://ae.jooble.org/salary/heavy-drivers)
  - Indeed has one employer's truck-driver pay at about AED 2,967/month, from only 2 reports. — [Indeed UAE: HRI driver salaries](https://ae.indeed.com/cmp/Hri-Uae/salaries/Driver)

### Inferences
*[Inference, practitioner knowledge, unverified; validate in interviews]*

- **How hisaab typically works in India:**
  - At dispatch the owner gives the driver a **trip advance** ("kharcha" / "advance") in cash or by UPI. It covers diesel not on card or credit, cash tolls, food, hamali, weighbridge, police/RTO payments and repairs.
  - Further top-ups are sent en route by UPI, or as cash collected from the consignor or consignee.
  - At trip end the driver submits receipts and claims. The difference (advance + top-ups + cash collected from parties − approved expenses) is carried as the driver's running balance in the **driver ledger**, or settled in cash.
  - Salary or "trip money" (per-trip pay) is credited separately.
- **Common disputes** (where the app's design should add evidence):
  - expenses with no bill (police/"mamool", cash hamali, puncture repairs);
  - diesel bills inflated or claimed but not filled;
  - money collected from the party that was not reported (To-Pay freight, advance in cash);
  - diesel shortfall against expected km/l;
  - missing items (tarpaulin, ropes, jack, stepney);
  - shortage or damage deducted from the driver;
  - delays and detention days;
  - advances given verbally and not recorded;
  - salary days vs trip days.

  The app should capture photo, GPS and time on every expense, require approval by the owner, and keep an immutable ledger.
- **Bata models seen in practice:**
  - per day on the road;
  - per trip (fixed by lane);
  - per km;
  - included in a lump-sum trip payment ("trip ka paisa").

  The app should support all of these, plus a separate "night halt" allowance and a "food allowance". Typical amounts are unverified; I found no source.
- **Helper/cleaner (khalasi):**
  - paid lower than the driver;
  - often per trip or monthly, plus food;
  - often the driver's trainee or relative.

  Treat as a separate ledger and role. No figures found.
- **UAE:** the basic + trip-allowance structure is visible in job ads. Ledgers should support a fixed monthly basic, a per-trip allowance, overtime, and deductions such as traffic fines charged back to drivers. The fines chargeback is an unverified inference.

### Gaps
- I found no authoritative source for typical bata or daily allowance amounts, per-km driver pay rates, or helper/khalasi pay in India or the UAE. Bata-specific searches returned nothing usable.
- I found no qualitative source, such as a forum or YouTube channel, documenting hisaab disputes. YouTube was not reachable through these tools.
- I did not research end-of-service gratuity or WPS (Wage Protection System) payroll requirements for UAE drivers. These are likely relevant to the UAE salary ledger.

---

## 5. Common trip expense categories (India and UAE), including tolls, fines and unofficial payments

### Takeaway
**India**, the documented categories:
- diesel;
- FASTag tolls (non-FASTag payment costs 1.25× by UPI or 2× in cash, from 15 Nov 2025; cash lanes are being phased out in 2026);
- overloading fines (₹20,000 + ₹2,000 per extra tonne);
- Delhi's Environment Compensation Charge (₹2,000 for LCVs/2-axle trucks, ₹4,000 for 3-axle and multi-axle trucks, from 1 Apr 2026, rising 5% a year).

Weighbridge, hamali, parking, food, repairs, punctures and "mamool" are standard categories in practice, but I found no 2026 sources for them.

**UAE:**
- Salik (AED 4 per gate; Dubai peak pricing reported at AED 6; 5% VAT on tolls from 1 June 2026);
- Darb (AED 4 per crossing in peak hours, Mon–Sat; caps removed for private vehicles);
- fuel;
- fines (Salik no-tag fines; truck-ban violations);
- permits.

### Cited Findings
- **FASTag (India):**
  - From 15 Nov 2025, vehicles without a valid FASTag pay **1.25× the toll via UPI**, while **cash stays at 2×**.
  - Blacklisted tags or tags with insufficient balance may still pay double.
  
  — [CarToq](https://svb1.cartoq.com/car-news/government-revises-fastag-penalty-structure-upi-users-to-pay-125x-toll-from-november-2025); [TaxHeal: new FASTag rules from 15 Nov 2025](https://www.taxheal.com/new-fastag-rules-from-november-15-2025.html)
- Cash phase-out at NH toll plazas in 2026: sources cite either 1 April or 10 April 2026 ("Zero-Cash" policy). — [Business Today, Apr 2026](https://www.businesstoday.in/latest/trends/story/fastag-upi-toll-cash-payment-india-2026-new-rules-525077-2026-04-11); [TollGuru India](https://tollguru.mapup.ai/india-toll)
- Under the e-notice system, unpaid user fees default to 2× the toll, waived if paid within 72 hours of the notice. Defaults persisting beyond 15 days can be flagged in VAHAN, which blocks services such as ownership transfer. — [TollGuru India](https://tollguru.mapup.ai/india-toll); [TrucksBuses: FASTag 2026 updates](https://www.trucksbuses.com/news/fastag-2026-updates-new-toll-rules-penalties-and-changes)
- The FASTag Annual Pass (launched 15 Aug 2025; ₹3,075 for FY 2026-27) is for **non-commercial** vehicles only, so it does not apply to trucks. — [TollGuru India](https://tollguru.mapup.ai/india-toll)
- **Delhi ECC from 1 Apr 2026:**
  - LCV/2-axle trucks (categories 2 and 3): ₹1,400 → **₹2,000**.
  - 3-axle and multi-axle trucks (categories 4 and 5): ₹2,600 → **₹4,000**.
  - Rates rise 5% a year, rounded to the nearest ₹10.
  - MCD collects the charge at roughly 116–124 entry points.
  - One analysis lists exemptions for vehicles carrying essential commodities, foodstuffs and oil tankers destined for Delhi.
  
  — [Tribune India](https://www.tribuneindia.com/news/delhi/delhi-govt-hikes-environment-compensation-charge-on-commercial-vehicles-entering-capital/); [SCC Online](https://www.scconline.com/blog/post/2026/04/21/sc-approves-enhanced-ecc-rates-curb-vehicular-pollution-delhi/); [Corpseed](https://www.corpseed.com/law-update/delhi-revises-ecc-rates-for-commercial-vehicles-from-april-2026); [Outlook Money](https://www.outlookmoney.com/news/mcd-charged-over-rs-80-crore-as-environment-compensation-from-commercial-vehicles); [LawSathi](https://lawsathi.in/supreme-court-approves-revised-environment-compensation/)
- **Overloading (India):**
  - ₹20,000 plus ₹2,000 per additional tonne under MV Act s.194(1).
  - A 2018 Supreme Court judgment applies it to loads more than 10% over the limit and directed states to set up weighbridges.
  - West Bengal's compounding order (from 1 Aug 2021) uses the same ₹20,000 + ₹2,000/t.
  - Local practice varies: the Badarpur toll plaza reportedly charged ₹2,000 for the first tonne and ₹1,000 per additional tonne, and border checkpoints such as Nipani are reported at around ₹25,000.
  
  — [TrucksBuses: overloading challan](https://www.trucksbuses.com/blog/overloading-challan-and-fine-rules-for-commercial-vehicles); [WBXPress: WB overloading penalty](https://wbxpress.com/penalty-overloading-goods-vehicles-west-bengal/); [FR8: overloading rules](https://www.fr8.in/blog/truck-overloading-rules-in-india/); [Udayavani](https://english.udayavani.com/article/120-trucks-entering-delhi-fined-for-overloading/309239)
- **Salik (Dubai):**
  - The official portal says all vehicles pay AED 4 per Dubai gate, and **5% VAT applies to all toll transactions from 1 June 2026**.
  - Tags are bought online or at petrol stations.
  
  — [u.ae: Tolls](https://u.ae/en/information-and-services/transportation/roadways/tolls)
- Salik variable pricing from 31 Jan 2025: AED 6 at peak and AED 4 off-peak. This conflicts with TollGuru's flat AED 4 for heavy vehicles. — [The National, Jan 2025](https://www.thenationalnews.com/news/uae/2025/01/17/dubais-new-salik-toll-gate-charges-come-into-effect-on-january-31/); [TollGuru UAE](https://tollguru.mapup.ai/united-arab-emirates-toll)
- Passing a Salik gate without a tag reportedly costs AED 100 (first offence), AED 200 (second) and AED 400 after that. — [Invygo: toll gates](https://www.invygo.com/en-ae/blog/toll-gates-and-how-to-avoid-heavy-fees); [The National](https://www.thenationalnews.com/news/uae/2025/01/30/new-salik-charges-dubai-toll-gates/)
- **Darb (Abu Dhabi) from 1 Sept 2025:**
  - AED 4 per gate crossing during peak hours: 07:00–09:00 and 15:00–19:00, Mon–Sat.
  - Free on Sundays and public holidays.
  - Daily and monthly caps removed (previously AED 16/day; AED 200/150/100 per month for private vehicles).
  - Automatic exemptions cover buses, motorcycles and trailers, but not trucks.
  
  — [Gulf News: Darb explained](https://gulfnews.com/living-in-uae/transport/darb-toll-system-abu-dhabi-explained-fees-timings-and-exemptions-1.500219396); [Gulf News: caps lifted](https://gulfnews.com/uae/transport/abu-dhabi-revises-darb-toll-rules-lifts-daily-and-monthly-caps-1.500233627); [Abu Dhabi Media Office](https://www.mediaoffice.abudhabi/en/transport/to-ease-traffic-flow-in-the-emirate-integrated-transport-centre-abu-dhabi-mobility-issues-amendments-to-road-tolls-in-abu-dhabi/); [The National, Aug 2025](https://www.thenationalnews.com/news/uae/2025/08/31/abu-dhabi-darb-toll-timings-darb/)
- New Darb gates at Ghantoot and Al Qurm were reportedly due to start on 4 May 2026, operating 24/7 at AED 4. This comes from a single unverified source. — [Dubizzle: Abu Dhabi toll gate system](https://www.dubizzle.com/blog/cars/abu-dhabi-toll-gate-system/)
- ENOC's Tasjeel Select Plus card covers vehicle testing, registration and **traffic fines**, as well as fuel. This shows that UAE fleets treat fines and testing as card-paid expense categories. — [Al Bawaba (2014)](https://www.albawaba.com/business/pr/enoc-retail-tasjeel-select-plus-547104)
- Fuel theft and "short fuelling" happen at dhabas, border check-posts and loading docks, so these locations are expense and fuel risk points. — [Fleetx: how fuel theft happens](https://blog.fleetx.io/how-fuel-theft-happens-in-indian-truck-fleets-and-how-a-sensor-catches-it-in-real-time/)

### Inferences
*[Inference, practitioner knowledge, unverified: proposed expense category list]*

**India.** Each expense captures amount, payment mode, location/GPS, photo of bill, odometer and notes.

| Expense | Notes / extra fields |
|---|---|
| Diesel | litres, rate, pump, card/credit/cash, full-tank flag |
| AdBlue/DEF | for BS-VI trucks |
| FASTag toll | usually auto-debited; import from the FASTag statement rather than driver entry |
| Toll paid by UPI/cash | penalty-rate flag |
| Weighbridge / dharam kanta | fee + slip photo + weight |
| Loading hamali | — |
| Unloading hamali | — |
| Parking / halt | — |
| Driver food / bata | — |
| RTO/police challan (official) | challan no. |
| "Mamool" / unofficial | kept as a separate, flagged category so owners can see it; some owners will want it hidden from customer-facing reports |
| State entry tax / temporary permit / border fees | — |
| Delhi ECC / municipal entry | — |
| Overloading fine | — |
| Tyre puncture | — |
| Tyre purchase | — |
| Repair: mechanical | — |
| Repair: electrical | — |
| Spares | — |
| Crane / towing | — |
| Tarpaulin / rope | — |
| Washing / greasing | — |
| Phone recharge | — |
| Medical | — |
| Miscellaneous | — |
| Driver cash top-up | advance; not an expense |
| Cash collected from party | not an expense |

**UAE:**

| Expense | Notes |
|---|---|
| Diesel (ADNOC/ENOC/Emarat; card vs cash) | — |
| Salik | import from account statement |
| Darb | import from account statement |
| Parking | — |
| Weighbridge | — |
| Traffic fines | attribute to driver/truck |
| Truck-ban permit fees | — |
| Border/customs charges | — |
| Accommodation / food allowance (cross-border) | — |
| Vehicle testing / registration fees | — |
| Labour / unloading | — |
| Miscellaneous | — |

Every expense also needs an "approved by owner / pending / rejected" status, which drives the driver ledger.

### Gaps
- I found no 2026 source on typical weighbridge fees, hamali rates, parking rates or police/RTO "mamool" amounts. The planned search on surveys of bribes paid by truckers (e.g. SaveLIFE Foundation) did not run.
- I found no truck-specific official Salik or Darb rate confirmation, or confirmation of whether peak pricing applies to heavy vehicles.
- I did not research state border or entry charges, such as temporary permits for vehicles without a national permit, or post-GST checkpost status.
- I did not research UAE weighbridge or axle-load fines.

---

## 6. Fuel management practice (mileage, km/l by truck type, theft and detection, pump credit, fuel cards)

### Takeaway
Indian heavy trucks typically return about 3–5 km/l loaded:
- 10-wheelers about 3–5;
- 12/14-wheelers about 3–4 (some listings up to 6);
- 18-wheeler tractors about 3–4;
- MCV 6-wheelers about 5–6 (small LCV 6-tyre about 9).

These are catalogue or dealer figures, not tested. The main fuel losses come from:
- siphoning at stops;
- pump–driver collusion (short fuelling);
- fake or duplicate bills;
- small daily draws.

Detection relies on tank-to-tank mileage checks, fuel-level sensors, fuel cards with PIN or RFID, and audits.

All three Indian oil marketing companies offer fleet cards: IOCL XTRAPOWER, HPCL DriveTrack Plus and BPCL SmartFleet. In the UAE, ENOC (Select Plus, ViP tag) and Emarat (Safeer, Atheer) cards are documented. I found no product details for an ADNOC fleet card.

### Cited Findings
- **Mileage by truck type (India; dealer/aggregator catalogue figures):**

  | Truck type | Reported km/l | Source |
  |---|---|---|
  | 10-wheeler (Mahindra listing) | ~3–5 | [TrucksBuses: Mahindra 10-wheeler](https://www.trucksbuses.com/pc/mahindra-10-wheeler-trucks) |
  | 10-wheeler (Eicher) | 4–5 | [TrucksBuses: Eicher 10-wheeler](https://www.trucksbuses.com/pc/eicher-10-wheeler-trucks) |
  | 12-wheeler | 3–6 or 2–4 (sources vary); one Ashok Leyland model 3.25–4.25 | [TrucksBuses: 12-wheeler](https://www.trucksbuses.com/pc/12-wheeler-trucks); [TrucksBuses: AL 4120 mileage](https://www.trucksbuses.com/trucks/cargo-truck/ashok-leyland-4120/mileage) |
  | 14-wheeler | 3–6 or 3–4; specific heavy-haul models 3.5–4 | [TrucksBuses: 14-wheeler](https://www.trucksbuses.com/pc/14-wheeler-trucks); [FR8 mileage table](https://www.fr8.in/blog/?p=4698) |
  | 6-wheeler | small LCV 6-tyre ~9; single-axle MCV 5–6; another page gives 4–8 | [TrucksBuses: 6-wheeler](https://trucksbuses.com/pc/6-wheeler-trucks) |
  | 18-wheeler tractor (Ashok Leyland) | ~3–4 | [TrucksBuses: Top 10 Ashok Leyland](https://www.trucksbuses.com/blog/top-10-ashok-leyland-tippers-and-trucks-price) |
  | 16-wheeler, 22-wheeler | no reliable figure found | [91trucks 22-wheeler](https://www.91trucks.com/trucks/22-wheeler); [TrucksBuses 16-wheeler](https://trucksbuses.com/pc/16-wheeler-trucks) |

- **Theft methods:**
  - **siphoning** overnight or at stops (dhabas, border check-posts, loading docks);
  - **systematic collusion** between drivers and pump attendants, who consistently under-fill at agreed stations;
  - **duplicate or fabricated receipts**, hard to verify, especially where stations don't issue GST-compliant invoices;
  - small repeated draws of 100 ml to 1–2 l a day (vendor claim).
  
  — [Fleetx: how fuel theft happens in Indian truck fleets](https://blog.fleetx.io/how-fuel-theft-happens-in-indian-truck-fleets-and-how-a-sensor-catches-it-in-real-time/); [Heavy Vehicle Inspection: fuel management challenges](https://heavyvehicleinspection.com/blog/post/fuel-management-challenges-indian-fleets)
- **Detection:**
  - fuel-level sensors integrated with GPS to compare expected and actual consumption and flag sudden drops;
  - video telematics around the tank;
  - locking fuel caps and anti-siphon devices;
  - refuelling SOP training;
  - unannounced fuel audits;
  - RFID fuel cards with driver authentication, to eliminate cash.
  
  — [Fleetx: 5 tips to prevent fuel theft](https://blog.fleetx.io/5-tips-to-prevent-fuel-theft-by-truck-drivers/); [Heavy Vehicle Inspection: best practices](https://heavyvehicleinspection.com/blog/post/fuel-management-challenges-indian-fleet-operations-best-practices); [Motorindia: Cyrrup fuel theft detection](https://www.motorindiaonline.in/cyrrup-offers-fully-indigenous-fuel-theft-detection-solution-for-fleets/)
- The claim that "about 8% of diesel filled in trucks is stolen in transit" recurs across vendor blogs and could not be traced to an independent study. — [Fleetx: fuel monitoring savings](https://blog.fleetx.io/fuel-monitoring-system-india-how-much-can-fleet-owners-realistically-save-per-vehicle/)
- **IOCL XTRAPOWER:**
  - recharge via RTGS, NEFT, UPI or cards;
  - loyalty points;
  - expense and trip tracking;
  - fleet analytics;
  - NBFC financing and insurance benefits;
  - more than 34,000 outlets.
  
  It launched in 2004 and is about 20% of IOCL's retail HSD sales. — [IOCL XTRAPOWER Fleet Card](https://iocl.com/pages/XTRAPOWERFleetCard); [IOCL news](https://iocl.com/NewsDetails/38669); [IOC XtraPower blog](https://www.iocxtrapower.com/blog/5-reasons-why-you-should-enroll-yourself-for-xtrapower-fleet-card)
- **HPCL DriveTrack Plus:**
  - prepaid card for fuel and lubes at HPCL pumps, with loyalty points;
  - co-branded with Ashok Leyland, BharatBenz ("DieselAabh") and VE Eicher;
  - credit fuel with Shriram Transport Finance (2018);
  - Paytm partnership for payments.
  
  — [HPCL loyalty program](https://hindustanpetroleum.com/pages/loyalty-program); [Daimler Truck: BharatBenz–HPCL DieselAabh](https://asia.daimlertruck.com/en/press-releases/india/bharatbenz-and-hindustan-petroleum-corporation-limited-launch-dieselaabh); [BW Marketing World: Paytm–HPCL](https://bwmarketingworld.com/article/paytm-hpcl-partner-to-transform-drivetrack-plus-loyalty-program-fuel-payments-491853)
- **BPCL SmartFleet:** launched Dec 2000; a cashless card with PIN or digital authentication at authorised stations. — [BPCL blog](https://www.bharatpetroleum.in//blog/Blog-Detail.aspx?id=46)
- **ENOC fleet products:**
  - **Select Plus Credit** (UAE and Oman): daily, weekly or monthly limits, web portal.
  - **Select Plus Debit**: per-card limits, downloadable statements.
  - **ViP** chip tag (2014): links each fuel transaction to a specific vehicle, to stop unauthorised refuelling.
  - **ENOC Link**: fuel delivery by bowsers (800–5,000 l) in industrial areas.
  
  — [Arabian Business](https://www.arabianbusiness.com/abnews/enoc-introduces-smart-cards-135564); [Gulf News: chip to chip](https://gulfnews.com/uae/chip-to-chip-fuelling-up-made-easy-in-dubai-1.1340843); [Gulf News: ENOC fuel delivery](https://gulfnews.com/uae/transport/uaes-enoc-launches-fuel-delivery-service-to-rival-cafu-1.66989265)
- **ADNOC:** I found no fleet-card details. Self-refuelling accepts smart tag, ADNOC Plus cards, Emirates ID or cash. — [Gulf News: ADNOC self-refuelling](https://gulfnews.com/business/tourism/adnoc-rolls-out-first-phase-of-self-refuelling-petrol-stations-1.1682393)
- **Emarat:** fleet cards are Safeer (large fleets), Atheer (small and medium fleets) and Connect. These appeared in UAE petrol-station guides; the specific page was not confirmed. — [Bayut: petrol stations in Dubai](https://www.bayut.com/mybayut/petrol-stations-dubai); [Cars24 UAE](https://www.cars24.ae/blog/best-petrol-stations-uae/)

### Inferences
*[Inference, practitioner knowledge, unverified]*

- **Full-tank-to-full-tank ("tank-to-tank") method:**
  - Driver fills to full and records the odometer.
  - At the next full fill, km since last full ÷ litres added (including any partial fills in between) = km/l.
  - The app needs a "full tank" checkbox and odometer photo on every fuel entry, and must chain partial fills.
- Compare trip km/l with a per-truck **expected km/l**, which varies by loaded vs empty and by lane or terrain. Flag variance above a threshold (e.g. 5–8%). Without sensors, this is the main way small fleets catch theft.
- **Pump credit:**
  - Many small Indian owners run a monthly credit account ("udhaar") with one or two pumps on their regular lanes.
  - The driver signs a slip; the pump sends a monthly statement.
  - The app should treat this as "payment mode = pump credit (party X)" and support statement reconciliation.
- **Fuel cards:** importing transactions (by API or CSV) into the trip is a high-value feature, whether XTRAPOWER, DriveTrack, SmartFleet or ENOC/Emarat. Card fills should not be double-counted against the driver's cash advance.
- **Fields per fuel entry:**
  - date/time, GPS;
  - pump/brand;
  - odometer + photo;
  - litres, rate, amount;
  - payment mode (cash / card no. / pump credit / UPI);
  - bill photo + bill no.;
  - full tank Y/N;
  - filled by (driver / co-driver);
  - tank capacity reference;
  - AdBlue litres.

### Gaps
- I found no independent or tested km/l benchmarks by axle configuration. All figures are catalogue or dealer claims.
- I found no data on prevalence of pump credit practice, typical credit periods or fuel-card fees.
- I found no ADNOC fleet-card product information.
- I found no UAE diesel price data for 2026. Prices are set monthly; not researched.

---

## 7. Tyre management practice (numbering, positions, retreading, km life, theft/swap fraud)

### Takeaway
Tyres are reportedly about 30% of Indian fleet operating costs. Over 70% of truck tyres are retreaded at least once (ICRA). A retread restores about 80–90% of life at about 40–50% of new-tyre cost.

Reported km life clusters around 95,000–150,000 km per life, from anecdotal operator data. One transporter reports about 250,000 km on new tyres with careful maintenance.

Fleet software maps each tyre to vehicle, axle and position, and tracks tread depth, pressure, rotation, retread cycles and mileage. I found no published Indian standard for position codes, and no published data on tyre swap or theft fraud.

### Cited Findings
- Tyres are about 30% of fleet operating cost, and retreading is moving from stop-gap to planned lifecycle strategy. — [Tyre Trends: Retreading reimagined](https://tyre-trends.com/spotlight/retreading-reimagined)
- A retread restores 80–90% of lifespan at about 40–50% of new-tyre cost. — [Tyre Trends Apr/May 2026](https://tyre-trends.com/magazine/tyre-trends-aprilmay-2026)
- Continental puts the retread saving at more than 50% of new-tyre cost. — [Autocar Pro: Continental–Indag retreading](https://autocarpro.in/news-national/continental-tires-and-indag-rubber-partner-for-retreading-of-truck-and-bus-radials-115156)
- ICRA: more than 70% of Indian truck tyres are retreaded at least once. — [Tyre Trends: ICRA](https://tyre-trends.com/spotlight/70-percent-truck-tyres-in-india-are-retreaded-once-icra)
- Indian CV tyres typically last 2–3 years (Continental India). — [Autocar Pro: Continental 5-year warranty](https://autocarpro.in/news-national/continental-india-announces-fiveyear-warranty-on-truck-and-bus-tyres-79617)
- **Km life anecdotes:**
  - A fleet reports about 95,000 km from retread casings.
  - Another transporter reports 150,000 km on retreads and about 250,000 km on new tyres.
  
  — [Retreading Business, Feb 2022, p.57](https://retreadingbusiness.com/magazines/2022/02/files/basic-html/page57.html)
- A Delhi retreader serving the DTC (bus) fleet averages 90,000 km per retread. — [Retreading Business](https://www.retreadingbusiness.com/?p=18745)
- A bus operator reports about 1,20,000 km for a new radial before its first retread, and over 2,00,000 km with two retreads. This is coach data, not trucks. — [Motorindia: fleet tyre selection](https://www.motorindiaonline.in/cost-service-and-safety-key-factors-in-selection-of-fleet-tyres/)
- Tyre-module practice: every tyre is "digitally tagged and mapped to a vehicle, axle, and position", and the module tracks tread depth, pressure, rotation schedules, retreading cycles and mileage. This is vendor marketing. — [Fleetx tyre management module](https://blog.fleetx.io/top-5-benefits-of-utilizing-the-fleetx-tyre-management-module/)
- Correct pressure, regular rotation and alignment, and sensible driving extend tyre life. No intervals are given. — [91trucks: increase tyre life](https://www.91trucks.com/news/top-3-ways-to-increase-tyre-life-on-trucks?amp=1)
- About 30% of Indian fleets are "organised", and most large fleets have 100–200+ trucks. JK Tyre has tie-ups with about 200 retread sites. — [Motorindia: JK Tyre and Jaipur fleets](https://www.motorindiaonline.in/jk-tyre-bonds-well-with-jaipurs-biggest-fleet-operators/)
- The retreading sector remains largely informal and under-incentivised (June 2026). — [The Week](https://www.theweek.in/news/sci-tech/2026/06/04/why-tyre-retreading-is-crucial-for-economic-environmental-sustainability-in-india.amp.html)

### Inferences
*[Inference, practitioner knowledge, unverified]*

- **Tyre master:**
  - tyre serial no. (moulded on the sidewall) or owner-stencilled no.;
  - brand/model;
  - size (common Indian truck sizes include 10.00-20 bias and 295/80 R22.5 radial; validate);
  - type (bias/radial; new/retread);
  - purchase date, vendor, price, invoice;
  - warranty;
  - retread count and retreader;
  - status (in use / spare / at retreader / scrapped / claimed).
- **Position codes** should be generated from the axle configuration. For a 10-wheeler (one front axle + tandem rear, dual tyres):
  - FL, FR (front);
  - R1-LO, R1-LI, R1-RI, R1-RO;
  - R2-LO, R2-LI, R2-RI, R2-RO;
  - SPARE.

  Trailers need their own position set, linked to the trailer's own ID.
- **Fit/remove event:**
  - date, truck, position;
  - odometer at fit and at removal → km run;
  - reason (puncture, wear, burst, rotation, retread, theft);
  - tread depth (mm);
  - photo of serial;
  - who did it and where.
- **Fraud patterns to design for:**
  - new tyres swapped for old or retreaded ones at a roadside garage;
  - the spare (stepney) sold;
  - false puncture or repair claims.

  Mitigation: a photo of the sidewall serial at every fit or remove event, plus periodic photo audits of all positions.

### Gaps
- I found no published Indian tyre-position coding standard, no rotation interval norms, and no typical km life by position (steer / drive / trailer) or by tyre type.
- I found no sources documenting tyre theft or swap fraud prevalence.
- I found no UAE tyre practice sources (heat-related wear, retread usage). Not researched.

---

## 8. Maintenance: service schedules, breakdown handling, defect/complaint tracking

### Takeaway
OEM service intervals for modern BS-VI heavy trucks are long. BharatBenz raised its HDT haulage and tractor interval from 50,000 to 60,000 km, and Ashok Leyland claims a 30% longer interval on the Boss BS6, an ICV. Generic (non-Indian) guidance for heavy long-haul trucks on conventional oil is 12,000–15,000 km.

I found no Indian sources on breakdown-handling practice or defect-tracking conventions.

### Cited Findings
- BharatBenz increased service intervals from 50,000 km to 60,000 km on its HDT haulage and tractor range, a 20% increase. — [BharatBenz BS-VI trucks](https://www.bharatbenz.com/BS-VI-trucks-and-buses)
- Ashok Leyland claims a 30% longer service interval on the Boss BS6, an intermediate CV, without stating km. — [Rushlane: Ashok Leyland Boss BS6](https://www.rushlane.com/ashok-leyland-boss-bs6-12380457.html)
- Generic guidance from a non-OEM, non-India source: city delivery trucks on conventional oil every 5,000–8,000 km; heavy long-haul trucks on conventional oil every 12,000–15,000 km. — [Oxmaint: engine oil change intervals](https://oxmaint.com/blog/post/blog-post-engine-oil-change-intervals-how-often)
- Fitness certificate renewal for heavy goods vehicles must go through a registered Automated Testing Station from 1 Apr 2023. This makes roadworthiness a hard periodic event (see §9). — [Fox Mandal](https://foxmandal.in/news/rules-governing-vehicle-fitness-certification-amended/)
- An e-way bill can be extended within 8 hours of expiry when the vehicle breaks down. Part B must be updated if goods are moved to another vehicle. Breakdown handling therefore has a document component. — [Tally e-way bill FAQ](https://tallysolutions.com/gst/frequently-asked-questions-about-the-e-way-bill-answered/); [Digit](https://www.godigit.com/gst/eway-bill-requirement-for-transportation-of-goods.amp.html)

### Inferences
*[Inference, practitioner knowledge, unverified]*

- **Service schedule master per truck model:**
  - interval by km and/or months, by item: engine oil, oil/fuel/air filters, gearbox/diff oil, greasing, brake adjustment or liners, clutch, coolant, battery, AdBlue filter, wheel alignment/balancing;
  - next-due km/date computed from the last service and odometer readings logged on trips.
- **Defect/complaint log raised by crew in the app:**
  - date/time, GPS, odometer;
  - category (engine, brakes, tyres, electrical, body, AC, tracker/VLTD);
  - severity (can continue / must stop);
  - description, voice note, photo/video;
  - status (open → assigned → fixed → verified);
  - workshop, cost, downtime hours.
- **Breakdown event:**
  - location, cause;
  - mechanic called (roadside) or towed;
  - parts bought (with bills, posted to driver ledger if paid in cash);
  - time lost;
  - cargo transhipped (new vehicle no. → e-way bill Part B update);
  - customer informed.
- **Job card:**
  - job no., truck, odometer;
  - complaints;
  - work done;
  - parts (part no., qty, price), labour;
  - vendor, invoice, GST;
  - warranty claim flag.

### Gaps
- I found no published OEM service schedules for Tata, Ashok Leyland or Eicher heavy trucks, and no UAE OEM data such as Volvo, Mercedes Actros or Isuzu intervals.
- I found no sources on Indian breakdown-handling norms or roadside assistance usage.

---

## 9. Vehicle and driver documents and expiries (India and UAE)

### Takeaway
**India vehicle documents** that drive expiry reminders:
- **Fitness certificate:** renewed every 2 years up to 8 years of age, then yearly, through Automated Testing Stations.
- **National Permit:** a 5-year permit, plus an annual authorisation. The authorisation fee figure is unverified.
- **Insurance.**
- **PUC.**
- **Road tax.**
- **AIS-140 VLTD:** required on national-permit goods vehicles in many states, enforced at registration, fitness and permit events.
- **FASTag.**

**India driver documents:**
- transport-vehicle driving licence;
- hazardous-goods endorsement (Rule 9 CMVR, with a 3-day course; the Supreme Court in 2025 confirmed it matters for insurance);
- hill-driving endorsement where relevant.

**UAE (Abu Dhabi):**
- an ITC operator permit (1 year);
- per-vehicle permits that require a working tracker;
- driver permits (2 years);
- a heavy-vehicle licence (licence category numbering conflicts across sources);
- Mulkiya registration (12 months, with insurance of at least 13 months, renewable up to 150 days early).

### Cited Findings
- **Fitness certificate (India):**
  - Renewed for 2 years for vehicles up to 8 years old, and 1 year for vehicles older than 8 years (CMV Eighth Amendment Rules 2022).
  - Tamil Nadu's STA describes the same 2-year / 1-year pattern, renewed after paying tax.
  
  — [Lexplosion](https://lexplosion.in/morth-notifies-central-motor-vehicles-eighth-amendment-rules-2022-amends-the-validity-period-of-a-renewed-certificate-of-fitness/); [TN STA: registration and fitness](https://tnsta.gov.in/Indexes/vehicle_reg_fitness)
- Fitness renewal must be done through a registered ATS: heavy goods vehicles from 1 Apr 2023; medium goods and light transport from 1 June 2024. — [Fox Mandal](https://foxmandal.in/news/rules-governing-vehicle-fitness-certification-amended/)
- **National Permit:**
  - HGV permit fee ₹2,515 (LGV ₹2,015), valid **5 years**.
  - LGV authorisation ₹500, valid 1 year.
  - Home-state fees vary from ₹1,500 to ₹5,000.
  - The fee figures may be outdated.
  
  — [BankBazaar: permit fees](https://www.bankbazaar.com/driving-licence/permit-fees-of-motor-vehicles-in-India.html); [Bajaj Finserv: permit fees](https://bajajfinserv.in/insurance/permit-fees-of-motor-vehicles-in-india)
- Assam's transport department says the NP covers at least four states including the home state. Another source says three plus the home state, excluding Delhi; the sources conflict. — [Assam Transport: apply for national permit](https://comtransport.assam.gov.in/how-to/apply-for-national-permit); [BankBazaar](https://www.bankbazaar.com/driving-licence/permit-fees-of-motor-vehicles-in-India.html)
- A used-truck guide says an NP renews on a 1-year cycle tied to fitness, and that goods vehicles on an NP should generally not exceed 12 years of age. This is a commercial source and is unverified. — [Vahan Bazaar: NP 12-year age cap](https://vahanbazaar.in/news/used-truck-national-permit-12-year-age-cap-2026)
- **AIS-140 VLTD:**
  - MoRTH notifications from 2016 to 2018, including GSR 979(E) of 28 Sept 2018, require vehicles with a national permit to carry a VLTD with emergency button(s).
  - **Odisha:** older vehicles had to comply by 30 June 2024; non-compliant vehicles are blocked from VAHAN services such as permit and NP authorisation.
  - **Tripura:** NP goods carriers registered on or after 1 Jan 2019 must fit from 20 Mar 2025; older vehicles follow in a later phase.
  - **Himachal Pradesh** requires it for NP vehicles.
  - A vendor guide says RTOs enforce it at registration, fitness renewal and permit issue or renewal.
  
  — [WBXPress VLTD notification](https://wbxpress.com/files/2023/01/326-VLTD.pdf); [Fleetx AIS-140 guide (May 2026)](https://blog.fleetx.io/blog-ais140-guide/); [Orissa Post](https://www.orissapost.com/odisha-mandates-vltd-panic-buttons-for-vehicles-all-you-need-to-know/amp/); [Tripura Transport](https://transport.tripura.gov.in/pdf/CMVR125H.pdf); [Himachal notification](https://himachal.nic.in/WriteReadData/l892s/3_l892s/1563282047.pdf); [Fleetx VLTD guide 2026](https://blog.fleetx.io/blog-vltd-ais-140-vehicle-location-tracking-device-guide/); [Vahan Bazaar: AIS-140 and fitness](https://vahanbazaar.in/news/ais-140-vltd-vahan-fitness-used-truck-2026)
- July 2026: MoRTH exempted battery-electric, ethanol, methanol and hydrogen goods and passenger vehicles from permit requirements for 7 years, on condition that they carry an AIS-140 VLTD. — [Autocar Pro](https://www.autocarpro.in/news/electric-ethanol-hydrogen-cvs-get-7-year-permit-exemption-133500)
- **FASTag compliance:** unpaid tolls persisting past 15 days can be flagged in VAHAN, blocking services. — [TollGuru India](https://tollguru.mapup.ai/india-toll)
- **Hazardous goods endorsement (India):**
  - Drivers of hazardous-goods vehicles need a Hazardous Goods endorsement in addition to a transport licence.
  - Under Rule 9 CMVR this requires literacy in an Indian language and English, plus a certificate from a 3-day course at a state-recognised institute.
  - A **Hill Driver** endorsement applies to ghat or hill sections.
  
  — [Wikipedia: Driving licence in India](https://en.wikipedia.org/wiki/Driving_licence_in_India); [NewMediaComm: transport of hazardous materials](https://newmediacomm.com/transportation-of-dangerous-and-hazardous-materials/)
- The Supreme Court in *Chatha Service Station v. Lalmati Devi* (2025 INSC 468) held that the endorsement is not a mere technicality and upheld "pay and recover" against the owner. A 2024 Punjab & Haryana HC ruling had required proof that the missing endorsement caused the accident. — [LawFoyer](https://lawfoyer.in/m-s-chatha-service-station-v-lalmati-devi-ors-2025-5-s-c-r-435-2025-insc-468/); [CaseMine commentary](https://www.casemine.com/commentary/in/reaffirming-the-endorsement-requirement-for-drivers-of-hazardous-goods-vehicles/view); [SupremeToday (P&H HC)](https://supremetoday.ai/doc/judgement/02300084814)
- Under s.132(2), as cited in one source, goods-carriage owners must ensure the consignor's dangerous-goods information is full and accurate before transport. — [Shashi Kallada: dangerous goods by road](https://shashikallada.com/wp-json/wp/v2/pages/\"https:\/\/shashikallada.com\/dangerous-goods-by-road-india\/\")
- **Abu Dhabi ITC Freight Transport Regulation:**
  - No freight activity without a prior ITC permit and a valid trade licence.
  - The permit requires electronic tracking devices in every vehicle operated.
  - **Operator permit: 1-year validity**, cancelled 2 months after expiry.
  - **Driver permit: 2 years**, renewable.
  - "Heavy vehicle" means a goods vehicle with an unloaded weight above 2.5 t, including trailers.
  - Applications go through the **Asateel** platform: company, vehicle and driver permits.
  - Fines apply after the grace period ended on 6 Oct 2022.
  
  — [ITC Freight Regulations PDF](https://www.itc.gov.ae/-/media/feature/itc-revamp/generic-content/related-documents/asateel/related-doc/freight-regulations-en.pdf); [Abu Dhabi Mobility: freight transport regulation](https://admobility.gov.ae/en/news/freight-transport-regulation-in-abu-dhabi-emirate); [Gulf News: new freight regulations](https://gulfnews.com/uae/abu-dhabi-issues-new-regulations-to-ensure-safety-of-freight-transport-1.87001625); [Gulf News: register on Asateel](https://gulfnews.com/uae/government/abu-dhabi-department-of-municipalities-urges-freight-transport-operation-companies-to-register-on-asateel-platform-1.1664925814493); [Dubai Eye](https://www.dubaieye1038.com/news/local/abu-dhabi-reminds-freight-carriers-of-new-rules)
- **UAE heavy-vehicle driving licence:**
  - Commonly described as covering trucks above 3.5 t.
  - Minimum age about 20.
  - Requires a light-vehicle licence first; one source says held for at least 1 year.
  - Theory and practical tests; heavy trailers need a separate category that requires a heavy licence first.
  - Sources conflict on category numbers ("Category 3" vs a training school's "Category 4") and on bus thresholds.
  - Dubai training requires an Emirates ID, an eye test and a medical fitness report from an RTA-approved centre.
  
  — [Shory: UAE licence categories](https://www.shory.com/car-insurance/blog/categories-of-driving-licenses-in-the-uae/); [LookInsure: licence categories](https://lookinsure.com/blog/driving-license-categories); [Bin Yaber: Heavy Truck Licence](https://binyaber.com/?p=1200); [Dubizzle: vehicle classification](https://www.dubizzle.com/blog/cars/motor-vehicle-classification-uae/); [PolicyBazaar UAE: driving licence](https://www.policybazaar.ae/rta/driving-license.md)
- RTA added theory classes for heavy-vehicle trainees and planned mandatory practical training for heavy trailer and tanker drivers. This is a 2016 article; current status unknown. — [Gulf News](https://gulfnews.com/amp/story/uae%2Ftransport%2Frise-in-uae-trailer-accidents-prompts-training-review-1.2004098)
- **Mulkiya (UAE vehicle registration):**
  - Renewal requires valid insurance (Dubai: at least 13 months), technical inspection where applicable (commonly for vehicles over 3 years old) and no outstanding fines.
  - Dubai registration runs up to 12 months, renewable up to 150 days before expiry, with a 30-day grace period.
  - Lapse can bring an AED 500 fine and impoundment of up to 3 months.
  - In Abu Dhabi, renewal is via the AD Police site/app with UAE Pass.
  - These sources are car-centric; heavy-vehicle specifics are not confirmed.
  
  — [Property Finder](https://www.propertyfinder.ae/blog/vehicle-registration-renewal-online/); [Shory: Mulkiya renewal](https://www.shory.com/car-insurance/blog/mulkiya-renewal-process-in-dubai); [ArabWheels](https://www.arabwheels.ae/blog/how-to-renew-your-car-registration-in-dubai-complete-guide/); [Dubizzle: Abu Dhabi registration](https://www.dubizzle.com/blog/cars/car-registration-renewal-abu-dhabi/); [PolicyBazaar UAE](https://www.policybazaar.ae/car-insurance/articles/how-to-renew-mulkiya-in-abu-dhabi.md)
- Dubai RTA "Mobility Permit for Heavy Vehicles in Banned Times and Roads" (from 1 Oct 2026). — [Khaleej Times](https://www.khaleejtimes.com/uae/transport/trucks-movement-restriction-dubai-roads-from-october-1)

### Inferences
*[Inference, practitioner knowledge, unverified: proposed document master]*

- **India vehicle documents.** Each record: doc no., issue date, expiry, issuing authority/state, scan, reminder lead days.
  - RC (and hypothecation/financier, with EMI date);
  - insurance: policy no., insurer, type (TP/comprehensive), IDV, expiry;
  - fitness certificate;
  - national permit: permit no., validity;
  - NP authorisation: annual;
  - state/contract permits;
  - temporary permits per trip;
  - PUC;
  - road/goods tax: state, period;
  - FASTag: tag ID, issuer bank, linked wallet;
  - AIS-140 VLTD: IMEI, vendor, SIM/annual renewal, state backend registration;
  - speed-governor certificate (not researched);
  - hazardous goods: explosive or PESO licence, TREM card (not researched).
- **India driver documents:**
  - DL no., class(es) and transport validity;
  - endorsements (hazardous: course certificate no. and expiry; hill);
  - issuing RTO;
  - Aadhaar/ID, PAN;
  - bank/UPI;
  - police verification;
  - medical fitness;
  - emergency contact;
  - photo;
  - joining date;
  - pay structure.
- **UAE vehicle documents:**
  - Mulkiya: plate no., emirate, expiry;
  - insurance;
  - test date;
  - ITC vehicle permit (Abu Dhabi);
  - tracker ID;
  - Salik tag no.;
  - Darb account;
  - RTA heavy-vehicle permits (banned times);
  - fuel card no.
- **UAE driver documents:**
  - UAE licence no. and categories, expiry;
  - Emirates ID, expiry;
  - residence visa, expiry;
  - work permit/labour card (MOHRE), expiry;
  - passport, expiry;
  - ITC driver permit (2 years);
  - medical fitness;
  - for cross-border: GCC entry documents.
- Expiry dashboard with lead times (e.g. 30/15/7 days) and "blocked" status. A truck should be undispatchable when its fitness or permit has expired; a driver should be unassignable with an expired licence or permit.

### Gaps
- I could not confirm the current NP authorisation fee. The ₹16,500/yr figure commonly quoted by practitioners was not found in any source. I also could not confirm the NP validity on an official site.
- I did not research PUC validity for BS-VI trucks, road-tax periodicity by state, speed-governor rules, or reflective tape and RUPD rules.
- I did not research India DL classes after the 2019 MV Amendment, including the LMV-transport up to 7,500 kg question, the badge requirement, or transport DL validity period.
- I found no UAE heavy-vehicle-specific inspection frequency or commercial insurance requirements.
- I did not research Dubai or other emirates' equivalents of Abu Dhabi's operator permit, nor UAE labour card or work permit formats.

---

## 10. Two-driver operations: how co-drivers share driving; records of who drove when

### Takeaway
India's Motor Transport Workers Act, 1961 sets an 8 h/day and 48 h/week limit, with a 30-minute break after 5 hours. Enforcement is weak and state-run. The mandatory two-driver requirement for national-permit trucks was relaxed around 2018, and in March 2025 the government said no mandatory double-driver proposal was under consideration.

I found no source describing how co-drivers split driving, or any record-keeping of who drove when, in India or the UAE.

### Cited Findings
- MTW Act: 8 hours/day and 48/week; overtime must be voluntary and total no more than 10 h/day; a 30-minute break after 5 hours of continuous driving. — [91trucks: truck driving limits in India](https://www.91trucks.com/news/truck-driving-limits-in-india-rules-reality-and-safety-tips)
- An academic paper gives a looser figure: hours extendable to 10–12 on special occasions and 48 per week, never beyond 54 per week. — [Loughborough University repository](https://repository.lboro.ac.uk/articles/Effects_of_driver_work-rest_patterns_lifestyle_and_payment_incentives_on_long-haul_truck_driver_sleepiness/9497444/files/17124113.pdf)
- Lok Sabha answer (March 2025): no proposal for a mandatory double-driver system is under consideration, and the MTW Act is administered by state governments. — [Sansad (Lok Sabha AU2437)](https://eparlib.sansad.in/bitstream/123456789/3015410/1/AU2437_glbjVS.pdf)
- A labour federation says the government relaxed the mandatory provision of two drivers on national-permit vehicles. An MP's letter refers to a 2018 amendment, after which a two-driver minimum was no longer required. — [NewsClick](https://www.newsclick.in/node/57754); [Deccan Herald](https://deccanherald.com/india/mp-binoy-viswam-urges-better-working-conditions-for-truck-drivers-807747)
- Driving-hour law has never been properly implemented, and the ministry planned an advisory to states. — [Global Fleet](https://www.globalfleet.com/en/safety-environment/global-asia-pacific/news/india-seeking-enforce-decades-old-driving-laws)
- Long-haul days run 10–14 hours, with up to about 600 km/day per driver on line-haul. — [91trucks: day in the life](https://www.91trucks.com/news/a-day-in-the-life-of-a-long-haul-truck-driver-in-india)

### Inferences
*[Inference, design recommendation]*

- Even without a legal mandate, a "driving shift" record is valuable for safety, disputes and accident and insurance claims (who was at the wheel).
- **Driver-change event** captured in the crew app:
  - time, GPS, odometer;
  - outgoing and incoming driver;
  - optional selfie for verification.

  The trip timeline can then compute km and hours per driver, and flag drives over 5 h without a break or over 10 h a day, matching the MTW Act thresholds.
- Co-driver pay may be split per trip, so the per-person ledger needs per-trip allocation of bata and incentives among driver, co-driver and helper.

### Gaps
- I found no source on how Indian or UAE crews actually share driving, or whether any fleet logs it.
- I found no UAE driver hours-of-service rules for trucks; the planned search did not run.
- Electronic logging requirements: none found for India or the UAE (not researched further).

---

## 11. Truck types, body types and capacity classifications (India and UAE)

### Takeaway
Indian transporters describe trucks by:
- **tyre count** ("chakka"): 6/10/12/14/16/18/22-wheeler;
- **body type**: open/half body, closed container (SXL/MXL), flatbed or trailer, tipper, tanker, reefer;
- **deck length in feet**: 19/20/22/24/32/40 ft;
- **payload in tonnes**.

Legal weights follow the 2018 MoRTH axle norms:
- 2-axle 18.5 t GVW;
- 3-axle 28.5 t;
- tandem axle 21 t; tri-axle 27 t;
- rigid cap 49 t; articulated/truck-trailer cap 55 t;
- +1 t per axle with pneumatic suspension.

In the UAE, Abu Dhabi defines a heavy vehicle as unloaded weight above 2.5 t. I found no UAE body-type taxonomy.

### Cited Findings
- **2018 MoRTH safe axle weights:**
  - 2-axle GVW 18.5 t (from 16.2 t); 3-axle 28.5 t (from 25 t).
  - Tandem axle 21 t; tri-axle 27 t (rigid vehicles, trailers and semi-trailers).
  - GVW not to exceed 49 t for rigid vehicles and 55 t for semi-articulated trailers and truck-trailers.
  - +1 t per axle with pneumatic suspension.
  - Models type-approved before 16 July 2018 are allowed the revised weights.
  
  — [MoRTH advisory on revision of safe axle weights](https://morth.gov.in/sites/default/files/circulars_document/Advisory_regarding_revision_of_safe_axle.pdf); [Team-BHP](https://www.team-bhp.com/news/government-increases-freight-loads-trucks); [Maritime Gateway](https://www.maritimegateway.com/?p=12476)
- **Body types and capacities (India; blog/commercial sources, figures conflict):**
  - **Open body:** a 19 ft open truck carries about 7–12 t; a 16-tyre open lorry is listed at 32 × 8 ft with 35 t; a 32 ft open truck at 32 t.
  - **Closed container:** 32 ft single-axle (SXL) about 7.5 t; 24 ft multi-axle about 16 t; 20 ft multi-axle about 16 t.
  - **ISO containers:** 20/40 ft, secured with twist-locks.
  - **Tankers:** 5,000–40,000 l.
  - **Tippers:** 6–31 t payload.
  - **Trailers:** 20–100 t; a 32 ft side-wall trailer at 50 t payload.
  - **18-wheelers:** 40–55 t+.
  
  — [Digit: types of trucks in India](https://www.godigit.com/motor-insurance/commercial-vehicle-insurance/truck-insurance/types-of-trucks-in-india); [FR8 transport service](https://www.fr8.in/transport-service/); [91trucks: truck types by capacity](https://www.91trucks.com/news/explore-truck-types-in-india-based-on-load-capacity-in-tons); [TrucksBuses: body types compared](https://www.trucksbuses.com/blog/truck-body-types-explained-container-flatbed-tanker-and-tipper-compared); [TrucksBuses: load capacities](https://www.trucksbuses.com/blog/different-types-of-trucks-and-their-load-capacities); [IndiaMART trailers](https://m.indiamart.com/impcat/trailer.html)
- GVW explained (Indian context). — [FR8: GVW of trucks](https://www.fr8.in/blog/gvw-gross-vehicle-weight-of-trucks)
- FR8's halting slabs distinguish "32 ft MXL" from "20 ft / 6-wheeler". This confirms that Indian rate cards use length + axle shorthand. — [FR8 Special Halting Terms](https://www.fr8.in/specialHaltingTerms.html)
- **UAE:**
  - Abu Dhabi defines a heavy vehicle as a goods vehicle with unloaded weight above 2.5 t, including trailers. — [ITC Freight Regulations](https://www.itc.gov.ae/-/media/feature/itc-revamp/generic-content/related-documents/asateel/related-doc/freight-regulations-en.pdf)
  - The heavy-vehicle licence covers trucks above 3.5 t, and heavy trailers need a separate category. — [Shory](https://www.shory.com/car-insurance/blog/categories-of-driving-licenses-in-the-uae/); [Dubizzle classification](https://www.dubizzle.com/blog/cars/motor-vehicle-classification-uae/)
- Delhi ECC categories split LCV/2-axle (cat. 2–3) from 3-axle and multi-axle (cat. 4–5). A truck's axle count therefore drives some charges directly. — [Tribune India](https://www.tribuneindia.com/news/delhi/delhi-govt-hikes-environment-compensation-charge-on-commercial-vehicles-entering-capital/)

### Inferences
*[Inference, practitioner knowledge, unverified: proposed truck master]*

**Identity and ownership:**
- Registration no.
- Make/model.
- Year/month of manufacture.
- Chassis no., engine no.
- Emission norm (BS-IV / BS-VI).
- Fuel type (diesel/CNG/LNG/EV).
- Owner/financier.

**Configuration:**
- Tyre count: 4/6/10/12/14/16/18/22.
- Axle configuration: 4x2, 6x2, 6x4, 8x2, 10x2, tractor 4x2/6x4 + trailer axles.
- Lift axle Y/N.
- Body type, from the list below.
- Deck length (ft), width, height.
- Tank capacity (l).
- AdBlue tank.

**Body types (pick-list):**
- open/half body;
- full body / high-side;
- container closed (SXL/MXL);
- flatbed trailer;
- semi-low-bed / low-bed;
- side-wall trailer;
- skeletal (container carrier);
- tipper;
- tanker (liquid / LPG / bulk cement "bulker");
- reefer;
- car carrier;
- curtain-side (more common in the UAE).

**Weights and capacity:**
- GVW, ULW (unladen weight), registered payload.
- Typical carrying capacity in tonnes and in cubic feet.

**Rate-card shorthand:**
- Capture the deck length + axle label used in rate cards (e.g. "32 ft MXL", "20 ft SXL", "40 ft trailer").

**UAE specifics:**
- Tractor and trailer are usually registered separately, so trailer plate and Mulkiya need their own record. This is common in GCC practice but was not verified here.

**Pairing with crew practice:**
- Wheel count maps loosely to axles: 6-wheeler = 2 axles; 10-wheeler = 3; 12-wheeler = 4; 14-wheeler = 5. Owners use wheel count in speech; the app should store axles too, because legal weights and Delhi ECC categories key on axles.

### Gaps
- I did not confirm per-configuration legal GVW figures for 4-axle and 5-axle rigid trucks or for tractor-trailers beyond the 49 t / 55 t caps; the advisory table was partly garbled.
- I found no UAE truck body-type taxonomy, axle-load limits or overloading fines.
- I found no standard Indian body-type nomenclature from a government source (VAHAN body-type codes). Not researched.
