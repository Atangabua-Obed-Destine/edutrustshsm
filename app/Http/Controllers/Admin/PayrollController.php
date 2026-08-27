<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AllowanceType;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DeductionType;
use App\Models\Designation;
use App\Models\PaymentAccount;
use App\Models\Payroll;
use App\Models\User;
use App\Models\PaymentAccountTransaction;
use App\Services\PaymentAccountService;
use App\Services\PayrollAccountingService;
use App\Services\TaxCalculationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'payroll';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('payroll.view', ['index']),
            static::can('payroll.generate', ['generate', 'store']),
            static::can('payroll.pay', ['pay']),
            static::can('payroll.unpay', ['unpay']),
            static::can('payroll.report', ['report']),
        ];
    }

    public function __construct(
        private TaxCalculationService $tax,
        private PayrollAccountingService $gl,
        private PaymentAccountService $accounts,
    ) {
    }

    public function index(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $salaryMonth = sprintf('%04d-%02d', $year, $month);

        $staff = User::staff()
            ->when($request->salary_type, fn ($q, $v) => $q->where('salary_type', $v))
            ->when($request->department_id, fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->designation_id, fn ($q, $v) => $q->where('designation_id', $v))
            ->whereNotNull('basic_salary')
            ->orderBy('staff_id')->get();

        $existing = Payroll::where('salary_month', $salaryMonth)->pluck('id', 'user_id');

        return view('admin.hr.payroll.index', [
            'staff' => $staff,
            'existing' => $existing,
            'month' => $month,
            'year' => $year,
            'salaryMonth' => $salaryMonth,
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('title')->get(),
        ]);
    }

    /** The generate screen: compute the payslip (server-side) and preview. */
    public function generate(Request $request, User $staff)
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $salaryMonth = sprintf('%04d-%02d', $year, $month);
        $payDate = Carbon::create($year, $month)->endOfMonth()->toDateString();

        $existing = Payroll::with('details')->where('user_id', $staff->id)->where('salary_month', $salaryMonth)->first();

        $breakdown = $this->tax->calculate((float) $staff->basic_salary, $payDate, $staff);

        return view('admin.hr.payroll.generate', [
            'staff' => $staff,
            'month' => $month,
            'year' => $year,
            'salaryMonth' => $salaryMonth,
            'payDate' => $payDate,
            'existing' => $existing,
            'taxBreakdown' => $breakdown,
            'allowanceTypes' => AllowanceType::where('status', true)->orderBy('title')->get(),
            'deductionTypes' => DeductionType::where('status', true)->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request, User $staff)
    {
        $validated = $request->validate([
            'salary_month' => ['required', 'string'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'array'],
            'allowances.*.title' => ['required_with:allowances', 'string'],
            'allowances.*.amount' => ['required_with:allowances', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'array'],
            'deductions.*.title' => ['required_with:deductions', 'string'],
            'deductions.*.amount' => ['required_with:deductions', 'numeric', 'min:0'],
        ]);

        $payDate = Carbon::parse($validated['salary_month'] . '-01')->endOfMonth()->toDateString();
        $basic = (float) $staff->basic_salary;
        $bonus = (float) ($validated['bonus'] ?? 0);
        $allowances = collect($validated['allowances'] ?? []);
        $deductions = collect($validated['deductions'] ?? []);

        $totalAllowance = (float) $allowances->sum('amount');
        $totalDeduction = (float) $deductions->sum('amount');
        $gross = $basic + $totalAllowance + $bonus - $totalDeduction;

        $breakdown = $this->tax->calculate($gross, $payDate, $staff);
        $employeeTax = $breakdown['employee_tax'];
        $employerTax = $breakdown['employer_tax'];
        $net = $gross - $employeeTax;
        $totalCost = $net + $employeeTax + $employerTax;

        $payroll = DB::transaction(function () use ($staff, $validated, $basic, $bonus, $totalAllowance, $totalDeduction, $gross, $employeeTax, $employerTax, $net, $totalCost, $allowances, $deductions) {
            $payroll = Payroll::updateOrCreate(
                ['user_id' => $staff->id, 'salary_month' => $validated['salary_month']],
                [
                    'basic_salary' => $basic,
                    'salary_type' => $staff->salary_type ?? 1,
                    'total_earning' => $basic,
                    'total_allowance' => $totalAllowance,
                    'bonus' => $bonus,
                    'total_deduction' => $totalDeduction,
                    'gross_salary' => $gross,
                    'tax' => $employeeTax,
                    'employer_tax' => $employerTax,
                    'net_salary' => $net,
                    'total_cost' => $totalCost,
                    'status' => Payroll::STATUS_UNPAID,
                    'created_by' => auth()->id(),
                ]
            );

            $payroll->details()->delete();
            foreach ($allowances as $a) {
                $payroll->details()->create(['title' => $a['title'], 'amount' => $a['amount'], 'status' => 1]);
            }
            foreach ($deductions as $d) {
                $payroll->details()->create(['title' => $d['title'], 'amount' => $d['amount'], 'status' => 0]);
            }

            return $payroll;
        });

        AuditLog::log('generated', Payroll::class, $payroll->id, null, $payroll->toArray());

        return redirect()->route('admin.payroll.generate', ['staff' => $staff->id, 'month' => (int) substr($validated['salary_month'], 5), 'year' => (int) substr($validated['salary_month'], 0, 4)])
            ->with('success', __('Payroll saved.'));
    }

    public function pay(Request $request, Payroll $payroll)
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string'],
            'payment_account_id' => ['nullable', 'exists:payment_accounts,id'],
            'bank_account_id' => ['nullable', 'exists:staff_bank_accounts,id'],
            'pay_date' => ['required', 'date'],
        ]);

        try {
            DB::transaction(function () use ($payroll, $validated) {
                if ($payroll->isPaid()) {
                    throw new RuntimeException(__('This payroll has already been paid.'));
                }

                $payroll->update($validated + ['status' => Payroll::STATUS_PAID]);

                // Money actually leaves the chosen account. Without this the GL
                // recorded the outflow but the treasury balance never moved, so
                // cash overstated by the full payroll every month.
                if ($payroll->payment_account_id) {
                    $account = PaymentAccount::findOrFail($payroll->payment_account_id);
                    $this->accounts->debit($account, $payroll->net_salary, [
                        'reference_type' => PaymentAccountTransaction::REF_PAYROLL,
                        'reference_id' => $payroll->id,
                        'transaction_date' => $payroll->pay_date,
                        'description' => __('Salary payment').' - '.$payroll->salary_month
                            .' - '.($payroll->user?->full_name ?? ''),
                    ]);
                }

                // Post to the OHADA ledger.
                $this->gl->createPayrollJournalEntry($payroll);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log('paid', Payroll::class, $payroll->id, null, null);

        return back()->with('success', __('Payroll paid and posted to the ledger.'));
    }

    public function unpay(Payroll $payroll)
    {
        try {
            DB::transaction(function () use ($payroll) {
                if (! $payroll->isPaid()) {
                    throw new RuntimeException(__('This payroll is not marked as paid.'));
                }

                $this->gl->reversePayrollJournalEntry($payroll);

                // Put the cash back in the account it left.
                $this->accounts->reverseFor(PaymentAccountTransaction::REF_PAYROLL, $payroll->id);

                $payroll->update(['status' => Payroll::STATUS_UNPAID, 'pay_date' => null]);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLog::log('unpaid', Payroll::class, $payroll->id, null, null);

        return back()->with('success', __('Payroll un-paid and reversed in the ledger.'));
    }

    public function report(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $salaryMonth = sprintf('%04d-%02d', $year, $month);

        $payrolls = Payroll::with('user')
            ->where('salary_month', $salaryMonth)
            ->when($request->department_id, fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('department_id', $v)))
            ->when($request->salary_type, fn ($q, $v) => $q->where('salary_type', $v))
            ->get();

        return view('admin.hr.payroll.report', compact('payrolls', 'month', 'year', 'salaryMonth'));
    }

    private function paymentAccounts()
    {
        return PaymentAccount::active()->orderBy('title')->get();
    }
}
