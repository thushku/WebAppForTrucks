# Mobile Apps — Structure, Flows and Screen Layouts

Native **B4A** (Android) first, **B4I** (iOS) later with shared business logic.
Rules from `docs/guidelines/PhoneAppPrompt.md` apply everywhere: card-based
premium look, an icon next to every title and button, simple English, splash
screen with a real progress bar, version shown in Settings and the side menu,
PIN + fingerprint/face login, fast on old low-end phones.

Two apps:

| App | For | Why separate |
|---|---|---|
| **Crew app** | Drivers, co-drivers, helpers | Big buttons, few screens, works offline, runs GPS in the background. |
| **Owner app** | Owner, manager, workshop, viewer | Approvals, live map, alerts, quick reports. No background GPS. |

A person who is both owner and driver installs both apps, or uses the crew app's
"Switch to owner view" if we later merge them. (Decision A1: two apps.)

---

## 1. Design rules for drivers

These come from 25 years of watching apps fail on the road:

1. **Big targets.** Buttons at least 48 dp high, main actions 64 dp. Usable with
   one thumb, in a moving cabin, at night.
2. **Icons + short words.** Every action has an icon and a one- or two-word
   label. Screens make sense to someone who reads little English. Language can
   be switched in Settings (English first; others added through string files).
3. **Photo first, numbers second.** For fuel, the camera opens first. The driver
   then types the odometer reading while looking at the photo just taken. This
   gives fewer typing mistakes and proof for every number.
4. **Number keypad only** for numbers. No full keyboard when it is not needed.
5. **Never lose an entry.** Every Save writes to the phone first and says
   "Saved on phone ✓". Upload happens by itself when signal returns.
6. **Always show sync state** in the top bar: ✓ All sent · ⟳ 5 waiting · ⚠ No network.
7. **Night mode** and high contrast, because many entries happen at night.
8. **Short forms.** Anything the phone knows (time, GPS, trip, truck, person)
   is filled automatically and never asked.

---

## 2. Crew app

### 2.1 Screen map

```
Splash ─► First login (mobile + activation code) ─► Set PIN / enable fingerprint
   │
   └─► PIN / fingerprint ─► HOME
                              ├── Trip (current)
                              │     ├── Timeline (all crew entries)
                              │     ├── Add entry  ─► Fuel / Toll / Weighbridge / Fine-fee /
                              │     │                 Payment without receipt / Loading /
                              │     │                 Repair / Parking / Other / Money received /
                              │     │                 Driver change / Rest / Stop reached /
                              │     │                 Loaded / Delivered / Incident / Note
                              │     ├── Route & stops (map)
                              │     ├── Load & documents (LR, e-way bill, POD)
                              │     └── Trip cash (my money for this trip)
                              ├── Approvals (payments waiting for me)
                              ├── Money
                              │     ├── My ledger (advances, bata, salary, bonus, payments)
                              │     ├── Salary slips
                              │     └── Settled trips
                              ├── Leave (balance, apply, history)
                              └── More
                                    ├── My profile & documents
                                    ├── My truck (documents, complaints)
                                    ├── Raise complaint
                                    ├── Waiting to send (sync queue)
                                    ├── Settings (language, PIN, fingerprint, night mode)
                                    ├── Help
                                    └── About (version, build date)
```

Bottom navigation: **Home · Trip · Money · Leave · More**

### 2.2 Splash

```
┌──────────────────────────────┐
│                              │
│           [ LOGO ]           │
│         [ App name ]         │   ← product name to be chosen
│                              │
│   Opening your data...       │
│   ████████████░░░░░░  62%    │   ← real steps: database, login,
│                              │     settings, pending uploads
│                              │
│        Version 1.0.0 (12)    │
└──────────────────────────────┘
```

### 2.3 PIN / fingerprint

```
┌──────────────────────────────┐
│  🔒  Welcome back, Ravi      │
│                              │
│        ●  ●  ○  ○            │
│                              │
│     1      2      3          │
│     4      5      6          │
│     7      8      9          │
│    👆      0      ⌫          │   ← 👆 = use fingerprint / face
│                              │
│   Forgot PIN? Ask office     │
└──────────────────────────────┘
```

