<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentFee;
use App\Support\ParentContext;

class ParentDashboardController extends Controller
{
    public function index()
    {
        $guardian = ParentContext::guardian();

        $children = Student::where('guardian_id', $guardian->id)
            ->with(['currentEnrollment.classSection.form', 'currentEnrollment.stream'])
            ->orderBy('first_name')
            ->get();

        // Per-child fee snapshot (outstanding balance across all fees of the current enrollment).
        $feeSnapshots = [];
        foreach ($children as $child) {
            $enrollment = $child->currentEnrollment;
            $balance = 0;
            $netTotal = 0;
            $paidTotal = 0;

            if ($enrollment) {
                $fees = StudentFee::where('student_enrollment_id', $enrollment->id)->get();
                $netTotal = $fees->sum('net_amount');
                $paidTotal = $fees->sum('paid_amount');
                $balance = $fees->sum('balance');
            }

            $feeSnapshots[$child->id] = [
                'net'     => $netTotal,
                'paid'    => $paidTotal,
                'balance' => $balance,
                'percent' => $netTotal > 0 ? round(($paidTotal / $netTotal) * 100) : 0,
            ];
        }

        return view('parent.dashboard', compact('guardian', 'children', 'feeSnapshots'));
    }
}
