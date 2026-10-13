# Trip Lifecycle, Road Events, Approvals and Settlement

This is the heart of the product. Everything else (ledgers, truck history,
profit reports, accounts) is built from the records described here.

Simple example used through this file:

> Sharma Roadlines sends truck **KL-07-AB-1234** from **Kochi** to **Mumbai**
> with cement for **ACC Ltd**. Driver **Ravi**, co-driver **Suresh**, helper
> **Anil**. Freight is ₹85,000. Ravi gets ₹20,000 cash advance.

---

## 1. What a "trip" is

A **trip** is one journey of one truck, from the moment the crew starts it to
the moment they end it.

```
Trip
 ├── Crew            (driver, co-driver, helper — each with a role on this trip)
 ├── Stops           (ordered list: loading, unloading, planned fuel, border...)
 ├── Consignments    (each load: party, LR number, material, weight, freight)
 ├── Road events     (everything the crew records: fuel, toll, police, driver change...)
 ├── Office entries  (income/expenses the office adds: commission, later bills...)
 ├── GPS track       (location points from the crew phones)
 └── Settlement      (cash account of each crew member for this trip)
```

- One trip can carry **more than one consignment**. Example: cement Kochi →
  Mumbai, then a return load of steel Mumbai → Kochi on the same trip, if the
  owner wants one round-trip settlement.
- Or the owner can make the return a **separate trip** and link the two as
  "return of".
- An **empty leg** (no load) is still a trip, so its fuel and tolls are not lost.

---

## 2. Trip number

Format: `YYMMDD-SHP-FRM-TO-NN` → `261013-ACC-COK-BOM-01`

See `docs/00-decisions.md` (P1). It is fixed once the trip is assigned to a crew.

---

## 3. Trip status flow

```
 ┌────────┐  send to crew  ┌──────────┐  all crew accept  ┌──────────┐
 │ DRAFT  │ ─────────────► │ ASSIGNED │ ────────────────► │ ACCEPTED │
 └────────┘                └──────────┘                   └──────────┘
     │ cancel                 │ crew rejects → back to manager   │ driver taps "Start trip"
     ▼                        ▼                                  ▼
 CANCELLED               (manager re-assigns)              ┌──────────┐
                                                           │ RUNNING  │◄── road events,
                                                           └──────────┘    GPS, driver changes
                                                                 │ driver taps "End trip"
                                                                 ▼
                                                           ┌──────────┐
                                                           │  ENDED   │◄── manager checks entries,
                                                           └──────────┘    2nd approvals finish
                                                                 │ manager settles each crew member
                                                                 ▼
                                                           ┌──────────┐
                                                           │ SETTLED  │
                                                           └──────────┘
                                                                 │ manager closes
                                                                 ▼
                                                           ┌──────────┐
                                                           │  CLOSED  │◄── crew entries locked,
                                                           └──────────┘    office entries still allowed
```

| Status | Who moves it | What is allowed |
|---|---|---|
| **Draft** | Owner / manager creates | Everything can be edited. Number not yet final. |
| **Assigned** | Manager taps "Send to crew" | Trip number fixed. Crew get a notification. Manager can still change crew or route. |
| **Accepted** | Each crew member taps Accept (or Reject with reason) | If anyone rejects, the manager gets an alert and re-assigns. |
| **Running** | Driver taps "Start trip" (start odometer, fuel, photos) | GPS tracking on. All road events allowed. |
| **Ended** | Driver taps "End trip" (end odometer, fuel, POD) | Crew can still add missed entries (marked "late entry") and give 2nd approvals. Manager verifies entries. |
| **Settled** | Manager settles cash with each crew member | Blocked while any entry is "Waiting for 2nd approval" or "Query open". |
| **Closed** | Manager or owner closes | Crew entries are **locked**. Office entries (income and expenses) can still be added or edited. Profit is final for crew-side costs. |
| **Cancelled** | Owner / manager, before Running | Reason required. Kept for history. |

