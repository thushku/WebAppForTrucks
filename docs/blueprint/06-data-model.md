# Data Model (MySQL)

The main tables, their key columns and indexes. Field-level details for tax
documents are in `07-accounting.md`. Laravel migrations will follow this file.

## 1. Rules for every table

| Rule | Why |
|---|---|
| `id` BIGINT UNSIGNED auto-increment primary key | Fast joins and small indexes. |
| `company_id` on every company table, **first column of every index** | Keeps each company's data separate and searches fast past 10 lakh+ rows. A global scope in Laravel adds `WHERE company_id = ?` automatically. |
| `uuid` (UUID v7, stored as BINARY(16)) on every record a phone can create | The phone makes the ID, so re-sending never makes duplicates. Version 7 UUIDs are time-ordered, which keeps indexes fast. |
| Money as `DECIMAL(14,2)` + company currency (INR / AED) | Exact, no rounding errors. |
| Quantities as `DECIMAL(12,3)` | Litres, tonnes, km. |
| Times stored in **UTC**; shown in the company's time zone | India and UAE customers on one server. |
| No hard deletes for trips, events, money or accounts | Records are **cancelled** with a reason. Required by audit rules. |
| `created_by`, `updated_by`, `created_at`, `updated_at` | Who and when. |
| Every insert/update/delete inside a DB transaction | Required by the guideline. No half-saved data. |
| Every change to money, trip, accounting and people tables is written to `audit_logs` | India's audit-trail rule for accounting software. It cannot be turned off. |

## 2. Platform (super-admin)

| Table | Key columns |
|---|---|
| `companies` | name, country (IN/AE), currency, time zone, tax IDs, financial year start, status (trial/active/suspended), trial_ends_at, plan_id |
| `plans` | name, billing type (monthly/annual/lifetime), price per truck / flat, truck limit, student flag, features JSON |
| `subscriptions` | company, plan, starts_at, ends_at, status, coupon_id |
| `coupons` | code (unique), type (percent/fixed), value, valid_from, valid_to, max_uses, used_count, plan_ids |
| `coupon_redemptions` | coupon, company, at, discount |
| `saas_invoices` | company, number, amount, tax, status, paid_at |
| `admins` | super-admin users (separate table, separate guard) |
| `support_access_grants` | company, granted_by, expires_at, reason |
| `platform_settings` | key, value (trial days, lockout thresholds, upload limits…) |
| `login_attempts` / `lockouts` | user or email, ip, at, level (15 min / 1 h / admin-only) |

## 3. Company setup and masters

| Table | Key columns |
|---|---|
| `branches` | name, code, address, state/emirate, tax registration (if separate) |
| `users` | name, mobile (unique per company), email, password hash, PIN hash, status, language |
| `user_roles` | user, role (owner/manager/accountant/workshop/viewer/driver/helper), branch |
| `devices` | user, device id, platform, app version, push token, activated_at, last_sync_at, last_location_at |
| `activation_codes` | user, code hash, expires_at, used_at |
| `company_settings` | key, value (dual_approval_on, auto_approve rules, freight_visible_to_crew, lock_minutes, required_photos, driving_limits…) |
| `number_series` | doc type (trip/LR/invoice/receipt/payment/journal…), pattern, financial year, next_number |
| `places` | name, short_code (3 letters, unique per company), lat, lng, state/emirate, country, type (city/pump/toll/weighbridge/customer site) |
| `materials`, `expense_categories`, `truck_types`, `document_types`, `leave_types` | company-editable lists, with system defaults per country |

## 4. Parties

| Table | Key columns |
|---|---|
| `parties` | name, short_code, type (customer/vendor/both), sub-type (shipper, consignee, fuel pump, workshop, tyre dealer, broker), tax ID (GSTIN/TRN), PAN, address, state code / emirate, credit days, opening balance, account_id |
| `party_contacts` | party, name, mobile, email, role |
| `route_rates` | party, from place, to place, material, rate basis, rate, valid_from |

