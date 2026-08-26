# HR Module — Re‑implementation Guide

A Laravel‑oriented reference for rebuilding the **Human Resource** module on another school system.
It completes the operational set alongside the accounts/payment‑accounts, budgeting, and OHADA
accounting guides: HR is the **identity + people layer** the rest of the system reads (teachers,
payroll, the GL), covering the full employee lifecycle — staff records, org structure, leave,
attendance, payroll, and statutory tax.

> This documents the **corrected** design (the way to build it). The leave‑balance enforcement and
> the tax‑report consistency fix described here are part of that corrected design; §11 (Gotchas)
> records the traps so you don't re‑introduce them.

---

## 1. Philosophy

HR sits on one central model — **`User`** (a "staff member" is a user with a non‑admin role) — and
layers four concerns on top of it:

| Concern | Role |
|---|---|
| **Staff + org structure** | Who the person is; their Department / Designation / Work Shift; their access scope. |
| **Leave** | Apply → approve workflow, capped by an annual per‑type balance. |
| **Attendance** | Daily (QR kiosk + bulk) for fixed‑salary staff; hourly (class‑tied) for lecturers. |
| **Payroll + Tax** | Payslip generation, a configurable multi‑pass tax engine, and posting to the GL. |

Principles that make it coherent:

- **One identity, many roles.** `User` carries employment, payroll, bank, tax and document data;
  Spatie roles/permissions gate everything; the same record is the teacher in `ClassRoutine`, the
  payee in `Payroll`, the applicant in `Leave`.
- **HR is the source of identity for the academic side.** `StaffAssignment` + HoD detection
  (`StaffAssignmentService`) decide which faculties/programs/courses a staff member can touch — the
  same scoping that gates exam‑marking and timetables.
- **Payroll is policy‑driven, not hard‑coded.** Allowances, deductions and the entire tax table
  (groups, brackets, employer contributions, dependencies, exemptions) are data, effective‑dated.
- **Payroll is the one HR→GL bridge.** Paying a payslip posts a balanced OHADA journal entry;
  un‑paying reverses it. Nothing else in HR writes to the ledger.
- **Reports recompute from config.** The tax distribution report recomputes under *current* tax
  rules (a "what‑if"), so it must apply exemptions exactly as the payslip does.

End‑to‑end:

```
 User (staff) ── department/designation/work-shift/roles
   │
   ├─ StaffAssignment ─► academic access scope (faculties/programs/courses)
   ├─ Leave ─► apply → approve (capped by LeaveType.limit per year)
   ├─ StaffAttendance / StaffHourlyAttendance ─► daily QR / hourly class records
   └─ Payroll ─► basic_salary + allowances/deductions + 3-pass tax
                     │ pay()
                     ▼
              OHADA Journal Entry (DR salary/charges, CR tax payable / net cash)
```

---

## 2. Staff core (`User`)

One rich model (`app/User.php`). Group the columns:

- **Identity/personal:** first/last/father/mother name, dob, gender, phone, national_id, passport,
  nationality, religion, marital_status, blood_group, addresses (present/permanent).
- **Employment:** `staff_id` (auto‑generated, prefixed), `department_id`, `designation_id`,
  `work_shift` (→ WorkShiftType), `contract_type`, `salary_type` (1 = fixed, 2 = hourly),
  `basic_salary`, `joining_date`, `ending_date`.
- **Education:** level, academy, year/field of graduation, experience.
- **Financial/tax:** legacy bank fields (`bank_account_name/no`, `bank_name`, `ifsc_code`,
  `bank_brach`), `epf_no`, `tin_no` — plus a separate `StaffBankAccount` model (see §11.6).
- **Account/state:** email, password, roles (Spatie), `login` (may log in?), `status`
  (active/inactive), `blocked_at`/`block_reason`/`blocked_by`, 2FA fields, `last_seen_at`.
- **Media:** photo, signature, resume, joining_letter; plus polymorphic `documents()`.