**Breakdown or accident mid-trip:** the trip is not cancelled. The driver records
an Incident event. The manager can swap the truck or crew (a "transfer" event
on the trip), or end the trip early with the flag "Ended early".

**Reopening a closed trip** (proposal): only the owner, with a written reason.
It is saved in the audit log.

---

## 4. Creating a trip (owner / manager)

| Section | Fields |
|---|---|
| Basics | Trip date, trip type (loaded / empty / round trip), linked trip (return of), branch (optional) |
| Truck | Truck and trailer (if any). Only free trucks are listed. An **expired** document (insurance, fitness, permit, PUC, tracker; UAE registration/permits) **blocks dispatch** unless a manager overrides it with a written reason (logged). Documents expiring within the reminder window show a warning. |
| Crew | Driver (required), co-driver (optional), helper(s) (optional). Shows each person's licence expiry, current status, last trip end. An expired licence blocks dispatch the same way. |
| Route | From, to, stops in order (loading/unloading/other); planned km (from map or typed); planned route on map (optional) |
| Consignments | Billing party, consignor (shipper), consignee, broker and commission (optional), material, packages, quantity and unit (MT / kg / bags / boxes), declared weight, freight basis (fixed / per tonne / per km / per package), rate, freight amount, advance from party, **LR payment type (Paid / To-Pay / To-Be-Billed)**, **GST payable by** (consignor / consignee / transporter), LR number (auto or typed from a printed LR book), e-way bill no. and expiry (India), consignor invoice no. and value, hazardous / over-dimension flags, seal or container no. **UAE:** transport type (domestic / export / import / UAE leg of international) and "we are main carrier / subcontractor" (decides 0% or 5% VAT). |
| Detention | Free waiting hours and rate per day; detention is calculated automatically from the reached/unloaded times recorded on the POD |
| Cash | Advance given to each crew member (cash / UPI / bank), fuel card / FASTag assigned |
| Plan | Planned start time, expected delivery time, budget (fuel, tolls, bata) for comparison later |
| Notes | Instructions to the crew (text or voice) |

The system suggests values from the last trip on the same route (same party,
from, to): planned km, toll budget, fuel budget, freight rate.

---

## 5. Road events (crew app)

### 5.1 Fields every event has (filled automatically)

| Field | How it is filled | Can user change it? |
|---|---|---|
| Event ID | Made by the phone (UUID) so the same entry is never saved twice | No |
| Trip | Current running trip | No |
| Entered by | Logged-in crew member | No |
| Event time | Phone clock at the moment of entry, **kept even when offline** | No (a "late entry" can set an earlier time, but it is marked as late) |
| Clock check | Phone uptime counter + GPS time, so the server can detect a changed phone clock | No |
| GPS | Latitude, longitude, accuracy, "mock location" flag | No |
| Place name | Filled by server later from GPS | No |
| Device | Phone ID and app version | No |
| Sync status | Saved on phone → Sent → Received by server | No |

### 5.2 Event types and their fields

