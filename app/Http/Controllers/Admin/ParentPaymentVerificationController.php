<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParentPaymentSubmission;
use App\Services\PaymentRecorder;
use Illuminate\Http\Request;

class ParentPaymentVerificationController extends Controller
{
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

        // Outstanding fees so the reviewer sees what the payment will be allocated to.
        $fees = $submission->enrollment
            ? $submission->enrollment->fees()->with('feeCategory')->get()
            : collect();

        return view('admin.parent-payments.show', compact('submission', 'fees'));
    }

    /** Approve a submission → record the official Payment + allocate it. */
    public function approve(Request $request, ParentPaymentSubmission $submission, PaymentRecorder $recorder)
    {
        if (! $submission->isPending()) {
            return back()->with('error', __('This submission has already been reviewed.'));
        }

        $enrollment = $submission->enrollment;
        if (! $enrollment) {
            return back()->with('error', __('The related enrollment no longer exists.'));
        }

        $payment = $recorder->record([
            'branch_id'             => $submission->branch_id,
            'student_enrollment_id' => $submission->student_enrollment_id,
            'target_fee_id'         => $submission->student_fee_id,
            'amount'                => $submission->amount,
            'payment_method'        => $submission->payment_method,
            'payment_date'          => $submission->payment_date,
            'payer_name'            => $submission->guardian->display_name,
            'payer_phone'           => $submission->guardian->primary_phone,
            'bank_name'             => $submission->bank_name,
            'transaction_ref'       => $submission->transaction_ref,
            'proof_document'        => $submission->receipt_path,
            'received_by'           => auth()->id(),
            'notes'                 => __('Approved from parent portal submission #:id', ['id' => $submission->id]),
        ]);

        $submission->update([
            'status'      => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'payment_id'  => $payment->id,
        ]);

        return redirect()->route('admin.parent-payments.show', $submission)
            ->with('success', __('Payment approved and recorded. Receipt: :r', ['r' => $payment->receipt_number]));
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
