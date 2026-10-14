# Parity Additions from Trucksarthi's Code

Decision **D17** allowed us to read Trucksarthi's public code
(`Sahilgumber7/trucksarthi-frontend`, an early owner app, last changed 27 Jan
2026) **only to check screens and fields**. No code was copied. Full study:
`research_notes/Trucksarthi modules and global invoicing/trucksarthi_code_study.md`.

Result: our specs already covered almost everything. These **31 additions**
close the remaining gaps. Each line names the spec it extends; where this file
and an older spec differ, **this file wins**.

> **Mistakes we found in their code and must not repeat:** trip date not saved;
> invoice printed with "today" instead of its own date; tax printed but not
> stored, so totals disagree; invoice showed the app's brand instead of the
> owner's company and GSTIN; biometric switch never actually asked at app open;
> invoiced trips could still be edited or deleted. Our design already prevents
> each of these (finalised documents are frozen; company identity comes from the
> snapshot; biometric check is enforced at open).

---

## A. Trips and trip list (extends `03`, `04`, `12`)

| # | Addition | Detail |
|---|---|---|
| 1 | **Other charges** on the trip / FTL quick form | Optional amount, default 0, billed to the client **on top of freight** (income, not cost). Own column on the trip list, trip report and LR annexure. Label says "billed to client". |
| 2 | **Office note** on a trip | Text for office users only (crew never see it); shown on the trip card and trip report; separate from "Instructions to the crew". |
| 3 | **"+ Add new" inside the trip form** | Next to Client, From, To, stops, Truck, Driver. Short forms: client (name + mobile), driver (name + mobile), truck (reg. no. + RC owner name), place (name + full address). New record is selected at once; a new place fills From if empty, else To. Other fields are completed later on the master page. |
| 4 | **Trip list tools** | Place filter (matches origin, destination or any stop); billing filter (Not invoiced / Invoiced / Paid); default sort newest first; Reset filters; **totals bar** "N trips · ₹ freight + other charges" that follows the filters (web and owner app). |
| 5 | **Trip report** of the filtered list | PDF and Excel with company header, time made, date range and filters; one row per trip: date, trip no., route, truck, driver, client, freight, other charges, total, office note; share by WhatsApp/email. |
| 6 | **Lock what is invoiced** | Once a trip/LR is on a **finalised** invoice: client, freight, other charges, date and route can't be edited and the trip can't be cancelled. Changes only by credit/debit note, supplementary invoice, or voiding the invoice. A short line on screen explains why. |

## B. Owner app and dashboard (extends `05`, `04`)

| # | Addition | Detail |
|---|---|---|
| 7 | Dashboard tiles | **Revenue this month** (freight + other charges of trips dated this month), **Trips this month**, **Recent trips** card (last 3–5: route, truck, driver, amount, date, "View all"), quick buttons Trucks / Drivers / Clients / Places. Web dashboard too. |
| 8 | Owner-app navigation | Bottom tabs **Home · New trip · Trips**. Side drawer: photo, name, email → My profile; Main: Trips, Ledgers, Documents, Invoices & LR, Settings; Masters: Trucks, Crew, Clients, Suppliers, Vendors, Places; bottom: Logout + version line. Bell → Reminders on every main screen. |
| 9 | Masters in the owner app | Clients and Places screens as well as Trucks and Crew: card list, add, edit, **deactivate (no delete)**, tap to open. |
| 10 | Loading and empty states | Skeleton placeholder cards while loading; friendly empty text with an add button ("No trucks yet — add your first truck"); lists refresh on return; **pull-to-refresh** on all owner-app lists. |

## C. Data model (also added to `06` §13)

| # | Addition | Detail |
|---|---|---|
| 11 | Place address | `places.address` (multi-line); required for customer sites; free names allowed ("Warehouse 1, Pune Main"); lat/lng optional from address search. |
| 12 | Truck fields | `rc_owner_name` (required), `vehicle_class` (e.g. Goods Carrier, HGV), `registered_rto`, `registration_date` (year worked out from it); **vehicle age** "X y Y m" on truck page and share text. |
| 13 | Second mobile | `party_contacts.alt_mobile`. |
| 14 | Profile and bank | `users.photo`, `users.date_of_birth` (optional); `bank_accounts.account_holder_name` (printed on invoices). |
| 15 | Notification priority | `notifications.priority` (high / medium / low) — drives icon, colour and sort (high first, then newest). |
| 16 | Pay runs | `payroll_runs` (period start/end, type monthly / fortnightly / weekly / trip-based / custom, status draft / approved / paid, totals, created_by) and `payslips` (run, person, gross, deductions, net, status pending / paid, paid voucher, paid_at). |

