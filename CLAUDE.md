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

## Run and test (cloud session or laptop)

- PHP 8.3+, MariaDB/MySQL. In `webapp/`: `cp .env.example .env`, `php artisan key:generate`, set `DB_*`,
  `php artisan migrate`, `npm ci && npm run build`, `php artisan serve --port=8130`.
- After pulling new PHP classes run `composer dump-autoload` (the optimized class map does not see new files).
- First console login: `php artisan platform:admin you@example.com`, then open `/<PLATFORM_PATH>/login`
  (default `/ctl/login`). Codes are emailed; with `MAIL_MAILER=log` they are in `storage/logs/laravel.log`.
- Tests: create database `fleet_test`, run `DB_DATABASE=fleet_test php artisan migrate`, then
  `php vendor/bin/pest < /dev/null` (input closed). The clock is frozen at 2026-10-25 09:00. Tests empty every table
  of `fleet_test` — never point them at the development database.
