# Accounts (Income/Expense) + Payment Accounts — Re‑implementation Guide

A complete, Laravel‑oriented reference for rebuilding two finance modules on another school
system:

1. **Accounts** — the *Income & Expense* module (sidebar group "Income & Expense", URL prefix
   `admin/account/*`).
2. **Payment Accounts** — real cash/bank/e‑wallet accounts with running balances, deposits,
   withdrawals and transfers.

> Scope note: the OHADA double‑entry accounting suite (Chart of Accounts, Journal Entries,
> General Ledger, financial statements, fiscal years) is **out of scope**. It is only referenced
> where these two modules touch it (a one‑way, optional auto‑posting via model observers). You can
> re‑implement everything below with **no** general ledger at all.

---

## 1. Philosophy — a two‑layer money model

The design separates **the meaning of money** from **the location of money**:

| Layer | Question it answers | Entities | Entry style |
|---|---|---|---|
| **Accounts (Income/Expense)** | *What was the money, and why?* | `Income`, `Expense` (+ their categories) | Single record per event |
| **Payment Accounts** | *Where is the money actually held?* | `PaymentAccount` + `PaymentAccountTransaction` + `PaymentAccountTransfer` | Running ledger per account |

Key principles:

- **An Income/Expense is a business fact** (a categorised, dated amount with a reference and
  optional attachment). It can exist *without* being tied to any cash location.
- **A Payment Account is a real container of money** (Main Bank, Petty Cash, Mobile Money…). Its
  `current_balance` is a **stored running field**, not a computed sum. Every movement
  (deposit, withdrawal, transfer, or a link to an income/expense/fee/payroll) writes a
  `PaymentAccountTransaction` row, mutates `current_balance` atomically, and snapshots the
  resulting balance in `balance_after`.
- **The two layers are linked by reference, not by hard coupling.** An income/expense (and also
  fee receipts, installment payments, multi‑payments, payroll) carries a nullable
  `payment_account_id`. When set, a matching transaction is created on that account. The
  transaction stores `reference_type` + `reference_id` pointing back to the source row.
- **Two ways to attach money to an account:**
  1. **At creation** — choose a payment account on the Income/Expense form; the controller creates
     the `PaymentAccountTransaction` and adjusts the balance in the same DB transaction.
  2. **Later** — leave it blank, then use the **Unlinked Transactions → Link** screen to assign an
     account after the fact (e.g., once a bank statement clarifies where the money landed).
- **Optional third layer (excluded):** model observers also auto‑post these events to the OHADA GL.
  That is independent of everything here and can be omitted entirely.

Money‑flow at a glance:

```
                 ┌─────────────────────────┐
  record a fact  │  Income  /  Expense      │  (category, amount, date, ref, attach)
                 └──────────┬──────────────┘
                            │ payment_account_id set?
              at creation   │                       │  left blank
            ┌───────────────▼───────┐       ┌───────▼─────────────────────┐
            │ create PaymentAccount │       │ shows in "Unlinked" report   │
            │ Transaction (credit/  │       │ → admin links it later       │
            │ debit) + adjust       │       └───────┬─────────────────────┘
            │ current_balance       │               │ same effect on link
            └───────────────┬───────┘◄──────────────┘
                            ▼
              PaymentAccount.current_balance (running) + balance_after snapshot
```

---

## 2. The Accounts module (Income & Expense)

### 2.1 Routes (`routes/web.php`, ~782‑787)

```php
Route::resource('account/income',           'IncomeController');
Route::resource('account/income-category',  'IncomeCategoryController');
Route::resource('account/expense',          'ExpenseController');
Route::resource('account/expense-category', 'ExpenseCategoryController');
Route::resource('account/outcome',          'OutcomeCalculationController');
```

All under the admin group (`auth:web` + `XSS` + license middleware).

### 2.2 Data model

**`income_categories`** (migration `2021_06_05_121933`) and **`expense_categories`**
(`2021_06_05_144224`) are identical in shape:

| Column | Type | Notes |
|---|---|---|
| id | increments | PK |
| title | string | unique category name |
| slug | string | `Str::slug(title)` |
| description | text, nullable | |
| status | boolean, default 1 | active/inactive |
| timestamps | | |

Model fillable: `title, slug, description, status`. Relationship:
`incomes()` / `expenses()` = `hasMany`. Both use the `Auditable` trait.

