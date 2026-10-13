# System Architecture

## 1. The big picture

```
                 ┌──────────────────────────────────────────────┐
                 │        LARAVEL APPLICATION (one codebase)     │
 Browser ───────►│  Marketing website   (public pages, sign-up)  │
 (office users)  │  Web app             (owner, manager, accounts)│
                 │  Super-admin area    (hidden URL + email OTP) │
 Crew app ──────►│  Mobile API /api/v1  (JSON, token login)      │
 Owner app ─────►│                                                │
                 │  Queue worker  (photos, alerts, reports, push) │
                 │  Scheduler     (expiry alerts, backups, GPS    │
                 │                 thinning, trial reminders)     │
                 └───────┬──────────────────────┬────────────────┘
                         │                      │
                 ┌───────▼───────┐      ┌───────▼────────┐
                 │  MySQL 8      │      │ File storage    │
                 │  (MariaDB on  │      │ (photos, PDFs)  │
                 │   local PC)   │      │ private, per    │
                 └───────────────┘      │ company folder  │
                                        └────────────────┘
```

**Two regional installations of the same code** (see `07-accounting.md`, section 4):

| Region | Who signs up there | Why |
|---|---|---|
| India | Indian companies | Companies Act: daily backup on servers in India; DPDP |
| UAE | UAE companies | UAE data-protection law prefers UAE hosting |

The India installation launches first.

## 2. Inside the Laravel project

| Area | Route prefix | Login |
|---|---|---|
| Marketing website | `/` | none |
| Web app (company users) | `/app` | session + CSRF, captcha, lockout, optional OTP |
| Super-admin | configurable secret prefix, for example `/ctl-7f3k` | session + captcha + **email OTP** + lockout |
| Mobile API | `/api/v1` | Sanctum token per device + app PIN on the phone |

Code is organised by **business module** so each area stays small and readable:
`Trips`, `RoadEvents`, `Approvals`, `Settlement`, `Fleet` (trucks, documents,
tyres, service, complaints), `People` (crew, leave, payroll), `Parties`,
`Accounting` (core + country packs `India`, `UAE`), `Tracking` (GPS), `Sync`,
`Billing` (SaaS plans, trials, coupons), `Admin`, `Website`.

**Multi-company:** one database; every company table has `company_id`, and a
global scope adds it to every query automatically. A request can never read
another company's rows.

## 3. Web app front end

- Server-rendered **Blade** pages (fast first load, simple hosting).
- **Alpine.js (CSP build)** for small interactions; works with a strict Content
  Security Policy (no inline scripts, no `eval`).
- **Tailwind CSS** compiled by **Vite** at build time; the built files are
  committed, so the server does not need Node.js.
- **Leaflet** with OpenStreetMap tiles for the live map and route replay (no
  cost per map load).
- A small chart library for dashboard charts.

## 4. Mobile API (crew app and owner app)

### 4.1 Login

1. The office creates the person and gets a one-time **activation code**.
2. The phone sends mobile number + code + device ID → receives a **Sanctum
   token** for that device (with expiry).
3. The phone stores the token encrypted and asks for the app PIN or fingerprint
   to open. Old tokens are cleaned daily.

### 4.2 Endpoints (first version)

| Endpoint | Purpose |
|---|---|
| `POST /auth/activate`, `POST /auth/refresh`, `POST /auth/logout` | Device login |
| `GET  /me` | Person, roles, company settings needed on the phone, server time |
| `GET  /sync/pull?cursor=…` | Everything changed since the last pull: trips assigned, approvals waiting, messages, master lists, ledger lines |
| `POST /sync/push` | A batch of new records (road events, approvals, driver changes, leave requests, complaints), each with its own UUID |
| `POST /photos` | One photo (multipart, ≤ 500 KB) linked to an event UUID and slot |
| `POST /gps/batch` | A gzip batch of GPS points |
| `POST /commands/{name}` | State changes: accept trip, start trip, end trip, approve payment, start/stop duty |
| `GET  /owner/...` | Owner app: dashboard numbers, approvals inbox, live positions, trips, trucks, crew |

### 4.3 Rules that make offline safe

- **Every record carries a UUID made by the phone.** The server saves it once;
  if the same UUID comes again, it returns the saved result (no duplicates).
- **Commands, not overwrites.** The phone sends "approve payment X", and the
  server decides whether that is allowed in the current state. Two phones can
  never overwrite each other.
- **Three times per event:** phone clock, phone uptime counter (cannot be
  changed by the user) and last GPS satellite time. The server rebuilds a
  **trusted event time** and flags possible clock tampering (difference
  above 5 minutes).
- **Event first, photos after.** The event is saved even if a photo is still
  uploading; it shows "photos pending" until they arrive.
- **Retry with growing gaps** (30 s, 1 min, 2 min, 5 min, 15 min, 30 min).
  Validation errors are shown to the user; nothing is deleted from the phone
  until the server confirms it.

## 5. Photos