- The app locks again after it has been in the background for a few minutes
  (owner setting). Screens and in-memory data are cleared when it locks.
- **GPS tracking keeps running** while the screen is locked, because it runs in a
  separate background service with a permanent notification
  ("Trip 261013-ACC-COK-BOM-01 · Tracking ON").

### 2.4 Home

```
┌──────────────────────────────┐
│ ☰  Ravi · Driver   ⟳ 3 waiting│  ← sync badge
│ 🟢 On duty        [Go off]   │
├──────────────────────────────┤
│ 🚚 CURRENT TRIP              │
│ 261013-ACC-COK-BOM-01        │
│ KL-07-AB-1234                │
│ Kochi ───────► Mumbai        │
│ Next stop: Mangaluru fuel    │
│ [ Open trip ]                │
├──────────────────────────────┤
│ ⚠ 1 payment waits for your   │
│   approval        [ View ]   │  ← red card, only when needed
├──────────────────────────────┤
│ QUICK ADD                    │
│ ⛽ Fuel   🛣 Toll   ⚖ Weigh  │
│ 🧾 Fine   💸 No-rcpt 📦 Load │
│ 🔧 Repair 🔁 Driver ➕ More  │
├──────────────────────────────┤
│ 📩 From office: "Collect     │
│    ₹10,000 balance at Pune"  │
└──────────────────────────────┘
│ 🏠 Home │🚚 Trip│💰 Money│🗓 Leave│⋯ More│
```

When no trip is running, the top card shows the **next assigned trip** with
**Accept / Reject** buttons, or "No trip assigned".

### 2.5 Start trip

```
┌──────────────────────────────┐
│ ←  Start trip                │
├──────────────────────────────┤
│ STEP 1 of 3 · 📷 Odometer    │
│ ┌──────────────────────────┐ │
│ │   [ camera preview ]     │ │
│ └──────────────────────────┘ │
│          ( ◉ Take photo )    │
├──────────────────────────────┤
│ Steps: Odometer → Fuel gauge │
│        → Quick check         │
└──────────────────────────────┘
```

Step 3, "Quick check", is a list of ✓/✗ toggles: tyres, lights, brakes, documents
in the cabin, load covered, fire extinguisher. Any ✗ asks for a photo and
creates a truck complaint automatically.

If the workshop marked a complaint on this truck as **solved** since the last
trip, the check shows it: "Brake noise (front left) was fixed on 12 Oct —
✓ Fixed / ✗ Still there". "Still there" reopens the complaint. This closes the
loop between driver, workshop and the next driver.

### 2.6 Add fuel (photo first)

```
┌──────────────────────────────┐
│ ←  ⛽ Fuel                   │
├──────────────────────────────┤
│ 📷 Photos      ✓ ✓ ○         │
│ [odo] [meter] [+ receipt]    │
├──────────────────────────────┤
│ 🔢 Odometer (km)             │
│ [ 1,24,560            ]      │
│ ⛽ Litres      💲 Rate        │
│ [ 180.5 ]     [ 92.40 ]      │
│ 💰 Amount  ₹ 16,678          │  ← calculated, can be typed
│ ⬜ Full tank                  │
├──────────────────────────────┤
│ 💳 Paid by                   │
│ (Cash) (UPI) (Fuel card)     │
│ (Pump credit) (Party)        │
├──────────────────────────────┤
│ 📍 HP Petrol Pump, Kasaragod │  ← from GPS, tap to change
├──────────────────────────────┤
│        [ ✓ SAVE ]            │
└──────────────────────────────┘
```

After Save: green toast **"Saved on phone ✓ — will send automatically"**.

Smart checks on the phone, before saving:
- Odometer lower than the last reading → "Odometer is less than last entry
  (1,24,900). Check again?"
- Litres more than the tank size → warning.
- Rate far from the usual rate → warning (not a block).

