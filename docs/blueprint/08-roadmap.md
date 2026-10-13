# Roadmap and Build Plan

## 1. Phases

| Phase | Goal | Main contents |
|---|---|---|
| **1 — India MVP** | A small Indian fleet can run every trip on the platform | Web app (trips, approvals, settlement, trucks, people, live map, reports, accounting core India phase 1, super-admin, security); mobile API; crew app + owner app (Android, English); marketing website with trial sign-up |
| **2 — India complete** | Replace paper, Excel and Tally for transporters | Payroll (Labour Codes), tyres, e-way bill API, FASTag and fuel-card statement import, reading receipts/odometers from photos automatically (OCR), Hindi + regional languages, share LR/invoice on WhatsApp, iOS apps (B4I) |
| **3 — UAE launch** | Same product for UAE fleets | UAE installation (UAE hosting), VAT engine, PINT-AE e-invoicing through an accredited provider, WPS salary file, UAE payroll rules, Salik/Darb import, fines and black points, truck-ban zones, Arabic for office users |
| **4 — Growth** | Bigger fleets and more industries | Hired/attached trucks and brokers, GPS device integrations (AIS-140, UAE-approved trackers), customer tracking link for shippers, maintenance predictions, more countries |

## 2. Web app build milestones (this cloud session, after blueprint approval)

Each milestone ends with automated tests passing and a push to the branch.

| # | Milestone | You can see / test |
|---|---|---|
| M1 | Project set-up: Laravel, MySQL, base layout, splash + version line, security base (CSP, CSRF, lockout, captcha, hidden admin URL, email OTP) | Log in to an empty web app and the super-admin area |
| M2 | Companies, users, roles, settings, masters (places with short codes, categories), number series | Create a company, users, places |
| M3 | Trucks (documents, expiry alerts, complaints, service) and people (documents, activation codes) | Truck and person pages |
| M4 | Trips: create, assign, statuses, trip number, consignments, LR | Create and send a trip |
| M5 | Mobile API: activation, sync pull/push, photos, GPS, commands — with a test script that acts like a phone | Trip timeline fills with "phone" entries |
| M6 | Approvals (including dual approval), settlement, close, office entries after close, audit log | Full trip from start to close |
| M7 | Accounting core (India phase 1): vouchers, automatic entries, invoices, ledgers, statements, GST data, TDS, Tally/Excel export | Profit per trip/truck from the books |
| M8 | Live map, route replay, reports, dashboard | Owner dashboard with real numbers |
| M9 | Super-admin: plans, trials, coupons, storage cleanup, DB export/import | Manage the SaaS |
| M10 | Marketing website and sign-up (after pricing is decided) | Public site |
| M11 | **Handover pack** for the phone-app session: API reference with examples, screen list, rules, sync design, local set-up guide | `docs/handover/` |

## 3. Phone app build (laptop session, after M5 and M11)

1. Check local B4A version and SDK (needs B4A 13.7+ for target API 36; ask
   before any update).
2. Crew app: splash, activation, PIN/fingerprint, home, trip, all road events,
   offline outbox, photos, GPS service, approvals, money, leave, complaints,
   phone health check, About (version).
3. Owner app: dashboard, approvals inbox, live map, trips, trucks, crew, alerts.
4. Field test with one real truck before release.

## 4. Decisions needed from you

| # | Decision | Needed before |
|---|---|---|
| 1 | Approve this blueprint (or tell us what to change) | Any code |
| 2 | Approve the software packages list (`01-architecture.md`, section 10) | M1 |
| 3 | Product / brand name | M1 (splash, website) |
| 4 | Languages after English | Phone app build |
| 5 | Pricing details (per truck? plans, trial days, student tier, coupons) | M9–M10 |
| 6 | Confirm the "(verify)" tax and payroll items with your CA / UAE tax agent | Before building each item |