**`incomes`** (migration `2021_06_05_125236`; `payment_account_id` added `2025_10_09_210002`):

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| category_id | int unsigned, FK → income_categories (cascade) | |
| title | string | |
| invoice_id | string, nullable | free‑text invoice ref |
| amount | decimal(10,2) | |
| date | date | transaction date |
| reference | string, nullable | |
| payment_method | int, nullable | method code |
| payment_account_id | bigint unsigned, nullable, FK → payment_accounts (set null) | **the link** |
| note | text, nullable | |
| attach | text, nullable | uploaded file path |
| status | boolean, default 1 | |
| created_by / updated_by | bigint unsigned, nullable | |
| timestamps | | |

Model fillable adds `created_by, updated_by`. Relationships: `category()` (belongsTo
IncomeCategory via `category_id`), `paymentAccount()` (belongsTo PaymentAccount), `recordedBy()`
(belongsTo User via `created_by`). Uses `Auditable`.

**`expenses`** (migration `2021_06_05_150317`) — same as incomes **plus** budgeting/approval:

Additional columns (budget columns added `2025_01_10_000004`):

| Column | Type | Notes |
|---|---|---|
| budget_id | bigint unsigned, nullable, FK → budgets | optional budget |
| budget_allocation_id | bigint unsigned, nullable, FK → budget_allocations | optional line |
| approval_status | enum(pending, approved, rejected), default **approved** | |
| approved_by | bigint unsigned, nullable | |
| approved_at | timestamp, nullable | |

Expense model casts: `amount → decimal:2`, `date → date`, `approved_at → datetime`. Extra
relationships: `budget()`, `budgetAllocation()`, `approvedBy()`. Scopes: `approved()`,
`pending()`, `withBudget()`. A model `boot()` hook recomputes budget spend when an expense becomes
`approved`.

> If you don't need budgeting/approval on the target system, drop those five columns and the
> related logic; `Expense` then mirrors `Income` exactly.

### 2.3 Controllers

`IncomeController` / `ExpenseController` (`app/Http/Controllers/Admin/`) are standard resource
controllers. Each `__construct` sets `$title/$route/$view/$path/$access` and applies
permission middleware (see §5). They use the `FileUploader` trait for attachments.

**`index`** — filters by `title` (LIKE), `category_id`, and a date range; default range is
**last year → today** (`Carbon::now()->subYear()` to `Carbon::today()`). Orders by `id desc`,
passes active categories for the filter dropdown.

**`store`** — validation (income):

```php
$request->validate([
    'category'           => 'required',
    'title'              => 'required',
    'amount'             => 'required|numeric',
    'date'               => 'required|date|before_or_equal:today',
    'attach'             => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,rar,csv,xls,xlsx,ppt,pptx|max:20480',
    'payment_account_id' => 'nullable|exists:payment_accounts,id',
]);
```

Expense `store` adds `budget_id`, `budget_allocation_id` (both `nullable|exists:`) and validates
the amount against the allocation's remaining funds before saving.

The save + payment‑account linkage is wrapped in a DB transaction. Income (credit) example —
`IncomeController::store` (verified):

```php
DB::beginTransaction();
$income = new Income;
$income->category_id = $request->category;
// … title, invoice_id, amount, date, reference, note, payment_method, payment_account_id …
$income->attach     = $this->uploadMedia($request, 'attach', $this->path);
$income->created_by = Auth::guard('web')->user()->id;
$income->save();

if ($request->payment_account_id) {
    $payment_account = PaymentAccount::findOrFail($request->payment_account_id);
    $new_balance = $payment_account->current_balance + $request->amount;   // income = +
    PaymentAccountTransaction::create([
        'payment_account_id' => $request->payment_account_id,
        'transaction_type'   => 'credit',          // money IN
        'amount'             => $request->amount,
        'transaction_date'   => $request->date,
        'title'              => 'Income - ' . $request->title,
        'reference_type'     => 'income',
        'reference_id'       => $income->id,
        'balance_after'      => $new_balance,
        'created_by'         => Auth::id(),
    ]);
    $payment_account->update(['current_balance' => $new_balance]);
}
DB::commit();
```

Expense `store` is the mirror image: `transaction_type = 'debit'`, `reference_type = 'expense'`,
balance `- amount`, **and it refuses to record if the account balance is insufficient**. Expenses
also bump `budget_allocation.spent_amount` and recompute the parent budget when an allocation is
chosen.

**`update`** — re‑validates the same core fields and updates them (attachment via
`updateMedia`). ⚠️ **Update does not create/adjust/reverse the `PaymentAccountTransaction` or the
account balance** — only `store` does. See §6.