### 2.7 Payment without receipt

```
┌──────────────────────────────┐
│ ←  💸 Payment without receipt│
├──────────────────────────────┤
│ 👮 Paid to                   │
│ (Police) (RTO) (Checkpost)   │
│ (Forest) (Other officer)     │
│ 💰 Amount  [ 300      ]      │
│ 📝 Reason  [ Document check ]│
│ 📍 Near Udupi (from GPS)     │
├──────────────────────────────┤
│ ℹ Suresh must approve this   │
│   before the trip is settled │
├──────────────────────────────┤
│        [ ✓ SAVE ]            │
└──────────────────────────────┘
```

### 2.8 Approvals (2nd driver)

```
┌──────────────────────────────┐
│ ←  Waiting for your approval │
├──────────────────────────────┤
│ 💸 ₹300 · Police · Udupi     │
│ By Ravi · 13 Oct, 11:42 pm   │
│ "Document check"             │
│ [ ✗ Reject ]   [ ✓ Approve ] │
└──────────────────────────────┘
```

Reject asks for a reason. The entry then goes to the manager as **Disputed**.

### 2.9 Driver change

```
┌──────────────────────────────┐
│ ←  🔁 Who is driving now?    │
├──────────────────────────────┤
│ Driving now: Ravi (3 h 40 m) │
│                              │
│ ( 👤 I am driving now )      │  ← big button for Suresh
│                              │
│ 🔢 Odometer [ 1,25,210 ]     │
│ 📷 Photo (optional)          │
│        [ ✓ CONFIRM ]         │
└──────────────────────────────┘
```

The app also shows this screen by itself after any stop longer than 15 minutes.

### 2.10 Trip timeline

```
┌──────────────────────────────┐
│ ← 261013-ACC-COK-BOM-01      │
│ [Timeline][Map][Load][Cash]  │
├──────────────────────────────┤
│ 13 Oct                       │
│ 06:10 ▶ Trip started  Ravi   │
│ 06:12 📦 Loaded 25 MT  Ravi  │
│ 09:40 🛣 Toll ₹455     ✓     │
│ 11:42 💸 No-rcpt ₹300  ⏳2nd │
│ 14:05 ⛽ 180 L ₹16,678 ⟳     │  ← ⟳ = still on phone
│ 14:30 🔁 Suresh driving      │
└──────────────────────────────┘
```

Status icons: ⟳ on phone only · ✓ checked by office · ⏳ waiting ·
❓ office has a question · ✗ rejected.

### 2.11 Money

```
┌──────────────────────────────┐
│ ←  💰 My money               │
├──────────────────────────────┤
│ THIS TRIP (with me)          │
│ Received ₹35,000             │
│ Spent    ₹16,250             │
│ Balance  ₹18,750             │
├──────────────────────────────┤
│ MY ACCOUNT                   │
│ Company owes you  ₹4,200     │
│ ─────────────────────────    │
│ 10 Oct  Bata Trip ...  +1,800│
│ 01 Oct  Salary Sep   +18,000 │
│ 28 Sep  Advance       -5,000 │
│ [ See all ] [ Salary slips ] │
└──────────────────────────────┘
```

### 2.12 Leave

Cards for each leave type with the balance. **Apply leave** form: from date,
to date, type, reason, then Send. Status: Waiting · Approved · Rejected.

### 2.13 More → About

Shows app name, **version and build number**, build date, server name and the
last sync time. Required by the guideline.

---

## 3. Owner app

### 3.1 Screen map

```
PIN / fingerprint ─► DASHBOARD
                       ├── Approvals inbox (entries waiting for check, disputed)
                       ├── Live map (all trucks / crew on duty)
                       ├── Trips (running, ended, to settle) ─► trip detail
                       ├── Trucks ─► truck page (status, documents, complaints, service)
                       ├── Crew ─► person page (ledger, leave, documents, driving hours)
                       ├── Create trip (quick form)
                       ├── Alerts (document expiry, long stop, driving limit, fuel mismatch)
                       └── More (reports summary, settings, about)
```