Key relations: `department`, `designation`, `workShift`, `programs` (pivot), `classes`
(ClassRoutine as teacher), `attendances`, `payrolls`, `leaves`, `staffAssignments`, `taxExemptions`,
`documents`. Computed `id_card_validity` ("YYYY‑YYYY" from joining→ending, default +4y).

`UserController` does CRUD + Excel import + password print/change, filters by
role/department/designation/shift/contract, and **excludes Super Admin/Admin** from the staff list.
Permissions: `user-{view,create,edit,delete,import}`, `user-password-{change,print}`.

---

## 3. Organisation structure

Four small models, all simple CRUD (`title/slug/description/status`, Auditable):

- **Department** — `users()`, used for grouping/filtering and HoD scoping.
- **Designation** — job titles.
- **WorkShiftType** — shifts (`staffs()` via `work_shift`).
- **StaffAssignment** — **polymorphic** (`assignable` → Faculty / Program / Subject): the academic
  access grants. Helpers `hasAssignment`, `getAssignmentsByType`, `hasAnyAssignments`.

### 3.1 Access scoping — `StaffAssignmentService`
The heart of multi‑tenant access. Resolves, for a user: accessible faculty/program/course IDs by
combining **explicit assignments** + **HoD** status (from `AcademicDepartment.head_of_department_id`),
with sensible inheritance (assigned to a faculty ⇒ all its programs unless narrowed). Exposes query
scopes (`filterFaculties/Programs/Courses`) and `canAccess*` checks. Rules: Super Admin → all;
HoD → their departments; assigned staff → only their grants; **no assignments → full access** (for
non‑restricted roles). This is what exam‑marking and timetable screens call to limit what a teacher
sees.

---

## 4. Leave

**Models:** `Leave` (type_id, user_id, review_by, apply_date, from_date, to_date, reason, attach,
pay_type [1=paid, 2=unpaid], status [0=pending, 1=approved, 2=rejected]) and `LeaveType`
(title, slug, **limit**, status).

**Workflow:**
- **Apply** (`LeaveController@store`, self‑service): validates dates, creates a **pending** leave.
- **Manage** (`LeaveManagementController`): admin `index` (filter by staff/type/pay_type/date),
  `status()` approve/reject, `update()` edit + decide, `destroy()`. Approving sets `review_by`.

**Leave balance (build this in from day one):** `LeaveType.limit` is the **max days per staff, per
type, per calendar year** (`limit ≤ 0` = unlimited). Enforce it in two places, plus surface it:
- Model helpers on `Leave`: `daysCount()`, `usedDaysForType($userId,$typeId,$year,$statuses,$exclude)`,
  `remainingForType($type,$userId,$year)` (counts approved **and** pending so stacked requests can't
  blow the cap).
- `store()` blocks an application that would exceed the limit (counting approved+pending).
- `LeaveManagementController` blocks **approval** (shared `exceedsLeaveLimit()` used by `status()`
  and `update()`) when approving would exceed the limit (counting other approved leaves that year).
- Show remaining on the apply form (per‑type hint) and a `used/limit` badge on the manage list.

Permissions: `staff-leave-{view,create,delete}`, `staff-leave-manage-{view,edit,delete}`,
`leave-type-{view,create,edit,delete}`.

---

## 5. Attendance

Two parallel systems, both using attendance codes **1=Present, 2=Absent, 3=Leave, 4=Holiday**:

- **Daily** — `StaffAttendance` (user, date, start/end time, attendance, note) for **fixed‑salary**
  staff (`salary_type=1`). `StaffAttendanceController` offers a **QR kiosk** (`scanner`/`scan`:
  first scan = clock‑in/Present, second = clock‑out with duration, cooldown guard), a **bulk**
  index (`updateOrCreate` on user+date with all‑present/absent/leave/holiday radios), a personal
  history (`myAttendance`), and a monthly `report`.
