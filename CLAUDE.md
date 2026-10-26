# Truck Fleet SaaS — project notes

A multi-company SaaS for truck owners and transport companies (India first, then UAE): trips, crew phone apps,
ledgers, invoicing and accounting. Laravel 13 web app + mobile API in `webapp/`; native B4A phone apps (built on the
founder's laptop) in `phoneapp/`.

## Read first

- `docs/00-decisions.md` — every decision (never reverse one silently; add a dated row instead).
- `docs/blueprint/` — the specification (01 architecture … 13 parity additions). `docs/blueprint/08-roadmap.md` has
  the build milestones.
- `docs/PROJECT-STATE.md` — what has been built, test count, open items. Update it at the end of every round.
- The founder's guideline files: `docs/guidelines/`.

## Rules

- Nothing new is downloaded or installed without the owner's approval. Approved: everything in `webapp/composer.lock`
  and `webapp/package-lock.json` (Laravel 13, Sanctum, Pest, Vite, Tailwind), Leaflet, Firebase push, map API keys,
  a verification aggregator, an SMS/WhatsApp OTP provider (decisions D18, D19, D21).
- Never commit `.env`, `vendor/`, `node_modules/`, `public/build/`, logs, exports or keys.
- Product rules: every company-owned model uses `BelongsToCompany` (a query without a company throws); every
  money/trip/people change is audited (no off switch); every financial write is one DB transaction; finalised
  documents are never edited (void or credit note); permissions are checked by code (`permission:trip.create`),
  never by role name; document types and statuses are VARCHAR + validation, never ENUM.
- Style: plain-English docblocks on every class and non-obvious branch, like Clinic Ledger (`thushku/clinicledger`),
  whose invoicing/templates are ported here (decision D14).
- Edit source with the editor tool rather than sed one-liners (they have mangled backslashes in class names before).

## Where things are

- `app/Platform` — tenancy, sign-in (incl. QR web login), roles and permissions, team, audit, signup, console.
  `app/Platform/Billing` (M13): `Plan`, `Coupon`, `Subscription`; `Billing` subscribes, extends trials, suspends,
  resumes and runs the nightly sweep (`fleet:subscriptions`) that makes ended trials and plans read-only (web via
  `RequireCompany`, phones via `ResolveApiCompany` → 423 `read_only`); `Limits` refuses one truck or invitation too
  many. Console pages: `Platform/{Company,Plan,System}Controller` (companies, plans and coupons, storage clean-up,
  database export, the announcement); the company side is Settings → Plan and billing (`billing.index`).
  `app/Platform/Data` (M17): `CompanyExport` (the whole company as a ZIP of CSVs and files; the same ZIP, encrypted, is
  the law copy), `CompanyDeletion` (the 72-hour countdown and the hourly purge, `fleet:deletions`). A plan's
  `modules` take their permissions away in `PermissionResolver` (`Plan::MODULE_PERMISSIONS`); `Limits` also guards
  storage for office uploads.
  `app/Platform/Notifications` (M20): `Notices` (who is told what, in what words — call it from the service where the
  thing happens), `Notifier` (inbox row per person and company, like news collapsed by key, push after commit),
  `SendPush` (queued; restores the company) and `Push/*` (`FcmGateway` — FCM HTTP v1 signed with OpenSSL, no SDK;
  `LogGateway` while `PUSH_DRIVER=log`). Tests bind a fake `PushGateway` (`NotificationsTest::notePushes`).
  `app/Platform/Website` (M14): `Pricing` (the website's price table from the plans, with coupons), `SiteText` (About
  and legal pages: shipped drafts in `resources/site/*.txt`, replaceable in console → Settings), `ContactMessage`.
  Public pages: `Site/{Site,Contact}Controller`, views `resources/views/site` on `layouts/site`; "/" is the website
  for visitors and the dashboard for people signed in. Getting started for a new company: `Settings/SetupController`
  with `Platform/Signup/GettingStarted` (the steps, ticked from the data; `/welcome`, opened by the first sign-in after the email is confirmed; a card on the dashboard until done).
- Branches (M22): `Platform/Tenancy/BranchContext` (the header filter in the session, and a person's branch limit from
  their role grants) and the `HasBranch` trait (`->inBranch()` for lists; route binding answers 404 to a limited person
  for another branch's record) on Trip, Truck, Person, Invoice, Quotation. Services and the books never apply it.
- `app/Modules/<Module>` — business modules: `Settings` (branches, places, lists, rules, number series), `Parties`
  (parties, roles, tax IDs, contacts, bank accounts, supplier vehicles, rate contracts), `Fleet` (trucks, complaints,
  service), `People` (people, activation codes), `Documents` (documents, versions, files, required types, shares,
  reminders, compliance / dispatch check), `Trips` (trips, crew, stops, consignments/LRs, advances, hire challans,
  broker commissions; TripService is the trip life and the dispatch gate; `LrBooks` (M21) — pre-printed LR books, a typed
  LR number claimed only from a free leaf of a book in use), `Road` (what the crew app sends: road events
  with three clocks and flags, photos, GPS, driving shifts, duty, e-POD; `CrewCommands` are the phone's taps, kept per
  UUID in `api_commands` so a retry never runs twice), `Tax` (registrations, dated codes/rates/rules from the
  country presets, `TaxEngine` → place of supply, rule match, `TaxCalculator`, `Withholding` for TDS; no rule = an
  error, never 0 %), `Invoicing` (bills from LRs, notes, receipts with TDS, balances, `DocumentData` snapshot →
  `resources/views/documents/*` one HTML page per document, PDF by headless Chromium). A finalised invoice is
  never edited (the model refuses); billed LRs and their trips are locked. Templates (M10): `Invoicing/Design/
  DesignSchema` checks every design (anything unknown is dropped and listed), `DocumentTemplates` resolves bill →
  client → branch → default → built-in, saves versions and rollbacks; a bill freezes its design at issue
  (`design_snapshot`); the editor is Settings → Templates with a live preview (`resources/js/templates.js`).
  Quotations (M21): `Quotations` (draft → issued with its QT/ number and fixed → accepted / declined; accepted →
  `toRateContract`), print `documents/quotation`; the voucher print is `documents/voucher` (`BooksController::printVoucher`).
  Messages (M18): `Messages` (the words sent with bills, receipts and reminders — Settings → Messages, shipped
  defaults, `{placeholders}`), `DocumentMailer` (email with the PDF and the secure link, every send kept as a
  delivery), `Reminders` (`fleet:reminders` daily: overdue reminders on the company's days, once per step).
  `Accounts` (M11: `ChartOfAccounts` makes groups and ledgers on first use, `Books` posts balanced vouchers once
  per source and reverses — never edits — them, honouring the lock date; `Posting` holds the automatic entries and
  `BookKeeper` hooks them to the models' saves; `Statements` reads ledgers, trial balance, P&L, balance sheet and
  truck profit; `Registers` ageing and TDS, `TaxReturns` GSTR-1 / VAT 201 data, `TallyExport` the Tally XML;
  `php artisan fleet:books` posts anything missing; M19: `OpeningBalances` — balance-sheet ledgers only, the rest in
  "Difference in opening balances" —, `BankReconciliation` — the bank's CSV in, auto match only when clear, match by
  hand, book charges or interest, the summary; a party's statement is `documents/statement` from `LedgerController`;
  M27: `CrewCash` — quick cash given to or spent by a crew member, from the person page and the owner app: for a trip
  it is a `TripAdvance` (so the settlement counts it), else an advance against pay (bucket `loan`) or dues paid
  (`earnings`); spent is the expense owed with their pay. `Statements::crewBuckets` has `loan` and a net of all three), `Reports` (M12: `Dashboard` numbers and alerts, `LiveMap`
  positions and route replay; maps in `resources/js/map.js` with Leaflet, charts as server-drawn SVG in
  `resources/views/charts` with `resources/js/charts.js`), `Payroll` (M16: `PayCalculator` works out pay per month — prorated by days of
  employment —, per trip ended or per km driven; `PayRuns` makes, edits, approves, pays and cancels runs; approval
  posts each payslip to the person's earnings and the payment clears them, via `Posting`/`BookKeeper`). Phone sign-in tokens and devices:
  `app/Platform/Devices`.
- The phone API is documented for the phone-app session in `docs/handover/api-v1.md`; keep it in step with
  `routes/api.php` and the tests (`PhoneApiTest`, `ApprovalTest`, `OwnerApiTest`). The owner app's screens are
  `Api/Owner/{Office,Money}Controller` (`/api/v1/owner/*`) and its writes `Api/Owner/WriteController` (M23: trips, bills,
  receipts, papers — each once per `Idempotency-Key`, `Concerns/RunsOnce`); the crew's My Khata is
  `GET /crew/money`. The rest of the handover pack (read-me, screens, sync design, local set-up) is in
  `docs/handover/`; `php artisan fleet:code <mobile>` prints a demo crew member's activation code.
- Controllers in `app/Http/Controllers` (`Settings/`, `Auth/`, `Api/`, `Platform/`); views in `resources/views`
  (`settings/` pages extend `layouts/settings`, whose side list comes from `SettingsController::PAGES`).
- Test helpers shared by several files live in `tests/Pest.php`; a helper inside one test file needs a name no other
  file uses (Pest loads them all into one process).

## Run and test (cloud session or laptop)

- PHP 8.3+, MariaDB/MySQL. In `webapp/`: `cp .env.example .env`, `php artisan key:generate`, set `DB_*`,
  `php artisan migrate`, `npm ci && npm run build`, `php artisan serve --port=8130`.
- After pulling new PHP classes run `composer dump-autoload` (the optimized class map does not see new files).
- Demo data: `php artisan fleet:demo --fresh` (sign in `demo@fleet.test` / `Demo-Pass-2026`). With the default
  captcha "always", set `CAPTCHA_MODE=after_failures` in `.env` for local clicking-through.
- First console login: `php artisan platform:admin you@example.com`, then open `/<PLATFORM_PATH>/login`
  (default `/ctl/login`). Codes are emailed; with `MAIL_MAILER=log` they are in `storage/logs/laravel.log`.
- Tests: create database `fleet_test`, run `DB_DATABASE=fleet_test php artisan migrate`, then
  `php vendor/bin/pest < /dev/null` (input closed). The clock is frozen at 2026-10-25 09:00. Tests empty every table
  of `fleet_test` — never point them at the development database.
