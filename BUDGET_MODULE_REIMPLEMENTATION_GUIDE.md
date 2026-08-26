# Budget Module — Re‑implementation Guide

A Laravel‑oriented reference for rebuilding the **Budgeting** module on another school system.
It pairs with `ACCOUNTS_PAYMENT_ACCOUNTS_REIMPLEMENTATION_GUIDE.md`: that one covers *recording*
money (income/expense) and *holding* money (payment accounts); **this one covers planning and
controlling spending** before/while it happens.

> This guide documents the **current (corrected) design** — i.e. the way you should build it,
> including the "single source of truth" recompute pattern. Where the original had rough edges,
> §8 (Gotchas) records the decisions taken so you don't reintroduce them.

---

## 1. Philosophy — a planning & control layer over expenses

The budget module answers *"how much are we allowed to spend, on what, and how much is left?"* It
sits **on top of the Expense module** and never moves money itself.

Three nested levels:

| Level | Entity | Meaning |
|---|---|---|
| **Envelope** | `Budget` | A pot of money for a period (annual / departmental / project) with a `total_amount`. |
| **Line item** | `BudgetAllocation` | A slice of that envelope earmarked for one **expense category** (optionally a department), e.g. "Lab Equipment Q1". |
| **Actual** | `Expense` | A real expense, optionally tagged to a budget + allocation; this is what "consumes" the budget. |

Core principles:

