# Full Load (FTL), Part Load (PTL / LTL) and Market-Truck Hiring

> **See also `13-parity-additions.md`** — additions from the Trucksarthi code study (D17) that extend this file; where they differ, that file wins.

Decision **D16** (founder, 14 Oct 2026): like Trucksarthi, the product must
handle **both Part Load and Full Load** operations — **several consignments
inside one truck journey** as well as **single bulk trips** — and **market-truck
hiring with broker commissions and supplier settlements**.

This file adds the load-type design on top of `03-trip-lifecycle.md` (trip
flow), `09-ledgers-and-documents.md` §4 (Supplier Ledger) and
`10-invoicing-lr-templates.md` (LR, hire challan). Items marked
**[Practice]** come from transport practice and should be confirmed in
transporter interviews.

---

## 1. The words

| Term | Meaning | Example |
|---|---|---|
| **FTL — Full Truck Load** | The whole truck is booked by one client for one load (one or a few LRs), straight from origin to destination | 25 MT cement, ACC Kochi → Bhiwandi |
| **PTL / LTL — Part / Less-than-Truck Load** | Several clients' smaller consignments share one truck; each has its own LR, pickup and drop point and freight | 20 bags rice for A, 12 boxes tiles for B, 3 drums oil for C on one truck Kochi → Coimbatore → Salem |
| **Mixed load** | A main FTL load plus part loads to fill spare space (common on return trips) | Steel FTL Mumbai → Kochi, plus 2 part loads picked up at Pune |
| **Consignment** | One booking = one LR, with its own consignor, consignee, goods, freight and POD | LR 000123 |
| **Truck journey (trip)** | One run of one truck, with its stops, crew and costs | Trip 261013-ACC-COK-BOM-01 |
| **Manifest / loading challan / transit memo** | List of LRs loaded on a truck for a leg (PTL network) | Manifest COK-CBE-0045 with 14 LRs |
| **Market truck** | A truck hired from a supplier (owner or broker) for a trip or a leg | Supplier Ramesh's truck TN-38-X-1234 |

---

## 2. What the trip setup screen offers

When creating a trip, the first choice is the **load type**:

```
Load type:  (•) Full load (FTL)   ( ) Part load (PTL)   ( ) Mixed
Truck:      (•) Our truck [KL-07-AB-1234 ▾]   ( ) Hired truck [Supplier ▾] [Vehicle ▾]
```

| Load type | Screen behaviour |
|---|---|
| **FTL — single bulk trip** | Quick form: one client, one route, one consignment (add more LRs only if the client needs separate LRs per invoice / e-way bill); freight per trip / tonne / km |
| **PTL** | Consignment list + stop list; each consignment chooses its **pickup stop** and **drop stop**; freight per kg / package / article / tonne with a **minimum charge**; capacity bar shows how full the truck is |
| **Mixed** | Main FTL consignment + extra part-load consignments; same stop logic as PTL |

---

## 3. Several consignments in one truck journey (phase 1 — Trucksarthi parity)

### 3.1 Model

```
Trip (truck journey)
 ├── Stops (ordered):  1 Kochi ─► 2 Thrissur ─► 3 Coimbatore ─► 4 Salem
 └── Consignments (LRs), each with pickup stop + drop stop:
       LR 1043  rice 20 bags    pick 1  →  drop 3
       LR 1044  tiles 12 boxes  pick 1  →  drop 4
       LR 1045  oil 3 drums     pick 2  →  drop 4
```

- Each **stop** shows what to **load** and what to **unload** there.
- Each **consignment** has its own LR, freight, payment type (Paid / To-Pay /
  To-Be-Billed), e-way bill(s), status and **POD**.
- A consignment can be **delivered in parts** at more than one drop (split
  delivery), each part with its own POD line.
- Consignments can be **added to a running trip** (a part load picked up on the
  way), with office approval.

### 3.2 Capacity check

- The truck's **payload (MT)** and **body volume** (from the truck profile) are
  compared with the consignments on board **between each pair of stops**.
