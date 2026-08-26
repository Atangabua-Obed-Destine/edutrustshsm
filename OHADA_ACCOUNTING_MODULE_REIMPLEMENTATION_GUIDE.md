# OHADA Accounting Module — Re‑implementation Guide

A Laravel‑oriented reference for rebuilding the **OHADA double‑entry accounting** engine on another
school system. It completes the finance set alongside the accounts/payment‑accounts and budgeting
guides: those record and locate money; **this is the formal general ledger** that turns every
operational event into balanced journal entries and produces statutory statements.

> This documents the **corrected** design (the way to build it). Several real bugs were found and
> fixed during hardening — the right behaviour is described inline, and §10 lists the decisions and
> the still‑open follow‑ups so you don't re‑introduce them.

---

## 1. Philosophy

Three layers, bottom is the source of truth:

| Layer | Role |
|---|---|
| **Operational records** (`Fee`, `Income`, `Expense`, `Payroll`, `PaymentPlanPayment`) | The business facts: money in/out. |
| **Auto‑mapping** (`TransactionAutoMapService` + `DefaultAccountMapping` + observers) | Turns each fact into a balanced 2‑line journal entry, automatically. |
| **General Ledger** (`ChartOfAccount` + `JournalEntry` + `JournalEntryLine`) | The OHADA double‑entry books; everything else is reported from here. |

Principles that make it trustworthy:

- **The ledger (posted journal lines) is the single source of truth.** Every report (trial balance,
  balance sheet, income statement, ledger, aging, cash flow) is **recomputed by summing posted
  `JournalEntryLine` debits/credits** — it does **not** trust the cached `ChartOfAccount.current_balance`.
  (That cached field is only updated by manual `post()` and is effectively advisory; reports ignore it.)
- **Every journal entry is balanced** (debits = credits, 0.01 tolerance) and validated on both
  creation and posting — so the trial balance always balances.
- **Posting is the one chokepoint** (`JournalEntry::post()`): it is atomic, validates balance, and
  refuses to post into a **closed period or closed fiscal year**.
- **Operations post automatically** via model observers, and **stay in sync**: editing/unpaying/
  deleting an operational record reverses or re‑posts its entry (`reverse()`/`remap()`).
- **OHADA‑shaped**: 8 account classes, French/English names, normal‑balance semantics, year‑end
  closing into retained earnings.

End‑to‑end:

```
 Fee/Income/Expense/Payroll saved
        │  model observer
        ▼
 TransactionAutoMapService::autoMap(type, id, categoryId, {amount,date,desc})
        │  looks up DefaultAccountMapping(mapping_type, category)
        ▼
 JournalEntry (balanced, is_system_generated, posted) + 2 JournalEntryLines
        │
        ▼
 General Ledger  ──►  Trial Balance / Balance Sheet / Income Statement (all summed from posted lines)
        │  end of year
        ▼
 Year‑End Closing: zero Class 6 & 7 → income summary → retained earnings
```

---

## 2. Chart of Accounts (`ChartOfAccount`)

Migration `2025_10_10_090637_*`. Key columns:

| Column | Notes |
|---|---|
| account_code | unique, hierarchical string (e.g. `52`, `521`, `5211`) |
| account_name / account_name_fr | EN / FR names |
| parent_id | self‑referential hierarchy (restrict on delete) |
| class_number | OHADA class **1–8** |
| account_type | asset / liability / equity / revenue / expense / other |
| account_category | **detail** (postable) / heading / total / subtotal |
| normal_balance | debit / credit (sign convention) |
| opening_balance, current_balance | cached only; **reports don't rely on these** |
| is_active, is_system | system accounts are protected from edit/deactivate/delete |
| display_order, created_by/updated_by, soft `deleted_at` | |

**OHADA classes:** 1 Capital/Equity · 2 Fixed Assets · 3 Inventory · 4 Third parties (AR/AP) ·
5 Treasury (cash/bank) · 6 Expenses · 7 Revenue · 8 Other. Only **`detail`** accounts can be posted
to (`canPost()` = detail && active). Seeded two‑pass (`OhadaChartOfAccountsSeeder`: create, then link
`parent_id` by code). `ChartOfAccountController` adds CRUD + `toggleStatus` + `getByClass` (AJAX,
postable‑only filter). Guards: can't delete a system account, one with children, or one with entries.

