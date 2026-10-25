# Local set-up for building the phone apps

The phone apps talk to the web app's API. While building, run the web app on the same
laptop and point the emulators (and a test phone) at it.

## 1. The web app on the laptop

Prerequisites already on the founder's laptop: XAMPP's MariaDB, and a portable PHP 8.3+
that is **not** the XAMPP PHP (the XAMPP install serves older projects; do not change it).

```bat
cd C:\path\to\WebAppForTrucks\webapp
copy .env.example .env
<portable php> artisan key:generate
```

In `.env`: `DB_DATABASE=fleet`, `DB_USERNAME` / `DB_PASSWORD` for MariaDB,
`APP_URL=http://<laptop LAN address>:8130`, `CAPTCHA_MODE=after_failures`,
`MAIL_MAILER=log`, `OTP_SMS_DRIVER=log` (SMS codes go to `storage/logs/laravel.log`).

```bat
<portable php> artisan migrate
<portable php> artisan fleet:demo --fresh
npm ci && npm run build
<portable php> artisan serve --host=0.0.0.0 --port=8130
```

`--host=0.0.0.0` lets emulators and phones on the Wi-Fi reach it. Windows Firewall will
ask once; allow it on private networks only.

## 2. Reaching it from the phones

| Where the app runs | Base URL |
|---|---|
| Android emulator (B4A / Android Studio AVD) | `http://10.0.2.2:8130/api/v1` |
| Nox emulator | `http://<laptop LAN address>:8130/api/v1` (Nox's own `172.17.100.2` also works on most installs) |
| A real phone on the same Wi-Fi | `http://<laptop LAN address>:8130/api/v1` (`ipconfig` → IPv4 address) |

Plain `http` is for building only. Allow it in the debug build alone (a network
security config that permits cleartext for the laptop's address); release builds talk
`https` only. Keep the base URL in one place in the shared code, with a hidden
"server" setting in debug builds.

Check it: `GET /api/v1/ping` answers `{ "ok": true, "server_time": "…", "version": "…" }`.

## 3. Demo data and sign-ins

`fleet:demo --fresh` builds **Sharma Roadlines** (India): trucks, crew, parties, trips in
every state, bills, the books, sample plans.

| Who | How to sign in |
|---|---|
| Owner (web) | `demo@fleet.test` / `Demo-Pass-2026` (also `anita@fleet.test`, accountant; `vikram@fleet.test`, manager) |
| Owner app | `POST /auth/otp` with `+919847000001`, read the code in `storage/logs/laravel.log`, then `/auth/otp/verify` |
| Crew app | `artisan fleet:code 9847100001` prints an 8-digit activation code (72 hours, once) |

Demo crew: Ravi Kumar `9847100001`, Suresh Babu `…002`, Manoj Thomas `…003`, Abdul
Rasheed `…004` (drivers); Binu Joseph `…005`, Shibu P `…006` (helpers). The web's
person page also makes codes, with a QR the app can scan (`fleet-activate:<mobile>:<code>`).

## 4. Watching what the phone sent

- Web → **Trips** → the trip → its timeline: every entry with its three clocks, flags
  and photos; **Approvals** for what waits for the office.
- Web → **Live map** for GPS.
- `storage/logs/laravel.log` for server errors (send the lines with any bug report).
- The phone API's automated tests show every call working: `tests/Feature/PhoneApiTest.php`,
  `ApprovalTest.php`, `OwnerApiTest.php` (`php vendor/bin/pest < /dev/null`).

## 5. Firebase push (later)

Push notifications (approved, D18) need a Firebase project. The app sends its token with
`POST /device`; the server side of push comes with the notifications round. Keep
`google-services.json` out of the repository.