- **Plan top‑down, consume bottom‑up.** You set a `total_amount`, carve it into allocations
  (guarded so allocations can't exceed the total), then expenses tagged to those allocations draw
  them down.
- **`spent_amount` is always recomputed from the source of truth (the expense rows)** — never
  incrementally patched. A budget/allocation's spend = `SUM(amount)` of its **non‑rejected**
  expenses; `remaining = base − spent`. This makes create/edit/delete of expenses, and re‑linking,
  drift‑free and idempotent.
- **A budget has a lifecycle** (draft → pending_approval → approved → active → closed/cancelled).
  Editing and allocation changes are only allowed while the budget is *not yet active*; once
  active it's locked except via a **revision** (a logged total adjustment).
- **It's a self‑contained planning layer.** A budget relates only to `Department`,
  `ExpenseCategory`, and `Expense`. It is **not** wired to income, payment accounts, or the
  general ledger, and its `fiscal_year` is a plain string (not a FK to any accounting fiscal‑year
  entity). You can build it with none of those present.

Flow:

```
  Budget (total_amount)            status: draft → pending_approval → approved → active → closed
     │  carve into
     ▼
  BudgetAllocation[]  (per expense category; Σ allocated ≤ total_amount)
     ▲  draw down
     │  tag expense to budget (+ optional allocation)
  Expense (amount, approval_status)
     │  on create/update/delete
     ▼
  updateSpentAmount()  →  allocation.spent = Σ its non‑rejected expenses
                          budget.spent     = Σ its non‑rejected expenses
                          remaining        = base − spent
```

---

## 2. Data model

### 2.1 `budgets` (migration `2025_01_10_000001`)

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| title | string | e.g. "2025 Annual Budget" |
| budget_code | string, unique | auto‑generated `BDG-{year}-{0001}` in model `creating` hook |
| type | enum(annual, departmental, project), default annual | |
| department_id | int unsigned, nullable, FK → departments (cascade) | null for annual |
| fiscal_year | string(10) | plain string, e.g. "2025" |
| start_date / end_date | date | |
| total_amount | decimal(15,2) | the envelope |
| allocated_amount | decimal(15,2), default 0 | Σ of allocations (recomputed) |
| spent_amount | decimal(15,2), default 0 | Σ non‑rejected expenses (recomputed) |
| remaining_amount | decimal(15,2), default 0 | total − spent |
| status | enum(draft, pending_approval, approved, active, closed, cancelled), default draft | |
| description / note | text, nullable | |
| created_by / updated_by / approved_by | bigint unsigned, nullable | |
| approved_at | timestamp, nullable | |
| timestamps | | |
| *committed_amount* | decimal, default 0 | added by `2025_12_17_051011`; **unused — see §8** |

Model: `Budget` (uses `Auditable`). Relationships: `department()`, `allocations()` (hasMany),
`expenses()` (hasMany via `budget_id`), `revisions()` (hasMany), `createdBy/updatedBy/approvedBy()`.
Accessors: `utilization_percentage`, `status_badge`, `utilization_color`. Scopes: `active()`,
`byFiscalYear()`, `byDepartment()`. The `creating` hook generates `budget_code` and seeds
`remaining_amount = total_amount`.

Key methods:
```php
public function updateSpentAmount() {       // single source of truth
    $this->spent_amount = $this->expenses()->where('approval_status','!=','rejected')->sum('amount');
    $this->remaining_amount = $this->total_amount - $this->spent_amount;
    $this->save();
}
public function calculateAllocatedAmount() { // recompute Σ allocations
    $this->allocated_amount = $this->allocations()->sum('allocated_amount');
    $this->save();
}
public function isOverBudget()        { return $this->spent_amount > $this->total_amount; }
public function canAddExpense($amount){ return ($this->spent_amount + $amount) <= $this->total_amount; }
```

### 2.2 `budget_allocations` (migration `2025_01_10_000002`)

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| budget_id | bigint unsigned, FK → budgets (cascade) | |
| expense_category_id | int unsigned, FK → expense_categories (restrict) | the link to spending categories |
| department_id | int unsigned, nullable, FK → departments (cascade) | optional |
| title | string | e.g. "Q1 Salaries" |
| allocated_amount | decimal(15,2) | |
| spent_amount | decimal(15,2), default 0 | recomputed |
| committed_amount | decimal(15,2), default 0 | **unused — see §8** |
| remaining_amount | decimal(15,2), default 0 | allocated − spent |
| period | enum(yearly, q1..q4, semester1, semester2), default yearly | |
| description | text, nullable | |
| is_active | boolean, default true | |
| created_by / updated_by | bigint unsigned, nullable | |
| timestamps | | |

Model: `BudgetAllocation` (uses `Auditable`). Relationships: `budget()`, `expenseCategory()`,
`department()`, `expenses()` (hasMany via `budget_allocation_id`). Accessors:
`utilization_percentage`, `available_amount`, `status_color`. Its `updateSpentAmount()` recomputes
its own spend then **cascades to the parent budget**:
```php
public function updateSpentAmount() {
    $this->spent_amount = $this->expenses()->where('approval_status','!=','rejected')->sum('amount');
    $this->remaining_amount = $this->allocated_amount - $this->spent_amount;
    $this->save();
    if ($this->budget) $this->budget->updateSpentAmount();
}
```

### 2.3 `budget_revisions` (migration `2025_01_10_000003`)

Logs total‑amount changes. Columns: `budget_id`, `revision_number` (auto‑increment per budget),
`previous_amount`, `new_amount`, `change_amount` (auto = new − prev), `change_type`
(increase/decrease/reallocation, auto from sign), `reason`, `justification`, `status`
(pending/approved/rejected), `requested_by`, `approved_by`, `approved_at`. Model `BudgetRevision`
has an `approve($userId)` that applies the change to the budget (`total_amount = new_amount`,
recompute remaining) and a `creating` hook that fills revision_number/change_amount/change_type.

### 2.4 Expense link (migration `2025_01_10_000004`)

`expenses` gains: `budget_id`, `budget_allocation_id` (both nullable FKs), `approval_status`
(enum pending/approved/rejected, default approved), `approved_by`, `approved_at`. See the accounts
guide for the rest of the Expense schema.

---

## 3. Budget lifecycle (`BudgetController`)

State machine, enforced by status checks in each action:

```
 draft ──submit──▶ pending_approval ──approve──▶ approved ──activate──▶ active ──close──▶ closed
   │                      │                          │
   └──────────── cancel ──┴──────────────────────────┘   (→ cancelled)
```

- **create/store** → status `draft`; validates `title, type, fiscal_year, start_date, end_date
  (after start), total_amount (≥0), department_id (required_if type=departmental)`.
- **edit/update** → allowed only when `draft` or `pending_approval`.
- **destroy** → allowed only when `draft` (deletes allocations first).
- **submitForApproval / approve / activate / close / cancel** → guarded transitions; `approve`
  stamps `approved_by/at`.
- **revise** (added) → for `approved`/`active` budgets only; see §5.

Permissions per action: `budget-{view,create,edit,delete,approve,activate,close,cancel}`.

---

## 4. Allocations (`BudgetAllocationController`)

- **index** → allocation screen for a budget (categories + departments for the form).
- **store/update/destroy** → only allowed while budget status is **not** active/closed/cancelled.
- **Over‑allocation guard:** an allocation cannot push `Σ allocated` past `total_amount`
  (computed as `total_amount − other_allocations_total`); rejects with a clear message.
- After every change it calls `Budget::calculateAllocatedAmount()` to keep the budget's
  `allocated_amount` accurate.
- Permissions: `budget-allocation-{create,edit,delete}` (+ `budget-view` for index).

---

## 5. Expense ↔ budget integration (the consumption path)

When recording an `Expense` (`ExpenseController`), the user may pick a `budget_id` and
`budget_allocation_id`:

- **Validation:** `budget_id`/`budget_allocation_id` are `nullable|exists:`; if an allocation is
  chosen, the expense `amount` is checked against the allocation's **remaining** before saving.
- **Recompute (corrected pattern):** after `store`/`update`/`destroy`, the controller calls
  `updateSpentAmount()` on the affected allocation(s) **and** budget(s) — never manual `+=`/`−=`.
  On `update` it recomputes both the **old and new** allocation/budget (handles re‑linking); on
  `destroy` it deletes first, then recomputes the remaining rows. This is drift‑free and idempotent.
- **`approval_status`:** non‑rejected expenses (pending + approved) count toward spend; rejected
  ones don't. (There is no approval UI in the source; `pending` therefore counts — see §8.)
- Expenses also support budget allocations spend rollup independent of whether the expense is tied
  to a payment account (that's a separate concern, see the accounts guide).

---

## 6. Revisions — adjusting a locked budget (`BudgetController@revise`)

Because allocations and `total_amount` lock once a budget is active, the supported way to change
the total is a **revision**:

- Route `POST budget/{id}/revise`; gated by `budget-edit`; allowed for `approved`/`active` budgets.
- Validates `new_amount (≥0)` + `reason`. Guards: new total **cannot be below** the already
  `allocated_amount` or `spent_amount`, and must differ from the current total.
- Creates a `BudgetRevision` then calls its `approve()` (apply‑immediately): updates
  `total_amount` and recomputes `remaining`. The change is logged (who/when/why, prev→new).
- UI: an inline "Revise Total Amount" form on the budget **show** page, visible for approved/active
  budgets (JS‑free; no modal dependency).

---

## 7. Dashboard & reports

**Dashboard** (`BudgetDashboardController@index`, route `budget-dashboard`) over **active** budgets:
- **KPIs:** total budget, allocated, spent, remaining, utilization %, allocation %, active count.
- **Charts:** utilization (Spent vs Remaining), department spending (pie), monthly spend trend
  (line, from approved budgeted expenses), category allocated‑vs‑spent (bar).
- **Alerts:** per budget and per allocation at **>75%** (info), **>90%** (warning), **>100%**
  (over‑budget, danger) utilization.
- `getBudgetSummary($id)` returns JSON for AJAX drill‑down.

**Reports** (`BudgetReportController`): `performance`, `variance`, `department`, `cashflow`. There
is also an overlapping `AccountingReportsController@budgetVsActual` ("Budget vs Actual").

---

## 8. Gotchas & decisions to carry over (or avoid)

1. **Recompute, don't increment.** The original code patched `spent_amount` with `+=`/`−=` in the
   expense controller *and* had a separate "sum approved only" recompute — they disagreed and
   drifted (especially on delete). **Always recompute from the expense rows** in one method used
   everywhere (this guide's pattern). This was the main bug fixed.
2. **`committed_amount` is unused.** Both tables carry it (encumbrance/"approved but not paid"),
   it's read in some formulas, but **nothing ever writes it** (always 0). It was hidden from the
   dashboard. **Decide up front:** either implement real commitment tracking (set it when an
   expense is pending/PO‑raised, clear on payment) or drop the column. Don't ship a dead KPI.
3. **Expense approval has no workflow.** `approval_status` exists but there's no approve/reject UI,
   and new expenses default to `pending` while still counting toward spend. Either build the
   approval flow or default expenses to `approved` — just be consistent about what counts.
4. **Active budgets are otherwise frozen.** Editing and allocation changes stop at `active`; the
   **revision** action is the only adjustment path. If you need to re‑slice allocations mid‑year
   (not just change the total), add an allocation‑revision flow too.
5. **No link to actual cash or the GL.** Budgets track *plan vs expense‑records*, not bank
   balances. `fiscal_year` is a string, not a FK. Keep that separation, or add the links
   deliberately.
6. **Revisions apply immediately** (no request→approve step) to match how approve/activate work for
   admins. If governance needs a maker/checker, wire `BudgetRevision.status` through an approval UI
   (the model already supports pending/approved/rejected + `approve()`/`reject()`), and note the
   model's `reject()` writes `rejected_reason` — ensure that column exists.

---

## 9. Suggested build order

1. **Tables/models:** `budgets`, `budget_allocations`, `budget_revisions`; add `budget_id`,
   `budget_allocation_id`, `approval_status` to `expenses`.
2. **Budget CRUD** + the `creating` hook (auto code, seed remaining) + the lifecycle transitions
   (submit/approve/activate/close/cancel) with status guards.
3. **Allocations CRUD** with the over‑allocation guard and `calculateAllocatedAmount()` rollup.
4. **Expense integration:** allocation‑remaining validation on save, and the **single
   `updateSpentAmount()` recompute** called on expense create/update/delete (old + new links).
5. **Revisions:** the `revise` endpoint (guards + log + apply) and the show‑page form.
6. **Dashboard** (KPIs, charts, alerts) and **reports** (performance/variance/department/cashflow).
7. **Permissions + sidebar**, then **audit logging** on all three models.
8. Decide on **committed_amount** and **expense approval** (gotchas #2, #3) before go‑live.

---

## 10. File reference index (source system)

| Concern | File |
|---|---|
| Budget model | `app/Models/Budget.php` |
| Allocation model | `app/Models/BudgetAllocation.php` |
| Revision model | `app/Models/BudgetRevision.php` |
| Budget CRUD + lifecycle + revise | `app/Http/Controllers/Admin/BudgetController.php` |
| Allocations | `app/Http/Controllers/Admin/BudgetAllocationController.php` |
| Dashboard | `app/Http/Controllers/Admin/BudgetDashboardController.php` |
| Reports | `app/Http/Controllers/Admin/BudgetReportController.php` |
| Expense ↔ budget | `app/Http/Controllers/Admin/ExpenseController.php` (store/update/destroy) |
| Routes | `routes/web.php` (~519‑547, plus `budget/{id}/revise`) |
| Show page (revise form) | `resources/views/admin/budget/show.blade.php` |
| Dashboard view | `resources/views/admin/budget/dashboard.blade.php` |
| Migrations | `database/migrations/2025_01_10_00000{1,2,3,4}_*`, `2025_12_17_051011_*` |
| Permissions | `database/seeders/AccountingPermissionSeeder.php` (`budget-*`, `budget-allocation-*`) |
