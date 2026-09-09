<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\ParentPaymentSubmission;
use App\Models\StudentFee;
use App\Support\ParentContext;
use Illuminate\Http\Request;

class ParentPaymentController extends Controller
{
    /** Fees assigned to each child (with pay buttons) + the submission history. */
    public function index()
    {
        $guardian = ParentContext::guardian();
        $children = ParentContext::children()
            ->load(['currentEnrollment.classSection.form', 'currentEnrollment.academicSession']);

        // Per-child assigned fees (mirrors the admin assignment-history breakdown).
        $feesByChild = [];
        foreach ($children as $child) {
            $enrollment = $child->currentEnrollment;
            if (! $enrollment) {
                continue;
            }

            $fees = StudentFee::where('student_enrollment_id', $enrollment->id)
                ->with('feeCategory')
                ->orderBy('fee_category_id')
                ->get();

            // Pending submissions already targeting a fee, so we can flag "under review".
            $pendingFeeIds = ParentPaymentSubmission::where('guardian_id', $guardian->id)
                ->where('student_enrollment_id', $enrollment->id)
                ->where('status', 'pending')
                ->pluck('student_fee_id')
                ->filter()
                ->all();

            $feesByChild[$child->id] = [
                'student'       => $child,
                'enrollment'    => $enrollment,
                'fees'          => $fees,
                'pendingFeeIds' => $pendingFeeIds,
                'totals'        => [
                    'net'     => $fees->sum('net_amount'),
                    'paid'    => $fees->sum('paid_amount'),
                    'balance' => $fees->sum('balance'),
                ],
            ];
        }

        $submissions = ParentPaymentSubmission::where('guardian_id', $guardian->id)
            ->with(['enrollment.student', 'enrollment.classSection.form', 'studentFee.feeCategory'])
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('parent.payments.index', compact('feesByChild', 'submissions'));
    }

    /** Upload form. Optionally pre-selects a child via ?student_id= and a fee via ?fee_id=. */
    public function create(Request $request)
    {
        $children = ParentContext::children()->load('currentEnrollment.classSection.form');

        $selectedStudent = null;
        $fees = collect();
        $selectedFee = null;

        if ($studentId = $request->query('student_id')) {
            $selectedStudent = $children->firstWhere('id', (int) $studentId);
            if ($selectedStudent && $selectedStudent->currentEnrollment) {
                $fees = StudentFee::where('student_enrollment_id', $selectedStudent->currentEnrollment->id)
                    ->where('balance', '>', 0)
                    ->with('feeCategory')
                    ->get();

                if ($feeId = $request->query('fee_id')) {
                    $selectedFee = $fees->firstWhere('id', (int) $feeId);
                }
            }
        }

        return view('parent.payments.create', compact('children', 'selectedStudent', 'fees', 'selectedFee'));
    }

    public function store(Request $request)
    {
        $guardian = ParentContext::guardian();

        $validated = $request->validate([
            'student_id'      => ['required', 'integer'],
            'fee_id'          => ['nullable', 'integer'],
            'amount'          => ['required', 'numeric', 'min:1'],
            'payment_method'  => ['required', 'in:bank_transfer,mtn_momo,orange_money,cash'],
            'bank_name'       => ['nullable', 'string', 'max:120'],
            'transaction_ref' => ['nullable', 'string', 'max:120'],
            'payment_date'    => ['required', 'date', 'before_or_equal:today'],
            'receipt'         => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        // Security: the student must belong to this guardian.
        $student = ParentContext::authorizeStudent((int) $validated['student_id']);
        $enrollment = $student->currentEnrollment;

        if (! $enrollment) {
            return back()->with('error', __('This student has no active enrollment to pay against.'))->withInput();
        }

        // If a fee was targeted, confirm it belongs to this student's enrollment.
        $feeId = null;
        if (! empty($validated['fee_id'])) {
            $fee = StudentFee::where('id', $validated['fee_id'])
                ->where('student_enrollment_id', $enrollment->id)
                ->first();
            $feeId = $fee?->id;
        }

        $path = $request->file('receipt')->store('parent-receipts', 'local');

        ParentPaymentSubmission::create([
            'branch_id'             => $student->branch_id,
            'guardian_id'           => $guardian->id,
            'student_enrollment_id' => $enrollment->id,
            'student_fee_id'        => $feeId,
            'amount'                => $validated['amount'],
            'payment_method'        => $validated['payment_method'],
            'bank_name'             => $validated['bank_name'] ?? null,
            'transaction_ref'       => $validated['transaction_ref'] ?? null,
            'payment_date'          => $validated['payment_date'],
            'receipt_path'          => $path,
            'notes'                 => $validated['notes'] ?? null,
            'status'                => 'pending',
        ]);

        return redirect()->route('parent.payments.index')
            ->with('success', __('Your payment receipt has been submitted. The school will verify and record it shortly.'));
    }

    /** View one submission's status. */
    public function show(ParentPaymentSubmission $submission)
    {
        $guardian = ParentContext::guardian();
        abort_if($submission->guardian_id !== $guardian->id, 403);

        $submission->load(['enrollment.student', 'enrollment.classSection.form', 'reviewedBy', 'payment', 'studentFee.feeCategory']);

        return view('parent.payments.show', compact('submission'));
    }
}