- A bar shows load factor (e.g. "Kochi → Thrissur: 7.2 / 9 MT, 80%").
- Over the payload or the legal axle limit (India / UAE weight rules) → warning
  before dispatch; weighbridge entries are checked against declared weights.

### 3.3 Crew app: stop-wise checklist

```
┌──────────────────────────────┐
│ 📍 Stop 1 of 4 · Kochi       │
│ LOAD here                    │
│ ☐ LR 1043 · Rice · 20 bags   │
│ ☐ LR 1044 · Tiles · 12 boxes │
│ [ 📷 Scan LR QR ]            │
│ Packages counted: [ 32 ]     │
│        [ ✓ LOADED ]          │
├──────────────────────────────┤
│ Next: Thrissur · load LR 1045│
└──────────────────────────────┘
```

- At loading: tick or **scan the LR QR/barcode**, count packages; mismatch →
  note + photo.
- At each drop: unload, count, **e-POD per consignment** (receiver, OTP,
  signature, stamp photo, shortage/damage).
- To-Pay LRs show the amount to collect at that drop.

### 3.4 Money per consignment

- **Trip income** = sum of all consignment freight (+ detention, loading,
  other charges).
- **Trip costs** (fuel, tolls, bata, hire…) are **shared out to consignments**
  so each consignment and client shows a profit. Method chosen by the owner:
  by **freight share**, by **weight**, or by **tonne-km** (weight × km carried).
  **[Practice]**
- Each consignment is billed on its own or put into the client's consolidated
  invoice; POD controls billing as in the Client Ledger queues.

### 3.5 Statuses

| Level | Statuses |
|---|---|
| Consignment | Booked → Picked up → In transit → Delivered (part / full) → POD received → Billed |
| Trip | Draft → Assigned → Running → Ended → Settled → Closed (unchanged) |

A trip can end only when every consignment on it is delivered, returned, or
transferred to another trip (with reason).

---

## 4. Part-load network operations (phase 2 — beyond Trucksarthi)

Many PTL transporters work through **booking branches and godowns (hubs)**.
This goes further than Trucksarthi and is planned for phase 2. **[Practice]**

```
Booking at branch ─► Godown (origin) ─► Manifest ─► Truck leg ─► Hub / godown
   ─► (re-manifest to another truck = transshipment) ─► Destination godown
   ─► Delivery run (local vehicle) or godown pickup by consignee ─► POD
```

| Feature | Detail |
|---|---|
| **Counter booking** | Walk-in or pickup booking at a branch → LR; charges per kg / article / package with **minimum charge**, docket/LR charge, door collection, door delivery, out-of-area (ODA) charge, risk/insurance charge; Paid / To-Pay / To-Be-Billed |
| **Pickup run** | Local vehicle collects booked consignments from customers' doors to the branch |
| **Manifest (loading challan / transit memo)** | Vehicle, driver, from branch → to branch, seal no., LR list with packages and weight; printed and visible in the crew app; can use our truck or a **hired truck** (hire challan linked to the manifest leg) |
| **Arrival and tally** | Unload at hub; count packages per LR against the manifest → **short / excess / damage report** with photos |
| **Godown stock** | LRs waiting in each godown, with age; **godown demurrage** after free days; location/bay |
| **Transshipment** | Move LRs from one manifest to another vehicle at a hub |
| **Delivery run sheet (DRS)** | Local delivery vehicle with a list of LRs; door delivery with e-POD; To-Pay collection; undelivered with reason (closed, refused, wrong address) → back to godown; reattempt |
| **Godown delivery** | Consignee collects at the branch; delivery memo / gate pass; ID check |
| **Return to origin** | Undelivered after N attempts → return booking |
| **Inter-branch accounts** | To-Pay freight collected at the delivery branch is credited to the booking branch; branch-to-branch ledger and cash transfers |
| **Tracking link per LR** | Client sees the LR's status timeline (booked, manifested, in transit, arrived, out for delivery, delivered) |

---

