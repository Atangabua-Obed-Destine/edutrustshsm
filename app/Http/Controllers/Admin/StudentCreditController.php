<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\PaymentAccount;
use App\Models\StudentCredit;
use App\Models\StudentCreditRefund;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Services\StudentCreditRefundService;
use App\Services\StudentCreditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use RuntimeException;

class StudentCreditController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'student-credit';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('student-credit.apply', ['apply']),
            static::can('student-credit.request-refund', ['requestRefund']),
            static::can('student-credit.approve-refund', ['approveRefund', 'rejectRefund']),
            static::can('student-credit.process-refund', ['processRefund']),
        ];
    }

    public function index(Request $request)
    {
        $credits = StudentCredit::with([
            'enrollment.student:id,student_id,first_name,last_name',
            'enrollment.classSection:id,name',
            'payment:id,receipt_number',
        ])
            ->withCount(['refunds as open_refunds_count' => fn ($q) => $q->whereIn('status', ['requested', 'approved'])])
            ->when($request->input('only_available'), fn ($q) => $q->available())
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.fees.credits.index', [
            'credits' => $credits,
            'totalAvailable' => (float) StudentCredit::where('balance', '>', 0)->sum('balance'),
            'studentsHolding' => StudentCredit::where('balance', '>', 0)
                ->distinct('student_enrollment_id')->count('student_enrollment_id'),
            'pendingRefunds' => StudentCreditRefund::whereIn('status', [
                StudentCreditRefund::STATUS_REQUESTED, StudentCreditRefund::STATUS_APPROVED,
            ])->count(),
        ]);
    }

    /** Apply a student's available credit to their outstanding fees. */
    public function apply(Request $request, StudentCreditService $credits)
    {
        $validated = $request->validate([
            'student_enrollment_id' => ['required', 'exists:student_enrollments,id'],
            'student_fee_id' => ['nullable', 'exists:student_fees,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $enrollment = StudentEnrollment::findOrFail($validated['student_enrollment_id']);

        try {
            $payment = $credits->apply(
                $enrollment,
                $validated['student_fee_id'] ?? null,
                isset($validated['amount']) ? (float) $validated['amount'] : null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Credit applied. Receipt: :r', ['r' => $payment->receipt_number]));
    }

    /** One credit: where it came from, and every refund against it. */
    public function show(StudentCredit $credit, StudentCreditRefundService $refunds)
    {
        $credit->load([
            'enrollment.student', 'enrollment.classSection', 'payment',
            'refunds.requestedBy', 'refunds.reviewedBy', 'refunds.processedBy', 'refunds.paymentAccount',
        ]);

        return view('admin.fees.credits.show', [
            'credit' => $credit,
            'refundable' => $refunds->refundable($credit),
            'accounts' => PaymentAccount::active()->orderBy('title')->get(['id', 'title', 'current_balance']),
            'methods' => StudentCreditRefund::METHODS,
        ]);
    }

    public function requestRefund(Request $request, StudentCredit $credit, StudentCreditRefundService $refunds)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $refunds->request($credit, (float) $validated['amount'], $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.student-credits.show', $credit)
            ->with('success', __('Refund requested. It needs approval before it can be paid.'));
    }

    public function approveRefund(StudentCreditRefund $refund, StudentCreditRefundService $refunds)
    {
        try {
            $refunds->approve($refund);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.student-credits.show', $refund->student_credit_id)
            ->with('success', __('Refund approved. It can now be paid out.'));
    }

    public function rejectRefund(Request $request, StudentCreditRefund $refund, StudentCreditRefundService $refunds)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $refunds->reject($refund, $validated['rejection_reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.student-credits.show', $refund->student_credit_id)
            ->with('success', __('Refund rejected. The amount is available on the credit again.'));
    }

    public function processRefund(Request $request, StudentCreditRefund $refund, StudentCreditRefundService $refunds)
    {
        $validated = $request->validate([
            'method' => ['required', 'in:'.implode(',', array_keys(StudentCreditRefund::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'payment_account_id' => ['nullable', 'exists:payment_accounts,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $refunds->process(
                $refund,
                $validated['method'],
                $validated['reference'] ?? null,
                isset($validated['payment_account_id']) ? (int) $validated['payment_account_id'] : null,
                $validated['note'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $redirect = redirect()->route('admin.student-credits.show', $refund->student_credit_id)
            ->with('success', __('Refund paid out.'));

        // Paid, but say plainly when it did not reach the ledger rather than
        // letting the books quietly disagree with the cash.
        if (! $result['posted']) {
            $redirect->with('error', __('The refund was not posted to the ledger because no "Credit Refunded to Families" account mapping is configured.'));
        }

        return $redirect;
    }
}
