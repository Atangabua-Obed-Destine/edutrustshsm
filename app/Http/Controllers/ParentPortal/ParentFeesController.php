<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\StudentFee;
use App\Support\ParentContext;

class ParentFeesController extends Controller
{
    public function index(int $student)
    {
        $studentModel = ParentContext::authorizeStudent($student);
        $enrollment = $studentModel->currentEnrollment;

        $fees = collect();
        $totals = ['net' => 0, 'paid' => 0, 'balance' => 0];

        if ($enrollment) {
            $fees = StudentFee::where('student_enrollment_id', $enrollment->id)
                ->with('feeCategory')
                ->orderBy('id')
                ->get();

            $totals = [
                'net'     => $fees->sum('net_amount'),
                'paid'    => $fees->sum('paid_amount'),
                'balance' => $fees->sum('balance'),
            ];
        }

        // Recent verified payments for this enrollment (the official receipts).
        $payments = $enrollment
            ? Payment::where('student_enrollment_id', $enrollment->id)
                ->where('verification_status', 'verified')
                ->orderByDesc('payment_date')
                ->limit(15)
                ->get()
            : collect();

        return view('parent.fees.index', compact(
            'studentModel', 'enrollment', 'fees', 'totals', 'payments'
        ));
    }
}
