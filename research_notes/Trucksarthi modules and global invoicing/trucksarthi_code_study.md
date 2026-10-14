# Trucksarthi Early App (Jan 2026): Code Study and Gap List

Prepared: 14 Oct 2026
Source studied: `/home/user/sahilgumber7/trucksarthi-frontend` (Expo / React
Native owner app, `app.json` version 1.0.2, Android build 29, last commit 27 Jan
2026). This is the same public repository named in decision **D17**
(`kaptickventures/trucksarthi-frontend`; the copy here sits under the author's own
account name, `sahilgumber7`).

Rules followed:
- **Read only.** Nothing in that folder was run, built or installed. Only
  file reading and searching were used.
- **D17:** read only to check screens, fields and behaviour. **No code was
  copied.** Field names below (for example `cost_of_trip`) are the API data
  names, listed so the inventory is exact. Behaviour is described in our own
  words.
- Our specs compared: `docs/blueprint/02`, `03`, `04`, `05`, `06`, `07`, `09`,
  `10`, `11`, `12`.

Words used: **"in code"** = seen directly in the source. **"appears"** = our
reading of the code where the meaning is not written down. **"backend"** = done by
their server, which is not in this repository. We only see what the app sends
and receives.

---

## 1. Summary

### 1.1 What this version is

- One **owner app** (no driver app), written in Expo / React Native with
  NativeWind (Tailwind-style styling). Talks to a REST backend with a JWT token.
  The data types say **"Mongoose"** (MongoDB). Two leftover **"Legacy SQL
  (PostgreSQL)"** types and a `firebase_uid` field show they moved off a
  Firebase + PostgreSQL design before this version.
- About **24 real screens**, 9 shared components, 15 hooks (12 data hooks),
  10 current data entities plus 2 legacy ones.
- Look: WhatsApp-green colours, light / dark / follow-phone theme, bottom tabs
  (**Home · Add Trip · Trip Log**) and a slide-in side menu.

### 1.2 What it can do (in code)

1. Sign in by **phone OTP (+91)** or **email + password**, plus email sign-up.
   Google login is shown as "coming soon".
2. A **"Complete your profile"** step after the first login.
3. **Trips:** one client, one origin, one destination, one truck, one driver,
   **trip cost**, **miscellaneous amount**, notes. Edit and **hard delete**.
4. **Trip log:** filters (driver, client, truck, place, date range), a total,
   and a **PDF trip report** shared through the phone's share sheet.
5. **Masters:** clients, drivers, trucks and locations (list, add, edit, delete).
   Each can also be added quickly from inside the trip form.
6. **Client profile:**
   - three totals: **Billed**, **Unbilled** and **Settled**;
   - three tabs: Unbilled trips, Invoices and Payment history;
   - **Generate invoice** from the unbilled trips you select;
   - invoice **PDF** with a flat 5% tax, shared from the phone;
   - **Settle** an invoice, and **Add payment**.
7. **Driver profile:** a balance (Credit / Debit), licence and Aadhaar photos,
   and a simple cash ledger with two entry kinds: *Expense* and *Payment*.
8. **Truck profile:**
   - vehicle data and **eight "important dates"**, with an "Urgent" badge when
     a date is 30 days away or less;
   - **four document slots** (RC, Insurance, Permit, Fitness);
   - the last five trips;
   - **share the truck's details as text**, choosing which ones.
9. **Owner profile** with Personal, Business and Banking tabs. Settings:
   theme, biometric switch and Help centre. A "Reminders" screen that is empty.

### 1.3 What it does not have (compared with Trucksarthi in Oct 2026)

This version has none of these. They are in the current Trucksarthi product,
listed in `trucksarthi_features.md`:

- **Business modules:** Supplier ledger, Vendor ledger, Truck ledger (expenses,
  fuel), bilty / LR, a proper GST invoice, and a central Document Manager with
  expiry alerts.
- **Driver side:** the driver app, trip status (started / in transit /
  completed), trip advances, start / end km, and freight rate basis.
- **Reports and exports:** profit per trip or per truck, client ledger PDF,
  driver cash register PDF and salary summary.
- **Platform:** offline mode, Hindi, QR desktop login, multi-business drivers,
  location sharing, KYC, plans and subscription, and roles.

**Present only as API calls, with no screen:** driver payroll runs, the client
ledger summary, editing a payment entry, deleting an invoice, deleting a truck
document, and deleting the user account.

**Stub or placeholder screens:**
- `create-invoice.tsx` (empty);
- `documents-manager.tsx` (an unrelated local "Add Client" mock-up, not
  documents);
- Notifications (always empty);
- Google login (disabled);
- Plans & Pricing, App Language and Notification Settings ("Coming soon");
- edit and delete buttons on the driver profile ("Coming soon").

### 1.4 Result of the comparison

Our blueprint already covers almost all of Trucksarthi's **business** features.
In many places it goes much further: road events, approvals, the tax engine,
LR with copies, ledgers with buckets, and the Document Manager.

Section 9 lists **31 concrete gaps**. Most are owner-app conveniences and a few
master-data fields that Trucksarthi has and our specs do not name yet:

- Truck fields: RC owner name, vehicle class, RTO and registration date
  (giving the vehicle's age).
- A full address for places.
- A misc / other-charges field and an office note on the quick trip form.
- Adding a client, truck, driver or place from inside the trip form.
- A totals bar and a PDF report on the trip list.
- A client page with Unbilled / Billed / Payments tabs and a one-tap Settle.
- Sharing truck details as a text message.
- Pay runs for any period, with Pending / Paid status (no table for this yet).
- Mobile OTP sign-in for owners.
- Owner-app navigation, dashboard tiles, Help, Reminders and loading
  skeletons.

Section 5.9 lists Trucksarthi **mistakes we must not copy** (for example, the
trip date is not saved, and the invoice date is the print date).

---

## 2. Screens and navigation

### 2.1 Navigation map

```
App start (index) ── token saved? ── yes ─► check token (GET /api/auth/me) ─► Home tab
                                  └─ no / invalid ─► Login options

Login options ─┬─► Phone login (+91, Send OTP → 6-digit OTP → Verify)
               ├─► Email login (email + password)
               ├─► "Google Login — Coming Soon" (not tappable)
               └─► Create account (name, email, password)
After any login: profile complete? ── no ─► Basic details (Complete your profile) ─► Home
                                    └ yes ─► Home

Bottom tabs:  [ Home ]  [ Add Trip ]  [ Trip Log ]
Header on tab screens:  ☰ side menu · "Trucksarthi" · 🔔 Reminders
Side menu:   profile header (photo, name, email) ─► My Profile
             MAIN MENU: Trip Log · Settings
             MANAGER:   Trucks · Drivers · Clients · Locations
             Logout
Stack screens: Clients ─► Client profile · Drivers ─► Driver profile ·
               Trucks ─► Truck profile · Locations · Settings ─► Help Center ·
               Reminders · Profile · Basic details
Unlinked files: create-invoice (empty), documents-manager (mock), testScreen
```

On iPhone the three tabs use the system's native tab bar. On Android they use a
standard tab bar. There is also an unused older custom tab bar component.

### 2.2 Screen inventory (every file under `app/`)

| File | Screen title | Reached from | What it shows | Actions |
|---|---|---|---|---|
| `app/_layout.tsx` | — (root) | — | Wraps the app in the gesture handler, theme store, bottom-sheet provider, navigation theme and status-bar colour | — |
| `app/index.tsx` | — (start-up check) | App start | A spinner while it checks the saved token | Goes to Home if the token works, else clears it and goes to Login |
| `app/testScreen.tsx` | — | Not linked | Developer test of a bottom sheet | — |
| `app/api/axiosInstance.js` | — (not a screen) | — | The HTTP client: base URL from an environment variable or a default cloud domain. Adds `Authorization: Bearer <token>`. On a 401 reply it deletes the token | — |
| `app/auth/login.tsx` | Welcome | Start, logout | Logo, "Welcome", "Manage your fleet effortlessly." | Continue with Phone · Continue with Email · "Google Login — Coming Soon" (disabled) · "New here? Create Account" |
| `app/auth/login-phone.tsx` | Phone Login | Welcome | Mobile field fixed to **+91** and 10 digits. Then a 6-digit OTP field | Send OTP · Verify & Continue · Change number · Use Email Instead · Back |
| `app/auth/login-email.tsx` | Login | Welcome | Email and password | Login · "No account? Sign Up" · Back |
| `app/auth/signup-email.tsx` | Create Account | Welcome, Login | Full name, email, password | Create Account · Login link · "Use Phone (OTP)" link |
| `app/auth/login-google.tsx` | Continue with Google | Not linked from the welcome screen | A button that only shows "temporarily disabled during system migration" | Go Back |
| `app/(tabs)/_layout.tsx` | — | — | The three bottom tabs: Home, Add Trip, Trip Log | — |
| `app/(tabs)/home/index.tsx` | Trucksarthi (Home) | Tab | Two tiles: **"Monthly Revenue"** and **"Number of Trips"**. A large **Add Trip** button. Four quick buttons: **Trucks, Drivers, Clients, Locations**. A **Recent Trips** card (last 3) with "View All →". Skeleton placeholders while loading | Open the side menu · Reminders · Add Trip · quick buttons · View All |
| `app/(tabs)/addTrip/index.tsx` | Trucksarthi (Add Trip) | Tab, Home button | The trip form (§3.1) in grouped cards, WhatsApp-settings style | Save Trip · "+" beside each picker opens a quick-add form (client, place, truck, driver) |
| `app/(tabs)/tripLog/index.tsx` | Trucksarthi (Trip Log) | Tab, side menu, Home "View All" | **Filters** button (shows or hides the filter card) and a **PDF** button. Summary card: **"N trips found"** and **total ₹**. One card per trip | Filter · Reset filters · PDF report · Edit (opens the Edit Trip form) · Delete (asks to confirm) |
| `app/(stack)/_layout.tsx` | — | — | Stack header with a back arrow and a bell icon on every stack screen. Titles: "Clients", "Trucks", "Drivers", "Locations", "Help Center", "Trucksarthi" (Settings, Basic details). Profile and the three profile pages draw their own header | — |
| `app/(stack)/clients-manager.tsx` | Clients | Home, side menu | A card per client: name, a fixed "VERIFIED PARTNER" label, POC (contact person), phone, address. Skeleton while loading. "No clients found." when empty. Reloads every time the screen is shown | Tap card → Client profile · Edit · Delete (asks to confirm) · "+" button → Add client |
| `app/(stack)/client-profile.tsx` | Client Profile | Clients | Client card (name, contact person, edit pencil). **Call** and **WhatsApp** buttons. Three totals: **Billed, Unbilled, Settled**. Tabs: **Unbilled · Billed · Settled**. **Add Payment Entry** button. Skeleton while loading. "Client not found" if the client is missing | Edit client · Call · WhatsApp · select trips → **Generate Invoice (n)** · invoice **Share (PDF)** · **Settle** · Add payment |
| `app/(stack)/drivers-manager.tsx` | Drivers | Home, side menu | A card per driver: name, a fixed "COMMERCIAL OPERATOR" label, phone, and "License Registered" or "License Missing" | Tap card → Driver profile · Edit · **Share** (sends name and phone as text) · Delete · "+" button |
| `app/(stack)/driver-profile.tsx` | Driver Profile | Drivers | Name and phone. **Balance Overview** (₹ amount marked "Credit" in green or "Debit" in red). **Documents**: License and Aadhaar tiles. **Financial Ledger** list | Share · Edit and Delete (both only say "Coming soon") · view a document full screen · upload or replace a document · **+ New Entry** |
| `app/(stack)/trucks-manager.tsx` | Trucks | Home, side menu | A card per truck: registration number in capitals, "`<class or HCV>` • FLEET UNIT", owner, capacity in tons and container size | Tap card → Truck profile · Edit · Delete · "+" button |
| `app/(stack)/trucks-profile.tsx` | Truck Profile | Trucks | Main card: registration number, make and model. Six details: **Chassis, Engine, Owner, Norms (shows "BS-VI" if empty), Weight (kg), Class (shows "HCV" if empty)**. Three fold-out sections: **Important Dates**, **Vault Documents** (4 tiles), **Recent Trip Logs** (last 5, open by default) | **Share** (choose details, §3.19) · **Edit** (§3.6b) · view a document full screen · upload or replace a document |
| `app/(stack)/locations-manager.tsx` | Locations | Home, side menu | A card per place: name, a fixed "VERIFIED HUB" label, full address | Edit · Delete · "+" button |
| `app/(stack)/profile.tsx` | My Profile | Side-menu header | Round photo with a camera button. Name and company (shows "Enterprise Account" if empty). Edit Profile / Discard Changes switch. Tabs: **Personal · Business · Banking** | Change photo (camera or gallery) · edit fields · **Save Profile Updates** (shown only while editing and after a change) |
| `app/(stack)/basic-details.tsx` | Complete Your Profile | After login when the profile is not complete | Photo picker and the onboarding fields (§3.13) | Save & Continue → Home |
| `app/(stack)/settings.tsx` | Settings | Side menu | Sections: **Account** (Plans & Pricing), **App Preferences** (Appearance, Biometric Login, App Language, Notification Settings), **Desktop** ("Use Truck Sarthi on Desktop"), **Support** (Help Center), **Logout** | See §3.14 |
| `app/(stack)/notifications.tsx` | Reminders | Bell icon | Always shows "No notifications found" (the list is a fixed empty list). The layout is ready for items with a **high / medium / low** priority, a title, a message and a time | — |
| `app/(stack)/helpCenter.tsx` | Help Center | Settings | "We are available 24×7…" and three cards: **WhatsApp Support**, **Call Support** (shows the number), **Email Support** (shows the address) | Opens WhatsApp chat, the phone dialler or the email app |
| `app/(stack)/create-invoice.tsx` | — | Not linked | **Empty** (draws nothing) | — |
| `app/(stack)/documents-manager.tsx` | — | Not linked | **Not a documents screen.** A leftover mock-up: a hard-coded sample client list and an "Add Client" form kept only in memory | — |

### 2.3 Shared components

| Component | Used by | What it is |
|---|---|---|
| `SideMenu` | Home, Add Trip, Trip Log | Slides in from the left (82% of the screen width) over a dark backdrop. Swipe left to close. Contents in §2.1 |
| `ClientModal` | Add Trip, Clients | Add / Edit Client sheet (§3.4) |
| `DriverModal` | Add Trip, Drivers | "Register Driver" / "Update Profile" sheet (§3.5) |
| `TruckModal` | Add Trip, Trucks | "Add New Truck" / "Update Vehicle" sheet (§3.6) |
| `LocationModal` | Add Trip, Locations | "Add Location" / "Edit Point" sheet (§3.7) |
| `EditTripModal` | Trip Log | Edit Trip sheet (§3.2) |
| `FilterSection` | Trip Log | The filter card (§3.3) |
| `Skeleton` | Home, all lists, profile pages | Grey block that pulses (opacity 0.3 ↔ 0.7 every 0.8 s) |
| `TabBar` | Not used | Older custom bottom bar with rounded top corners |

All forms open as **full-height bottom sheets** with a grab handle.
**Dragging down more than about 120 px closes them.**

---

## 3. Forms and fields

Legend: **Req** = the app blocks saving when it is empty. **Opt** = optional.
"Checks" lists every validation the app does. Type checking on the backend is
unknown.

### 3.1 Add Trip (`app/(tabs)/addTrip`)

Sent as `POST /api/trips`. The form is grouped into cards in this order: date and
client · origin and destination · truck and driver · money and notes.

| # | Label on screen | Field sent | Type / control | Req | Default | Checks / notes |
|---|---|---|---|---|---|---|
| 1 | Trip Date | *(not sent)* | Date picker, shown as "dd Month yyyy" (Indian format) | — | Today | **Bug: the date is never sent on create.** The trip date is set by the backend or left empty. The Trip Log and the invoice then show "No Date" or the server's value |
| 2 | Client | `client` | Dropdown of client names, with a **"+"** button | Req | Empty ("Select Client") | "+" opens Add Client. A new client is selected automatically |
| 3 | Origin | `start_location` | Dropdown of place names, with **"+"** | Req | "Starting Point" | "+" opens Add Location |
| 4 | Destination | `end_location` | Dropdown of place names, with **"+"** | Req | "Going To" | A new place fills **Origin if it is empty, else Destination**. **Same origin and destination is allowed (no check)** |
| 5 | Truck | `truck` | Dropdown of registration numbers, with **"+"** | Req | "Assign Truck" | "+" opens Add Truck. No check that the truck is free or that its documents are valid |
| 6 | Driver | `driver` | Dropdown of driver names, with **"+"** | Req | "Assign Driver" | "+" opens Add Driver. No licence check |
| 7 | Trip Cost (₹) | `cost_of_trip` | Number keypad | Req | Empty ("0.00") | Changed to a number. **A zero or negative value is not checked** |
| 8 | Misc (₹) | `miscellaneous_expense` | Number keypad | Opt | Empty, sent as **0** | Despite the name, it is **added to the trip total and billed to the client** (§5.1) |
| 9 | Notes | `notes` | Text, several lines | Opt | Empty | "Tap to add notes…". Shown on the trip card, the trip PDF and the edit form |

- If any of fields 2–7 is empty, the app shows **"Please complete all fields to
  save the trip."**
- After saving, it shows "Trip saved successfully!" and clears the form back to
  today's date.
- There is **no** trip status, number, km, advance, rate basis, material,
  weight, LR, consignee or crew other than one driver.

**Quick-add rules inside Add Trip**

| Quick add | Required in this flow | After saving |
|---|---|---|
| Client | name, contact number | The new client is selected |
| Driver | name, contact number | The new driver is selected. **The two photos are sent as local phone file paths, not uploaded** (bug) |
| Truck | registration number, registered owner | The new truck is selected. Loading capacity is sent as a number or left empty |
| Location | name, full address | Fills Origin if it is empty, otherwise Destination |

### 3.2 Edit Trip form (`components/EditTripModal`)

Sent as `PUT /api/trips/:id`.

| Label | Field | Control | Req | Notes |
|---|---|---|---|---|
| Trip Date | `trip_date` | Text, **read-only** | — | Shown as yyyy-mm-dd. Sent back unchanged |
| Truck / Driver / Client / Start Location / End Location | `truck`, `driver`, `client`, `start_location`, `end_location` | Dropdowns, each saying "Select …" | Req (all five) | Only one dropdown can be open at a time |
| Cost of Trip (₹) | `cost_of_trip` | Number | Req | |
| Miscellaneous Expense (₹) | `miscellaneous_expense` | Number | Opt | Empty is sent as 0 |
| Notes | `notes` | Text, 3 lines | Opt | |

- Buttons: **Save Changes** · **Delete Trip** (asks "Are you sure?") · Cancel.
  If a required field is empty, the app shows "Please fill all required fields."
- **No lock:** a trip that is already on an invoice can still be edited or
  deleted from here.

### 3.3 Trip Log filters (`components/FilterSection`)

| Filter | Control | Rule |
|---|---|---|
| Driver | Dropdown of drivers | Trip's driver = chosen driver |
| Client | Dropdown of clients | Trip's client = chosen client |
| Truck | Dropdown of registration numbers | Trip's truck = chosen truck |
| Location | Dropdown of places | Trip's **origin OR destination** = chosen place |
| From | Date picker ("Select Date") | Trip date on or after it. Trips with no date are left out |
| To | Date picker | Trip date on or before it. The compare uses the full date-time the picker returns, so a trip on the last day can drop out depending on its stored time |
| Reset Filters | Button | Clears everything |

- The filter card is hidden until **Filters** is tapped, and opens with a short
  animation.
- Filtering happens **on the phone, over all trips** (no paging, no search
  box).
- The list is sorted by **trip date, newest first**.

**Summary card:** "`N` trips found" and **₹ sum of (trip cost + misc)** for the
trips that are showing.

**PDF button:** builds the "Trip Report" (§5.6) from the filtered list.

### 3.4 Client form (`components/ClientModal`, Clients list, quick add)

Title is "Add Client" or "Edit Client". The button is "Save" or "Update". Cancel
is also offered. Sent as `POST /api/clients` or `PUT /api/clients/:id`.

| Label (made from the field name) | Field | Keyboard | Req |
|---|---|---|---|
| client name | `client_name` | Text | **Req** |
| contact number | `contact_number` | Phone keypad | **Req** |
| contact person name | `contact_person_name` | Text | Opt (the data type marks it required) |
| alternate contact number | `alternate_contact_number` | Phone keypad | Opt |
| email address | `email_address` | Email keyboard | Opt (the data type marks it required) |
| office address | `office_address` | Text, several lines | Opt |

- No format checks on the phone number or email.
- Delete asks "Delete this client?" and then deletes for good.
- **Edit Client inside Client Profile** is a second, shorter form with: Client
  Name, Contact Person, Phone, Email and Office Address. The alternate number is
  **not shown there, but it is kept**. If name or phone is empty, the app says
  "Please fill all required fields."

### 3.5 Driver form (`components/DriverModal`)

The title is "Register Driver" or "Update Profile", with the line "Driver
Information". The button is "Register Driver" or "Apply Changes". Sent as
`POST /api/drivers` or `PUT /api/drivers/:id`.

| Label | Field | Control | Req | Notes |
|---|---|---|---|---|
| Full Name | `driver_name` | Text | **Req** | |
| Contact Number | `contact_number` | Phone keypad | **Req** | "Mobile number" |
| Aadhaar / ID Proof | `identity_card_url` | Picks an image from the **gallery** (crop allowed, quality 0.8). "Tap to change photo" | Opt | **From this form only the local file path is saved**, not a real upload |
| Driving License | `license_card_url` | Same as above | Opt | Same problem |

- There is **no licence number, licence expiry, Aadhaar number, address, date
  of birth or salary** field.
- The **real upload** of these photos happens only on the Driver Profile
  (§3.9b).

### 3.6 Truck forms

**(a) Add / Edit Truck sheet (`components/TruckModal`)**

The title is "Add New Truck" or "Update Vehicle", with the line "Vehicle
Configuration". The buttons are "Save Truck" or "Apply Changes", and "Discard".
Sent as `POST /api/trucks` or `PUT /api/trucks/:id`.

| Order | Label | Field | Control | Req | Placeholder / notes |
|---|---|---|---|---|---|
| 1 | Registration Number | `registration_number` | Text, **all capitals** | **Req** | "e.g. MH 12 AB 1234". **No format check** |
| 2 | Registered Owner | `registered_owner_name` | Text | **Req** | "Full Owner Name" (owner name as on the RC) |
| 3 | Container Dimension | `container_dimension` | Text | Opt | "e.g. 20ft / 32ft" |
| 4 | Loading Capacity (Tons) | `loading_capacity` | Number | Opt | "e.g. 10". From the Trucks list, **an empty value is sent as 0** |
| 5 | Chassis Number | `chassis_number` | Text | Opt | "Optional" |
| 6 | Engine Number | `engine_number` | Text | Opt | "Optional" |

**(b) Edit Truck on the Truck Profile**

The sheet is titled "Edit Truck". Pressing **Update Details** sends two calls
at once: `PUT /api/trucks/:id` (basic details) and
`PATCH /api/trucks/:id/important-dates` (all seven dates).

| Label | Field | Control |
|---|---|---|
| Plate Number | `registration_number` | Text |
| Brand / Make | `make` | Text |
| Owner Name | `registered_owner_name` | Text |
| Fitness Expiry | `fitness_upto` | Date picker ("Select Date") |
| Insurance Expiry | `insurance_upto` | Date picker |
| National Permit | `national_permit_upto` | Date picker |

- The form also holds the other truck fields. They are sent back unchanged, so
  you cannot edit them on screen.
- **Bug:** the model is sent under the name `model` instead of
  `vehicle_model`.
- **No screen lets you type** model, vehicle class, fuel type, fuel norms,
  unladen weight, RTO, registration date, pollution date, road-tax date or
  state-permit date. Those fields exist in the data and are shown on the
  profile. They **appear** to be filled by the backend, probably from an RC /
  Vahan lookup. That is our reading, not visible in this code.

### 3.7 Location form (`components/LocationModal`)

The title is "Add Location" or "Edit Point", with the line "Network Access
Point". The button is "Save Location" or "Update Record". Sent as
`POST /api/locations` or `PUT /api/locations/:id`.

| Label | Field | Control | Req | Placeholder |
|---|---|---|---|---|
| Location Alias | `location_name` | Text | **Req** | "e.g. Warehouse 1, Pune Main" |
| Full Postal Address | `complete_address` | Text, 4 lines | **Req** | "Street, Landmark, City..." |

`latitude` and `longitude` exist in the data, but **no screen sets them** (no map
and no address search).

### 3.8 Client Profile: payment, settle and invoice

**(a) Add Payment sheet** ("Add Payment", with the client's name). Saves with
`POST /api/ledger/entry`.

| Label | Field sent | Control | Req | Default |
|---|---|---|---|---|
| Date | `date` (full date-time) | Date picker, shown as "dd Month yyyy" | — | Today |
| Amount | `amount` | Number, with an "INR" tag | **Req** ("Enter amount") | Empty, or the invoice total when settling |
| Remarks * | `remarks` | Text, "Payment details (e.g., Bank Transfer)" | **Req** ("Remarks are mandatory") | Empty, or "Settlement for Invoice #`<no>`" when settling |
| *(hidden)* | `entry_type` | Always `credit` | — | — |
| *(hidden)* | `invoice` | The invoice ID, only when started from **Settle** | — | — |

There is **no** payment mode, UTR or cheque number, bank, TDS or deductions
field, and no way to split one payment across several invoices.

**(b) Settle** (shown on Billed-tab invoices that are not `paid`):
1. opens the payment sheet;
2. amount = the invoice's `total_amount`;
3. remark = "Settlement for Invoice #`<no>`";
4. date = today;
5. links the payment to the invoice.

**(c) Generate Invoice** (Unbilled tab):
1. Tap trip cards to select them. A selected card gets a green border.
2. A "Generate Invoice (n)" button appears.
3. It sends `POST /api/invoices` with `client_id`, the selected trip IDs, and
   `due_date` = **today** (the due date is the same day the invoice is made).
4. It reloads invoices, trips and the ledger.

There is no screen to set the invoice date, number, terms, tax or due date
(`create-invoice.tsx` is empty).

**(d) Edit a payment entry:** the hook can change amount, remarks and date
(`PUT /api/ledger/entry/:id`), and the screen keeps the matching state. But
**no tap opens it**, so it cannot be used.

The developer note in the repo (`.agent/CLIENT_PROFILE_UPDATES.md`) says this
was finished, and says the Settled tab shows paid invoices. **The code shows a
payment list instead.** We trust the code.

### 3.9 Driver Profile: cash entry and documents

**(a) Add Transaction sheet** (opened by "+ New Entry"). Saves with
`POST /api/driver-ledger`.

| Label | Field sent | Control | Req | Default |
|---|---|---|---|---|
| Amount (₹) | `amount` | Number | **Req** | Empty |
| Remarks | `remarks` | Text, "e.g. Food, Diesel, Advance" | **Req** (the hook also says "Remarks are mandatory") | Empty |
| Expense / Payment | `transaction_nature` | Two-button switch | — | **Expense** |
| *(set by the app)* | `direction` | Expense → `to`, Payment → `from` | — | — |
| *(set by the app)* | `counterparty_type` | Always `owner` | — | — |

- If a field is empty, the app shows "Please fill all fields".
- There is **no date field**: the backend sets `entry_date`.
- There is no trip link and no payment mode.

**(b) Documents:**
- two tiles, **License** and **Aadhaar**;
- an empty tile says "TAP TO UPLOAD";
- a filled tile shows the photo, opens full screen when tapped, and has a
  pencil to replace it;
- photos come from the gallery (crop allowed, quality 0.5) and are uploaded to
  `POST /api/drivers/:id/license` or `/aadhaar`;
- **no expiry date and no document number.**

### 3.10 Truck documents (Truck Profile → "Vault Documents")

| Item | Behaviour in code |
|---|---|
| Document types | **RC, INSURANCE, PERMIT, FITNESS** (a fixed list of 4). PUC, road tax and national permit exist only as dates (§3.6), not as files |
| Tile | Shows the photo if there is one, otherwise a "MISSING" tile. Tap: view full screen, or upload if missing. Pencil: replace |
| Upload | Images from the gallery only (crop allowed, **quality 0.3**). The file is named `<TYPE>.jpg`. Sent as `POST /api/truck-documents/:truck_id/:TYPE/upload` (multipart: `file`, `expiry_date`) |
| Expiry date | **Never asked.** The app always sends **today + 365 days** |
| One file per type | A new upload **replaces** the same type for that truck. There is no history and no versions |
| Expiry shown? | The tile is given an "expiring" flag and the expiry date, but **does not show them** |
| Status | The data has `active` / `expired`. It is not shown |
| Delete | The hook has `DELETE /api/truck-documents/:id`. There is no button for it |
| All documents | The hook can list every document across all trucks (`GET /api/truck-documents`). No screen uses this |

### 3.11 Invoice

See §6 for the full invoice description. Creating one is covered in §3.8(c).

### 3.12 My Profile (`app/(stack)/profile.tsx`)

The fields can only be changed after tapping **Edit Profile**. **Save Profile
Updates** appears only when something has changed. Saving sends the whole form
and the photo path to `PUT /api/users/:id`.

| Tab | Label | Field | Control | Notes |
|---|---|---|---|---|
| Personal ("Basic Details") | Full Name | `name` | Text | |
| | Email Address | `email` | Text, **always read-only** | |
| | Phone Number | `phone` | Text | |
| | Date of Birth | `date_of_birth` | Date picker | Stored as yyyy-mm-dd |
| Business ("Business Information") | Company Name | `company_name` | Text | |
| | GST Number | `gstin` | Text, "Optional" | **No format check** |
| | Office Address | `address` | Text, several lines | "Full street address" |
| Banking ("Settlement Details") | Bank Name | `bank_name` | Text | |
| | Account Holder | `account_holder_name` | Text | |
| | Account Number | `account_number` | Text | **No check** |
| | IFSC Code | `ifsc_code` | Text, capitals | **No format check** |
| Header | Photo | `profile_picture_url` | "Take Photo" or "Choose from Library", square crop, quality 0.7 | Uploaded at once to `POST /api/users/:id/profile-picture` |

### 3.13 Basic details: onboarding (`app/(stack)/basic-details.tsx`)

| Label | Field | Control | Req | Notes |
|---|---|---|---|---|
| Add Profile Picture | `profile_picture_url` | Gallery, crop, quality 0.7 | Opt | Uploaded at once |
| Name * | `name` | Text | **Req** | Filled in from the account |
| Company Name * | `company_name` | Text | **Req** | |
| DD/MM/YYYY * | `date_of_birth` | Number keypad. The slashes are added as you type. Up to 10 characters | **Req** | Day, month and a 4-digit year must be present, else "Use DD/MM/YYYY format". The day and month values are not checked. Saved as yyyy-mm-dd |
| Phone Number * | `phone` | Phone keypad | Marked * but **not checked** | **Read-only** if the account already has a phone (OTP sign-in) |
| Email Address * | `email` | Text | Marked * but **not checked** | **Read-only** if the account already has an email |
| Address * | `address` | Text area | **Req** | |

- The screen appears when `GET /api/users/check-profile/:id` says the profile
  is not complete. If that call fails, it appears anyway.
- **Save & Continue** saves with `PUT /api/users/:id` and then goes to Home.

### 3.14 Settings (`app/(stack)/settings.tsx`)

| Section | Item | Behaviour |
|---|---|---|
| Account | Plans & Pricing | Shows "Coming Soon" |
| App Preferences | **Appearance**: System / Light / Dark buttons | Saved **on the phone only**. "System" follows the phone and changes live |
| | **Biometric Login** switch | Shown only if the phone has biometric hardware and a finger or face enrolled. Turning it on asks for a biometric check first. Saved on the phone. **The app never asks for it at start-up**, so the switch does nothing in this version |
| | App Language | "Coming Soon" |
| | Notification Settings | "Coming Soon" |
| Desktop | "Use Truck Sarthi on Desktop" | Opens their website |
| Support | Help Center | Opens §3.16 |
| — | Logout (red button) | Deletes the token and goes to the Welcome screen |

### 3.15 Reminders (notifications)

The header title is "Reminders". There is **no data source**: the list is
always empty, showing "No notifications found".

The layout is ready for items like this:

| Part | Detail |
|---|---|
| Priority | `high` (alert icon, red), `medium` (warning, accent colour), `low` (info, green) |
| Text | Title, message |
| Time | Shown as text |
| Colour dot | One per priority |

### 3.16 Help Center

- "We are available 24×7 to help you with any issues or questions."
- Three cards:
  - **WhatsApp Support** opens a `wa.me/91<number>` chat. If WhatsApp is
    missing, the app says "WhatsApp not installed".
  - **Call Support** opens the phone dialler.
  - **Email Support** opens the email app.
- The number and email are fixed in the app. They are the same support
  contacts as on their website.

### 3.17 Login and sign-up methods

| Method | Fields | Rules in code | API |
|---|---|---|---|
| Phone OTP | Mobile (starts as "+91". Only digits are kept, a leading 91 is removed, up to 10 digits) → OTP ("6 digit OTP", number keypad, up to 6) | Send OTP only if the number has exactly 10 digits ("Enter a valid 10-digit number."). Verify only if an OTP was typed. "Change number" goes back. **No resend timer** | `POST /api/auth/send-otp {phone}` → `POST /api/auth/phone {phone, otp}` → `{token, user}` |
| Email login | Email, password | Both required. Spaces trimmed. **No "forgot password"** | `POST /api/auth/login {email, password}` |
| Email sign-up | Full Name, Email, Password | All required. **No password rules, no confirm-password field, no email check** | `POST /api/auth/ {name, email, password}` |
| Google | — | Disabled ("Coming Soon"; "temporarily disabled during system migration") | — |
| After any sign-in | — | Token saved on the phone → `GET /api/auth/me` → profile-complete check → Home or Basic details | — |
| Session | — | Bearer token on every call. A 401 deletes the token. A bad token at start-up goes to Login | — |
| Logout | — | Side menu or Settings. Clears the token | — |
| Account delete | — | The hook has `DELETE /api/users/:id`. **There is no screen for it** | — |

### 3.18 Documents manager (`documents-manager.tsx`)

**Not a feature in this version.** The file holds a mock-up: one hard-coded
sample client and an "Add Client" form (name, contact person, contact number,
alternate number, email, address) kept only in memory. Nothing links to it.

So this early version has **no central Document Manager**. Its only documents
are the 4 truck tiles (§3.10) and the 2 driver tiles (§3.9).

### 3.19 Share truck details (Truck Profile → share icon)

The sheet "Select details to share" shows chips you can switch on or off. Then
**Share Selected Details** sends a plain-text message through the phone's share
sheet (WhatsApp and others). The message starts with a "Truck Details:
`<number>`" title and ends with "Shared via TruckSarthi".

| Chip | On by default | Line in the message |
|---|---|---|
| Number | Yes | Always in the title |
| Make/Model | Yes | Brand: make + model |
| Owner | Yes | Owner name |
| Chassis | No | Chassis number |
| Engine | No | Engine number |
| Norms | No | Fuel norms ("BS-VI" if empty) |
| Weight | No | Unladen weight in kg |
| Vehicle Age | No | "Xy Ym" worked out from the registration date |
| Fitness Date | No | Fitness valid-upto date |
| Insurance Date | No | Insurance valid-upto date |

**Driver share** (Drivers list and Driver Profile): a plain-text message with
"Driver Details", name and contact number.

---

## 4. Data model and API

### 4.1 Entities (`types/entity.ts`)

Every current entity has `_id` and `user`: the **owner's account is the
tenant**. There is no company, branch or staff-role table, and no
`createdAt`/`updatedAt` on trucks or documents.

**User**

| Field | Type | Req | Notes |
|---|---|---|---|
| name | text | yes | |
| email | text | yes | |
| password | text | no | Only for email accounts |
| phone | text | no | Set by phone-OTP sign-in |
| firebase_uid | text | no | Left over from the Firebase design |
| company_name, address | text | no | |
| date_of_birth | date | no | |
| profile_picture_url | text | no | A file path |
| gstin | text | no | |
| bank_name, account_holder_name, account_number, ifsc_code | text | no | Bank details for invoices (not printed in this version) |

**Truck**

| Field | Type | Req | Notes |
|---|---|---|---|
| registration_number | text | yes | |
| chassis_number, engine_number | text | no | |
| registered_owner_name | text | no (the form requires it) | |
| make, vehicle_model | text | no | |
| vehicle_class | text | no | Shown as "HCV" if empty |
| fuel_type | text | no | |
| fuel_norms | text | no | Shown as "BS-VI" if empty |
| unladen_weight | number | no | kg |
| registered_rto | text | no | |
| container_dimension | text | no | e.g. "20ft / 32ft" |
| loading_capacity | number | no | tons |
| registration_date, fitness_upto, pollution_upto, road_tax_upto, insurance_upto, permit_upto, national_permit_upto | dates | no | The "important dates". Changed through a separate PATCH call |

**TruckDocument**

| Field | Type | Req | Notes |
|---|---|---|---|
| truck | link | yes | |
| document_type | text | yes | Saved in capitals: RC / INSURANCE / PERMIT / FITNESS |
| file_url | text | yes | |
| expiry_date | date | no | The app always sends today + 365 days |
| status | `active` \| `expired` | no | |

**Driver**

| Field | Type | Req | Notes |
|---|---|---|---|
| driver_name | text | yes | |
| contact_number | text | yes | |
| identity_card_url | text | no | Aadhaar / ID photo |
| license_card_url | text | no | Licence photo |

**DriverLedger** (the driver's cash account)

| Field | Type | Req | Notes |
|---|---|---|---|
| driver | link | yes | |
| transaction_nature | `paid_by_driver` \| `received_by_driver` | yes | Shown as "Expense" / "Payment" |
| counterparty_type | `owner` \| `vendor` \| `client` | yes | The app only uses `owner`. The model already allows "driver paid a vendor" and "driver received from a client" |
| counterparty_id | link | no | |
| direction | `to` \| `from` | yes | |
| amount | number | yes | |
| remarks | text | no (the app requires it) | |
| title | text | no | Not used |
| entry_date | date | yes | Set by the backend |

**Client**

| Field | Type | Req | Notes |
|---|---|---|---|
| client_name | text | yes | |
| contact_person_name | text | yes in the data (optional in the form) | |
| contact_number | text | yes | |
| email_address | text | yes in the data (optional in the form) | |
| alternate_contact_number | text | no | |
| office_address | text | no | |

There is **no GSTIN, PAN, state, credit days or opening balance.**

**ClientLedger**

| Field | Type | Req | Notes |
|---|---|---|---|
| client | link | yes | |
| invoice | link | no | Set when settling an invoice |
| entry_type | `debit` \| `credit` | yes | The app only creates `credit` (payments). Debits **appear** to be written by the backend when an invoice is made |
| amount | number | yes | |
| remarks | text | no | |
| entry_date | date | yes | |

**Location**

| Field | Type | Req | Notes |
|---|---|---|---|
| location_name | text | yes | A free name ("alias") |
| complete_address | text | no (the form requires it) | |
| latitude, longitude | number | no | Never set by the app |

**Trip**

| Field | Type | Req | Notes |
|---|---|---|---|
| trip_date | date | no | Not sent on create (bug) |
| truck, driver, client | links | yes | One of each |
| start_location, end_location | links | yes | |
| cost_of_trip | number | yes | The freight amount |
| miscellaneous_expense | number | no | Billed on top of the freight |
| notes | text | no | |
| invoiced_status | `not_invoiced` \| `invoiced` | no | The only trip status |

**Invoice** and **InvoiceItem**

| Field | Type | Req | Notes |
|---|---|---|---|
| client | link | yes | |
| invoice_number | text | yes | Made by the backend |
| total_amount | number | yes | Made by the backend. **Appears to be the sum of the items, without tax** |
| due_date | date | no | The app sends today |
| status | `pending` \| `paid` \| `cancelled` | yes | |
| items | list | yes | One per trip: `trip`, `trip_cost`, `misc_expense`, `total` |

There is **no invoice date field** (the PDF prints the day it is made) and **no
tax fields.**

**Legacy SQL types (from the old design)**

| Type | Fields |
|---|---|
| DriverFinancialSQL | `driver_id`, `trip_id`, `entry_type` (`advance` / `expense` / `salary` / **`per_trip`**), `amount`, `remarks`, `entry_date`, `firebase_uid` |
| DriverPayrollSQL | `payroll_id`, `driver_id`, `period_start`, `period_end`, `total_amount`, `status` (text) |

### 4.2 API endpoints used by the hooks

| Hook | Method | Endpoint | Payload / response | Used by a screen? |
|---|---|---|---|---|
| useAuth | POST | `/api/auth/login` | `{email, password}` → `{token, user}` | Yes |
| useAuth | POST | `/api/auth/send-otp` | `{phone}` (e.g. "+91XXXXXXXXXX") | Yes |
| useAuth | POST | `/api/auth/phone` | `{phone, otp}` → `{token, user}` | Yes |
| useAuth | POST | `/api/auth/` | `{name, email, password}` → `{token, user}` (sign-up) | Yes |
| useAuth / useUser | GET | `/api/auth/me` | → user | Yes |
| useAuth / useUser | GET | `/api/users/check-profile/:id` | → `{profileCompleted}` | Yes |
| useUser | PUT | `/api/users/:id` | Some user fields → user | Yes |
| useUser | POST | `/api/users/:id/profile-picture` | Multipart `file` → `{file_url}` or `{profile_picture_url}` | Yes |
| useUser | DELETE | `/api/users/:id` | — | **No** |
| useTrips | GET / POST | `/api/trips` | Trip fields (§3.1) | Yes |
| useTrips | PUT / DELETE | `/api/trips/:id` | Trip fields (§3.2) | Yes |
| useClients | GET / POST | `/api/clients` | Client fields | Yes |
| useClients | PUT / DELETE | `/api/clients/:id` | Client fields | Yes |
| useClientLedger | GET | `/api/ledger/client/:client_id` | → list of ledger entries | Yes |
| useClientLedger | GET | `/api/ledger/client/:client_id/summary` | → `{total_debits, total_credits, outstanding}` (the code calls this the "source of truth") | **No** (the screen adds up totals itself) |
| useClientLedger | POST | `/api/ledger/entry` | `{client, invoice?, amount, remarks, date, entry_type: "credit"}` | Yes |
| useClientLedger | PUT | `/api/ledger/entry/:id` | `{amount?, remarks?, date?, client_id}` | **No** (no button opens it) |
| useInvoices | GET | `/api/invoices` | → list of invoices | Yes |
| useInvoices | GET | `/api/invoices/:id` | → invoice | **No** |
| useInvoices | POST | `/api/invoices` | `{client_id, tripIds[], due_date}` | Yes |
| useInvoices | DELETE | `/api/invoices/:id` | — | **No** |
| useDrivers | GET / POST | `/api/drivers` | Driver fields | Yes |
| useDrivers | PUT / DELETE | `/api/drivers/:id` | Driver fields | Yes |
| useDrivers | POST | `/api/drivers/:id/license` | Multipart `file` | Yes (Driver Profile) |
| useDrivers | POST | `/api/drivers/:id/aadhaar` | Multipart `file` | Yes (Driver Profile) |
| useDriverFinance | GET | `/api/driver-ledger/driver/:id` | → list of entries | Yes |
| useDriverFinance | GET | `/api/driver-ledger/driver/:id/balance` | → `{balance}` | Yes |
| useDriverFinance | POST | `/api/driver-ledger` | `{driver_id, transaction_nature, counterparty_type, counterparty_id?, direction, amount, remarks}` | Yes |
| useDriverPayroll | POST | `/api/driver-payroll` | `{driver_id (number), period_start, period_end}` → payroll with the backend's `total_amount` and status `pending` | **No** |
| useDriverPayroll | GET | `/api/driver-payroll/driver/:driver_id` | → payroll history | **No** |
| useDriverPayroll | PUT | `/api/driver-payroll/:payroll_id/pay` | → status `paid` | **No** |
| useLocations | GET / POST | `/api/locations` | Location fields | Yes |
| useLocations | PUT / DELETE | `/api/locations/:id` | Location fields | Yes |
| useTrucks | GET / POST | `/api/trucks` | Truck fields | Yes |
| useTrucks | PUT / DELETE | `/api/trucks/:id` | Truck fields | Yes |
| useTrucks | PATCH | `/api/trucks/:id/important-dates` | The 7 date fields (an empty date is sent as null) | Yes |
| useTruckDocuments | GET | `/api/truck-documents` or `/api/truck-documents/truck/:truck_id` | → list of documents | Yes (per truck) |
| useTruckDocuments | POST | `/api/truck-documents/:truck_id/:TYPE/upload` | Multipart `file` + `expiry_date` | Yes |
| useTruckDocuments | DELETE | `/api/truck-documents/:document_id` | — | **No** |

How the hooks behave:
- Each error is shown in a pop-up, using the server's `error` text when there
  is one.
- After a create, the new item is added to the list on screen. Driver ledger
  entries are added at the top. Client payments reload the whole ledger.
- **Payroll uses number IDs from the old SQL design, while drivers now have
  MongoDB text IDs.** This is an unfinished part of the move.

Stored files can be full web addresses or relative paths. A relative path is
put after a base file address taken from an environment setting or a default
cloud domain.

---

## 5. Business rules visible in code

### 5.1 Calculations

| Figure | Where | Rule in code |
|---|---|---|
| Trip total | Trip Log card, Trip Log summary, trip PDF, client Unbilled tab, invoice line | **trip cost + misc**. So "Miscellaneous expense" is really an **extra charge billed to the client**, not a cost to the owner |
| "Monthly Revenue" tile | Home | Sum of **trip cost over all trips loaded** (misc left out). **Not limited to this month**, despite the label |
| "Number of Trips" tile | Home | Count of **all** trips |
| Recent Trips | Home | The **last 3 trips in the order the server sends them**. Shows "Trip #" + the last 6 characters of the trip ID, plus **the last 6 characters of the truck and driver IDs instead of their names** (bug) |
| Trip Log total | Trip Log | Sum of (trip cost + misc) over the filtered trips |
| Client **Unbilled** | Client Profile | Sum of (trip cost + misc) over this client's trips that are `not_invoiced` |
| Client **Billed** | Client Profile | Sum of `total_amount` over this client's invoices with status `pending` |
| Client **Settled** | Client Profile | Sum of **all credit (payment) entries** for the client, linked to an invoice or not |
| Client outstanding | Backend summary call (not shown) | debits, credits and outstanding come from the backend |
| Driver balance | Driver Profile | From the backend (`/balance`). **Positive or zero = "Credit" (green); negative = "Debit" (red).** It **appears** that "Expense" (the driver spent his own money) raises it and "Payment" (the driver received money) lowers it. In the list, Expense shows **+** in green and Payment shows **−** in red |
| Invoice subtotal | Invoice PDF | Sum of (trip cost + misc) of the invoice's items |
| Invoice tax | Invoice PDF | **Always 5% of the subtotal**, one line "Tax (5%)". **No CGST / SGST / IGST split and no reverse-charge option** |
| Invoice grand total | Invoice PDF | subtotal × 1.05 |
| Vehicle age | Truck Profile, truck share | Whole years and months from the registration date to today ("Xy Ym") |
| "Urgent" date | Truck Profile → Important Dates | **30 days away or less**, which also covers dates already passed. The date turns red and gets an "URGENT" badge |
| Payroll total | Backend | Worked out by the server for the chosen period (not shown) |

### 5.2 Statuses and flows

| Thing | Values | How it changes |
|---|---|---|
| Trip | `invoiced_status`: `not_invoiced` → `invoiced` | Changes when the trip goes onto an invoice (backend). **There is no trip status (planned / running / done)** |
| Invoice | `pending` → `paid`. `cancelled` exists | Made as `pending`. **Appears** to become `paid` when a Settle payment linked to it is saved (backend). Nothing in the app sets `cancelled` |
| Client ledger entry | `debit`, `credit` | The app only writes credits. Editing exists in the API but no screen uses it |
| Truck document | `active`, `expired` | Set by the backend. Not shown |
| Driver payroll | `pending` → `paid` | Create for a period → "mark paid" (API only) |
| Profile | complete / not complete | The backend check decides whether onboarding appears |

### 5.3 Filters, sorting, search and paging

- **Filters:** only the Trip Log has them (§3.3).
- **Sorting:** the Trip Log sorts by trip date, newest first. Every other list
  keeps the server's order.
- **No search box, no paging, no pull-to-refresh** anywhere. Every list loads
  in full.
- **When lists reload:**
  - the Trucks, Drivers, Clients and Locations lists reload each time the
    screen is shown;
  - Add Trip and Trip Log load their lists when opened;
  - the Client Profile reloads clients, invoices, trips and the ledger when it
    opens, and again after each action.

### 5.4 Loading and empty states

- **Skeleton placeholders** appear while loading on: Home, the Clients,
  Drivers, Trucks and Locations lists, Client Profile and Driver Profile.
- The Truck Profile, Basic details, Profile and Add Trip show a spinner instead.
- Empty lists show a short text: "No trips found.", "No trips available", "No
  clients found.", "No drivers found.", "No trucks found.", "No locations
  found.", "No logs recorded.", "No notifications found".
- The client tabs show nothing when they are empty (the developer note claims
  empty-state messages, but they are not in the code).

### 5.5 Theme and look

- WhatsApp-style colours: main green, a darker teal and light green tints, with
  matching dark-mode colours.
- Theme mode **System / Light / Dark** is saved on the phone. System follows the
  phone live. The navigation bar, status bar and system background colours
  follow the theme.
- **Several screens read the phone's colour setting directly** instead of the
  app's theme setting, so they may not follow a manual Light / Dark choice:
  Add Trip, Trip Log, filters, Edit Trip, Locations, Reminders and the stack
  header. The login screens are always light.

### 5.6 PDF outputs

The app builds the HTML itself, turns it into a PDF on the phone, renames the
file and opens the phone's share sheet. There are only two PDFs:

| PDF | File name | Content |
|---|---|---|
| **Trip Report** | `TripHistory.pdf` | Title "Truck Sarthi" with the app maker's name; "Trip Report"; "Generated on" date and time; a date-range line (From / Until / both); **one card per trip**: date, total, route, truck, driver, client, trip cost, misc expense, notes |
| **Invoice** | `Invoice-<number>.pdf` | See §6 |

There is **no** client ledger PDF, driver cash register PDF, fuel report, bilty,
Hindi version or Excel export in this version.

### 5.7 Side menu and header

The side menu, header bell and bottom tabs are listed in §2.1. Logout waits a
short moment for the menu to close, then clears the screen history and goes to
Login.

### 5.8 Deleting and editing

- **Hard deletes** (after one "are you sure" pop-up) for trips, clients,
  drivers, trucks and locations. Invoices and documents can also be deleted
  through the API.
- **No check** stops you deleting a client that has trips or invoices, or
  deleting a trip that is on an invoice.
- The app shows **no audit trail and no "cancelled" state.**

### 5.9 Mistakes in this version (lessons; most are already avoided in our specs)

1. The trip date is shown but **not saved** when a trip is created.
2. The Home "Monthly Revenue" tile counts **all** trips, and leaves out misc.
3. Recent Trips shows raw ID fragments instead of truck and driver names.
4. The invoice PDF adds **5% tax that is not stored** on the invoice. "Settle"
   then fills in the stored total, which has no tax, so **the paid amount and
   the printed grand total do not match**.
5. The invoice date printed is **the day the PDF is made**, not the day the
   invoice was created. Printing again on another day changes the date.
6. The invoice header shows **the app's brand ("TruckSarthi — Professional
   Logistics & Transportation")**. It does not show the owner's company,
   GSTIN, address or bank, even though the profile stores all of them.
7. The invoice due date is the same day it is made. No credit days.
8. Truck document expiry is never asked; it is always set to a year ahead. The
   expiry is not shown on the tile.
9. Driver photos picked in the Add / Edit Driver form are saved as **local phone
   paths**, not uploaded.
10. The biometric switch does nothing (the app never asks for it).
11. Invoiced trips can still be edited or deleted. Records can be deleted for
    good.
12. The truck model is sent under the wrong field name, so it is not saved.
13. A trip with the same origin and destination, or a zero amount, is accepted.

---

## 6. Invoice (detail)

| Topic | What the code shows |
|---|---|
| **Where it is made** | Client Profile → Unbilled tab → tap trips to select them → **Generate Invoice (n)**. You can only bill trips of that one client that are `not_invoiced`. There is **no POD check** (there is no POD in this version) |
| **Invoice screen** | None. `create-invoice.tsx` is empty. You cannot set the date, number, terms, tax mode, bank or notes |
| **Data saved** | Client, number (backend), `total_amount` (backend), `due_date` (the app sends today), `status` = pending, and **one item per trip** (`trip`, `trip_cost`, `misc_expense`, `total`) |
| **Numbering** | Made by the backend. The format is not visible in the app |
| **PDF: header** | Brand name, a fixed tagline and a blue rule |
| **PDF: details row** | Invoice Number · Invoice Date (**today**) · **Total Trips** |
| **PDF: Bill To** | Client name and office address. If the address is empty, it prints the fixed text "Logistics & Transportation Services" |
| **PDF: table columns** | **Date · Route (from → to) · Truck · Driver · Trip Cost · Misc Expense · Total** (one row per trip) |
| **PDF: totals** | "Subtotal (N trips)" · "Tax (5%)" · **Grand Total** |
| **PDF: footer** | Thank-you line, the brand's slogan, "Generated on" date and time |
| **GST** | **Not really handled.** A flat 5% is added in the PDF only: no GSTIN for seller or buyer, no SAC, no place of supply, no CGST / SGST / IGST, no reverse-charge statement, no amount in words, no rounding line, no signature, no bank or UPI |
| **Sharing** | The PDF is made on the phone and shared through the phone's share sheet (WhatsApp, email and others). The Billed tab has a share icon for each invoice |
| **Payments** | **Settle** (pre-filled with the invoice total and a remark) or the general **Add Payment**. Each is a single `credit` entry, and **one payment can link to only one invoice** |
| **Statuses** | pending → paid (backend). `cancelled` is never used. **Delete** exists in the API only |
| **Lists** | Billed tab: **all** of this client's invoices (number, due date, status badge — green for paid, red otherwise — amount, Share, and Settle when not paid). There is **no invoice list across all clients** |

**Conclusion:** the invoice in this version is a basic "bill of trips" with a
printed 5% tax line. The current Trucksarthi markets "GST invoices and bilties
from trips" (`trucksarthi_features.md` §3.7), which means this part was rebuilt
after January 2026. Our spec (`10` §5 and `11`) is already far beyond both
versions. Only a few small display ideas are worth taking (gap 25).

---

## 7. Documents (detail)

| Topic | What the code shows |
|---|---|
| **Truck document types** | RC, INSURANCE, PERMIT, FITNESS: one image each, one current file per type |
| **Truck validity dates (without files)** | Registration date, fitness, pollution (PUC), road tax, insurance, permit, national permit, all on the truck record. The Truck Profile shows them with "Urgent" badges (30 days or less). Only fitness, insurance and national permit can be edited in the app |
| **Driver documents** | Licence photo and Aadhaar / ID photo. **No number and no expiry** |
| **Upload** | Images from the phone gallery only. **No camera, no PDF, no several pages, no front and back.** Compression: truck 0.3, driver 0.5, profile 0.7 |
| **Expiry handling** | Truck documents: expiry is **set to today + 365 days** with no question asked, then a status `active` / `expired` on the server. Important dates: the "Urgent" badge on screen only |
| **Reminders** | **None.** The Reminders screen is empty. There is no push, SMS or WhatsApp alert and no reminder setting ("Notification Settings" is "Coming soon") |
| **View** | Full-screen image viewer, closed with a button or a tap |
| **Share** | No sharing of document files. You can only share the truck details as text (§3.19) |
| **Versions** | None. A new upload replaces the old file |
| **Central manager** | **None.** `documents-manager.tsx` is an unrelated mock-up |

The Document Manager with **expiry alerts, renewal reminders, sharing and
uploads from both apps** that Trucksarthi markets today
(`trucksarthi_features.md` §3.6) was therefore built **after** this version. Our
`09` §6 already covers all of that and more.

---

## 8. Comparison with our specs

How to read the **Covered?** column:
- **Yes** — our spec has it.
- **Partly** — our spec has the idea but is missing the detail named here.
- **No** — missing; see the gap number in section 9.
- **Stricter** — Trucksarthi does it, and our spec does it differently on
  purpose. That is correct, not a gap.
- **n/a** — a Trucksarthi bug or a stub; nothing to add.

### 8.1 Trips and trip log

| # | Trucksarthi item | Covered? | Where in our spec / what to add |
|---|---|---|---|
| 1 | Trip date (default today) | Yes | `03` §4 Basics "Trip date"; `06` §7 `trips.date`. Lesson 5.9-1: it must be saved |
| 2 | One client per trip | Yes | `03` §4 Consignments (billing party); `12` §2 FTL quick form "one client" |
| 3 | Origin / destination from a places master | Yes | `03` §4 Route; `04` §3.2; `06` §3 `places` |
| 4 | Truck and driver pickers | Yes (more) | `03` §4 Truck and Crew (free trucks only, document and licence checks) |
| 5 | Trip cost (one freight amount) | Yes | `03` §4 Consignments (freight basis "fixed", freight amount); `12` §2 FTL |
| 6 | **Misc (₹)** added to the trip total and billed | Partly | The idea is in `03` §9 Income "Other charges billed", `06` §7 `consignments` "hamali/other charges" and `10` §5 charge line "other". **The quick form has no such field → Gap 1** |
| 7 | Trip notes (shown on lists and PDF) | Partly | `03` §4 Notes are only "Instructions to the crew". **No separate office note → Gap 2** |
| 8 | "+" quick add of client / place / truck / driver inside the trip form; new item selected automatically | No | Not in `03` §4 or `04` §3.2 → **Gap 3** |
| 9 | Required set: client, origin, destination, truck, driver, cost | Yes | `03` §4; `04` §3.2 |
| 10 | Edit a trip at any time, including after invoicing | Stricter | `03` §3 (Draft editable; crew entries locked at Closed); `10` §2 (finalised documents never change). **Locking commercial fields after invoicing is not stated outright → Gap 6** |
| 11 | Hard delete of a trip | Stricter | `06` §1 no hard deletes; `03` §3 Cancelled needs a reason |
| 12 | `invoiced_status` per trip | Yes | `12` §3.5 consignment status "Billed"; `09` §2.3 queues. **A billing-status filter on the trip list → Gap 4** |
| 13 | Trip list filters: driver, client, truck, date range, reset | Yes | `04` §2 Trips "filter: status, date, truck, driver, party, route" |
| 14 | Filter by **one place matching origin OR destination** | Partly | `04` §2 has "route" only → **Gap 4** |
| 15 | Default sort: trip date, newest first | No | Not stated → **Gap 4** |
| 16 | Summary bar "N trips found · ₹ total" for the filtered list | No | → **Gap 4** |
| 17 | **Trip Report PDF** of the filtered list, shared through the phone | Partly | `10` §6.3 "Trip sheet" is for one trip only; `04` §2 Reports has none → **Gap 5** |
| 18 | Trip card: date, total, route, client, truck, driver, cost, misc, notes, edit and delete icons | Yes | `04` §2 All trips; `05` §3.1 Trips |
| 19 | No trip status, km, advance, load details, LR | Yes (we go beyond) | `03` §3, §4, §5; `12` |

### 8.2 Home, navigation and general look

| # | Trucksarthi item | Covered? | Where / what to add |
|---|---|---|---|
| 20 | Home tiles "Revenue" and "Number of trips" | Partly | `05` §3.2 has month profit and today's spending; `04` §3.1 has month profit. **No revenue or trip-count tiles → Gap 7** |
| 21 | Large "Add Trip" button on Home | Yes | `05` §3.2 "New trip"; `04` §3.2 |
| 22 | Quick buttons Trucks / Drivers / Clients / Locations | Partly | `05` §3.1 owner app has Trucks and Crew only. **Clients and Places missing → Gaps 7, 9** |
| 23 | Recent Trips (last 3) + "View All" | No | → **Gap 7** |
| 24 | Bottom tabs Home / Add Trip / Trip Log, side menu with profile header, Main and Manager groups, Logout; bell icon on every header | Partly | `05` §2.1 has the crew-app bottom bar; `05` §3.1 lists owner-app screens but **no owner-app navigation layout → Gap 8** |
| 25 | Skeleton placeholders while loading; empty-list texts; reload when the screen is shown | Partly | `05` §1 rules and §2.2 splash; `05` §4 sync. **Skeletons and empty states not specified → Gap 10** |
| 26 | Light / Dark / System theme | Yes | `09` §8 Theme; `05` §1 rule 7 (night mode) |
| 27 | Bottom-sheet forms closed by dragging down | n/a | A UI detail; our apps are B4A (`05`) |
| 28 | WhatsApp-green colours | n/a | Branding is open (`08` §5 decision 3) |

### 8.3 Clients (parties) and the client ledger

| # | Trucksarthi item | Covered? | Where / what to add |
|---|---|---|---|
| 29 | Client name (req), contact number (req), contact person, email, office address | Yes | `06` §4 `parties`, `party_contacts`; `09` §2.1 Profile |
| 30 | Alternate contact number | Partly | `party_contacts` allows several contacts, but **one contact has no second mobile → Gap 13** |
| 31 | Smallest quick-add client = name + mobile | No | No minimum field set is defined → **Gap 3** |
| 32 | Call and WhatsApp buttons on the client page (chat opens with a greeting) | No | → **Gap 22** |
| 33 | Client page totals **Billed / Unbilled / Settled** | Partly | `09` §0.1 plain-language balance; `09` §2.4 unbilled revenue report; `09` §2.3 queues are for the whole company. **No three totals on the client page → Gap 20** |
| 34 | Client tabs Unbilled / Billed / Settled (payment history) | Partly | `04` §2 Party page (details, tax IDs, rates, trips, ledger, outstanding). **No billing tabs → Gap 20** |
| 35 | Pick unbilled trips → "Generate Invoice (n)" | Yes | `10` §5 Consolidated invoice ("Add unbilled LRs"); `09` §2.1 billing rule decides whether POD is needed |
| 36 | **Settle** button on an invoice row, pre-filled with the amount, today's date and the remark "Settlement for Invoice #…" | Partly | `10` §7 Payments and allocation; `09` §2.2 Receipt. **No one-tap Settle with pre-fill → Gap 21** |
| 37 | Add payment: date (default today), amount, **remarks required**; optional link to one invoice | Yes (more) | `09` §2.2 Receipt (mode, UTR, split across bills); `10` §6.2, §7. **Remarks required → Gap 21** |
| 38 | Edit a saved payment (amount, remarks, date) | Stricter | `10` §2 — after finalising: void or credit note only |
| 39 | Ledger summary (total debits, credits, outstanding) | Yes | `09` §0.1; `06` §11.2 `party_balances` |
| 40 | Client "VERIFIED PARTNER" label | n/a | A fixed label, not a feature. Real checks are in `09` §8 phase 2 |
| 41 | Delete a client | Stricter | `06` §1 (cancel, never delete) |

### 8.4 Drivers (people) and the driver ledger

| # | Trucksarthi item | Covered? | Where / what to add |
|---|---|---|---|
| 42 | Driver name (req), contact number (req) | Yes | `06` §6 `people`, `users` |
| 43 | Aadhaar / ID photo, licence photo, with full-screen view and replace | Yes | `09` §6.1 Crew documents; `06` §11.4 `documents`; `09` §6.2 Upload and Privacy (Aadhaar masked) |
| 44 | Drivers list "License Registered / Missing" | Partly | `09` §6.2 has document health **per truck only → Gap 19** |
| 45 | **Share driver details** (name and phone as text) | No | → **Gap 19** |
| 46 | Balance "Credit / Debit" in green or red | Yes | `09` §0.1 Plain-language balance; `05` §2.11 |
| 47 | Ledger list: remark, date, ± amount in colour | Yes | `09` §1.3 Person statement; `04` §3.7 |
| 48 | New entry: amount, remarks (req), **Expense (driver spent) / Payment (driver received)** | Partly | `09` §1.2 has Advance, Reimbursement, Cash returned and more. **There is no simple two-way cash entry outside a trip on the owner app → Gap 23** |
| 49 | The ledger model can name the other side: owner / vendor / client | Yes | `09` §1.2 "Cash collected for the company" (debits the driver, credits the client), fuel "who paid", `07` §1.1 automatic entries |
| 50 | **Payroll for a period (from–to)** with Pending → Paid and a history (API only) | Partly | `07` §2.6 and `04` §2 Payroll describe a **monthly** run in phase 2; `09` §1.2 Salary and payout. **No tables for a pay run or payslip, no period other than a month → Gaps 16, 24** |
| 51 | Old pay type **"per trip"** | Partly | `06` §6 `salary_structures` has a monthly salary plus a bata rule (per day / trip / km). **No "per trip" wage instead of a monthly salary → Gap 24** |
| 52 | Driver profile edit and delete ("Coming soon") | Yes | `04` §2 People; `06` §1 |

### 8.5 Trucks

| # | Trucksarthi item | Covered? | Where / what to add |
|---|---|---|---|
| 53 | Registration number (req, capitals) | Yes | `06` §5 `trucks.reg_no` |
| 54 | **Registered owner name (RC), required** | No (own trucks) | `06` §11.3 `supplier_vehicles` has the RC owner name for hired trucks only. `trucks` has ownership and owner party, but no RC owner name → **Gap 12** |
| 55 | Chassis, engine | Yes | `06` §5 |
| 56 | Make, model | Yes | `06` §5 |
| 57 | **Vehicle class** (shown as "HCV" if empty) | Partly | `06` §5 has `type` and `body type`, but no RC vehicle class → **Gap 12** |
| 58 | Fuel type, fuel norms (BS-VI) | Yes | `06` §5 fuel type, emission norm |
| 59 | Unladen weight (kg) | Yes | `06` §5 |
| 60 | **Registered RTO** | No | `06` §5 has registration state only → **Gap 12** |
| 61 | Container size ("20ft / 32ft") | Yes | `06` §5 deck length and rate-card label ("32 ft MXL") |
| 62 | Loading capacity (tons) | Yes | `06` §5 payload (MT) |
| 63 | **Registration date** and the **vehicle age** worked out from it | No | `06` §5 has `year` only → **Gap 12** |
| 64 | Validity dates: fitness, PUC, road tax, insurance, permit, national permit | Yes | `09` §6.1 Truck types (fitness, PUC, road tax, permit national / state + NP authorisation, insurance); `06` §11.4 `documents.expiry` |
| 65 | **One "Important Dates" card** with "Urgent" at 30 days or less | Partly | `09` §6.2 Expiry alerts (30 / 15 / 7 / 1), Compliance dashboard; `04` §3.6 Documents tab. **No single card of dates on the truck page, and no record with dates but no file → Gap 17** |
| 66 | Truck page: main card, dates, documents, last 5 trips | Yes | `04` §3.6 Truck page tabs; `05` §3.1 truck page |
| 67 | **Share truck details as text, choosing the fields** | No | `09` §6.2 Share covers document packs and `09` §3.2 a report-card PDF, not a quick text → **Gap 18** |
| 68 | Trucks list card: number, class, owner, capacity, size | Yes | `04` §2 All trucks |
| 69 | Hard delete of a truck | Stricter | `06` §1; status "sold / out of service" in `06` §5 |
| 70 | RC data probably filled by the backend (no screen to type it) | Yes | `09` §6.2 Vahan RC fetch (phase 2) |

### 8.6 Locations (places)

| # | Trucksarthi item | Covered? | Where / what to add |
|---|---|---|---|
| 71 | Location name ("alias", e.g. "Warehouse 1, Pune Main"), required | Yes | `06` §3 `places.name`, type "customer site" |
| 72 | **Full postal address, required** | No | `06` §3 `places` has no address column → **Gap 11** |
| 73 | Latitude and longitude (in the data, not set) | Yes | `06` §3 `places.lat/lng`; `01` §7 geocoding |
| 74 | Places list with edit and delete | Partly | `04` §2 Settings › Masters (places with short codes) on the web; **no Places screen in the owner app → Gap 9** |

### 8.7 Invoice

| # | Trucksarthi item | Covered? | Where / what to add |
|---|---|---|---|
| 75 | Invoice from several trips of one client | Yes | `10` §5 Consolidated invoice |
| 76 | Number made by the server | Yes | `10` §3 Numbering (gap-free, per financial year, Rule 46) |
| 77 | Due date | Yes | `09` §2.1 Credit control (credit days, from invoice or submission date) |
| 78 | Items: trip cost, misc, total per trip | Yes | `10` §5 LR annexure (freight, detention, other, total) |
| 79 | Columns Date, Route, **Truck, Driver** | Partly | `10` §5 annexure has vehicle and from–to, **but no Driver column → Gap 25** |
| 80 | "Total Trips" on the header; "Subtotal (N trips)" | No | → **Gap 25** |
| 81 | Flat 5% tax in the PDF only | Stricter | `10` §5 Tax and `11` (GTA RCM / FCM 5% / 18%, place of supply, declarations) |
| 82 | Invoice date = print date | Stricter (lesson) | `10` §2 Finalise freezes the date and a snapshot |
| 83 | Header with the app's brand | Stricter (lesson) | `10` §4.1 / §5 header from company settings; `10` §9 templates |
| 84 | PDF made on the phone, shared with the phone's share sheet | Yes | `10` §8 Sharing (WhatsApp, email, secure link); `10` §10 server PDF |
| 85 | PDF file name `Invoice-<number>.pdf` | No | Naming not specified → **Gap 26** |
| 86 | Statuses pending / paid / cancelled | Yes | `10` §2 (draft … paid, void) |
| 87 | Delete an invoice (API) | Stricter | `10` §2 void only |
| 88 | Bank details stored in the profile (not printed) | Yes | `10` §5 Bank and pay; `06` §9 `bank_accounts` |

### 8.8 Documents

| # | Trucksarthi item | Covered? | Where / what to add |
|---|---|---|---|
| 89 | Truck documents RC, Insurance, Permit, Fitness | Yes | `09` §6.1 |
| 90 | One file per type, replaced on upload | Stricter | `09` §6.2 Renewal makes a new version; `06` §11.4 `document_versions` |
| 91 | Expiry automatically +365 days | Stricter (lesson) | `09` §6.2 the document record has an expiry date |
| 92 | "MISSING" tile for each expected type | Partly | `09` §6.2 Compliance dashboard and document health. **No list of required types per truck or person that shows "missing" tiles → Gap 17** |
| 93 | Full-screen viewer | Yes | Implied by `09` §6.2; `05` §6.1 Truck documents offline |
| 94 | Gallery-only images | Stricter | `05` §1 rule 3 and `01` §5 (camera first; PDFs and many pages allowed in `09` §6.2) |
| 95 | No reminders | Yes (we go beyond) | `09` §6.2 Expiry alerts; `06` §11.4 `document_reminder_rules` |

### 8.9 Account, profile, settings, help

| # | Trucksarthi item | Covered? | Where / what to add |
|---|---|---|---|
| 96 | **Phone OTP sign-in (+91)** for the owner | No | `02` §5 has email or mobile + password for office users, activation code for crew, "SMS / WhatsApp OTP later" for crew only → **Gap 27** |
| 97 | Email + password sign-in and sign-up | Yes | `04` §5 Sign up and Log in; `01` §8 email check and password reset |
| 98 | Google sign-in (shown as coming soon) | No (optional) | → **Gap 27** (later, needs approval) |
| 99 | "Complete your profile" after the first login | Yes | `04` §5 Company setup wizard |
| 100 | Owner profile: name, email (read-only), phone, **date of birth**, **profile photo** | Partly | `06` §3 `users` (name, mobile, email). **No photo or date of birth → Gap 14** |
| 101 | Business: company name, GSTIN, office address | Yes | `06` §2 `companies`; `04` §2 Settings › Company; `06` §11.5 `party_tax_ids` / `tax_registrations` |
| 102 | Banking: bank, **account holder**, account number, IFSC | Partly | `10` §5 Bank and pay; `06` §9 `bank_accounts`. **Account holder name not listed → Gap 14** |
| 103 | Profile screen with Personal / Business / Banking tabs, edit switch, Save only after a change | No | No owner-app profile screen is specified → **Gap 28** |
| 104 | Settings: Plans & Pricing, Appearance, Biometric switch (only if supported), App Language, Notification Settings, "Use on desktop", Help, Logout | Partly | `05` §2.1 crew settings (language, PIN, fingerprint, night mode); `04` §2 Settings › Subscription; `09` §8 Theme, QR login. **No owner-app settings list, no per-user notification settings, no "Use on desktop" entry → Gap 29** |
| 105 | Help Center: WhatsApp, Call and Email support, "24×7" text | Partly | `05` §2.1 crew "Help"; `04` §4 Branding (support contacts). **Help screen content not specified → Gap 30** |
| 106 | Reminders screen with high / medium / low priority | Partly | `06` §10 `notifications` (no priority); `05` §3.1 Alerts → **Gaps 15, 31** |
| 107 | Account delete (API only) | Yes | `09` §8 Account deletion (72-hour countdown) |
| 108 | Logout from the side menu and from Settings | Yes | `01` §4.2 `/auth/logout` |
| 109 | The owner's account is the tenant (no company or roles) | Yes (we go beyond) | `02` §1–3; `06` §2, §3 |

---

## 9. Gap list (spec-ready)

Each item names the spec file and section to change. All are **additions**. None
reverses a decision. Items 1–31 are what this early Trucksarthi version has and
our specs do not yet name.

### Trips and trip list — `03-trip-lifecycle.md`, `12-ftl-ptl-operations.md`, `04-web-app-ux.md`

1. **Other charges on the quick trip form.**
   - **Where:** `03` §4 (Consignments row) and `12` §2 (FTL quick form).
   - **Field:** "Other charges (₹)", optional, default 0, number keypad.
   - **Billing:** it is billed to the client on top of freight and counts as
     income, not cost.
   - **Display:** it shows as its own column on the trip list, the trip report
     and the invoice LR annexure. It saves to `consignments` "other charges"
     (`06` §7).
   - **Label:** say clearly on screen that this is billed to the client, not
     spent.

2. **Office note on a trip.**
   - **Where:** `03` §4 Notes and `06` §7 `trips`.
   - **Field:** `office_note` (text, optional), for the office only. Crew never
     see it.
   - **Use:** shown on the trip card and printed on the trip report. It is
     separate from the existing "Instructions to the crew".

3. **Add a new item from inside the trip form.**
   - **Where:** `03` §4 and `04` §3.2 (web), `05` §3.1 (owner app).
   - **Button:** a "+ Add new" button next to Client, From, To (and stops),
     Truck and Driver. It opens a short form; the new record is selected at
     once.
   - **Places:** a new place fills **From** if it is empty, otherwise **To**.
   - **Smallest forms:**
     - Client: name + mobile.
     - Driver: name + mobile.
     - Truck: registration no. + RC owner name.
     - Place: name + full address.
   - **Rest later:** the other fields are filled on the master page.

4. **Trip list tools.**
   - **Where:** `04` §2 Trips › All trips.
   - **Place filter:** one place that matches the **origin, the destination or
     any stop**.
   - **Billing-status filter:** Not invoiced / Invoiced / Paid.
   - **Sort and reset:** the default sort is **trip date, newest first**, and
     there is a **Reset filters** button.
   - **Totals bar:** "**N trips · ₹ freight + other charges**". It updates as
     filters change and appears on the web and in the owner app.

5. **Trip report of the filtered list.**
   - **Where:** `10` §1 Statements, `10` §12 and `04` §2 Reports.
   - **Content:** "Trip report" as PDF and Excel, with the company header, the
     time it was made, a date-range line ("From … / Until … / … → …") and the
     filters used.
   - **One block or row per trip:** date, trip no., route, truck, driver,
     client, freight, other charges, total and office note.
   - **Sharing:** WhatsApp and email, from web and owner app.

6. **Lock what has been invoiced.**
   - **Where:** `03` §3 and `10` §2.
   - **Locked fields:** once a consignment (LR) or trip is on a **finalised**
     invoice, its client, freight, other charges, date and route cannot be
     edited, and the trip cannot be cancelled.
   - **How to change it:** only through a credit note, a debit note, a
     supplementary invoice, or by voiding the invoice first.
   - **On screen:** a short line explains why the fields are locked.

### Owner app and dashboard — `05-mobile-app-ux.md`, `04-web-app-ux.md`

7. **Owner dashboard additions.**
   - **Where:** `05` §3.2 and `04` §3.1.
   - **Tiles:** "**Revenue this month**" (freight + other charges of trips dated
     in the current month) and "**Trips this month**".
   - **Recent trips card:** the last 3–5 trips with route, truck no., driver
     name, amount and date, plus "View all".
   - **Quick buttons:** Trucks, Drivers, Clients, Places.

8. **Owner app navigation.**
   - **Where:** `05` §3.1.
   - **Bottom tabs:** Home · New trip · Trips.
   - **Side drawer:**
     - top: photo, name, email → My profile;
     - Main: Trips, Ledgers, Documents, Settings;
     - Masters: Trucks, Drivers, Clients, Places;
     - bottom: Logout, and the version line (as the guideline requires).
   - **Header:** a **bell → Reminders** on every main screen.

9. **Masters in the owner app.**
   - **Where:** `05` §3.1 and §6.2.
   - **Screens:** **Clients** and **Places**, as well as Trucks and Crew.
   - **Each list:** card list, add, edit, deactivate (no delete), and tap to
     open the page.

10. **Loading and empty states.**
    - **Where:** `05` §1 (design rules) and `04` §1.
    - **Skeletons:** grey placeholder cards shaped like the real cards while
      lists and profile pages load.
    - **Empty lists:** a short text for each ("No trucks yet — add your first
      truck") with an add button.
    - **Fresh data:** lists refresh when the screen opens again, and
      pull-to-refresh is on all owner-app lists. (Trucksarthi has no
      pull-to-refresh; we add it.)

### Data model — `06-data-model.md`

11. **Address for places.**
    - **Where:** `06` §3 `places`.
    - **New field:** `address` (full postal address, several lines). Required
      for type "customer site", optional for others.
    - **Names:** allow free names ("Warehouse 1, Pune Main") as the place name.
    - **Map point:** latitude and longitude stay optional, filled from the
      address search (`01` §7).

12. **New truck fields.**
    - **Where:** `06` §5 `trucks`, `04` §3.6 and `09` §6.1.
    - **New fields:**
      - `rc_owner_name` — the registered owner as on the RC; required;
      - `vehicle_class` — the RC class, e.g. "Goods Carrier" or HGV/HCV;
      - `registered_rto` — the RTO office or code;
      - `registration_date` — a full date; `year` is then worked out from it.
    - **Vehicle age:** show "X y Y m" from the registration date on the truck
      page and in the share text.

13. **Second mobile for a contact.**
    - **Where:** `06` §4 `party_contacts`.
    - **Change:** add `alt_mobile`, so one contact (for example the dispatch
      person) can have two numbers, as Trucksarthi's "alternate contact
      number" allows.

14. **Profile and bank fields.**
    - **Where:** `06` §3 `users` and `06` §9 `bank_accounts`.
    - **On `users`:** add `photo` (profile picture) and `date_of_birth`
      (optional).
    - **On `bank_accounts`:** add `account_holder_name`, printed on invoices
      with the bank name, account number and IFSC.

15. **Priority for notifications.**
    - **Where:** `06` §10 `notifications`.
    - **Change:** add `priority` (high / medium / low). This drives the icon and
      colour in Reminders, and the sort order: high first, then newest.

16. **Pay-run tables.**
    - **Where:** `06` §6.
    - **New table `payroll_runs`:** period_start, period_end, type (monthly /
      fortnightly / weekly / trip-based / custom), status (draft / approved /
      paid), totals, created_by.
    - **New table `payslips` (or `payroll_lines`):** run, person, gross,
      deductions, net, status (pending / paid), paid voucher, paid_at.
    - **Status:** the spec currently has salary structures but no table for a
      pay run or a payslip.

### Ledgers and documents — `09-ledgers-and-documents.md`

17. **Important dates card and missing documents.**
    - **Where:** `09` §6.2 and `04` §3.6.
    - **Important dates card:** one card on the truck page listing:
      - registration date and vehicle age;
      - fitness, PUC, road tax, insurance, permit, national permit — each with
        its valid-upto date;
      - an **"Urgent" badge** at 30 days or less (red when expired).
    - **Quick date edit:** the dates can be changed from the card.
    - **Dates without a file:** a document record may hold dates **with no
      file yet** ("File missing" tile).
    - **Required document types:** a per-company list of **required types per
      truck type and per crew role**. Each required type with no record shows a
      "**Missing**" tile.

18. **Share truck details as text.**
    - **Where:** `09` §6.2 Share and `04` §3.6.
    - **Choose fields with chips:**
      - on by default: number, make/model, RC owner;
      - off by default: chassis, engine, emission norm, unladen weight, vehicle
        age, fitness date, insurance date, permit date, PUC date.
    - **Message:** a plain text through WhatsApp or the share sheet, ending with
      the company name. (Our suggestion: show only the last 5 digits of chassis
      and engine unless the user turns that off.)

19. **People list: share contact and document health.**
    - **Where:** `04` §2 People, `05` §3.1 and `09` §6.2.
    - **Share contact:** a "Share contact" action (name + mobile as text) on
      the people list and the person page.
    - **Document chip:** each person card shows "Licence on file / Licence
      missing / Licence expires in N days".
    - **Health per person:** document health (green / amber / red) for each
      **person**, not only each truck.

20. **Client page billing view.**
    - **Where:** `09` §2.4 and `04` §2 Party page.
    - **Header totals:**
      - **Unbilled** — value of delivered trips or LRs not yet invoiced;
      - **Billed, unpaid** — open invoice balances;
      - **Received** — payments in the chosen period.
    - **Tabs:**
      - **Unbilled:** tap to select (with select-all) → "**Create invoice
        (n)**" (`10` §5);
      - **Invoices:** no., date, due date, status chip, amount, Share PDF,
        **Settle**;
      - **Payments:** date, mode, remark, amount, invoices it was applied to.

21. **One-tap Settle and a quick payment.**
    - **Where:** `09` §2.2 and `10` §7.
    - **Settle:** on each open invoice row. It opens the receipt form with:
      - amount = balance due;
      - date = today (can be changed);
      - narration = "Settlement for Invoice <no>";
      - allocation to that invoice.
    - **Add payment:** a general button on the client page with date (default
      today), amount, mode and a **narration that must be filled in**. On save,
      the user can allocate it oldest-first or by hand.

22. **Call and WhatsApp buttons.**
    - **Where:** `04` §2 Party and Person pages, and `05` §3.1.
    - **Call:** opens the phone dialler.
    - **WhatsApp:** opens a chat with a ready greeting ("Hello <name>"), using
      the country code of the company's country (+91 / +971).
    - **Shown on:** client, supplier, vendor, broker and crew pages.

23. **Quick cash entry for a driver in the owner app.**
    - **Where:** `09` §1.2 and `05` §6.2.
    - **Form:** a short "Cash entry" form on the person page with:
      - a choice: **"Driver spent for company"** (company owes the driver) or
        **"Driver received from company"** (driver owes the company);
      - amount, remark (must be filled in), date (default today);
      - optional trip.
    - **Booking:** it posts as a reimbursement or as an advance in the right
      bucket, with a ± colour in the statement.

24. **Driver pay runs.**
    - **Where:** `09` §1.2 Salary and `07` §2.6.
    - **Pay basis per person:** monthly salary / **per trip** / per km (stored
      in `salary_structures`).
    - **Pay run:** for **any date range**. Shows pay for the period, Pending →
      **Mark paid** (makes the payment voucher), and a history list per
      driver.
    - **Phase:** we suggest a **basic driver pay run in phase 1**, because
      Trucksarthi had it early. Full Labour-Code payroll stays in phase 2
      (`08` §1).

### Invoicing — `10-invoicing-lr-templates.md`

25. **Invoice display options.**
    - **Where:** `10` §5 and §9.3 ("What prints" switches).
    - **Driver column:** an optional "**Driver**" column in the consolidated
      invoice's LR annexure. Off by default.
    - **Count:** print "**Total trips / LRs: N**" in the invoice summary.
    - **Subtotal label:** "Subtotal (N LRs)" on the subtotal line.

26. **PDF file names.**
    - **Where:** `10` §8 and §10.
    - **Shared or downloaded PDFs:** `<DocType>-<Number>.pdf` (for example
      `Invoice-COK-26-27-000123.pdf`, `LR-000123.pdf`).
    - **Reports:** `<Report>-<from>-<to>.pdf`.
    - **Characters:** any `/` in a number becomes `-` in the file name.

### Account, settings and help — `02-roles-permissions.md`, `05`, `04`, `01`

27. **Mobile OTP sign-in for owners.**
    - **Where:** `02` §5, `04` §5 and `01` §8.
    - **Sign-up and login by mobile OTP:**
      - country code fixed by the regional site (+91 / +971);
      - 10-digit check for India;
      - 6-digit code, a 30-second resend timer, and "Change number".
    - **After sign-in:** the setup wizard runs if the company profile is
      incomplete.
    - **Approval needed:** an SMS or WhatsApp OTP provider (DLT registration in
      India).
    - **Google sign-in:** optional, later, also needs approval.

28. **Owner-app profile screen.**
    - **Where:** `05` §3.1.
    - **Tabs:**
      - **Personal:** name, verified email (read-only), mobile, photo (camera
        or gallery, square crop);
      - **Business:** company name, GSTIN / TRN, office address — owner only;
      - **Banking:** bank, account holder, account no., IFSC, UPI — owner only.
    - **Behaviour:** an Edit / Discard switch, and Save appears only after a
      change. Business and banking changes are logged (`07` §1 Audit trail).

29. **Owner-app settings list.**
    - **Where:** `05` §3.1.
    - **Items:** Plans & billing, Appearance (System / Light / Dark), Biometric
      login, App language, **My notification settings**, **Use on desktop**,
      Help centre, About, Logout.
    - **My notification settings:** which alerts the user wants and on which
      channels.
    - **Use on desktop:** shows the web address and opens the QR scanner of
      `09` §8.
    - **Biometric login:** shown only if the phone supports it and has it set
      up. It must ask for a biometric check to turn on, and it **must then
      really be asked at app open** (lesson 5.9-10).

30. **Help centre screen.**
    - **Where:** `05` §2.1 More › Help and §3.1.
    - **Content:** an availability line, **WhatsApp support** (opens a chat),
      **Call support** (dialler) and **Email support**, plus FAQ and user-guide
      links.
    - **Contacts:** taken from super-admin **Branding** (`04` §4), never fixed
      in the app.

31. **Reminders screen.**
    - **Where:** `05` §3.1 Alerts and §2.1.
    - **List:** icon and colour by priority (gap 15), title, message, time,
      read or unread; tap opens the related record.
    - **Empty state:** "No reminders".
    - **Mark all read** button.

---

## 10. What the current Trucksarthi has beyond this code

These modules are **not in this January 2026 code**. They are known only from
their website and store listings (`trucksarthi_features.md`). Our specs already
cover each one (`09` §7 parity checklist):

| Module | Our spec |
|---|---|
| Supplier and vendor ledgers | `09` §4, §5 |
| Bilty / LR and a proper GST invoice | `10` §4, §5 |
| Document Manager with alerts and sharing | `09` §6 |
| Driver app (trips, expenses, POD, offline, location sharing, several businesses) | `05` §2, §6.1 |
| Truck ledger, fuel and expenses (running vs maintenance) | `09` §3; `03` §5 |
| Trip status, advances, start / end km | `03` §3–5 |
| PDFs: client ledger, driver cash register, fuel bill, itemised bilties | `09` §1.3, §2.4, §3.2; `10` §4.4 |
| QR desktop login | `09` §8 |
| Hindi | `10` §8; `05` §1 |
| KYC | `09` §8 |
| Plans | Pricing decision pending |

Field-level detail for those modules is still unknown. It would need the newer
code or screenshots (`trucksarthi_features.md` §1.4).
