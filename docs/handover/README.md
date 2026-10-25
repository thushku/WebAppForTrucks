# Handover pack — the phone apps

For the session that builds the two native phone apps on the founder's laptop
(B4A for Android first, B4I for iOS later). Everything the apps need from the web
side is built and tested; this pack says what to build and how it talks to the server.

## Read in this order

1. **`local-setup.md`** — run the web app on the laptop, reach it from the emulators
   and a real phone, demo data and activation codes.
2. **`screens.md`** — every screen of the crew app and the owner app, what it shows,
   what it sends, which API call it uses.
3. **`sync-design.md`** — the phone's own database, the send queue, clocks, photos,
   GPS, retries, tokens, several companies, the read-only rule.
4. **`api-v1.md`** — the API reference with examples (§1–8 crew and approvals, §9 QR
   sign-in for the web, §10 the owner app's other screens).
5. The design rules and screen sketches: `docs/blueprint/05-mobile-app-ux.md` (and
   `13-parity-additions.md`, which wins where they differ); the founder's rules for
   phone apps: `docs/guidelines/PhoneAppPrompt.md`.

## Ground rules (from the founder)

- **Native apps only** — B4A / B4I, never a website inside an app shell.
- **Nothing is installed or updated without asking first**: not B4A, not an SDK, not a
  library. First check the laptop's B4A version and Android SDK (target API 36 needs
  B4A 13.7 or newer); if something is missing, stop and explain.
- Plain-English comments on every sub and branch; clear names for every view and
  variable; simple English on every screen.
- A splash with a real progress bar; the version and build shown in the side menu and
  in About; PIN and fingerprint/face to unlock; cards with an icon next to every title
  and button; fast on old, cheap phones.
- Keep `phoneapp/` for the B4A and B4I projects (`phoneapp/crew/`, `phoneapp/owner/`,
  shared code in `phoneapp/shared/`). Never commit keystores, `google-services.json`
  or API keys.

## What the server already does (so the app does not)

- Works out the true time of every entry from the phone's uptime and flags a phone
  clock that was wrong; flags reused photos, odd fuel, cash limits; decides who must
  approve what (two drivers, the manager, the office).
- Keeps every entry and command once, by its UUID — sending twice is always safe.
- Posts everything to the books; makes bills, LRs and PDFs; shares secure links.

## The two apps in one line each

| App | Who | API |
|---|---|---|
| **Crew app** | drivers, co-drivers, helpers | sign in with an activation code; `GET /crew/sync`; push entries, commands, photos, GPS (`api-v1.md` §2–6, §8); notifications and Firebase push (§11) |
| **Owner app** | owner, manager, accountant, workshop, viewer | sign in with an SMS/WhatsApp code; approvals (§7), QR web sign-in (§9), dashboard, map, trips, trucks, papers, bills, ledgers (§10); notifications and Firebase push (§11) |

## When the API needs to change

The web session owns the API. If a screen needs something the API does not give, write
it down in `docs/PROJECT-STATE.md` under "Phone app asks" with the screen and the field;
do not work around it on the phone. Version 1 is fixed once the first app is in the
Play Store; additions are new fields (the apps must ignore fields they do not know) or
`/api/v2`.