| Event | Fields the crew fill | Photos | Who must approve |
|---|---|---|---|
| **Start trip** | Odometer, fuel level (litres or gauge 1/4..full), checklist (tyres, lights, documents, load secured) | Odometer, fuel gauge | — |
| **Fuel** | Litres, rate, amount (any two → third is calculated), full tank? (yes/no), odometer, payment mode (cash / UPI / company fuel card / credit with pump / paid by party), pump name (suggested from GPS) | Odometer, pump meter, receipt | Manager verifies |
| **Toll** | Amount, toll plaza (suggested from GPS), payment mode (FASTag / Salik / Darb / cash) | Receipt (cash only) | Manager verifies |
| **Weighbridge** | Gross weight, tare weight, net weight, charge, slip number | Weighbridge slip | Manager verifies |
| **Official fine / fee (with receipt)** | Authority (police / RTO / checkpost / municipality / other), challan or receipt number, amount, reason | Challan / receipt | Manager verifies |
| **Payment without receipt** | Authority type, amount, reason, place | Optional (photo of location) | **2nd driver** (if rule is ON) → then manager verifies |
| **Loading / unloading charges** | Amount, labour count (optional), paid to | Receipt if any | Manager verifies |
| **Parking / entry fee** | Amount | Receipt if any | Manager verifies |
| **Repair / puncture** | What was repaired, amount, workshop name, odometer | Bill, damaged part | Manager verifies; also opens a **truck complaint** |
| **Food / other** | Category, amount, note | Receipt if any | Manager verifies (owner can turn food off if covered by bata) |
| **Money received** | From (office / party / other), amount, mode, reference | Receipt / UPI screenshot | Manager confirms |
| **Driver change** | Odometer (time and GPS automatic) | Optional (owner can require) | Shown to other driver |
| **Rest start / rest end** | — | — | — |
| **Reached / left a stop** | Stop (loading/unloading), odometer | — | — |
| **Loaded** | Quantity / weight, LR number, seal number | LR, loaded truck | — |
| **Unloaded / delivered** | Quantity delivered, shortage / damage note, receiver name | POD (signed LR), unloaded truck | Manager verifies |
| **Incident** | Type (breakdown / accident / theft / police case / other), description | Photos | Manager alerted immediately |
| **Note** | Text or voice note | Optional | — |
| **End trip** | Odometer, fuel level | Odometer, fuel gauge | — |

**Photos** are always taken with the in-app camera (no gallery), shrunk to a
small size on the phone before upload, and stamped with time and GPS.

**Fuel extras:** AdBlue litres (optional). **UAE:** supplier TRN and VAT amount,
so input VAT can be recovered.

### 5.2a Automatic checks (flags for the manager, never a block on saving)

Saving on the phone is never blocked, because a driver with no signal must
always be able to record. Instead the server flags entries in the approvals
inbox:

| Check | Flag when |
|---|---|
| Odometer order | Lower than the previous reading for this truck |
| Fuel mileage | km/l since the last full tank is outside the truck's normal range |
| Fuel place | Entry is more than ~300 m from the truck's GPS track, or the truck did not stop there |
| Tank size | Litres more than the tank capacity |
| Same photo twice | Photo fingerprint matches an earlier photo |
| Fake GPS | Phone reported a mock location, or impossible speed (> 150 km/h) between points |
| Clock changed | Phone time differs from the rebuilt "trusted time" by more than 5 minutes |
| Late entry | Entered long after the event time it claims |
| Cash limit (India) | Cash to one payee in a day above ₹35,000 (goods-carriage limit) |

### 5.3 Editing and deleting entries

- Nothing is ever deleted. A crew member can **cancel** their own entry with a
  reason; it stays visible as "Cancelled".
- A crew member can **edit** their own entry until the manager verifies it.
  The old values are kept and shown as history.
- After the trip is **Closed**, crew entries are locked for everyone except an
  owner-approved reopen.

---

## 6. Approvals

### 6.1 Normal expense

```
Crew enters ──► "Waiting for check" ──► Manager: Approve ──► Approved
                                    └─► Manager: Query  ──► crew answers ──► back to manager
                                    └─► Manager: Reject ──► Rejected (not counted, reason shown)
```

The owner can set auto-approve rules, for example "auto-approve tolls under
₹500 that have a matching FASTag deduction".

### 6.2 Payment without receipt (dual approval)

Applies **only** to unofficial payments made without a proper receipt
(decision D5). Official fines and fees with a receipt follow the normal flow.

```
Driver A enters ──► "Waiting for 2nd approval"
                        │
                        ├─ Driver B approves (any time before settlement) ──► "Waiting for check" ──► manager
                        └─ Driver B rejects (reason required)            ──► "Disputed" ──► manager decides
```