## 5. Trucks

| Table | Key columns |
|---|---|
| `trucks` | reg_no (unique per company), registration state/emirate, type, make, model, year, chassis no, engine no, emission norm, fuel type, wheels/axle configuration, lift axle, body type (open/container SXL-MXL/trailer/tipper/tanker/reefer/…), deck length, rate-card label (e.g. "32 ft MXL"), GVW, unladen weight, payload (MT), fuel tank (L), normal mileage loaded/empty (km/l), financier and EMI day, ownership (own/attached), owner party (if attached), linked trailer, status (idle/on_trip/workshop/breakdown/out_of_service/sold), odometer, FASTag/Salik tag no, tracker device id (AIS-140 / SecurePath / Asateel), account_id (for cost centre) |
| `truck_documents` | truck, document type (RC/Mulkiya, insurance, fitness, permit, PUC, road tax…), number, issued_on, expires_on, amount, file, reminder days |
| `tyres` | serial no, brand, size, type (new/retread), purchase date, cost, vendor, status (in store / fitted / retreading / scrapped) |
| `tyre_fitments` | tyre, truck, position (e.g. "R2-OUT"), fitted_at, fitted_odo, removed_at, removed_odo, reason |
| `service_schedules` | truck, service type, every_km, every_days, last_done_odo, last_done_on |
| `service_jobs` | truck, number, date, odometer, vendor, type (scheduled/breakdown/accident), status, total cost, voucher_id |
| `service_job_items` | job, part/labour, qty, rate, amount |
| `complaints` | truck, number, raised_by, trip, category, description, priority, status (open/in_progress/waiting_parts/solved/closed), assigned_to, solved_at, service_job_id |
| `complaint_updates` | complaint, user, note, photos, status change |

## 6. People

| Table | Key columns |
|---|---|
| `people` | user (if app user), employee code, type (driver/helper/office), date of birth, joining date, leaving date, licence no, licence class, licence expiry, hazardous endorsement, badge no, nationality, ID number (stored masked where law requires), bank name/account/IBAN, emergency contact, account_id (personal ledger) |
| `person_documents` | person, type (licence, ID, visa, passport, medical…), number, expires_on, file |
| `salary_structures` | person, from date, monthly salary, allowances JSON, bata rule (per day/per trip/per km/route table), overtime rule |
| `leave_balances` | person, leave type, year, entitled, taken, balance |
| `leave_requests` | person, type, from, to, days, reason, status, decided_by |

## 7. Trips and road events

| Table | Key columns |
|---|---|
| `trips` | number (unique per company), date, type (own load/market load/contract/return/empty/round), status, truck, trailer, branch, from_place, to_place, planned_km, start_odo, end_odo, actual_km, started_at, ended_at, settled_at, closed_at, linked_trip_id, budget JSON, instructions, ended_early flag |
| `trip_crew` | trip, person, role (driver/co_driver/helper), accepted_at, rejected_at, reject_reason |
| `trip_stops` | trip, sequence, place, type (loading/unloading/fuel/border/other), planned_at, arrived_at, left_at |
| `consignments` | trip, billing party, consignor, consignee, broker + commission, material, packages, qty, unit, declared/actual/charged weight, rate basis, rate, freight, hamali/other charges, advance, TDS deducted by party, LR number, LR payment type (paid/to-pay/to-be-billed), GST payable by, e-way bill no + expiry / delivery note no, consignor invoice no + value, hazardous / ODC flags, seal/container no, UAE transport type + main-carrier flag, invoice_id, loaded_qty, delivered_qty, shortage, detention free hours + rate + days, POD received_at |
| `trip_events` | **uuid**, trip, type, entered_by, device_id, event_at (phone time), received_at (server time), phone uptime, gps lat/lng/accuracy, mock_location flag, place_id, odometer, amount, payment_mode, category, details JSON (type-specific fields), status, late_entry flag, cancel_reason |
| `event_photos` | **uuid**, event, kind (odometer/meter/receipt/slip/POD/other), path, bytes, width, height, sha256, perceptual hash, taken_at, gps |
| `event_approvals` | event, step (second_driver/manager), user, decision (approve/reject/query), note, at |
| `trip_office_entries` | trip, type (income/expense), category, amount, note, voucher_id — can be added after close |
| `crew_cash_moves` | trip, person, type (advance/top-up/collected from party/returned), amount, mode, voucher_id |
| `trip_settlements` | trip, person, received, spent, balance, settled_by, mode, voucher_id |
| `driving_shifts` | trip, person, started_at, ended_at, start_odo, end_odo, source event — built from driver-change events |

