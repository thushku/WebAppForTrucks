# Users, Roles and Permissions

## 1. Layers

```
Platform (you, the SaaS owner)
 └── Company (a customer, e.g. "Sharma Roadlines")      ← one "tenant"
      ├── Branches (optional, e.g. Kochi, Mumbai)
      ├── Office users (owner, manager, accountant, workshop, viewer)
      ├── Crew (drivers, helpers)
      └── Trucks
```

- Each company's data is completely separate from other companies' data.
- One person can work for **more than one company** with the same mobile login
  (decision P7); each company sees only its own data.
- One person can have more than one role. For example, a small owner is both
  **Owner** and **Driver** of their only truck. They log in once and switch
  role with one tap.
- A person's role on a trip (driver, co-driver, helper) is set per trip. Ravi
  can be the driver on one trip and the co-driver on the next.

## 2. Roles

| Role | Uses | In one line |
|---|---|---|
| **Super-admin** | Web (hidden admin URL, email OTP) | Runs the SaaS: companies, plans, trial days, coupons, storage cleanup, database export/import, platform settings. |
| **Owner** | Web + owner app | Full control of their company, including billing and company settings. |
| **Manager** | Web + owner app | Runs operations: trips, crew, trucks, approvals, settlement. |
| **Accountant** | Web | Accounts, invoices, payments, payroll, tax reports. |
| **Workshop** (optional) | Web + owner app | Sees truck complaints and service jobs, updates their status. |
| **Viewer** (optional) | Web + owner app | Read-only, for example a partner or family member. |
| **Driver** | Crew app | Runs assigned trips, records events, sees own money and leave. |
| **Helper** | Crew app (simpler screens) | Sees assigned trips, own money and leave; can record basic events if the owner allows. |
| **Hired-truck driver (guest)** | Crew app, one trip only | A market truck's driver invited for a single trip to record milestones and POD. No ledger or company data. |

**Super-admin support access:** by default the super-admin cannot open a
company's trips or accounts. When a customer asks for help, the owner grants
time-limited support access (for example 24 hours). Every action is logged.
This protects customer trust and helps with privacy law.

## 3. Permission matrix

✅ = allowed · 👁 = view only · ⚙️ = owner can turn on/off · — = no access

| Area | Owner | Manager | Accountant | Workshop | Viewer | Driver | Helper |
|---|---|---|---|---|---|---|---|
| Company settings, rules, number formats | ✅ | 👁 | 👁 | — | — | — | — |
| Subscription and billing | ✅ | — | 👁 | — | — | — | — |
| Users and roles | ✅ | ⚙️ | — | — | — | — | — |
| Trucks (add/edit) | ✅ | ✅ | 👁 | 👁 | 👁 | 👁 own truck | 👁 own truck |
| Truck documents | ✅ | ✅ | 👁 | 👁 | 👁 | 👁 current truck | — |
| Crew profiles | ✅ | ✅ | 👁 | — | 👁 | 👁 own | 👁 own |
| Create / assign trips | ✅ | ✅ | — | — | — | — | — |
| Trip details | ✅ all | ✅ all | 👁 all | 👁 | 👁 | ✅ own trips | 👁 own trips |
| Freight amount on trip | ✅ | ✅ | ✅ | — | 👁 | ⚙️ (default hidden) | — |
| Record road events | ⚙️ | ⚙️ | — | — | — | ✅ | ⚙️ |
| 2nd approval (payment without receipt) | ✅ (single-driver trips) | ✅ (single-driver trips) | — | — | — | ✅ | — |
| Verify / reject crew entries | ✅ | ✅ | ⚙️ | — | — | — | — |
| Settle and close trips | ✅ | ✅ | ⚙️ | — | — | — | — |
| Office-side trip income/expenses (also after close) | ✅ | ✅ | ✅ | — | — | — | — |
| Reopen a closed trip | ✅ | — | — | — | — | — | — |
| Live map / route replay | ✅ | ✅ | — | — | 👁 | own location | own location |
| Complaints (truck) | ✅ | ✅ | 👁 | ✅ | 👁 | ✅ raise + view own | ✅ raise + view own |
| Service and maintenance | ✅ | ✅ | 👁 | ✅ | 👁 | 👁 own truck | — |
| Accounts, invoices, payments | ✅ | ⚙️ | ✅ | — | 👁 | — | — |
| Payroll: salary, bata, bonus, leave | ✅ | ⚙️ | ✅ | — | — | 👁 own | 👁 own |
| Leave requests | ✅ approve | ✅ approve | 👁 | — | — | ✅ apply | ✅ apply |
| Reports and profit | ✅ | ⚙️ | ✅ | — | 👁 | — | — |
| Audit log | ✅ | 👁 | 👁 | — | — | — | — |

## 4. What each crew member sees in the crew app

- **Own trips**: every entry made by anyone on the same trip (full transparency
  inside the crew), the route, stops and instructions. Freight is hidden unless
  the owner turns it on.
- **Own money**: trip cash account, personal ledger (advances, bata, salary,
  bonus, deductions, payments), current balance.
- **Own leave**: balance, apply, status.
- **Current truck**: number, document status (so they know if insurance or
  permit is expiring), complaints raised on it.
- **Own documents**: licence, badge or permit, expiry reminders.

## 5. Login and security (summary)

| Who | Login |
|---|---|
| Super-admin | Hidden URL + password + email OTP + captcha + lockout |
| Office users | Email or mobile + password + captcha + lockout; email OTP optional (owner can force it) |
| Office users (QR) | **Scan the QR code** on the web login page with the owner app (like WhatsApp Web); QR refreshes every 2 minutes; each sign-in is logged and can be revoked |
| Crew app | First login: mobile number + one-time activation code shown to the office in the web app (no SMS cost; SMS/WhatsApp OTP can be added later — Indian SMS needs DLT registration). After that: a 4-6 digit app PIN or fingerprint/face. |
| Owner app | Same as crew app, plus a forced PIN/biometric re-check before money actions |

Lockout rule (from the website guideline): 15 minutes after the first set of
wrong attempts, 1 hour after the next set, then locked until the super-admin
(or the company owner, for their own staff) unlocks it.