---

## 3. Journal Entries (`JournalEntry` / `JournalEntryLine`)

Migrations `2025_10_10_090640/090641_*`. A `JournalEntry` has `entry_number` (unique),
`entry_date`, `fiscal_year_id`, `accounting_period_id`, `journal_type`
(general/sales/purchase/cash/bank/adjustment/opening/closing), `reference_type`+`reference_id`
(link back to the source fact), `total_debit`/`total_credit`, `is_posted`, `is_system_generated`,
`is_reversed`+`reversed_entry_id`, `posted_by`/`posted_at`, soft deletes. Each `JournalEntryLine` has
`account_id`, `line_number`, `debit`, `credit` (a line is debit **or** credit, never both).

**Creation** (`JournalEntryController@store`): ≥2 lines, each line debit‑or‑credit, **debits must
equal credits** (0.01 tolerance) or it throws; users can create manual entries; period auto‑detected
from the date.

**Posting** (`JournalEntry::post($userId)` — the single chokepoint):
```php
if ($this->is_posted) return false;
if (!$this->isBalanced()) throw ...;                       // debits == credits
if ($this->accountingPeriod?->is_closed) throw ...;        // period lock
if ($this->fiscalYear?->is_closed) throw ...;              // fiscal-year lock
DB::transaction(function () {                               // atomic
    $this->is_posted = true; ... $this->save();
    foreach ($this->lines as $line) {                      // update cached balances
        // debit-normal: current_balance += debit - credit; credit-normal: += credit - debit
    }
});
```
`unpost()` is the atomic mirror (and the controller blocks un‑posting in a closed period).

**Guards:** posted entries **cannot be edited or deleted**; system‑generated entries can't be
deleted; force‑delete refuses posted; `duplicate()` clones as a new draft; soft‑delete `trash`/
`restore`/`forceDelete`. Permissions: `journal-entry-{view,create,edit,delete,post,reverse}`,
`chart-of-accounts-{view,create,edit,delete,activate}`.

---

## 4. Fiscal Years & Accounting Periods

`FiscalYear` (`is_active`, `is_closed`) + `AccountingPeriod` (`is_closed`, `period_number`).
- **Exactly one active** fiscal year, enforced at the model `saving` hook **and** the controller's
  `setActive()`.
- `generatePeriods()` creates **monthly** periods spanning the year.
- **Close** requires all periods closed **and** all entries posted; sets `is_closed`, deactivates.
- Posting into a closed period/year is blocked (§3). Auto‑posting always targets an **open** period
  (it queries `AccountingPeriod where is_closed = false` for the entry date).

---

## 5. Operational → GL integration (auto‑posting)

### 5.1 The service — `TransactionAutoMapService`
- **`autoMap($type, $id, $categoryId, $data)`**: idempotent (skips if an **active** mapping exists);
  finds a `DefaultAccountMapping` by `mapping_type` + category; **`updateOrCreate`s** the
  `TransactionMapping` (so a reversed one can be re‑activated — the pair `(transaction_type,
  transaction_id)` is unique); creates a balanced 2‑line `JournalEntry` and posts it; links the JE
  back on the mapping. If **no mapping is found**, it logs a warning and surfaces a non‑blocking
  flash ("recorded, not posted — configure a mapping"), leaving the record in the unmapped list.
- **`reverse($type, $id)`**: posts a reversal JE (debit/credit swapped) and marks the mapping
  `reversed` — used on unpay/delete.
- **`remap($type, $id, $categoryId, $data)`** = `reverse()` + `autoMap()` — used when a posted
  amount/category changes.

`mapping_type` values: `fee_category`, `income_category`, `expense_category`, `payroll`
(payment‑plan payments reuse `fee_category`).

### 5.2 The models
- **`DefaultAccountMapping`**: `mapping_type`, nullable `category_id`, `debit_account_id`,
  `credit_account_id`, `status`. One row per (type, category) — the rule for posting that category.
- **`TransactionMapping`**: `transaction_type`, `transaction_id` (**unique pair**), debit/credit
  accounts, `amount`, `journal_entry_id`, **`status` (string `active`/`reversed`)**. One row per
  operational fact, pointing at its current posting.