- If the rule is **OFF** (owner setting), it follows the normal flow.
- If the trip has **only one driver**, the manager gives the second approval
  (default A4).
- The trip cannot be **settled** while any such entry is still waiting.
- Both drivers' phones show a red counter: "1 payment waiting for your approval".

---

## 7. Driver change and driving hours

Example: Ravi drives from Kochi. At 2:10 am near Mangaluru, Suresh takes the
seat. Suresh opens the app and taps **"I am driving now"**, then types the
odometer (or takes a photo). Ravi's driving time stops; Suresh's starts.

- The app asks **"Who is driving now?"** after every stop longer than 15
  minutes, so changes are not forgotten.
- A driver change can only be confirmed while the truck is **stopped** (GPS
  speed near zero). If the truck is moving, the app says "Stop the truck
  first". The same rule is used by leading US logging apps for co-driver swaps.
- The incoming driver confirms with **their own PIN**, even on a shared phone.
  If both drivers have phones, the server checks the two phones were within
  about 200 m of each other at that time.
- The server combines the driver-change log with the GPS track (moving /
  stopped) to calculate:
  - driving time per driver, per trip, per day, per week
  - continuous driving time without a break
  - rest time
- Alerts go to the manager (and the driver) when limits are crossed. The
  limits come from the country pack (India / UAE law) and can be made stricter
  by the owner.

---

## 8. Settlement (hisaab)

Each crew member has their own small cash account for the trip, so
co-drivers and helpers who spend from their own pocket are paid back correctly.

Example for Ravi:

| | ₹ |
|---|---|
| Advance given at start | 20,000 |
| Top-up by UPI on day 2 | 5,000 |
| Cash collected from party (freight balance) | 10,000 |
| **Total money with Ravi** | **35,000** |
| Fuel paid in cash | 12,400 |
| Cash tolls | 1,850 |
| Loading charges | 1,200 |
| Official fine (with challan) | 500 |
| Payment without receipt (2nd approved by Suresh) | 300 |
| **Total approved spending** | **16,250** |
| **Ravi must return** | **18,750** |

Bata (daily allowance) is **not** mixed into this cash account. It is posted to
each person's **personal ledger** (like salary), using the company rule: per day,
per trip, per km, or a fixed table by route.

At settlement, the manager:
1. Checks that there are no entries waiting for 2nd approval or with an open query.
2. Sees the calculated balance for each crew member.
3. Records how it was settled (cash returned / paid to crew / carried to next
   trip / adjusted against salary).
4. Each crew member sees the settlement in their app.

---

## 9. Trip profit

| Income | Direct costs | Shared costs (optional, owner setting) |
|---|---|---|
| Freight (all consignments) | Fuel used (bought + tank level change) | Driver/helper salary share (days on trip) |
| Detention / halting charges | Tolls | Truck EMI, insurance, road tax share (per day) |
| Loading/unloading recovered from party | Weighbridge | Tyre wear (per km) |
| Other charges billed | Fines and fees, payments without receipt | Maintenance reserve (per km) |
| Minus: shortage / damage claims | Loading/unloading, parking | Office overheads |
| | Repairs on the trip | |
| | Bata | |
| | Commission / brokerage | |
| | Office-side expenses added later | |

The report shows:
- **Trip profit (direct)** = income − direct costs
- **Trip profit (full)** = trip profit (direct) − shared costs
- Per km, per tonne, per day figures, and comparison with the trip budget and
  with past trips on the same route.

---

## 10. GPS tracking

- While a crew member is **on duty** (a trip is running or they tapped "Start
  duty"), the phone sends its location to the server in small batches.
- With no network, points are stored on the phone and sent later.
- The owner sees a live map and can replay each trip's route.
- Tracking stops when duty ends. The crew app shows a clear "Tracking ON" badge.
  This openness matters for driver trust and for privacy law.