- **Hourly** — `StaffHourlyAttendance` (adds subject/session/program/semester/section) for
  `salary_type=2` lecturers; the index lists that day's scheduled classes (from `ClassRoutine`),
  `store()` upserts per class, with monthly `report` + per‑lecturer `reportDetails`.
- **Settings** — `AttendanceSetting` (key/value/type/category store) with helpers
  `getValue/setValue/getAllAsArray`; configurable kiosk/scan/logbook behaviour (cooldown,
  auto‑clock‑out, class‑duration %, etc.).

Permissions: `staff-daily-attendance-{action,report}`, `staff-hourly-attendance-{action,report}`,
`attendance-setting-{view,edit}`.

> **Policy note (intentional):** attendance does **not** reduce pay. Payroll displays
> present/absent/leave/holiday and payable/unpayable days for information, but salary is computed
> from `basic_salary` only. Keep that decision explicit (see §11.5).

---

## 6. Payroll

**Models:** `Payroll` (user_id, basic_salary, salary_type, total_earning, total_allowance, bonus,
total_deduction, gross_salary, **tax**, **employer_tax**, net_salary, total_cost, salary_month,
pay_date, payment_method, payment_account_id, bank_account_id, status [0=unpaid, 1=paid]) +
`PayrollDetail` (payroll_id, title, amount, status [0=deduction, 1=allowance]). `AllowanceType` /
`DeductionType` are config lists.

**Flow:**
1. `generate($user,$month,$year)` loads the staff, their (non‑expired) tax exemptions, attendance,
   and the **effective** tax config (`TaxGroup::getEffectiveGroups`, `TaxSetting::getEffectiveBrackets`,
   `getEffectiveDependentBrackets`) for the pay date.
2. The payslip math lives in `resources/views/admin/payroll/generate.blade.php` — **server PHP and a
   mirrored JS `salaryCalculator()`** (so the form previews live and the server re‑computes on save).
   `gross = (basic + allowances + bonus) − deductions`; then the 3‑pass tax engine (§7);
   `net = gross − employee_tax`; `total_cost = net + employee_tax + employer_tax`.
3. `store()` upserts the `Payroll` + its `PayrollDetail` lines (status 0).
4. `pay()` sets status=1, records payment, and **posts the GL journal entry** (§8). `unpay()`
   reverses it. `report()` and `print()` for output.

Permissions: `payroll-{view,action,report,print}`.

---

## 7. The tax engine (the sophisticated part)

Three model types, **effective‑dated**, evaluated in **3 passes** (identical in payslip PHP, payslip
JS, and the report):

- **`TaxGroup`** (`is_progressive`, `code`, effective dates) → has many **`TaxSetting`** brackets.
- **`TaxSetting`** = a bracket *or* a standalone tax: `tax_group_id` (null = standalone),
  `bracket_order`, `min_amount`/`max_amount`, `tax_type` (1=percentage, 2=fixed), `percentange` /
  `fixed_amount`, `employer_percentage` / `employer_fixed_amount`, `paid_by`
  (employee/employer/both), `max_no_taxable_amount` (tax‑free allowance), `is_shared`, and
  dependency fields `is_dependent` + `depends_on_type` (tax_group|tax_setting) + `depends_on_id`.
- **`StaffTaxExemption`** — per staff per `tax_setting_id`: `custom_percentage`,
  `custom_fixed_amount`, `expires_at` (a `notExpired()` scope + `isExpired()` guard).

