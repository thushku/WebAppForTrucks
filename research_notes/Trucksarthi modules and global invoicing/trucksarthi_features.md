# Trucksarthi: Full Feature Study, Module by Module

Prepared: 14 Oct 2026
Purpose: list every Trucksarthi feature we must match in our SaaS truck-fleet
platform (Laravel web app + B4A phone apps).
Starting point: `research_notes/Truck fleet app research India UAE/india_competitors.md`.

---

## 1. Sources and limits

### 1.1 How this was researched

- WebFetch and curl do not work in this environment, so **no page was opened
  directly**. Every fact below comes from **search-engine summaries** of the
  pages listed here (51 searches in total).
- Search summaries sometimes merge text from different parts of a page. They
  also mix up version numbers and dates (for example, the App Store
  "What's New" dates appear differently in different snapshots). Treat version
  numbers and dates as approximate.
- No screenshots, videos or in-app screens could be seen. So **field-level
  detail (exact input fields on each screen) is thin**. Where we list fields
  that Trucksarthi did not publish, they are clearly marked as our own
  proposal, not as Trucksarthi facts.
- No independent reviews exist yet. The apps are very new: 10+ to 50+
  downloads on Google Play, and 1–2 ratings on the App Store.
- A public GitHub repo for the owner app front end exists
  (`kaptickventures/trucksarthi-frontend`, also seen as
  `Sahilgumber7/trucksarthi-frontend`). Its README is the default Expo
  (React Native) template. The repo is **not attached to this session**, so its
  screens and fields were not read. Reading it later, if the founder agrees,
  would give exact field lists.

### 1.2 Source list (short keys used in the tables)

| Key | Source | What it is |
|---|---|---|
| W-Home | https://trucksarthi.in/ | Marketing homepage (features, pricing, testimonials) |
| W-About | https://trucksarthi.in/about | About page; lists the khata modules one by one |
| W-Terms | https://trucksarthi.in/legal/terms | Terms of Service (driver access, billing, deletion) |
| W-Privacy | https://trucksarthi.in/legal/privacy | Privacy Policy (data collected, third parties) |
| W-Contact | https://trucksarthi.in/contact | Contact page |
| W-Support | https://trucksarthi.in/support | Support page ("All-in-One ERP for India's Transporters") |
| W-Desk | https://app.trucksarthi.in/ | Desktop web dashboard sign-in (QR login) |
| W-Console | https://console.trucksarthi.in/ | "Trucksarthi Admin" (internal admin console) |
| W-API | https://cloudapi.trucksarthi.in/ | "Trucksarthi API" (back end) |
| AS-Owner | https://apps.apple.com/in/app/trucksarthi/id6758569173 | iOS owner app, description + version history |
| AS-Driver | https://apps.apple.com/in/app/trucksarthi-for-truck-driver/id6760187775 | iOS driver app, description + version history |
| GP-Owner | https://play.google.com/store/apps/details?id=com.trucksarthi.app | Android owner app (Sarthitech) |
| GP-Driver | https://play.google.com/store/apps/details?id=com.trucksarthi.driver | Android driver app ("Trucksarthi - For Driver") |
| GP-Old | https://play.google.com/store/apps/details?id=com.sahilgumber7.trucksarthi | Older Android owner listing, developer "KAPTICK VENTURES", updated 12 Jan 2026, 10+ downloads |
| YT | https://www.youtube.com/channel/UCzj0zaTomGCycupKTd1_R2g | YouTube channel (description only; no video titles found) |
| GH | https://github.com/kaptickventures/trucksarthi-frontend | Public front-end repo (Expo / React Native) |

### 1.3 Confidence labels

- **Confirmed**: a Trucksarthi page or store listing says it.
- **Likely**: strongly implied by a Trucksarthi source, or found only in one
  search summary that may have merged text.
- **Unknown**: not found; listed so the designer knows it is an open question.
- **Our proposal**: not a Trucksarthi fact; standard Indian practice we suggest.

### 1.4 What could not be verified