### 5.3 The observers (registered in `AppServiceProvider`)
`Fee`, `Income`, `Expense`, `Payroll`, `PaymentPlanPayment` are observed:
- **created** → `autoMap` (Fee only when `paid_amount > 0 && pay_date` and not under a payment plan).
- **updated** → if unpaid now → `reverse`; if amount/category changed → `remap`.
- **deleted** → `reverse`.
- Each observer reads the source's real `category_id` and passes `{amount, date, description}`.

### 5.4 Payroll — `PayrollAccountingService`
Payroll posts a richer **multi‑line** entry (not a simple 2‑liner): DR salary expense (Class 6),
DR employer charges (Class 6, optional), CR tax payable + social charges payable (Class 4), CR net
cash/bank (Class 5) — with account auto‑detection fallbacks by code/keyword, balanced, posted, and a
matching `TransactionMapping`. `reversePayrollJournalEntry()` posts a swapped reversal and marks the
mapping reversed (used on payroll un‑pay).

---

## 6. Reports — all recomputed from posted journal lines

`GeneralLedgerController`:
- **Account ledger** (`account`): opening balance (sum of posted lines before the start date) +
  running balance through the period.
- **Trial balance** (`trialBalance`): per account, opening + period debit/credit from posted lines;
  balances because every entry is balanced.
- **Balance sheet** (`balanceSheet`): Assets = Class 2/3/5 + Class 4 (debit) ; Liabilities+Equity =
  Class 1 + Class 4 (credit). Net result reaches equity via **retained earnings** (year‑end closing).
- **Income statement** (`incomeStatement`): Class 7 revenue (credit − debit) vs Class 6 expense
  (debit − credit), plus Class 8; net result. ⚠️ It has **two modes** (operational tables vs journal
  lines) — see §10.
- PDF exports work; some Excel exports are stubs.

`AccountingReportsController` (`accounting-reports/*`): receivables aging (Class 41), payables aging
(Class 40), student‑fee aging, cash‑flow statement (indirect), comparative cash flow, and budget‑vs‑
actual — all aggregated from posted `JournalEntryLine` rows filtered by fiscal year / date.

---

## 7. Year‑End Closing (`YearEndClosing`)

Lifecycle `draft → in_progress → pending_approval → completed` (or `reversed`). `calculateClosingAmounts()`
sums Class 7 and Class 6 posted lines for the fiscal year; `generateClosingEntries()` (atomic) builds
**one balanced closing journal entry** and posts it through `post()`:
- debit each revenue account by its credit balance, credit the **income‑summary** account by total
  revenue;
- credit each expense account by its debit balance, debit income summary by total expenses;
- close income summary to **retained earnings** (net result).
Then links the JE and moves to `pending_approval`. Closing a fiscal year requires all periods closed
and all entries posted first.

---

## 8. Peripheral accounting

- **Fixed assets + depreciation** (`FixedAsset`, `DepreciationService`): monthly straight‑line/
  declining schedules; posting a schedule creates the depreciation JE; disposal posts a gain/loss JE.
- **Bank reconciliation** (`BankReconciliationService`): match book lines (posted Class 5 entries)
  to the statement; on complete it optionally posts adjusting JEs (bank charges/interest/NSF).
- **Recurring entries** (`RecurringJournalEntry`, `RecurringEntryService`): templates with a
  frequency + `next_run_date`; due ones generate (and optionally auto‑post) a JE.

---

## 9. Suggested build order

1. **Tables/models:** `chart_of_accounts`, `fiscal_years`, `accounting_periods`, `journal_entries`,
   `journal_entry_lines`, `default_account_mappings`, `transaction_mappings` (status = **string**),
   `year_end_closings`.
2. **Chart of accounts** CRUD + OHADA seeder (two‑pass parent linking) + system‑account guards.
3. **Fiscal years/periods** (single active, monthly period generation, close prerequisites).
4. **Journal entries**: balanced creation, the single `post()`/`unpost()` (atomic + period/year
   lock), edit/delete guards, trash/duplicate.
5. **Default mappings** UI + **`TransactionAutoMapService`** (autoMap / reverse / remap, idempotent,
   updateOrCreate the mapping).
6. **Observers** for Fee/Income/Expense/Payroll/PaymentPlanPayment (create→autoMap, update→reverse/
   remap, delete→reverse), using the real `category_id`. Payroll via its multi‑line service.
