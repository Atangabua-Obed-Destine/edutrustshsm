<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\ParentPaymentSubmission;
use App\Services\PaymentRecorder;
use App\Exceptions\AlreadyReviewed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ParentPaymentVerificationController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'parent-payment';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('parent-payment.view', ['index', 'show']),
            static::can('parent-payment.approve', ['approve']),
            static::can('parent-payment.reject', ['reject']),
        ];
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $submissions = ParentPaymentSubmission::query()
            ->when(in_array($status, ['pending', 'approved', 'rejected']), fn ($q) => $q->where('status', $status))
            ->with(['guardian', 'enrollment.student', 'enrollment.classSection.form', 'studentFee.feeCategory', 'reviewedBy'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $pendingCount = ParentPaymentSubmission::where('status', 'pending')->count();

        return view('admin.parent-payments.index', compact('submissions', 'status', 'pendingCount'));
    }

    public function show(ParentPaymentSubmission $submission)
    {
        $submission->load([
            'guardian',
            'enrollment.student',
            'enrollment.classSection.form',
            'studentFee.feeCategory',
            'reviewedBy',
            'payment.allocations.studentFee.feeCategory',
        ]);

        // Outstanding fees so the reviewer sees what the payment will be
        // allocated to, last year's arrears included: they are paid first.
        $fees = $submission->enrollment
            ? \App\Models\StudentFee::owedOn($submission->enrollment)->with(['feeCategory', 'enrollment.academicSession'])->get()
            : collect();

        $sameReference = $this->sameReference($submission);

        return view('admin.parent-payments.show', compact('submission', 'fees', 'sameReference'));
    }

    /** Approve a submission → record the official Payment + allocate it. */
    public function approve(Request $request, ParentPaymentSubmission $submission, PaymentRecorder $recorder)
    {
        if (! $submission->enrollment) {
            return back()->with('error', __('The related enrollment no longer exists.'));
        }

        // The same bank or mobile-money reference already paid for something:
        // most often one transfer sent in twice. The reviewer checks the
        // statement and says so before it can count again.
        if ($this->sameReference($submission)->isNotEmpty() && ! $request->boolean('reference_checked')) {
            return back()->with('error', __('This reference has already been used. Check the bank or mobile-money statement, then tick the box to confirm it is a different payment.'));
        }

        // One transaction, with the pending check re-read under a row lock. The
        // payment used to be created in its own transaction and the submission
        // updated outside it, so a failure there left an orphaned receipt on a
        // still-pending submission, and a double-click recorded it twice.
        try {
            $payment = DB::transaction(function () use ($submission, $recorder) {
                $locked = ParentPaymentSubmission::whereKey($submission->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->isPending()) {
                    throw new AlreadyReviewed();
                }

                $payment = $recorder->record([
                    'branch_id'             => $locked->branch_id,
                    'student_enrollment_id' => $locked->student_enrollment_id,
                    'target_fee_id'         => $locked->student_fee_id,
                    'amount'                => $locked->amount,
                    'payment_method'        => $locked->payment_method,
                    'payment_date'          => $locked->payment_date,
                    'payer_name'            => $locked->guardian->display_name,
                    'payer_phone'           => $locked->guardian->primary_phone,
                    'bank_name'             => $locked->bank_name,
                    'transaction_ref'       => $locked->transaction_ref,
                    'proof_document'        => $locked->receipt_path,
                    'received_by'           => auth()->id(),
                    'notes'                 => __('Approved from parent portal submission #:id', ['id' => $locked->id]),
                ]);

                $locked->update([
                    'status'      => 'approved',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'payment_id'  => $payment->id,
                ]);

                return $payment;
            });
        } catch (AlreadyReviewed) {
            return back()->with('error', __('This submission has already been reviewed.'));
        }

        return redirect()->route('admin.parent-payments.show', $submission)
            ->with('success', __('Payment approved and recorded. Receipt: :r', ['r' => $payment->receipt_number]));
    }

    /**
     * Receipts and other parent submissions carrying the same transaction
     * reference. Short references ("1", "ok") say nothing and are ignored.
     *
     * @return \Illuminate\Support\Collection<int, string> one line per earlier use
     */
    private function sameReference(ParentPaymentSubmission $submission)
    {
        $ref = trim((string) $submission->transaction_ref);
        if (mb_strlen($ref) < 4) {
            return collect();
        }

        $payments = \App\Models\Payment::with('enrollment.student')
            ->where('transaction_ref', $ref)
            ->where('verification_status', 'verified')
            ->when($submission->payment_id, fn ($q) => $q->where('id', '!=', $submission->payment_id))
            ->get()
            ->map(fn ($p) => __('Receipt :r of :date for :name, :amount', [
                'r' => $p->receipt_number, 'date' => $p->payment_date?->format('d/m/Y'),
                'name' => $p->enrollment?->student?->full_name, 'amount' => number_format((float) $p->amount),
            ]));

        $others = ParentPaymentSubmission::with('enrollment.student')
            ->where('transaction_ref', $ref)
            ->where('id', '!=', $submission->id)
            ->where('status', 'pending')
            ->get()
            ->map(fn ($s) => __('Another submission waiting for review, for :name, :amount', [
                'name' => $s->enrollment?->student?->full_name, 'amount' => number_format((float) $s->amount),
            ]));

        return $payments->concat($others)->values();
    }

    public function reject(Request $request, ParentPaymentSubmission $submission)
    {
        if (! $submission->isPending()) {
            return back()->with('error', __('This submission has already been reviewed.'));
        }

        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:500'],
        ]);

        $submission->update([
            'status'       => 'rejected',
            'reviewed_by'  => auth()->id(),
            'reviewed_at'  => now(),
            'review_notes' => $validated['review_notes'],
        ]);

        return redirect()->route('admin.parent-payments.index')
            ->with('success', __('Submission rejected. The parent will see your note.'));
    }
}