- The exact list of the "8 modules" (the homepage says "One plan. All 8
  modules." but never names all eight).
- Input fields on each form (add trip, add bilty, add invoice, add supplier, etc.).
- Whether staff / multi-user logins with roles exist (no evidence found).
- Whether e-way bill is really integrated (one marketing line only).
- Whether RC lookup from Vahan auto-fills truck details (Parivahan/Vahan is
  named as a third-party provider, and an older listing mentions "RTO vehicle
  lookups", but no screen description was found).
- The "Refund and Plan Guarantee Rules" page content.
- Any FAQ page content (the contact page refers to an FAQ page, but it did not
  show in results).

---

## 2. Company and product facts

| Fact | Detail | Evidence | Confidence |
|---|---|---|---|
| Company | Sarthitech Private Limited, New Delhi (registered office in South West Delhi); founded 2025 | W-About | Confirmed |
| Earlier publisher | An older Play listing (com.sahilgumber7.trucksarthi) names "KAPTICK VENTURES"; GitHub org "kaptickventures" | GP-Old, GH | Confirmed |
| Self-description | "Transport ERP for Indian fleet owners"; "all-in-one transport business management app" covering drivers, clients, trucks, documents, invoices and cashflow | W-About, W-Support | Confirmed |
| Disclaimer | "Operational record-keeping tool… not accounting software"; does not file GST or tax returns; no financial or legal advice; figures only as accurate as entries | W-About, W-Terms | Confirmed |
| Apps | Owner app (Android + iOS), free driver app (Android + iOS), desktop web dashboard | W-About, AS-*, GP-* | Confirmed |
| Tech | Front end built with Expo (React Native) | GH | Likely |
| Tagline | "Transport Management App for Truck Owners"; "Built different. For a reason." | W-Home | Confirmed |
| Traction | Owner app 10+ → 50+ downloads (Play); 1 rating 5.0 (iOS). Driver app 10+ downloads, 4.7–4.8 stars (Play); 2 ratings 5.0 (iOS) | GP-*, AS-* | Confirmed (thin) |
| Last update | Both Play apps updated 11 Oct 2026. iOS owner 1.3.3, iOS driver 1.3.4 | GP-*, AS-* | Confirmed |

---

## 3. Modules

Each table: **Feature | Details / fields | App | Evidence | Confidence**.
App codes: **O** = owner app (phone), **D** = driver app, **W** = desktop web.

### 3.1 Driver Ledger ("Driver Khata")

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Separate ledger per driver | "Keep a separate account for every driver." Running ledger of salary, advances and expenses | O, W | W-About, W-Home, GP-Old | Confirmed |
| Salary entries | Salary is part of the ledger | O | W-About | Confirmed |
| Monthly salary summary | Homepage lists "monthly salary summary" | O | W-Home | Confirmed |
| Advances | Advances given to driver; "trip-wise cash advance tracking" | O | W-Home, W-About | Confirmed |
| Expenses by driver | Driver's road expenses posted to his ledger | O, D | W-About, AS-Driver | Confirmed |
| Settlements | 1.2.0: ledgers "track advances, expenses, settlements and outstanding balances in one place" | O | AS-Owner (1.2.0) | Confirmed |
| Running / pending balance | "Track salary, advances, and pending balances automatically"; "full running balance" | O, D | GP-Old, W-Home | Confirmed |
| "Net balance due" line | Sample WhatsApp ledger message ends with "Net balance due ₹1,12,000" | O | W-Home | Confirmed (sample) |
| Driver cash register (PDF) | "Driver cash register" is one of the PDF exports | O | W-Home | Confirmed |
| PDF in English or Hindi, share to WhatsApp in one tap, no watermark | Applies to ledgers and reports | O | W-Home | Confirmed |
| Fuel "who paid" | Fuel entry records who actually paid: owner or driver (affects driver balance) | O, D | AS-Owner (1.2.4), AS-Driver (1.2.5) | Confirmed |
| Balance bug fixes | 1.2.4 fixed advance/expense entries miscounted in driver balance; 1.3.2 fixed "payment due and balances" / "correct total balance" | O, D | AS-Owner, AS-Driver | Confirmed |
| Driver sees own ledger | Driver app shows complete ledger, advance balance, salary and balance, money received from owner | D | W-About, AS-Driver, GP-Driver | Confirmed |
| Driver profile data | Name, phone, Aadhaar or ID details, driving licence details | O | W-Privacy | Confirmed (from privacy policy, not a screen) |
| Driver documents | Licence and ID uploads | O, D | W-Privacy, GP-Driver | Likely |
| Salary types (monthly / per trip / per km) | Not found | — | — | Unknown |
| Bata / daily allowance | Not found | — | — | Unknown |

**Workflow (from sources):** owner gives advance (trip-wise) → driver logs
expenses (fuel / toll / food / repair) in driver app, or owner logs them →
fuel entry marks owner-paid or driver-paid → salary is posted monthly →
ledger shows running balance → "settlement" closes it → PDF / WhatsApp share
of the driver cash register.

### 3.2 Client Ledger ("Client Khata" / "Client Hisaab")

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Client master | Add Client screen (redesigned in 1.2.0) | O, W | AS-Owner | Confirmed |
| Ledger per client | "Trips, invoices, payments received and outstanding balance per client" | O, W | W-About | Confirmed |
| Receivables | App Store calls it client ledger for receivables | O | AS-Owner | Confirmed |
| Partial or full payments | Client khata records invoices and partial or full payments | O | GP-Owner | Confirmed |
| Outstanding balance | Shown per client | O | W-About | Confirmed |
| Client ledger PDF | "Download any client ledger… as a PDF in English or Hindi" + WhatsApp share | O | W-Home | Confirmed |
| Transaction reports | Added in 1.2.0 | O | AS-Owner | Confirmed |
| "Payment due" figures | Fixed in 1.3.2 | O | AS-Owner | Confirmed |
| Client fields | Not published. Business KYC uses PAN/GST, so GSTIN on client is likely needed for GST invoices | O | — | Unknown (fields) |
| Payment reminders to clients | Not found | — | — | Unknown |
| Opening balance | Not found | — | — | Unknown |

**Workflow:** add client → create trip for client → generate bilty and GST
invoice from the trip → record partial / full payment → outstanding balance
updates → share client ledger PDF on WhatsApp.

### 3.3 Truck Ledger ("Truck Khata")

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Truck profiles | "Truck profiles with vehicle data" | O, W | GP-Owner | Confirmed |
| Ledger per truck | "Fuel, repairs, challans and profitability per vehicle" | O, W | W-About | Confirmed |
| Running expenses | Fuel, FASTag recharge, loading/unloading, challans | O | GP-Owner | Confirmed |
| Maintenance expenses | Servicing, tyres, insurance, repairs (kept separate from running expenses) | O | GP-Owner | Confirmed |
| Fuel entries | Fuel entries live under Truck Khata; record who paid (owner or driver) | O | AS-Owner (1.2.4) | Confirmed |
| Fuel bill report (PDF) | One of the named PDF exports | O | W-Home | Confirmed |
| Profit per truck | "Profitability per vehicle" | O | W-About | Confirmed |
| Truck documents | RC, insurance, fitness, PUC, permit attached to truck (see 3.6) | O | GP-Owner | Confirmed |
| Tyre and service reminders | Older listing mentions tyre and service reminders | O | GP-Old | Likely (older listing only) |
| RC / RTO lookup | Older listing mentions "RTO vehicle lookups"; Parivahan/Vahan named as a third-party provider in the Terms | O | GP-Old, W-Terms | Likely |
| Truck fields | Only "vehicle number" is certain. Others not published | O | — | Unknown (fields) |
| Odometer / mileage (km per litre) | Trips record start and end KM; no mileage report found | O | GP-Old | Likely (KM) / Unknown (mileage) |
| EMI / loan tracking | Not found | — | — | Unknown |

### 3.4 Supplier Ledger ("Supplier Khata")

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Supplier = market-truck supplier | "Supplier khata — market-truck suppliers, hire rates and balance due" | O, W | W-About | Confirmed |
| Hire rates | Hire (lorry hire / bhada) rate per hired truck | O | W-About | Confirmed |
| Balance due | Amount still payable to the supplier | O | W-About | Confirmed |
| Add Supplier screen | Redesigned in 1.2.0 | O | AS-Owner | Confirmed |
| Supplier & Vendor section | New "Supplier & Vendor" section added (version entry dated 27 Aug) | O | AS-Owner | Likely (version number unclear) |
| Advance to supplier / balance payment / POD-linked release | Not found | — | — | Unknown |
| Market truck on a trip (truck number not in own fleet) | Implied by "market-truck suppliers" | O | W-About | Likely |
| TDS on hire (194C), commission / brokerage | Not found | — | — | Unknown |

### 3.5 Vendor Ledger ("Vendor Khata")

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Vendor = service / goods provider | "Vendor khata — fuel pumps, mechanics, spare-parts shops and RTO agents" | O, W | W-About | Confirmed |
| Credit purchases and payments | Implied by "khata" (running account) | O | W-About | Likely |
| Link from fuel / repair entries to vendor | Not stated; release note on fuel "who paid" covers only owner vs driver | O | AS-Owner | Unknown |
| Vendor fields | Not published | — | — | Unknown |

### 3.6 Document Manager

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Vehicle documents | RC, insurance, fitness, PUC, permits | O, W | W-About, GP-Owner | Confirmed |
| Store and share | "Storage and sharing" of documents | O | GP-Owner | Confirmed |
| Expiry alerts | "Automatic expiry alerts"; "Get alerts before insurance, fitness or permit expires"; "No more expired permits" | O | W-About, W-Home | Confirmed |
| Automated renewal reminders | "Automated reminders for renewals" | O | GP-Owner | Confirmed |
| Driver / ID documents | Uploads of identity documents; driver licence and Aadhaar/ID details | O, D | W-Privacy | Confirmed |
| Driver uploads documents | Driver can upload "delivery proof and important documents" from phone; driver app stores licence and vehicle papers | D | AS-Driver, GP-Driver | Confirmed |
| Alert lead time (days before) | Not published | — | — | Unknown |
| Alert channel (push / SMS / WhatsApp) | Not published; push notifications exist in driver app | — | AS-Driver | Unknown |
| Document fields (number, issue date, expiry date, file) | Expiry date must exist for alerts | O | W-About | Likely (expiry date) |

### 3.7 Invoices and LR ("Invoice & Bilties")

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Invoice & Bilties section | New section in version 1.1 (14 Jul) | O | AS-Owner | Confirmed |
| Bilty / LR / GR creation | "Create and manage LR/GR digitally with proper records and easy sharing" | O | AS-Owner | Confirmed |
| GST invoices | "GST invoices and bilties generated from trips" | O, W | W-About, W-Support | Confirmed |
| Generated from trips | Bilty and invoice are created from a trip record | O | W-About | Confirmed |
| WhatsApp share | Each bilty / invoice shareable on WhatsApp | O | W-About, W-Home | Confirmed |
| Itemized bilties (PDF) | "Itemized bilties" export as PDF, English or Hindi, no watermark | O | W-Home | Confirmed |
| "GST-compliant Online Bilty and Invoices in seconds" | Marketing claim | O | GP-Old | Confirmed (claim) |
| Invoice settings | Settings screen has "invoice details" (company details on the invoice) | O | AS-Owner (1.2.0) | Confirmed |
| Professional invoices / billing | "Generate professional invoices and manage billing" | O | AS-Owner | Confirmed |
| Bilty and invoice fixes | 1.3.1 / 1.3.2 | O | AS-Owner | Confirmed |
| Invoice payment → client ledger | Payments against invoices reduce client outstanding | O | W-About, GP-Owner | Confirmed |
| E-way bill | Support page says it "automates E-Way Bills" | ? | W-Support | Likely (marketing line only) |
| POD upload | Driver uploads proof of delivery | D | AS-Driver, GP-Old | Confirmed |
| Bilty fields | Not published | — | — | Unknown (fields) |
| e-invoice (IRN / QR), credit notes, freight terms (paid / to-pay / TBB) | Not found | — | — | Unknown |

### 3.8 Trips ("Trip Hisaab")

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Create and manage trips | "Create and manage trips easily" | O, W | AS-Owner | Confirmed |
| Assign truck and driver | "Assign trucks and drivers" | O | AS-Owner | Confirmed |
| Route | "Track routes"; driver sees route, pickup and drop locations | O, D | AS-Owner, GP-Driver | Confirmed |
| Load details and instructions | Driver sees load details and instructions | D | AS-Driver | Confirmed |
| Start / end KM | Trip records start and end KM | O | GP-Old | Likely (older listing) |
| Freight rate | Trip records freight rate | O | GP-Old | Likely |
| Trip advance | Trip records advances | O | GP-Old, W-Home | Confirmed |
| Trip status | Started → in transit → completed (driver taps once) | D | AS-Driver | Confirmed |
| Real-time progress | "Monitor trip progress in real time" (from status + optional location sharing) | O | AS-Owner | Confirmed |
| Trip profit | "Know exactly how much profit you made on every single trip" | O | GP-Old, W-Support | Confirmed |
| Trip expenses | Fuel, tolls, other costs per trip | O, D | AS-Driver, AS-Owner | Confirmed |
| Trip used for bilty / invoice | Bilty and invoice generated from trip | O | W-About | Confirmed |
| Trip notifications to driver | Driver gets notifications; switches business from notification | D | AS-Driver (1.2.6) | Confirmed |
| Multi-stop, return load, round trip | Not found | — | — | Unknown |

### 3.9 Expenses and Fuel

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Expense types (owner) | Running: fuel, FASTag recharge, loading/unloading, challans. Maintenance: servicing, tyres, insurance, repairs | O | GP-Owner | Confirmed |
| Expense types (driver) | Fuel, tolls, food, repairs, "other costs" | D | GP-Driver, AS-Driver | Confirmed |
| Fuel entry | Simple 2-step form; receipt photo; who paid (owner / driver) | D, O | AS-Driver (1.2.5), AS-Owner (1.2.4) | Confirmed |
| Fuel usage tracking | "Track fuel usage" | O | AS-Owner | Confirmed |
| Fuel bill report PDF | Named export | O | W-Home | Confirmed |
| Fuel litres, rate, odometer at fill | Not published | — | — | Unknown |
| Expense approval by owner | Not found | — | — | Unknown |

### 3.10 Reports and Dashboard

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Income, expense, trip, profit reports | "Clear reports on income, expenses, trips, and profits" | O, W | AS-Owner | Confirmed |
| Profit and loss overview | "Clear overview of profit and loss with all transactions" | O | GP-Owner | Confirmed |
| Transaction reports | Added 1.2.0 | O | AS-Owner | Confirmed |
| Per-trip P&L | See 3.8 | O | GP-Old | Confirmed |
| Per-truck profitability | See 3.3 | O | W-About | Confirmed |
| Named PDF reports | Client ledger, fuel bill report, driver cash register, itemized bilties | O | W-Home | Confirmed |
| Cashflow | About page lists "cashflow" among things it manages | O | W-About | Likely |
| Excel / CSV export | Not found | — | — | Unknown |
| Date filters / search | Not published | — | — | Unknown |
| Charts | Not found | — | — | Unknown |

### 3.11 Driver App ("Trucksarthi – For Truck Driver" / "For Driver")

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Free | Driver app is free | D | W-Terms, W-About | Confirmed |
| Separate driver login | Limited access: assigned trips, funds received from owner, expense entries, optional location sharing | D | W-Terms | Confirmed |
| Cannot see company money | Drivers cannot see financial summaries, company accounts or sensitive business info | D | W-Terms | Confirmed |
| View assigned trips | Route, load details, instructions, pickup and drop | D | AS-Driver, GP-Driver | Confirmed |
| Update trip status | Started / in transit / completed, one tap | D | AS-Driver | Confirmed |
| Upload POD and documents | From phone | D | AS-Driver | Confirmed |
| Log expenses | Fuel, tolls, food, repairs, other | D | AS-Driver, GP-Driver | Confirmed |
| Fuel form | 2-step form, receipt photo, who paid | D | AS-Driver (1.2.5) | Confirmed |
| Khata (earnings and expenses) | Money received from owner; balance of earnings vs expenses; complete ledger; advance balance; salary and balance; trip-wise cash advances | D | GP-Driver, AS-Driver | Confirmed |
| Offline | Record expenses and view khata with no internet (1.2.3, about 9 Sept); offline sync made reliable (1.3.1 / 1.3.2) | D | AS-Driver | Confirmed |
| Location sharing | Added 1.2.4. Off by default; driver can turn off any time; visible only to linked owner; owners may not require it outside working hours; precise GPS including background / screen off | D | AS-Driver, W-Terms, W-Privacy | Confirmed |
| Multi-business | 1.2.6: driver can work for more than one owner; current business shown on home screen; app switches business automatically when a notification is opened | D | AS-Driver | Confirmed |
| Push notifications | Implied by 1.2.6 note | D | AS-Driver | Confirmed |
| Hindi | Hindi text improvements (1.3.2) | D | AS-Driver | Confirmed |
| Document storage | Licence and vehicle papers | D | GP-Driver | Confirmed |
| Account deletion | Driver login deleted at once; owner keeps trips and ledgers | D | W-Terms | Confirmed |

### 3.12 Desktop Web Dashboard

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Web dashboard | "Full-screen command center" for office staff; multi-column view; real-time coordination | W | W-Home | Confirmed |
| Target | "Built for fleets with 10 or more trucks" | W | W-Home | Confirmed |
| QR login (like WhatsApp Web) | Open phone app → desktop login → scan QR; QR refreshes every 2 minutes | W, O | W-Desk, GP-Owner | Confirmed |
| One desktop at a time | Only one desktop can be signed in at a time | W | W-Desk | Confirmed |
| Syncs with phone | Desktop version syncs with phone app | W | W-Support | Confirmed |
| Same data as phone | Implied | W | W-Home | Likely |

### 3.13 Users, Roles, Security and Account

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Phone-based signup, faster onboarding | 1.2.0 "simpler onboarding: faster signup, business verification, KYC" | O | AS-Owner | Confirmed |
| Business KYC | PAN, GST and bank account verification via Cashfree | O | W-Terms | Confirmed |
| Owner account data | Name, phone, email, company details, PAN, GST, address, date of birth (optional), bank details (optional) | O | W-Privacy | Confirmed |
| Biometric / device lock | Biometric login option in Settings | O | GP-Owner, AS-Owner (1.2.0) | Confirmed |
| Cloud storage | Data stored in cloud; encrypted in transit | O | GP-Owner | Confirmed |
| Roles | Only two roles found: fleet owner and driver. "Office staff" are mentioned for the web dashboard but no staff login or permission feature was found | O, D, W | W-Terms, W-Home | Owner/driver Confirmed; staff roles Unknown |
| Account deletion | Owner: permanent 72 hours after confirmation, with a countdown that can be stopped. Driver: immediate | O, D | W-Terms, W-Privacy | Confirmed |
| Data ownership | User owns the records they enter | O | W-Terms | Confirmed |
| Data after plan ends | Not deleted; paid features locked; data returns when a plan is bought again | O | W-Terms | Confirmed |
| Admin console | console.trucksarthi.in (internal) | — | W-Console | Confirmed (exists) |

### 3.14 Settings

| Feature | Details / fields | App | Evidence | Confidence |
|---|---|---|---|---|
| Theme | Choose theme (light / dark likely) | O | AS-Owner (1.2.0) | Confirmed (theme); Likely (light/dark) |
| Biometric login on/off | | O | AS-Owner (1.2.0) | Confirmed |
| Invoice details | Company details printed on invoices | O | AS-Owner (1.2.0) | Confirmed |
| Language | Hindi and English | O, D | W-About, AS-Owner (1.3.2) | Confirmed |

### 3.15 Subscription and Billing (of Trucksarthi itself)

See section 5.

### 3.16 Notifications and Reminders

| Feature | Details | App | Evidence | Confidence |
|---|---|---|---|---|
| Document expiry alerts | Insurance, fitness, permit, PUC, RC | O | W-Home, W-About | Confirmed |
| Renewal reminders | "Automated reminders for renewals" | O | GP-Owner | Confirmed |
| Tyre / service reminders | Older listing | O | GP-Old | Likely |
| Driver push notifications | Trip-related; opening one switches business | D | AS-Driver | Confirmed |
| Payment-due reminders to clients | Not found | — | — | Unknown |

### 3.17 Sharing, Export, Languages, Offline

| Feature | Details | App | Evidence | Confidence |
|---|---|---|---|---|
| WhatsApp share | Ledgers, bilties, invoices, reports, "in one single tap" | O | W-Home, W-About | Confirmed |
| PDF export | English or Hindi; no watermark; instant | O | W-Home | Confirmed |
| Languages | Hindi and English (app UI and support) | O, D | W-About, W-Contact | Confirmed |
| Offline (driver) | Expenses and khata offline; auto-sync | D | AS-Driver | Confirmed |
| Offline (owner) | "Offline entry syncing" improved in 1.3.2; older listing says "works with low internet" | O | AS-Owner, GP-Old | Confirmed |
| Excel export / Tally export | Not found | — | — | Unknown |

### 3.18 Integrations

| Integration | Detail | Evidence | Confidence |
|---|---|---|---|
| Parivahan / Vahan | Named as a third-party provider (probably RC / vehicle lookup) | W-Terms, GP-Old | Confirmed (named); Likely (RC auto-fill) |
| Cashfree | Website payments; PAN, GST and bank account verification | W-Terms | Confirmed |
| Firebase, Sentry, PostHog | Push / crash / analytics | W-Terms, W-Privacy | Confirmed |
| Meta (Facebook / Instagram) | Ads measurement only; no business data shared | W-Privacy | Confirmed |
| Google Play / Apple billing | In-app subscriptions | W-Terms, AS-Owner | Confirmed |
| E-way bill | "Automates E-Way Bills" (support page) | W-Support | Likely (one line) |
| FASTag | Only as an expense type ("FASTag recharge"); no FASTag API | GP-Owner | Confirmed (expense type); no integration found |
| GPS devices | None; tracking is driver-phone location sharing only | AS-Driver, W-Terms | Likely (no device integration) |
| Tally / accounting export | Not found | — | Unknown |

### 3.19 Support

| Item | Detail | Evidence | Confidence |
|---|---|---|---|
| Channels | Phone and WhatsApp +91 93195 95984; support@trucksarthi.in (contact@ on support page); grievance@trucksarthi.in | W-Contact, W-Support | Confirmed |
| Hours / speed | "24/7"; "2-hour" email response; every day, Hindi and English | W-Support, W-Contact | Confirmed (claims) |
| Grievances | Acknowledged in 48 hours, resolved within 30 days; escalation to Data Protection Board of India | W-Privacy | Confirmed |
| Demo call | Homepage offers demo call booking | W-Home | Confirmed |

---

## 4. Terminology notes

| Term | Trucksarthi meaning | Evidence | Confidence |
|---|---|---|---|
| **Khata** | Ledger / running account (Driver khata, Client khata, Truck khata, Supplier khata, Vendor khata) | W-About | Confirmed |
| **Hisaab** | Account / settlement ("client hisaab", trip hisaab) | W-Home | Confirmed |
| **Supplier** | **Market-truck supplier**: a truck owner or broker from whom the transporter hires trucks. Ledger holds hire rates and balance due | W-About: "Supplier khata — market-truck suppliers, hire rates and balance due" | **Confirmed** |
| **Vendor** | **Service / goods provider to own fleet**: fuel pumps, mechanics, spare-parts shops, RTO agents | W-About: "Vendor khata — fuel pumps, mechanics, spare-parts shops and RTO agents" | **Confirmed** |
| **Client** | The party who pays freight (consignor / shipper / customer) | W-About | Confirmed |
| **Bilty** | Trucksarthi's main word for the consignment note ("Invoice & Bilties", "itemized bilties") | AS-Owner, W-Home | Confirmed |
| **LR / GR** | Used in App Store text as the same document ("Create and manage LR/GR digitally"). Play listing typo: "bitly/LR" | AS-Owner, GP-Owner | Confirmed |
| LR vs GR vs bilty | Same document: Lorry Receipt (LR) = Goods Receipt (GR) = bilty = consignment note. No legal difference | Industry guides (fleetx.ai, mobosafe.in) | Confirmed (industry) |
| **Invoice** | GST tax invoice to client, made from a trip | W-About | Confirmed |
| **Running vs maintenance expenses** | Running = fuel, FASTag recharge, loading/unloading, challans. Maintenance = servicing, tyres, insurance, repairs | GP-Owner | Confirmed |
| **Driver cash register** | Printable driver ledger (cash given / spent / balance) | W-Home | Confirmed |
| **Business** | One owner account / company. A driver can belong to several businesses | AS-Driver | Confirmed |

---

## 5. Pricing, plans and platform facts

| Item | Detail | Evidence | Confidence |
|---|---|---|---|
| Free trial | 30 days, no credit card, full features | W-Home, W-Terms | Confirmed |
| Website plans | **3 months ₹1,799** and **12 months ₹5,999**, one-time payment, no auto-renew, "Full access to all 8 modules" | W-Home (structured data) | Confirmed |
| In-app plans | **Quarterly ₹2,999** and **Annual ₹9,999**, auto-renewing through Google Play / Apple | AS-Owner, W-Terms | Confirmed |
| Plan tiers | Only one plan ("One plan. All 8 modules."). No feature tiers | W-Home | Confirmed |
| Plan limits (trucks / drivers / users) | None published | — | Unknown |
| Driver app | Free | W-Terms | Confirmed |
| Refunds | Separate "Refund and Plan Guarantee Rules" page; store subscriptions must be cancelled / refunded via Google or Apple | W-Terms | Confirmed (content Unknown) |
| GST on price | Prices "and taxes" shown on pricing page | W-Terms | Likely |
| Platforms | Android, iOS, desktop web (app.trucksarthi.in) | W-About | Confirmed |
| Languages | English, Hindi | W-About, AS-Owner | Confirmed |
| Offline | Driver app: yes. Owner app: offline entries sync | AS-Driver, AS-Owner | Confirmed |
| Multi-user / roles | Owner + driver only found | W-Terms | Confirmed (owner/driver); staff Unknown |
| Data export | PDF (English / Hindi) + WhatsApp | W-Home | Confirmed |
| Country | India only (GST, PAN, Vahan, Hindi) | All | Confirmed |

**Price note:** the in-app price is about 1.67x the website price.
Our inference (not stated by them): this steers buyers to the website
(Cashfree), where there are no app-store fees.

### Version timeline (approximate; dates from App Store snapshots)

| Version | Date (2026) | App | What changed |
|---|---|---|---|
| 1.1 | 14 Jul | Owner | New **Invoice & Bilties** section |
| (unclear) | 27 Aug | Owner | New **Supplier & Vendor** section; improved UI |
| 1.2.0 | Aug | Owner | **Driver & Client Ledgers**; **transaction reports**; new **Settings** (theme, biometric, invoice details); simpler onboarding (signup, business verification, KYC); redesigned Add Client and Add Supplier |
| 1.2.3 | 9 Sept | Driver | **Offline**: record expenses and view khata without internet |
| 1.2.4 | 11 Sept | Owner | Truck Khata fuel entries: **who paid** (owner / driver); driver balance bug fix |
| 1.2.4 | Sept | Driver | **Location sharing** with fleet owner |
| 1.2.5 | 20 Sept | Driver | Fuel entry 2-step form; **receipt photo**; who paid |
| 1.2.6 | 22–23 Sept | Driver | **Multi-business** support |
| 1.3.0 | 27 Sept | Owner | Bug fixes |
| 1.3.1 | 2 Oct | Owner | Payment-due and balance fixes; bilty and invoice fixes; offline sync |
| 1.3.2 | Oct | Both | **Hindi**; offline sync reliability; correct total balance; layout fixes |
| 1.3.3 / 1.3.4 | Oct | Owner / Driver | Bug fixes (latest) |

---

## 6. User reviews, complaints and gaps

- **Reviews:** almost none. iOS owner app 1 rating (5.0); iOS driver app
  2 ratings (5.0), reportedly from the same reviewer. Play driver app 4.7–4.8
  from 10+ downloads. Homepage testimonials (Delhi, Indore, Ludhiana,
  Vijayawada; fleets of 8–24 trucks) are curated marketing. A homepage "Covered
  by" strip (ABP News, ThePrint, Business Standard…) could not be verified.
- **Bugs shown in their own release notes:** driver balance miscounts,
  payment-due and balance errors, bilty and invoice errors, offline sync not
  reliable. So **balance accuracy and offline sync are their weak spots**.
- **Data-safety mismatch:** Play listing says "No data collected", but the
  privacy policy says the driver app collects precise background GPS. Worth
  noting as a trust gap.
- **Gaps we found (not offered or not visible):**
  - No staff / accountant / manager logins with permissions.
  - Only one desktop session at a time.
  - No full accounting (they say so); no GST return, TDS, Tally export.
  - No e-invoice (IRN), credit notes, or freight terms (paid / to-pay / TBB) seen.
  - No GPS-device or FASTag integration; tracking is phone-only and optional.
  - No expense approval flow, no dual approval, no driver-change log.
  - No Excel export seen.
  - India only (no multi-currency, no Arabic, no VAT).

---

## 7. Spec-ready summary (read this first)

Everything below is something Trucksarthi has (Confirmed or Likely). Our
product must match each line. Items marked **[L]** are Likely, not fully
confirmed; build them anyway because they are standard in India.

### 7.1 Driver Ledger
- One running ledger per driver (salary, advances, expenses, payments, settlements).
- Trip-wise cash advance entries.
- Monthly salary entries and a **monthly salary summary**.
- Driver expenses (from driver app or owner) post to the driver's ledger.
- Fuel entry flag **"who paid: owner / driver"**; only driver-paid amounts
  affect the driver's balance.
- Running balance and **"Net balance due"** shown at all times.
- Settlement entry that clears or carries forward the balance.
- **Driver cash register** PDF (English / Hindi), WhatsApp one-tap share,
  ready-made WhatsApp text summary.
- Driver sees the same ledger in the driver app (read-only, own data only).
- Driver profile: name, phone, licence details, Aadhaar / ID, documents.

### 7.2 Client Ledger
- Client master (Add / edit client).
- Per-client ledger: trips, invoices, payments received, outstanding balance.
- Record **partial and full payments** against invoices.
- Payment-due / outstanding view.
- Client ledger PDF (English / Hindi) and WhatsApp share.
- Transaction report per client.

### 7.3 Truck Ledger
- Truck master (truck profile with vehicle data; number at minimum).
- Per-truck ledger of **running expenses** (fuel, FASTag recharge,
  loading/unloading, challans) and **maintenance expenses** (servicing, tyres,
  insurance, repairs).
- Fuel entries per truck, with who-paid and receipt photo.
- **Fuel bill report** PDF.
- Profit per truck (income from its trips minus its costs).
- Truck documents linked (see 7.6).
- **[L]** Tyre and service reminders.
- **[L]** RC / vehicle lookup from Vahan to fill truck details.

### 7.4 Supplier Ledger (market-truck suppliers)
- Supplier master (Add / edit supplier) = owner or broker of a **hired /
  market truck**.
- Record **hire rate** per hired truck / trip.
- Payments to supplier; **balance due** per supplier.
- Supplier ledger statement, PDF and WhatsApp share **[L]** (same as other ledgers).
- **[L]** Trip with a market truck (truck not in own fleet) linked to a supplier.
- Note: our decision A3 puts hired / market trucks in a later phase. To match
  Trucksarthi, the Supplier Ledger (at least hire rate + payments + balance)
  must be in phase 1.

### 7.5 Vendor Ledger (fuel pumps, mechanics, spare-parts shops, RTO agents)
- Vendor master with type (fuel pump / mechanic / spare parts / RTO agent / other).
- **[L]** Credit purchases (bills) and payments; running balance per vendor.
- **[L]** Vendor statement PDF and WhatsApp share.

### 7.6 Document Manager
- Store vehicle documents: **RC, insurance, fitness, PUC, permit**.
- Store driver documents: **licence, Aadhaar / ID**.
- Upload from owner app and driver app (photo / file).
- Expiry date per document, **automatic expiry alerts** and renewal reminders
  before expiry.
- Share documents (WhatsApp / download).

### 7.7 Invoices and LR (bilty)
- **Bilty / LR / GR** creation from a trip; list, edit, share.
- **GST invoice** creation from a trip (one or more bilties), with company
  invoice details from Settings.
- PDF (English / Hindi, no watermark) and WhatsApp one-tap share.
- **Itemized bilties** report PDF.
- Invoice payments update the client ledger (partial / full).
- **POD upload** by driver, linked to the trip / bilty.
- **[L]** E-way bill support (Trucksarthi claims "automates E-Way Bills").

### 7.8 Trips (Trip Hisaab)
- Create trip: client, truck, driver, route (from / to, pickup and drop
  locations), load details, instructions, freight rate, start KM, end KM,
  advances.
- Assign to driver; driver gets a notification.
- Status: started → in transit → completed (driver one tap).
- Trip expenses (owner and driver).
- **Trip profit** (freight minus trip costs) per trip.
- Real-time progress (status + optional driver location).

### 7.9 Expenses and Fuel
- Expense categories: fuel, FASTag recharge, toll, loading/unloading, challan,
  food, repairs, servicing, tyres, insurance, other.
- Running vs maintenance grouping.
- Fuel entry: 2-step quick form, amount, receipt photo, who paid.
- Each expense links to trip, truck and (if paid by driver) driver ledger.

### 7.10 Reports and Dashboard
- Income, expense, trip and profit reports; **P&L overview** with all transactions.
- **Transaction reports**.
- Per-trip and per-truck profit.
- Named PDFs: client ledger, fuel bill report, driver cash register, itemized bilties.
- All reports: English / Hindi PDF, WhatsApp share.

### 7.11 Driver App (free)
- Separate driver login; sees **only** own trips, money received, own
  expenses, own ledger; no company finances.
- Trip list and details (route, pickup / drop, load, instructions).
- One-tap status updates.
- Expense entry (fuel 2-step with receipt photo and who-paid).
- POD and document upload.
- Khata: money received, expenses, balance, advance balance, salary.
- **Offline** entry and viewing, with automatic sync.
- **Location sharing**: off by default, driver can switch off, visible only
  to owner, background GPS when on.
- **Multi-business**: one driver, many owners; current business on home
  screen; auto-switch from a notification.
- Push notifications. Hindi and English.
- Account deletion (instant for driver; owner keeps the records).

### 7.12 Web Dashboard
- Full web app with the same data (for fleets of 10+ trucks; office use).
- **QR login from the phone app** (like WhatsApp Web), QR refreshes every 2 minutes.
- Multi-column "command center" view.
- (They allow one desktop at a time. We can do better: many users with roles.)

### 7.13 Account, Security, Settings
- Signup with phone; business verification / KYC (PAN, GST, bank).
- Owner profile: name, phone, email, company, PAN, GST, address, DOB, bank details.
- Biometric / device-lock app login.
- Settings: theme, biometric on/off, invoice details (company name, address,
  GSTIN, bank, logo **[L]**), language (English / Hindi).
- Account deletion with 72-hour cancellable countdown; data export before deletion **[L]**.
- Data kept (read-only / locked) when plan expires; restored on renewal.

### 7.14 Subscription
- 30-day free trial, no card.
- Website one-time plans (3 months, 12 months) via a payment gateway; plus
  in-app store subscriptions (quarterly, annual).
- One plan with all modules; driver app always free.
- Refund / plan-guarantee policy page.

### 7.15 Sharing, Languages, Offline, Support
- WhatsApp share and PDF everywhere (ledgers, bilty, invoice, reports, documents).
- English and Hindi in app, PDFs and support.
- Offline entry with later sync (both apps).
- Support by phone, WhatsApp and email; grievance officer; demo booking.

### 7.16 Where we already go further (from our blueprint)
- Staff roles (manager, accountant), dual approval, driver-change log,
  multi-stop trips, full accounting (India + UAE), GPS-time-stamped road
  events. These are not seen in Trucksarthi and are our differentiators.