## D. Ledgers and documents (extends `09`)

| # | Addition | Detail |
|---|---|---|
| 17 | **Important dates card** and **Missing** tiles | Truck page card: registration date + vehicle age; fitness, PUC, road tax, insurance, permit, national permit valid-upto dates; **"Urgent" badge at ≤ 30 days** (red when expired); quick date edit from the card. A document record may hold dates **without a file yet** ("File missing"). Per-company list of **required document types per truck type and per crew role**; a required type with no record shows a **Missing** tile. |
| 18 | **Share truck details as text** | Pick fields with chips — on by default: number, make/model, RC owner; off by default: chassis, engine (last 5 digits only unless turned off), emission norm, unladen weight, vehicle age, fitness/insurance/permit/PUC dates. Plain text via WhatsApp/share sheet, ending with the company name. |
| 19 | People list: share contact and document health | "Share contact" (name + mobile) on list and person page; card chip "Licence on file / missing / expires in N days"; document health green/amber/red **per person** as well as per truck. |
| 20 | **Client page billing view** | Header totals **Unbilled** (delivered, not invoiced), **Billed unpaid**, **Received** (period). Tabs: **Unbilled** (select / select-all → "Create invoice (n)"), **Invoices** (no., date, due, status chip, amount, Share PDF, **Settle**), **Payments** (date, mode, remark, amount, invoices applied). |
| 21 | **One-tap Settle** and quick payment | Settle on each open invoice opens the receipt form with amount = balance, date = today (editable), narration "Settlement for Invoice <no>", allocated to that invoice. General **Add payment** on the client page: date, amount, mode, **narration required**; allocate oldest-first or by hand. |
| 22 | Call and WhatsApp buttons | On client, supplier, vendor, broker and crew pages: Call opens the dialler; WhatsApp opens a chat with "Hello <name>" using the company country code (+91 / +971). |
| 23 | **Quick cash entry** for a crew member (owner app) | Choice **"Spent for company"** (company owes them) or **"Received from company"** (they owe the company); amount, remark (required), date (today), optional trip. Posts as reimbursement or advance in the right bucket, ± colour in the statement. |
| 24 | **Basic driver pay run in phase 1** | Pay basis per person: monthly / **per trip** / per km (in `salary_structures`). Pay run for **any date range**: pay for the period, Pending → **Mark paid** (creates the payment voucher), history per person. Full Labour-Code / UAE payroll stays in phase 2–3. |

## E. Invoicing (extends `10`)

| # | Addition | Detail |
|---|---|---|
| 25 | Invoice display options | Optional **Driver** column in the consolidated LR annexure (off by default); "**Total trips / LRs: N**" in the summary; subtotal label "Subtotal (N LRs)". |
| 26 | PDF file names | Documents: `<DocType>-<Number>.pdf` (e.g. `Invoice-COK-26-27-000123.pdf`, `LR-000123.pdf`); reports: `<Report>-<from>-<to>.pdf`; `/` in numbers becomes `-`. |

## F. Account, settings and help (extends `02`, `04`, `05`, `01`)

| # | Addition | Detail |
|---|---|---|
| 27 | **Mobile OTP sign-in for owners** | Sign-up and login by mobile OTP: country code from the regional site (+91 / +971), 10-digit check for India, 6-digit code, 30-second resend timer, "Change number"; setup wizard if the company profile is incomplete. **Needs an SMS/WhatsApp OTP provider (DLT registration in India) — open question Q5.** Until then: email + password and QR login. Google sign-in later (also needs approval). |
| 28 | Owner-app profile | Tabs **Personal** (name, verified email read-only, mobile, photo with square crop), **Business** (company name, GSTIN/TRN, office address — owner only), **Banking** (bank, account holder, account no., IFSC, UPI — owner only). Edit / Discard; Save appears only after a change; business and bank changes are audited. |
| 29 | Owner-app settings list | Plans & billing, Appearance (System / Light / Dark), Biometric login (only if the phone supports it; asks for a biometric check to switch on, and **is really asked at app open**), App language, **My notification settings** (which alerts, which channels), **Use on desktop** (web address + QR scanner), Help centre, About, Logout. |
| 30 | Help centre | Availability line, **WhatsApp support**, **Call support**, **Email support**, FAQ and user-guide links — contacts come from super-admin **Branding**, never fixed in the app. |
| 31 | Reminders screen | Icon and colour by priority, title, message, time, read/unread; tap opens the related record; "No reminders" empty state; **Mark all read**. |
