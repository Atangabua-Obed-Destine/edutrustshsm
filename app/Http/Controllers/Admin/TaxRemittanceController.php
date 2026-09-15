<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Models\TaxRemittance;
use App\Services\TaxRemittanceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use RuntimeException;

/**
 * Paying withheld payroll tax over to the authority it was withheld for.
 *
 * @see TaxRemittanceService for why the unit is the salary month and the
 *      figure owed comes from the ledger
 */
class TaxRemittanceController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'tax-remittance';

    public function __construct(private TaxRemittanceService $remittances)
    {
    }

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('tax-remittance.void', ['void']),
        ];
    }

    public function index()
    {
        $rows = $this->remittances->outstanding();
        $owing = array_values(array_filter($rows, fn ($r) => $r['outstanding'] > 0.005));

        return view('admin.hr.tax.remittances', [
            'rows' => $rows,
            'oldestOwing' => $owing[0] ?? null,
            'totalOwing' => round(array_sum(array_column($owing, 'outstanding')), 2),
            'reconciliation' => $this->remittances->reconciliation(),
            'sourceAccounts' => $this->remittances->sourceAccounts(),
            'paymentAccounts' => PaymentAccount::active()->orderBy('title')->get(['id', 'title', 'current_balance']),
            'remittances' => TaxRemittance::with(['liabilityAccount', 'sourceAccount', 'paymentAccount', 'createdBy', 'voidedBy'])
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
            'monthLabel' => fn (string $m) => $this->remittances->monthLabel($m),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'liability_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'salary_month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'source_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'payment_account_id' => ['nullable', 'exists:payment_accounts,id'],
            'reference' => ['nullable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $remittance = $this->remittances->record($validated + [
                'allow_overpayment' => $request->boolean('allow_overpayment'),
                'allow_additional' => $request->boolean('allow_additional'),
            ]);
        } catch (RuntimeException $e) {
            // Expected refusals — a month already paid, more than is owed, a
            // source that is not cash — name their reason, so it is shown.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.tax-remittances.index')
            ->with('success', __('Remittance for :month recorded and posted.', [
                'month' => $this->remittances->monthLabel($remittance->salary_month),
            ]));
    }

    public function void(Request $request, TaxRemittance $remittance)
    {
        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $this->remittances->void($remittance, $validated['void_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('The payment was voided and reversed in the ledger.'));
    }
}