**Pass 1 — Group taxes.** `TaxGroup::calculateTax($salary, $exemptions)` uses **step lookup**: the
single applicable bracket = the active bracket with the **highest `min_amount` ≤ salary** (tax is
applied once, never summed across bands — this matches the institution's tax‑table design). Employer
side uses `applicableBracket()` so it picks the *same* bracket.

**Pass 2 — Standalone base taxes.** For each non‑dependent standalone `TaxSetting` whose range covers
the salary, employee/employer contributions via `calculateEmployeeContribution`/`...Employer...`.

**Pass 3 — Dependent taxes.** A dependent tax computes from its **source tax's output**
(employee+employer of the group/standalone it depends on), not the salary.

**Exemptions (must be consistent everywhere).** For a non‑expired exemption: a `custom_percentage`
(for percentage taxes) or `custom_fixed_amount` (for fixed taxes) **replaces** the normal rate;
no custom value ⇒ fully exempt (0); the **employer side is 0 when exempt**. This logic must be
identical in the payslip *and* the tax report (see §11.1).

**Tax distribution report** (`StaffTaxReportController`) **recomputes** taxes from current config
(not saved payslip values), giving salary‑band distribution, effective rates, exemptions and PDF/Excel
exports. Because it recomputes, it must apply exemptions exactly as the payslip — including custom
rates on standalone/dependent taxes.

Permissions: `tax-setting-{view,create,edit,delete}` (shared by tax‑group, tax‑setting, report).

---

## 8. Payroll → GL integration

`PayrollAccountingService::createPayrollJournalEntry($payroll)` builds a **balanced multi‑line**
OHADA entry (and posts it through the GL's `post()`):

```
DR  Salary expense (Class 6, 661/662)         gross_salary
DR  Employer social charges (Class 6, 664)    employer_tax        (if > 0)
   CR  Tax payable (Class 4, 442x)            employee tax        (if > 0)
   CR  Social charges payable (Class 4, 431)  employer_tax        (if > 0)
   CR  Cash/Bank (Class 5, 521/57)            net_salary
```

Accounts come from a `DefaultAccountMapping` (mapping_type `payroll`) with **fallback auto‑detection**
by account code/name (OHADA + French keywords). It records a `TransactionMapping`, then posts.
`reversePayrollJournalEntry()` posts a swapped reversal and marks the mapping `reversed` (used by
`unpay()`). `PayrollObserver@created` also auto‑maps via `TransactionAutoMapService`.
`checkConfiguration()` validates the fiscal year + required accounts exist. (See the OHADA guide for
the ledger side, and note `transaction_mappings.status` must be a **string** column.)

---

## 9. Reports

Staff list (filterable) · Staff ID cards (single/batch print, ZIP, photo upload, validity period) ·
Payroll report · Tax distribution report (recompute‑based, PDF/Excel) · Daily & hourly attendance
reports (+ per‑lecturer detail) · Leave manage list (with balance badge).

---

## 10. Suggested build order

1. **`User`** (+ Spatie roles/permissions, staff_id generation, blocking/login/status) and CRUD.
2. **Org structure:** Department, Designation, WorkShiftType; then **StaffAssignment** +
   `StaffAssignmentService` (the access scope the academic side depends on).
3. **Leave:** models + apply/manage workflow + **`LeaveType.limit` balance enforcement** (helpers,
   store guard, approval guard, UI hints) from the start.
4. **Attendance:** daily (bulk + QR kiosk) and hourly (class‑tied) + `AttendanceSetting`. Decide the
   pay‑impact policy explicitly.
5. **Payroll config:** AllowanceType, DeductionType, and the **tax engine**
   (TaxGroup/TaxSetting/StaffTaxExemption, effective‑dated, 3‑pass) — implement the calculation once
   and mirror it in PHP + JS + report identically.
6. **Payroll run:** generate → store → pay/unpay, then **GL posting** via `PayrollAccountingService`.
7. **Reports + ID cards + permissions + audit logging.**

---

## 11. Gotchas & decisions (don't repeat these)

1. **Apply tax exemptions identically in every place.** The payslip honored `custom_percentage`/
   `custom_fixed_amount` for standalone/dependent taxes, but the **tax report** treated any
   exemption as all‑or‑nothing — so the report disagreed with what payroll actually paid. Centralize
   the rule (custom value replaces the rate; none ⇒ 0; employer 0 when exempt; expired ⇒ full tax)
   and use it in payslip PHP, payslip JS, and the report.
2. **Enforce leave balance, don't just store a `limit`.** A `LeaveType.limit` that nothing reads
   means unlimited leave. Enforce it on **both** apply and approval, count approved+pending, define
   the period (calendar year here), and treat `limit ≤ 0` as unlimited so existing data isn't
   broken. Surface remaining days so users aren't surprised.
3. **Step‑lookup vs progressive.** This tax model taxes by the **single** applicable bracket, not by
   summing bands. Make employee and employer use the **same** bracket (`applicableBracket()`), or the
   two sides silently disagree.
4. **Recompute‑based reports drift if rules change.** The tax report recomputes under current config,
   so retroactive rule changes make it differ from historical payslips. Mitigate by **effective‑
   dating** every tax row (already supported) and querying the effective set for the pay date.
5. **Attendance not reducing pay is a *decision*.** Payroll shows payable/unpayable days but pays
   `basic_salary` only. That's intentional here — but it means the on‑screen day counts are
   informational; keep that clear so nobody "fixes" it by accident.
6. **Two bank stores.** Legacy `User.bank_*` fields and a newer `StaffBankAccount` model both exist
   (payroll points at `StaffBankAccount`). Intentional here for continuity, but on a fresh build pick
   **one** (the dedicated model) to avoid edit‑one‑pay‑from‑the‑other bugs.
7. **Watch user‑scoping in aggregate helpers.** `Leave::paid_leave()` originally omitted the
   `where('user_id', …)` filter its sibling had, so it summed *all* staff's paid leave. Audit any
   "count for this person in this month" helper for the missing scope.
8. **Magic numbers.** Attendance (1–4) and statuses (0/1/2) are bare integers across views/models;
   prefer constants/enums on a rebuild.
9. **Daily vs hourly attendance are disjoint** (`salary_type` 1 vs 2), and hourly is class‑centric
   (requires subject/program) — not usable for non‑teaching hourly staff. Decide if you need a
   non‑class hourly path.

---

## 12. File reference index (source system)

| Concern | Files |
|---|---|
| Staff core | `app/User.php`, `app/Http/Controllers/Admin/UserController.php` |
| Org structure | `app/Models/{Department,Designation,WorkShiftType,StaffAssignment}.php` + matching controllers |
| Access scoping | `app/Services/StaffAssignmentService.php`, `app/Http/Controllers/Admin/StaffAssignmentController.php` |
| Leave | `app/Models/{Leave,LeaveType}.php`, `app/Http/Controllers/Admin/{LeaveController,LeaveManagementController,LeaveTypeController}.php`, `resources/views/admin/{staff-leave,leave-manage}/` |
| Attendance | `app/Models/{StaffAttendance,StaffHourlyAttendance,AttendanceSetting}.php`, `app/Http/Controllers/Admin/{StaffAttendanceController,StaffHourlyAttendanceController,AttendanceSettingController}.php` |
| Payroll | `app/Models/{Payroll,PayrollDetail,AllowanceType,DeductionType}.php`, `app/Http/Controllers/Admin/PayrollController.php`, `resources/views/admin/payroll/generate.blade.php` |
| Tax engine | `app/Models/{TaxGroup,TaxSetting,StaffTaxExemption}.php`, `app/Http/Controllers/Admin/{TaxGroupController,TaxSettingController,StaffTaxReportController}.php` |
| Payroll→GL | `app/Services/PayrollAccountingService.php`, `app/Observers/PayrollObserver.php` |
| ID cards | `app/Http/Controllers/Admin/StaffIdCardController.php` |
| Permissions | `database/seeders/PermissionSeeder.php` |
| Sidebar/menus | `resources/views/admin/layouts/inc/sidebar.blade.php` (Human Resource / Staff Attendance / Staff Leave groups) |
| Routes | `routes/web.php` (staff/*, attendance/*, leave/*) |