**`destroy`** — deletes the attachment then the row. Expense destroy also unwinds the budget
allocation spend. ⚠️ **Destroy does not reverse the payment‑account transaction/balance** either.

`IncomeCategoryController` / `ExpenseCategoryController` are simple CRUD: validation
`'title' => 'required|max:191|unique:{table},title[,id]'`, `slug` generated from title, `status`
flag. Delete is a hard delete (child rows cascade via FK).

### 2.4 Outcome Calculation (`OutcomeCalculationController`)

The income‑vs‑expense report. `index`:

- Date range with defaults (1 year ago → today).
- `total_income` = `SUM(incomes.amount)` where `date BETWEEN … AND status = 1`.
- `total_expense` = same over `expenses`. Net = income − expense (computed in the view).
- Active income/expense categories for breakdown pie charts.
- A **monthly** series for the current year (Jan→current month) for `incomes` and `expenses`,
  JSON‑encoded for a line chart.

`show/{id}` reuses the same logic with a preset range, where `{id}` = number of months back
(`0` = all time, from `2001‑01‑01`).

---

## 3. The Payment Accounts module

### 3.1 Routes (`routes/web.php`, ~550‑581)

```
payment-account[/create|/{id}/edit|/store|/{id}/update|/{id}/delete]
payment-account/{id}/account-book
payment-account/{id}/deposit            (GET form + POST)
payment-account/{id}/withdraw           (GET form + POST)
payment-account/{id}/transactions-data  (AJAX JSON)
payment-account/transaction/{id}/{edit|update|delete}
payment-account-transfer[/...CRUD...]
payment-account-report/{cashflow|statement/{id}|summary|unlinked|link}
```

### 3.2 Data model (migrations `2025_10_09_2000*`)

**`payment_account_types`**: `id, title, slug (unique), description, status (default 1),
timestamps`. `hasMany(PaymentAccount)`. (e.g. "Bank Account", "Cash Box", "Mobile Money".)

**`payment_accounts`**:

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| title | string | account name |
| account_number | string, nullable | bank/wallet ref |
| account_type_id | bigint unsigned, FK → payment_account_types | |
| opening_balance | decimal(15,2), default 0 | initial balance |
| **current_balance** | decimal(15,2), default 0 | **stored running balance** |
| description | text, nullable | |
| status | boolean, default 1 | |
| created_by / updated_by | bigint unsigned, nullable | |
| timestamps | | |

Relationships: `accountType()`, `transactions()` (hasMany, ordered `transaction_date desc, id desc`),
`transfersFrom()`/`transfersTo()` (hasMany PaymentAccountTransfer on `from_account_id`/`to_account_id`),
`creator()`/`updater()`.

**`payment_account_transactions`** — the per‑account ledger:

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| payment_account_id | bigint unsigned, FK (cascade) | |
| transaction_type | enum(debit, credit) | credit = in, debit = out |
| amount | decimal(15,2) | |
| transaction_date | date | |
| title | string, nullable | |
| description | text, nullable | |
| reference_type | string, nullable | `income/expense/fees/transfer/payment_receipts/payment_plan_payments/payrolls/…` or null (manual) |
| reference_id | bigint unsigned, nullable | source row id |
| payment_method | string, nullable | |
| payment_reference | string, nullable | cheque #, transfer id… |
| **balance_after** | decimal(15,2) | running balance snapshot after this row |
| attach | string, nullable | receipt file |
| created_by | bigint unsigned, nullable | |
| timestamps | | |

`reference()` helper resolves the source model for `fees/expense/income/transfer` (see §6 for the
naming caveat).

**`payment_account_transfers`**:

| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| from_account_id | bigint unsigned, FK (restrict) | |
| to_account_id | bigint unsigned, FK (restrict) | |
| amount | decimal(15,2) | |
| transfer_date | date | |
| note | text, nullable | |
| attach | string, nullable | |
| created_by | bigint unsigned, nullable | |
| timestamps | | |

### 3.3 Balance mechanics (`PaymentAccountController`) — all atomic

`current_balance` is mutated in step with each transaction, every operation wrapped in
`DB::beginTransaction()/commit()`:

- **Create account** — if `opening_balance > 0`, seed a `credit` transaction; set
  `current_balance = opening_balance`.