7. **Reports** (all summed from posted lines): GL account, trial balance, balance sheet, income
   statement, aging, cash flow.
8. **Year‑end closing** (income summary → retained earnings, atomic) and **peripherals** (fixed
   assets/depreciation, bank rec, recurring).
9. **Permissions + audit logging**.

---

## 10. Gotchas & decisions (learned the hard way — don't repeat)

1. **Status columns must be strings, not booleans.** `transaction_mappings.status` (and
   `year_end_closings.status`) were boolean/enum but the code uses string states — so `active` and
   `reversed` collided and reversal silently failed. Use **string** status everywhere.
2. **Keep the model and table in sync.** `year_end_closings` was missing columns the model used
   (`checklist`, `closing_journal_entry_id`, `started_at`, …) and its status enum lacked
   `draft`/`pending_approval` — the feature errored on save. Add every column the model writes.
3. **Reports must read posted journal lines, not `current_balance`.** Auto‑posting sets
   `is_posted` directly (doesn't call `post()`), so `current_balance` is unreliable — never report
   from it. (Either compute on demand, as here, or make every posting path update it reliably.)
4. **Post atomically + lock closed periods.** `post()`/`unpost()` must be transactional, and posting
   into a closed period/fiscal year must be refused (else "closed" means nothing).
5. **Keep the GL in sync on edits.** Observers must `reverse` on unpay/delete and `remap` on
   amount/category change — otherwise the ledger drifts from the operational records. Mind the
   unique `(transaction_type, transaction_id)` mapping key (re‑map reuses the row).
6. **Surface missing mappings.** If no `DefaultAccountMapping` exists, the fact is saved but **not**
   posted; make that visible (flash + unmapped list) rather than silent.
7. **Still‑open follow‑ups (decide before relying on it as the sole statutory ledger):**
   - The **income statement has two sources** (operational tables vs journal lines) that can
     disagree — pick one (the journal) for the official P&L.
   - **Two entry‑number formats** coexist (`JE-000001` from auto‑map vs `JE-YYYY-0001` from manual/
     duplicate) — unify.
   - **Not everything is journalized**: payment‑account deposits/withdrawals/transfers and
     StudentCredit refunds don't post to the GL — add if you need them on the books.

---

## 11. File reference index (source system)

| Concern | File |
|---|---|
| Chart of accounts | `app/Models/ChartOfAccount.php`, `app/Http/Controllers/Admin/ChartOfAccountController.php`, `database/seeders/OhadaChartOfAccountsSeeder.php` |
| Journal entries | `app/Models/JournalEntry.php`, `app/Models/JournalEntryLine.php`, `app/Http/Controllers/Admin/JournalEntryController.php` |
| Fiscal years/periods | `app/Models/FiscalYear.php`, `app/Models/AccountingPeriod.php`, `app/Http/Controllers/Admin/FiscalYearController.php` |
| Auto‑posting | `app/Services/TransactionAutoMapService.php`, `app/Models/DefaultAccountMapping.php`, `app/Models/TransactionMapping.php`, `app/Http/Controllers/Admin/AccountMappingController.php` |
| Observers | `app/Observers/{Fee,Income,Expense,Payroll,PaymentPlanPayment}Observer.php` (registered in `app/Providers/AppServiceProvider.php`) |
| Payroll posting | `app/Services/PayrollAccountingService.php` |
| GL reports | `app/Http/Controllers/Admin/GeneralLedgerController.php`, `app/Http/Controllers/Admin/AccountingReportsController.php`, services in `app/Services/Accounting/*` |
| Year‑end closing | `app/Models/YearEndClosing.php`, `app/Http/Controllers/Admin/YearEndClosingController.php` |
| Peripherals | `app/Models/FixedAsset.php`, `app/Services/Accounting/{DepreciationService,BankReconciliationService,RecurringEntryService}.php` |
| Routes | `routes/web.php` (~585‑690) |
| Permissions | `database/seeders/AccountingPermissionSeeder.php` |
| Migrations (incl. hardening fixes) | `database/migrations/2025_10_10_0906*` (core), `2025_12_04_*` / `2025_12_05_*` (mappings, year‑end), `2026_06_14_00000{1,2,3}_*` (status→string, YEC columns) |
