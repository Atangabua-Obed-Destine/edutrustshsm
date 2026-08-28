<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\ChartOfAccount;
use App\Models\DefaultAccountMapping;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\PaymentAllocation;
use App\Models\TransactionMapping;
use App\Services\TransactionAutoMapService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

class AccountMappingController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'account-mapping';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('account-mapping.view', ['index', 'unmapped']),
            static::can('account-mapping.edit', ['save']),
            static::can('account-mapping.post', ['postUnmapped']),
        ];
    }

    public function __construct(private TransactionAutoMapService $mapper)
    {
    }

    public function index()
    {
        $accounts = ChartOfAccount::postable()->orderBy('account_code')->get();
        $mappings = DefaultAccountMapping::get()->keyBy(fn ($m) => $m->mapping_type . ':' . ($m->category_id ?? 'all'));

        $groups = [
            'income' => IncomeCategory::where('status', true)->orderBy('title')->get(['id', 'title']),
            'expense' => ExpenseCategory::where('status', true)->orderBy('title')->get(['id', 'title']),
            // Fee revenue posts per allocation, so each fee category can carry
            // its own rule (tuition vs boarding vs PTA levy).
            'fee_payment' => FeeCategory::where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($c) => (object) ['id' => $c->id, 'title' => $c->name]),
            // Over-payments held on account, and those credits later applied.
            // Both are catch-all rules — they do not vary by fee category.
            'student_credit' => collect(),
            'credit_applied' => collect(),
        ];

        return view('admin.accounting.mappings.index', compact('accounts', 'mappings', 'groups'));
    }

    /** Save one mapping rule (type + optional category → debit/credit accounts). */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'mapping_type' => ['required', 'in:income,expense,fee_payment,student_credit,credit_applied'],
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
        // Shares unmappedFacts() with the backfill action, so the list you see
        // is exactly the list "Post to Ledger" will act on.
        $transactions = $this->unmappedFacts()->sortByDesc('date')->values();

        return view('admin.accounting.mappings.unmapped', compact('transactions'));
    }

    /**
     * Post every currently-unmapped fact through the auto-mapper.
     *
     * Auto-posting fails silently when no DefaultAccountMapping exists — the
     * service logs a warning and returns false, and the observers ignore it. So
     * facts recorded before the mappings were configured never reach the ledger
     * and nothing retries them. This is that retry.
     */
    public function postUnmapped()
    {
        $posted = 0;
        $skipped = 0;

        foreach ($this->unmappedFacts() as $fact) {
            $ok = $this->mapper->autoMap($fact->type, $fact->id, $fact->category_id, [
                'amount' => $fact->amount,
                'date' => $fact->date ? Carbon::parse($fact->date)->toDateString() : now()->toDateString(),
                'description' => $fact->label,
            ]);

            $ok ? $posted++ : $skipped++;
        }

        if ($posted === 0 && $skipped === 0) {
            return back()->with('success', __('Nothing to post — every transaction is already in the ledger.'));
        }

        $message = trans_choice(':count transaction posted to the ledger.|:count transactions posted to the ledger.', $posted, ['count' => $posted]);
        if ($skipped > 0) {
            $message .= ' ' . trans_choice(':count still has no account mapping.|:count still have no account mapping.', $skipped, ['count' => $skipped]);
        }

        return back()->with($skipped > 0 ? 'error' : 'success', $message);
    }

    /**
     * Operational facts with no active TransactionMapping, with the category id
     * the auto-mapper needs to pick a rule.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function unmappedFacts()
    {
        $mappedIds = fn (string $type) => TransactionMapping::where('transaction_type', $type)
            ->where('status', 'active')->pluck('transaction_id');

        $rows = collect();

        foreach ([
            ['income', Income::class, 'title'],
            ['expense', Expense::class, 'title'],
        ] as [$type, $model, $label]) {
            $model::whereNotIn('id', $mappedIds($type))->orderBy('id')->get()
                ->each(function ($r) use (&$rows, $type, $label) {
                    $rows->push((object) [
                        'type' => $type,
                        'id' => $r->id,
                        'label' => $r->{$label},
                        'amount' => $r->amount,
                        'date' => $r->date,
                        'category_id' => $r->category_id,
                    ]);
                });
        }

        // Fee revenue is posted per allocation, so that is the unit here too.
        PaymentAllocation::with(['payment', 'studentFee.feeCategory'])
            ->whereNotIn('id', $mappedIds('fee_payment'))
            ->orderBy('id')
            ->get()
            ->each(function (PaymentAllocation $a) use (&$rows) {
                $rows->push((object) [
                    'type' => 'fee_payment',
                    'id' => $a->id,
                    'label' => trim(($a->studentFee?->feeCategory?->name ?? __('Fee'))
                        .' - '.($a->payment?->receipt_number ?? ''), ' -'),
                    'amount' => $a->amount,
                    'date' => $a->payment?->payment_date,
                    'category_id' => $a->studentFee?->fee_category_id,
                ]);
            });

        return $rows;
    }
}