**Indexes (examples):**
- `trip_events (company_id, trip_id, event_at)`
- `trip_events (company_id, status, event_at)` for the approvals inbox
- unique `trip_events (uuid)`, unique `event_photos (uuid)`
- `trips (company_id, status, date)`, `trips (company_id, truck_id, date)`

## 8. GPS points (the biggest table)

| Table | Key columns |
|---|---|
| `gps_points` | company, person, trip (nullable), device time, lat, lng, accuracy, speed, heading, battery %, mock flag, received_at |

- A small **`last_positions`** table (one row per person and per truck) is updated
  on every batch, so the live map never searches the big table.
- **Partitioned by month** (MySQL RANGE partitions), so old months can be
  archived or dropped quickly and recent months stay fast.
- Index `(company_id, trip_id, device_time)` and `(company_id, person_id, device_time)`.
- Inserted in batches (one insert per upload, not one per point).
- After a set time (for example 6 months), points are thinned (one point per
  minute) and full detail is removed. Per-trip route summaries are kept forever.

## 9. Accounting (summary, details in 07-accounting.md)

| Table | Key columns |
|---|---|
| `financial_years` | start, end, status (open/closed), locked_until date |
| `account_groups` | name, parent, nature (asset/liability/income/expense/equity) |
| `accounts` | group, name, code, linked type (party/person/truck/bank/cash/tax), linked id, opening balance |
| `vouchers` | type, number, date, narration, status (draft/posted/cancelled), source (trip/settlement/payroll/manual), financial year |
| `voucher_lines` | voucher, account, debit, credit, cost centres (trip, truck, branch) |
| `tax_lines` | voucher line, tax type, rate, taxable amount, tax amount |
| `invoices` | sales tax invoice header (country-specific fields) linked to voucher |
| `e_documents` | invoice or consignment, type (IRN / e-way bill / UAE e-invoice), status, reference no, response JSON |
| `bank_accounts`, `bank_statement_lines` | for bank reconciliation |

**One source of truth for money:** a person's ledger in the crew app *is*
their accounting ledger. Example: advance to Ravi → Dr Ravi, Cr Cash. Fuel
approved → Dr Fuel expense (trip, truck), Cr Ravi. Ravi returns cash → Dr Cash,
Cr Ravi. Bata → Dr Bata expense, Cr Ravi. So the trip profit, Ravi's balance and
the company books always agree.

## 10. Cross-cutting

| Table | Key columns |
|---|---|
| `audit_logs` | company, table, record id, action, old values JSON, new values JSON, user, ip, device, at — **append-only**, partitioned by month |
| `files` | path, owner type/id, bytes, sha256, pruned_at (when the super-admin cleans storage, a placeholder is shown) |
| `messages` | from user, to user/trip, text, voice file, read_at |
| `notifications` | user, type, data, read_at, push_sent_at |
| `sync_cursors` | device, table, last_server_change_id |
| `jobs`, `failed_jobs` | Laravel database queue (no Redis needed on simple hosting) |

---

## 11. Additions for the ledger, document, invoicing and tax modules (14 Oct 2026)

From `09-ledgers-and-documents.md`, `10-invoicing-lr-templates.md` and
`11-tax-engine.md`. Where a new table replaces an earlier one, it says so.