## 5. Market-truck hiring, broker commission and supplier settlement

Works for **FTL trips, PTL trips and PTL manifest legs**. Full ledger detail is
in `09-ledgers-and-documents.md` §4; the document is the **lorry hire challan**
(`10-invoicing-lr-templates.md` §6.1).

```
Trip / manifest leg with "Hired truck"
  ─► Hire challan: supplier, vehicle, driver, agreed hire (fixed or rate × weight)
  ─► Advance at loading (to owner, broker or driver)
  ─► Trip runs (hired driver can use the crew app as a one-trip guest)
  ─► POD received & verified
  ─► Deductions (shortage, damage, late, late POD, fuel given, office charge)
  ─► TDS (or nil with the owner's ≤10-truck declaration for the year)
  ─► Balance paid ─► supplier settled
```

### 5.1 Broker commission — both directions

| Case | Who pays whom | How it is recorded |
|---|---|---|
| **Vehicle broker** arranged a market truck for us | Broker earns commission, usually **deducted from the truck owner's hire** or paid by us | Commission line on the hire challan: payable to broker (broker ledger) or deducted from supplier |
| **Load broker** arranged a load for our truck | We pay the broker a commission (fixed or % of freight) | Commission expense on the trip; payable in the broker ledger |
| **We act as broker** (we arrange a market truck for a client and keep the difference) | Client freight − hire = **our margin**; or a commission we charge | Margin shown per load in the hire register |

**Broker** is a party role (`party_roles`) with its own ledger view: commission
earned/payable, payments, balance, statement and WhatsApp share.

### 5.2 Settlement reports

Supplier settlement statement (per supplier and per vehicle), hire register with
**client freight vs hire = margin per load**, pending balance split (POD not
received / POD received not paid / paid), **advances without POD ageing**,
broker commission register, TDS register.

---

## 6. Reports for load types

| Report | Content |
|---|---|
| Consignment register | All LRs with load type, trip, pickup/drop, status, freight, POD |
| Load factor | Per trip and per truck: average capacity used, by leg |
| FTL vs PTL profitability | Income, cost and profit per load type, client, route |
| Profit per consignment / client | Using the owner's cost-sharing method |
| Part-load network (phase 2) | Manifest register, godown stock ageing, short/excess report, delivery run performance, branch bookings, To-Pay outstanding by branch |
| Hire & broker | Hire register with margins, supplier settlements, broker commissions |

---

## 7. Data model additions (also listed in `06-data-model.md` §12)

| Table | Key columns |
|---|---|
| `trips` (changed) | `load_type` (ftl / ptl / mixed), `truck_source` (own / hired), `cost_allocation_method` (freight / weight / tonne_km) |
| `consignments` (changed) | `pickup_stop_id`, `drop_stop_id`, `booking_branch_id`, `delivery_branch_id`, packages, volume, `status`, `trip_id` nullable (PTL bookings waiting in a godown have no trip yet) |
| `consignment_deliveries` | consignment, stop, packages/qty delivered, shortage/damage, POD fields — supports split delivery |
| `consignment_events` | consignment, status, place, time, by whom — the tracking timeline |
| `consignment_cost_allocations` | consignment, trip, method, share %, allocated cost |
| `stop_tasks` | stop, consignment, action (load / unload), planned/actual packages, done_at |
| `manifests`, `manifest_lines` (phase 2) | manifest no., trip/vehicle, from/to branch, seal no., status; lines: consignment, packages, weight, loaded/unloaded counts, short/excess |
| `godown_stock` (phase 2) | branch, consignment, bay, in_at, out_at, demurrage start |
| `delivery_runs`, `delivery_run_lines` (phase 2) | DRS no., vehicle, driver, date; lines: consignment, result (delivered / undelivered + reason), collection |
| `branch_transfers` (phase 2) | from branch, to branch, consignment / cash, amount — inter-branch ledger |
| `broker_commissions` | broker party, trip / hire challan / consignment, direction (payable / receivable / deducted from supplier), basis (fixed / %), amount, voucher |