| Step | Where | What happens |
|---|---|---|
| Capture | Phone | In-app camera only (no gallery) |
| Shrink | Phone | Long side 1024 px (meters) or 1280–1600 px (receipts), JPEG quality 60–75; if over 400 KB, reduce step by step. Target 120–350 KB |
| Stamp | Phone | Date, time, GPS and trip number printed on the photo |
| Fingerprint | Phone + server | SHA-256 of the file; server also makes a "look-alike" fingerprint to catch the same photo used twice |
| Store | Server | Private folder per company and month; never public; shown through short-lived signed links |
| Clean-up | Super-admin | Old files can be pruned; screens show a neat "photo archived" placeholder instead of a broken image |

## 6. GPS tracking

| Part | Design |
|---|---|
| Phone | Location-type foreground service, started when the user taps Start duty / Start trip; a point every 30–60 s while moving, less when stopped; stored in SQLite and sent in batches |
| Server | One insert per batch; table `gps_points` **partitioned by month**. (MySQL rule: partitioned tables cannot have foreign keys, and the time column must be part of the primary key. Integrity is checked in code.) |
| Retention | Full detail for a set number of months (company setting), then thinned to one point per minute; trip route summaries kept with the trip |
| UAE | Heavy trucks there must also carry a government-approved tracker. Our phone GPS **works alongside** it, not instead of it; device integration comes later |

## 7. Maps and routes

| Need | Choice | Cost |
|---|---|---|
| Live map, route replay (web) | Leaflet + OpenStreetMap tiles | Free / low |
| Place search and address lookup | Mappls (best Indian addresses) for India, Google for UAE; results cached | Within free monthly allowances at first |
| Planned km and route line | Self-hosted OSRM or GraphHopper (phase 2) | Server cost only |
| Truck-aware routes (height/weight, city bans) | HERE or Mappls truck routing, only when needed | Pay per request |
| Navigation for the driver | Open Google Maps / Mappls app from our app with the destination | Free |

Google does not offer truck routing. City truck-ban times (Dubai, Abu Dhabi,
Indian city no-entry hours) are stored as **editable time-based zones** in our
own database and checked at trip planning and live.

## 8. Security (from the website guideline, applied to the whole app)

- Strict server validation on every input (type, length, range).
- Eloquent / query builder only, never raw SQL with user input.
- Output escaping in Blade; **Content Security Policy** header; no inline scripts.
- CSRF tokens on every form.
- Progressive lockout: 15 min → 1 hour → locked until admin unlock.
- Secret super-admin URL prefix, set in configuration.
- Self-hosted image captcha (no outside scripts) on login and sign-up.
- Email verification on sign-up, self-service password reset.
- Email OTP for super-admin (and optionally for company users).
- Uploads limited to 250–500 KB, checked by type and real content.
- Every write in a database transaction; audit log for every change to
  money, trips, people and accounts.
- API: rate limits per device, token expiry, mock-location flag stored and
  shown, optional Play Integrity check later.

## 9. Environments

| Environment | Where | Notes |
|---|---|---|
| Cloud dev (this session) | Claude cloud container | Web app built and tested here (PHP + MySQL/MariaDB + automated tests) |
| Your laptop | `C:\Users\Thushar\OneDrive\Documents\Claude\PhoneApp WebApp ForTrucks` | Portable "latest PHP" + existing XAMPP MariaDB, **without changing the global XAMPP setup**; B4A + emulators for the phone apps |
| Production | VPS in India (later a second one in UAE) | MySQL 8, HTTPS, queue worker, cron, daily backups |

## 10. Software packages that need your approval

Your guideline says new dependencies need your confirmation first. These are
the ones the web app needs. Most are standard, widely used and free.

| Package | Why | Alternative if not approved |
|---|---|---|
| Laravel (latest stable) + PHP 8.3+ | The framework you chose | — |
| Laravel Sanctum (official) | Token login for phones | Hand-written token table (more risk) |
| Vite + Tailwind CSS + Alpine.js (CSP build) — Node.js needed **only at build time** | Modern UI, small files, CSP-safe | Plain hand-written CSS/JS (slower to build, bigger files) |
| Leaflet (JS) + OpenStreetMap tiles | Maps without per-load fees | Google Maps JS (cost per load) |
| Chart.js (or similar small chart library) | Dashboard charts | Simple CSS bar charts only |
| DomPDF (or similar) | Invoices, LR, payslips, ledgers as PDF | Browser print-to-PDF only |
| PhpSpreadsheet / Laravel Excel | Excel import/export, bank statement import | CSV only |
| Intervention Image (uses PHP GD) | Server-side thumbnails, photo checks, look-alike fingerprint | Store photos as-is; no reuse check |
| Firebase Cloud Messaging (Google) | Push notifications to phones (new trip, approvals) | Phones check for news at every sync (slower alerts) |
| Map / geocoding API keys (Mappls, Google), later HERE | Place search, truck routing | Typed place names only |
| Email sending service (SMTP) | Verification, OTP, password reset, alerts | — required |

On the phone side (for the laptop session), the research shows the B4A app
needs **B4A 13.7 or newer** to meet Google Play's 2026 target (API 36).
If your installed version is older, updating it is a change that needs your
approval; the phone-app session will check this first.