- **Deposit** — `new = current + amount`; create `credit`; `update(['current_balance' => $new])`.
- **Withdraw** — guard first: `if (current_balance < amount) → error`; then `new = current − amount`;
  create `debit`.
- **Edit transaction** — reverses the old amount and applies the new by delta (credit: `+ (new −
  old)`; debit: `+ (old − new)`), updates `balance_after`. **Blocked for linked transactions**
  (`reference_type` in fees/expense/income/payroll).
- **Delete transaction** — reverses its effect (credit → `− amount`; debit → `+ amount`). Also
  blocked for linked transactions.
- **Delete account** — refused if it has any transactions.

> The stored‑balance design is fast and gives a per‑row `balance_after` audit trail, but it means
> the balance can **drift** if rows are edited out of band. A robust port should provide a
> "recompute balance from transactions" maintenance action.

### 3.4 Transfers (`PaymentAccountTransferController`)

A transfer is one `PaymentAccountTransfer` row **plus two** `PaymentAccountTransaction` rows, all
in one DB transaction:

```php
// validate: to_account_id 'different:from_account_id'; from_account balance >= amount
$transfer = PaymentAccountTransfer::create([...]);
// FROM (debit)
$from->update(['current_balance' => $from->current_balance - $amount]);
PaymentAccountTransaction::create(['transaction_type'=>'debit','reference_type'=>'transfer','reference_id'=>$transfer->id, ...]);
// TO (credit)
$to->update(['current_balance' => $to->current_balance + $amount]);
PaymentAccountTransaction::create(['transaction_type'=>'credit','reference_type'=>'transfer','reference_id'=>$transfer->id, ...]);
```

**Edit** restores the old from/to balances, then applies the new ones (re‑checking sufficiency)
and updates both transactions. **Delete** reverses both balances and deletes both transactions +
the transfer. No transfer fees are modelled.

### 3.5 Reports (`PaymentAccountReportController`)

- **Cashflow** — all transactions across accounts with filters (account, date range, type, method,
  reference type); summary computed *before* pagination: `total_credit`, `total_debit`,
  `net_flow`, `transaction_count`, plus total balance across accounts.