### 11.1 Identity across companies

| Table | Key columns |
|---|---|
| `accounts` | Global login identity: mobile (unique), name, PIN hash, language — one per real person |
| `users` (changed) | Now links `account_id` + `company_id` (one row per company the person works for); roles stay per company |
| `web_login_qr` | One-time QR token, created_at (expires after 2 minutes), approved_by_device, approved_at — for QR login to the web app |

### 11.2 Parties and ledgers

| Table | Key columns |
|---|---|
| `party_roles` | party, role (client / supplier / vendor / broker / consignor / consignee), active — **replaces the single `type` on `parties`** |
| `party_tax_ids` | party, tax ID (GSTIN/TRN), state/emirate, address, default flag (plant-wise billing) |
| `party_credit_terms` | party, credit limit, credit days, due-date basis (invoice / submission), breach action |
| `rate_contracts`, `rate_contract_lines` | party, version, validity, approval; route, material, truck type, basis, rate, minimum weight, detention slabs JSON, escalation JSON, penalties JSON — **extends/replaces `route_rates`** |
| `bill_refs` | voucher line, ref type (new / against / advance / on_account), ref no., due date, amount — basis of bill-wise outstanding |
| `bill_submissions` | invoice, submitted_on, mode, to, acknowledgement no., portal ref, courier docket |
| `client_deductions` | invoice/receipt, type (shortage / damage / late / POD late / rate), amount, evidence, credit_note_id, recover_from (driver / supplier), recovery_id |
| `party_balances` | Read model updated in the same transaction as every posting (fast lists) — from Clinic Ledger |
| `ledger_acknowledgements` | voucher, person/party, method (tap / OTP / signed photo), at |
| `ledger_queries` | voucher, raised_by (crew member), reason (not received / wrong amount), thread, status |
| `person_loans`, `person_loan_installments` | person, principal, interest, consent file; due month, amount, paid voucher |
| `recoveries` | person or supplier, type, basis, amount, approved_by, notified_at, cap-check result |
| `security_deposits` | person, amount, received voucher, refunded voucher |
| Voucher lines (changed) | Add `bucket` (cash / earnings / loan) for crew-member accounts |

### 11.3 Suppliers, hired trucks and vendors

| Table | Key columns |
|---|---|
| `supplier_vehicles` | supplier, reg no., type, RC owner name, verification JSON, checked_at |
| `hire_challans` | number, date, trip, supplier, vehicle, driver, hire basis and amount, terms JSON, broker, commission, TDS decision, status |
| `hire_challan_lines` | challan, type (advance / fuel / detention / extra / deduction / commission / TDS / balance), amount, voucher_id |
| `trips` (changed) | Add `truck_source` (own / hired) and `supplier_vehicle_id` for hired trucks |
| `fuel_slips` | slip no., truck, driver, pump vendor, litres/amount, issued_at, trip_event_id, statement_line_id, status |
| `vendor_statements`, `vendor_statement_lines` | vendor, period, file; line date, slip no., vehicle, litres, rate, amount, match status |
| `workshop_estimates` | vendor, truck, items, amount, approved_by, service_job_id |

### 11.4 Document Manager

| Table | Key columns |
|---|---|
| `documents` | Owner type + id (truck / person / company / party), type, number, authority, issue date, valid from, expiry, cost, vendor, original kept at, current version — **replaces `truck_documents` and `person_documents`** |
| `document_versions` | document, version no., files, renewal voucher, renewed_by, archived_at |
| `document_reminder_rules` | document type, days-before list, repeat rule, channels, recipients |
| `document_shares` | documents (pack), token, purpose, watermark text, expires_at, opened_at |
| `verification_checks` | entity, provider, check type (RC / DL / PAN / GST / bank / DigiLocker / challan), request, response JSON, cost, at |
| `insurance_claims` | truck, policy document, claim no., surveyor, estimate, approved, received, linked incident and service job |

