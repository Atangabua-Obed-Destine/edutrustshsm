<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\DefaultAccountMapping;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Payment;
use App\Models\TransactionMapping;
use Illuminate\Http\Request;

class AccountMappingController extends Controller
{
    public function index()
    {
        $accounts = ChartOfAccount::postable()->orderBy('account_code')->get();
        $mappings = DefaultAccountMapping::get()->keyBy(fn ($m) => $m->mapping_type . ':' . ($m->category_id ?? 'all'));

        $groups = [
            'income' => IncomeCategory::where('status', true)->orderBy('title')->get(['id', 'title']),
            'expense' => ExpenseCategory::where('status', true)->orderBy('title')->get(['id', 'title']),
        ];

        return view('admin.accounting.mappings.index', compact('accounts', 'mappings', 'groups'));
    }

    /** Save one mapping rule (type + optional category → debit/credit accounts). */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'mapping_type' => ['required', 'in:income,expense,fee_payment'],
            'category_id' => ['nullable', 'integer'],
            'debit_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'credit_account_id' => ['required', 'exists:chart_of_accounts,id', 'different:debit_account_id'],
        ]);

        DefaultAccountMapping::updateOrCreate(
            ['mapping_type' => $validated['mapping_type'], 'category_id' => $validated['category_id'] ?: null],
            [
                'debit_account_id' => $validated['debit_account_id'],
                'credit_account_id' => $validated['credit_account_id'],
                'status' => 'active',
            ]
        );

        return back()->with('success', __('Mapping saved.'));
    }

    /** Operational facts that have no posted journal entry yet (no/inactive mapping). */
    public function unmapped()
    {
        $mappedIds = fn (string $type) => TransactionMapping::where('transaction_type', $type)
            ->where('status', 'active')->pluck('transaction_id');

        $rows = collect();
        foreach ([
            ['income', Income::class, 'title'],
            ['expense', Expense::class, 'title'],
            ['fee_payment', Payment::class, 'receipt_number'],
        ] as [$type, $model, $label]) {
            $model::whereNotIn('id', $mappedIds($type))->latest('id')->limit(100)->get()
                ->each(function ($r) use (&$rows, $type, $label) {
                    $rows->push((object) [
                        'type' => $type,
                        'id' => $r->id,
                        'label' => $r->{$label},
                        'amount' => $r->amount,
                        'date' => $r->date ?? $r->payment_date,
                    ]);
                });
        }

        $transactions = $rows->sortByDesc('date')->values();

        return view('admin.accounting.mappings.unmapped', compact('transactions'));
    }
}