### 3.2 Dashboard

```
┌──────────────────────────────┐
│ ☰  Sharma Roadlines     🔔 4 │
├──────────────────────────────┤
│ 🚚 12 trucks                 │
│ 🟢 7 running 🟡 3 idle 🔴 2 shop│
├──────────────────────────────┤
│ ✅ 9 entries to check   [Go] │
│ ⚠ 1 disputed payment   [Go] │
├──────────────────────────────┤
│ TODAY                        │
│ Spent ₹48,200 · Fuel ₹39,100 │
│ This month profit ₹3,82,000  │
├──────────────────────────────┤
│ ⚠ Insurance KL-07-AB-1234    │
│   expires in 6 days          │
│ ⚠ Ravi drove 5 h non-stop    │
├──────────────────────────────┤
│ [ 🗺 Live map ] [ ➕ New trip ]│
└──────────────────────────────┘
```

### 3.3 Approvals inbox

One card per entry, with photo thumbnails (tap to zoom). Buttons: **Approve**,
**Ask question**, **Reject**. "Approve all tolls with FASTag match" as a bulk
action. Fuel cards show the calculated mileage since the last full tank, with a
red flag when it is far from the truck's normal mileage.

---

## 4. Offline and sync behaviour (both apps)

```
User taps Save
   │
   ▼
Write to phone database (SQLite) ── status: ON PHONE ⟳
   │
   ▼
Sync worker (runs every few minutes and when network returns)
   │  1. send entries (small JSON, in order of time)
   │  2. send photos one by one (each under 500 KB)
   │  3. send GPS points in batches
   │  4. get new data from server (new trips, approvals, messages)
   ▼
Server confirms each entry by its ID ── status: SENT ✓
```

- Every entry has its own ID made on the phone, so sending it twice never
  creates two records.
- Entries keep the time they were made, not the time they were sent.
- The **Waiting to send** screen lists everything not yet sent, with a
  **Send now** button.
- If the phone is lost or replaced before sending, unsent data is lost. The app
  warns the user when entries have been waiting more than 24 hours.

## 5. Permissions the crew app asks for (in this order, with a simple reason screen before each)

1. Location "while using the app" — "To record where each entry was made."
   Together with the location foreground service started by **Start duty**,
   this is enough to track all day with the screen locked.
2. Location "all the time" — **not asked in version 1.** It is only needed to
   restart tracking by itself after a phone reboot, and it brings a strict
   Google Play review. Instead, after a reboot the app shows a notification:
   "Tap to resume trip tracking". The office also gets an alert when a crew
   phone stops sending GPS for a set time (for example 25 minutes).
3. Camera — "To take photos of meters and receipts."
4. Notifications — "To tell you about new trips and approvals."
5. **Phone health check** screen (not a system prompt) — "So your phone does
   not stop trip tracking." It checks battery saving, autostart and background
   data, and shows phone-brand-specific steps (Xiaomi, Oppo, Vivo, Realme,
   Samsung). Google Play restricts apps from showing the direct "ignore battery
   optimisation" prompt, so we guide the user instead.

Technical notes for the phone-app session (from research, 2026):
- Background tracking runs as a **location-type foreground service** with a
  permanent notification. On Android 14+ it must be started while an app screen
  is open, so tracking starts when the crew member taps **Start duty** or
  **Start trip**. This matches our flow.
- Restarting tracking after a phone reboot without a tap would need the "all
  the time" location permission, which needs a Google Play declaration with a
  short video and a clear in-app explanation. Version 1 avoids it (see item 2
  above).
- When two crew members share one phone, each enters their own PIN, so every
  entry belongs to a person, not a device.
- Do not use the `dataSync` service type for uploads (Android 15 limits it to
  6 hours a day). Uploads run inside the location service or as short jobs.
- Play target: new apps and updates must target **API 36** from 31 Aug 2026.
  B4A 13.7 supports this. Test keyboard and layout behaviour early, because the
  B4X forum reports layout changes at target 35/36.