### 11.5 Invoicing, LR and templates (ported from Clinic Ledger + transport)

| Table | Key columns |
|---|---|
| `consignments` (changed) | Becomes the **LR**: LR no., book leaf, copy set, payment type (paid / to-pay / to-be-billed / FOC), risk, insurance, declared value, charges JSON, POD fields, status |
| `consignment_ewaybills` | consignment, e-way bill no., invoice no., value, valid_until (several per LR) |
| `lr_books`, `lr_book_leaves` | branch, book no., range, issued to, issued on; leaf no., status (unused / used / cancelled / lost), consignment |
| `invoices` | doc_type (text + validation: invoice / tax_invoice / bill_of_supply / simplified / supplementary / credit_note / debit_note / quotation…), number, dates, party, tax registration, place of supply, currency + rate, status, totals, related_invoice_id, reason, **snapshot (encrypted)**, **design_snapshot**, public token, submitted info |
| `invoice_lines` | invoice, consignment (LR) link, service class, item code, description, unit, qty, rate, discount, tax code, line net, trip/truck/branch |
| `payments`, `payment_allocations` | receipt no., party, mode, UTR, amount, TDS deducted, deductions; allocation per invoice (oldest-first or manual), advance credit |
| `document_sequences` | doc type, scope (company / branch / tax registration), financial year, prefix/format, next no., max length, allowed characters, gap-free |
| `print_templates`, `print_template_versions` | family, name, scope (company / branch / tax registration / client), default flag, theme, **design JSON** (strictly validated), paper, copy set, languages, version, created_by |
| `copy_sets` | family, copy order, label, tint, hide-freight flag |
| `custom_fields`, `custom_field_values` | document type, label, kind (text / number / date / list), show-on-print; values per document |
| `label_overrides` | template, language, label key, text |
| `declaration_texts` | country, tax mode (RCM / FCM5 / FCM18 / exempt / reverse charge…), text, valid_from, valid_to |
| `message_templates` | channel (WhatsApp / SMS / email), purpose, language, body with placeholders, provider template id |
| `document_deliveries` | document, channel, to, sent_at, opened_at, printed_at, downloaded_at |

### 11.6 Tax engine

See `11-tax-engine.md` §4 for the full list: `countries`, `tax_jurisdictions`,
`tax_regimes`, `tax_types`, `tax_codes`, `tax_rates`, `tax_group_members`,
`service_classes`, `commodity_codes`, `service_class_codes`, `tax_rules`,
`fiscal_positions`, `fiscal_position_maps`, `withholding_codes`,
`withholding_rates`, `return_box_mappings`, `exchange_rates`,
`tax_registrations`, `party_tax_profiles`, `tax_certificates` (replaces the
earlier `tds_declarations` idea), trip tax facts, `document_tax_lines`,
`document_withholding_lines`, `document_tax_traces`, `e_invoice_submissions`,
`e_invoice_providers`.

Money columns in tax tables use `DECIMAL(19,4)` (3-decimal currencies, per-unit
taxes); the general rule in §1 (`DECIMAL(14,2)`) is raised to `DECIMAL(19,4)` for
all invoice and tax amounts.

Document types and statuses are **text columns with validation**, never MySQL
ENUMs (Clinic Ledger lesson: an ENUM blocked debit notes until a migration).

---

## 12. Additions for FTL / PTL operations (14 Oct 2026)

From `12-ftl-ptl-operations.md` §7: `trips.load_type` (ftl / ptl / mixed),
`trips.cost_allocation_method`; consignment pickup/drop stops, booking/delivery
branch, packages, volume, status, nullable trip; `consignment_deliveries`
(split delivery + POD), `consignment_events` (tracking timeline),
`consignment_cost_allocations`, `stop_tasks`, `broker_commissions`; phase 2:
`manifests`, `manifest_lines`, `godown_stock`, `delivery_runs`,
`delivery_run_lines`, `branch_transfers`.
