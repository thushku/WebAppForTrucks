# Roadmap and Build Plan

## 1. Phases

| Phase | Goal | Main contents |
|---|---|---|
| **1 — India MVP (Trucksarthi parity + our edge)** | A small Indian fleet runs every trip, document, invoice and ledger on the platform | **Operations:** trips with load type **FTL / PTL / mixed**, several consignments per journey with pickup/drop stops, **own or hired trucks**, approvals (incl. dual approval), settlement, driver-change log, live map. **Seven modules:** Driver, Client, Truck, Supplier, Vendor ledgers, Document Manager, Invoices & LR (`09`, `10`, `12`). **Invoicing like Clinic Ledger:** LR with copies, single and consolidated invoices, receipts with TDS, hire challan, broker commission, templates + template editor, headless-Chromium PDFs, WhatsApp/email/public-link sharing. **Tax:** global engine with India preset (GTA options, TDS, GSTR data) and UAE preset ready. **Accounting core**, audit trail, Tally/Excel export. Super-admin, security, crew app + owner app (Android, English + Hindi), QR web login, crew in several companies, marketing website with trial sign-up. Plus the 31 parity additions from Trucksarthi's code (`13-parity-additions.md`), incl. a **basic driver pay run**. Launch extras: fuel soft audit, photo-reuse checks, expired documents block dispatch, phone-stopped-reporting alert, e-way bill register, custom labels and fields |
| **2 — India complete** | Replace paper, Excel and Tally for transporters | Payroll (Labour Codes), tyres, **part-load network** (branches, manifests, godown stock, transshipment, delivery runs, inter-branch accounts), e-way bill API, FASTag and fuel-card statement import, pump-statement auto-matching, Vahan/Sarathi/DigiLocker checks via an aggregator, OCR of receipts and documents, more Indian languages, WhatsApp Business API reminders, thermal printing from the phone, pre-printed stationery mode, iOS apps (B4I) |
| **3 — UAE launch** | Same product for UAE fleets | UAE installation (UAE hosting), UAE VAT preset live, PINT-AE e-invoicing through an accredited provider, WPS salary file, UAE payroll rules, Salik/Darb import, fines and black points, truck-ban zones, Arabic + bilingual documents |
| **4 — Growth** | Bigger fleets, more countries and industries | Saudi (FATOORA) and Oman e-invoicing adapters, EU/UK base, other country presets as data, US via external tax calculator, GPS device integrations (AIS-140, UAE-approved trackers), client portal and tracking links, attached-truck commission accounting, maintenance predictions |

## 2. Web app build milestones (this cloud session, after blueprint approval)

Each milestone ends with automated tests passing and a push to the branch.

| # | Milestone | You can see / test |
|---|---|---|
| M1 ✅ | Project set-up in Clinic Ledger style (Laravel 13, PHP 8.3, Tailwind 4, Vite 8, Pest): base layout, splash + version line, security base (CSP nonce, CSRF, lockout, captcha, hidden admin URL, email OTP) | Log in to an empty web app and the super-admin area |
| M2 ✅ | Companies, branches, users, roles, crew in several companies, QR web login, settings, masters (places with short codes, categories), number series | Create a company, users, places |
| M3 ✅ | **Parties** (clients, suppliers, vendors, brokers — one firm, many roles; tax IDs; credit terms; rate contracts) and **Document Manager** (all owners, versions, expiry alerts, shares) | Party pages; document expiry dashboard |
| M4 ✅ | Trucks (profile, complaints, service) and people (profiles, activation codes); expired documents block dispatch | Truck and person pages |
| M5 ✅ | **Trips with FTL / PTL / mixed**, stops, several consignments (LR data), own or **hired truck**, hire challan, broker commission, trip number | Create and send a part-load trip with a hired truck |
| M6 ✅ | Mobile API: activation, sync pull/push, photos, GPS, commands, stop tasks, e-POD — with a test script that acts like a phone | Trip timeline fills with "phone" entries |
| M7 ✅ | Approvals (incl. dual approval), settlement, close, office entries after close, audit log | Full trip from start to close |
| M8 ✅ | **Tax engine** (masters, rules, dated rates, withholding, trace) with India preset + UAE preset | Tax worked out correctly in tests for the examples in `11-tax-engine.md` |
| M9 | **Invoicing** ported from Clinic Ledger: LR with copies, invoices (single, consolidated, supplementary), credit/debit notes, receipts with TDS and deductions, payment vouchers, numbering, finalise/void, sharing, public links | Create, print, share an LR and an invoice from a trip |
| M10 ✅ | **Templates and template editor** (themes incl. Classic LR, Compact A5; named templates, defaults per branch/client, copy sets, custom fields, label renames, versions) | Design an LR and an invoice in the editor |
| M11 | **Accounting core + five ledgers** (driver buckets, client bill-wise outstanding and queues, truck P&L, supplier settlement, vendor bills and fuel slips), statements, GST/TDS data, Tally/Excel export | Ledgers and profit per trip/truck/consignment from the books |
| M12 | Live map, route replay, reports, dashboard, work queues | Owner dashboard with real numbers |
| M13 | Super-admin: plans, trials, coupons, storage cleanup, DB export/import | Manage the SaaS |
| M14 | Marketing website and sign-up (after pricing is decided) | Public site |
| M15 | **Handover pack** for the phone-app session: API reference with examples, screen list, rules, sync design, local set-up guide | `docs/handover/` |

## 3. Phone app build (laptop session, after M6 and M15)

1. Check local B4A version and SDK (needs B4A 13.7+ for target API 36; ask
   before any update).
2. Crew app: splash, activation, PIN/fingerprint, home, company switcher,
   trip with stop-wise load/unload checklist, all road events, e-POD per
   consignment, offline outbox, photos, GPS service, approvals, My Khata,
   leave, complaints, truck documents offline, phone health check, About.
3. Owner app: dashboard, approvals inbox, live map, trips (FTL/PTL, hired
   trucks), create LR / invoice and share, receipts and payments, ledgers,
   Document Manager, QR web login, work queues, alerts.
4. Field test with one real truck before release.

## 4. Recommended checks before and during the build (from the research)

1. Hands-on test of the closest rivals: Trucksarthi (owner + driver app),
   TransportBook, Fleetx Driver, WheelsEye; a demo of Truckoom (UAE).
2. 10–15 short interviews with owners of 1–50 trucks in India to confirm bata
   rules, settlement habits, LR payment types and typical expense amounts.
3. CA review of the India "(verify)" items; UAE tax agent review before the
   UAE phase.
4. One-truck field pilot of the crew app (battery, photos, offline) before
   general release.

## 5. Decisions needed from you

| # | Decision | Needed before |
|---|---|---|
| 1 | Approve this blueprint (or tell us what to change) | Any code |
| 2 | Approve the software packages list (`01-architecture.md`, section 10) | M1 |
| 3 | Product / brand name | M1 (splash, website) |
| 4 | Languages after English | Phone app build |
| 5 | Pricing details (per truck? plans, trial days, student tier, coupons) | M9–M10 |
| 6 | Confirm the "(verify)" tax and payroll items with your CA / UAE tax agent | Before building each item |
