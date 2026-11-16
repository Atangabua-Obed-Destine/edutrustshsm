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
        $query = StudentCredit::with([
            'enrollment.student:id,student_id,first_name,last_name',
            'enrollment.classSection:id,name',
            'payment:id,receipt_number',
        ])
            ->withCount(['refunds as open_refunds_count' => fn ($q) => $q->whereIn('status', ['requested', 'approved'])])
            ->when($request->input('only_available'), fn ($q) => $q->available());

        $list = $this->creditList($request)->filters([
            __('Show only available') => $request->input('only_available') ? __('Yes') : null,
        ]);

        if ($download = $list->export($query)) {
            return $download;
        }

        $credits = $list->paginate($query);

        return view('admin.fees.credits.index', [
            'credits' => $credits,
            'list' => $list,
            'totalAvailable' => (float) StudentCredit::where('balance', '>', 0)->sum('balance'),
            'studentsHolding' => StudentCredit::where('balance', '>', 0)
                ->distinct('student_enrollment_id')->count('student_enrollment_id'),
            'pendingRefunds' => StudentCreditRefund::whereIn('status', [
                StudentCreditRefund::STATUS_REQUESTED, StudentCreditRefund::STATUS_APPROVED,
            ])->count(),
        ]);
    }

    /** The student credit list's columns: what sorts, and what an export writes. */
    private function creditList(Request $request): \App\Support\Listing
    {
        // The pupil's name and class, as values the list can sort on.
        $student = fn (string $column) => StudentEnrollment::query()
            ->join('students', 'students.id', '=', 'student_enrollments.student_id')
            ->whereColumn('student_enrollments.id', 'student_credits.student_enrollment_id')
            ->select('students.'.$column)
            ->limit(1);
        $class = fn () => StudentEnrollment::query()
            ->join('class_sections', 'class_sections.id', '=', 'student_enrollments.class_section_id')
            ->whereColumn('student_enrollments.id', 'student_credits.student_enrollment_id')
            ->select('class_sections.name')
            ->limit(1);

        return new \App\Support\Listing($request, 'student-credits', __('Student Credits'), [
            'student' => ['label' => __('Student'), 'sort' => fn ($q, $dir) => $q->orderBy($student('last_name'), $dir)->orderBy($student('first_name'), $dir),
                'value' => fn ($c) => trim($c->enrollment?->student?->first_name.' '.$c->enrollment?->student?->last_name)],
            'student_id' => ['label' => __('Student ID'), 'value' => fn ($c) => $c->enrollment?->student?->student_id],
            'class' => ['label' => __('Class'), 'sort' => fn ($q, $dir) => $q->orderBy($class(), $dir),
                'value' => fn ($c) => $c->enrollment?->classSection?->name],
            'source' => ['label' => __('Source'), 'sort' => 'student_credits.source', 'value' => fn ($c) => __(ucfirst(str_replace('_', ' ', (string) $c->source)))],
            'receipt' => ['label' => __('Receipt #'), 'value' => fn ($c) => $c->payment?->receipt_number],
            'amount' => ['label' => __('Amount'), 'sort' => 'student_credits.amount', 'money' => true, 'value' => fn ($c) => $c->amount],
            'used' => ['label' => __('Used'), 'sort' => 'student_credits.used_amount', 'money' => true, 'value' => fn ($c) => $c->used_amount],
            'available' => ['label' => __('Available'), 'sort' => 'student_credits.balance', 'money' => true, 'value' => fn ($c) => $c->balance],
            // Newest first, as the list always opened.
            'date' => ['label' => __('Date'), 'sort' => 'student_credits.id', 'value' => fn ($c) => $c->created_at],
        ], default: 'date', defaultDir: 'desc');
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