- **Account statement / {id}** — single account for a date range. Opening balance = `balance_after`
  of the last transaction *before* `date_from` (else the account's `opening_balance`); period
  credit/debit sums; closing balance = current `current_balance`.
- **Summary** — per‑account credit/debit/net/count for a period + grand totals.
- **Unlinked transactions** — operational records with `payment_account_id IS NULL`: approved
  **fee receipts**, **installment payments**, approved **multi‑payments**, **expenses**,
  **incomes**, **payroll**. Merged, standardised, sorted, paginated.
- **Link** (`POST link`) — assigns a chosen account to one of those records: sets its
  `payment_account_id`, creates the matching `PaymentAccountTransaction` (credit for
  fees/income; debit for expense/payroll, with a balance‑sufficiency check), and adjusts
  `current_balance`. This is the *deferred* counterpart to at‑creation linking.

---

## 4. Integration: how the two modules meet

| Trigger | Effect on Payment Account |
|---|---|
| Create Income with `payment_account_id` | `credit` txn (`reference_type='income'`), balance `+amount` |
| Create Expense with `payment_account_id` | `debit` txn (`reference_type='expense'`), balance `−amount` (needs funds) |
| Link an unlinked record later | same as above, by record type (fees/installment/income → credit; expense/payroll → debit) |
| Manual deposit / withdraw | `credit` / `debit` txn, `reference_type = null` |
| Transfer | one `debit` (from) + one `credit` (to), `reference_type='transfer'` |

`reference_type` + `reference_id` are the soft foreign key from the cash ledger back to the source
fact. There is intentionally **no** hard DB FK from a transaction to the polymorphic source.

---

## 5. Permissions & menu

Permissions (Spatie), seeded in `database/seeders/AccountingPermissionSeeder.php`, gate every
route via `permission:` middleware in each controller's `__construct`:

- **Income/Expense:** `income-{view,create,edit,delete}`, `income-category-{…}`,
  `expense-{…}`, `expense-category-{…}`, `outcome-view`.
- **Payment accounts:** `payment-account-{view,create,edit,delete,book,deposit,withdraw}`,
  `payment-account-transaction-{edit,delete}`.
- **Transfers:** `payment-account-transfer-{view,create,edit,delete}`.
- **Reports:** `payment-account-report-{view,export}`.

Sidebar (`resources/views/admin/layouts/inc/sidebar.blade.php`): the **Income & Expense** group
matches `admin/account*` and is shown via `@canany` of the income/expense/outcome permissions; the
**Payment Accounts** group lists Account List, Fund Transfers and Reports.

---

## 6. Gotchas & decisions to make when porting

These are real behaviours in the source system — decide deliberately when re‑implementing:

1. **Income/Expense edit & delete do NOT touch the payment account.** Only `store` creates the
   `PaymentAccountTransaction` and adjusts `current_balance`. Editing an income's amount, or
   deleting it, leaves the cash ledger and balance untouched → drift. *Recommendation:* mirror
   create logic in update/delete (reverse old, apply new) inside the same DB transaction.
2. **`reference_type` naming is inconsistent.** `store`/transfer write `income`, `expense`,
   `transfer`; the *link* flow writes `incomes`, `expenses`, `payment_receipts`,
   `payment_plan_payments`, `payrolls`; but `PaymentAccountTransaction::reference()` only resolves
   `fees/expense/income/transfer`. *Recommendation:* pick one canonical token per source type and
   use it everywhere (a single enum/const map).
3. **`linkTransaction` (and `unlinkedTransactions`) are not in the report controller's
   `permission:` middleware** `only` lists — they rely solely on `auth:web`. *Recommendation:* gate
   them (e.g. a `payment-account-report-link` permission).
4. **Stored running balance can drift.** Provide a recompute/repair action and consider deriving
   balances from transactions for reports.
5. **No transfer fees / no multi‑currency** are modelled. Add if your institution needs them.
6. **`payment_method` is an integer code on income/expense but a string on transactions** — unify
   if you want consistent reporting.
7. **Optional GL coupling exists in the source** (model observers auto‑post income/expense/fees/
   payroll to a double‑entry ledger). It is **not required** — omit it unless you also build that
   ledger; nothing in these two modules depends on it.

---

## 7. Suggested build order (clean re‑implementation)

1. **Migrations/tables:** `payment_account_types`, `payment_accounts`,
   `payment_account_transactions`, `payment_account_transfers`; `income_categories`,
   `expense_categories`, `incomes`, `expenses` (+ `payment_account_id` on incomes/expenses).
2. **Models + relationships** (and the `Auditable`/file‑upload traits or your equivalents).
3. **Category CRUD** for income & expense (title/slug/description/status).
4. **Income & Expense CRUD** with validation + attachments; *defer* payment‑account linking until
   step 7 so you can test the fact layer alone.
5. **Payment account types + accounts CRUD**, with opening‑balance seeding.
6. **Deposit / withdraw** (atomic balance math + `balance_after`); then **transfers** (two
   transactions + reversal on edit/delete).
7. **At‑creation linking** in income/expense `store` (and, per gotcha #1, also in update/delete).
8. **Reports:** account book / statement, cashflow, summary.
9. **Unlinked transactions + Link** (deferred linking) — and gate it (gotcha #3).
10. **Permissions + sidebar**, then **audit logging** across all models.
11. **Outcome Calculation** report (totals, by category, monthly trend).

---

## 8. File reference index (source system)

| Concern | File |
|---|---|
| Income | `app/Models/Income.php`, `app/Http/Controllers/Admin/IncomeController.php` |
| Income category | `app/Models/IncomeCategory.php`, `…/IncomeCategoryController.php` |
| Expense | `app/Models/Expense.php`, `…/ExpenseController.php` |
| Expense category | `app/Models/ExpenseCategory.php`, `…/ExpenseCategoryController.php` |
| Outcome report | `app/Http/Controllers/Admin/OutcomeCalculationController.php` |
| Payment account | `app/Models/PaymentAccount.php`, `app/Models/PaymentAccountType.php`, `…/PaymentAccountController.php` |
| Transactions | `app/Models/PaymentAccountTransaction.php` |
| Transfers | `app/Models/PaymentAccountTransfer.php`, `…/PaymentAccountTransferController.php` |
| Reports / link | `app/Http/Controllers/Admin/PaymentAccountReportController.php` |
| Routes | `routes/web.php` (~550‑581 payment accounts; ~782‑787 account/income‑expense) |
| Sidebar | `resources/views/admin/layouts/inc/sidebar.blade.php` |
| Permissions | `database/seeders/AccountingPermissionSeeder.php` |
| Migrations | `database/migrations/2021_06_05_*` (income/expense), `2025_10_09_2000*` (payment accounts), `2025_10_09_2100*` (`payment_account_id` columns) |
