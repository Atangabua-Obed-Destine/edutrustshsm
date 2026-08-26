<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\Payment;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeReportController extends Controller
{
    public function index(Request $request)
    {
        $currentSession = AcademicSession::current();
        $sessionId = $request->input('session_id', $currentSession?->id);
        $sessions = AcademicSession::orderByDesc('start_date')->get();

        // Overall stats
        $totalExpected = StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))->sum('net_amount');
        $totalCollected = StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))->sum('paid_amount');
        $totalOutstanding = StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))->sum('balance');
        $totalDiscounts = StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))->sum('discount_amount');

        $collectionRate = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 1) : 0;

        // Collection by category
        $byCategory = StudentFee::select(
                'fee_category_id',
                DB::raw('SUM(net_amount) as expected'),
                DB::raw('SUM(paid_amount) as collected'),
                DB::raw('SUM(balance) as outstanding'),
                DB::raw('COUNT(*) as student_count')
            )
            ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))
            ->groupBy('fee_category_id')
            ->with('feeCategory')
            ->get();

        // Collection by class
        $byClass = StudentFee::select(
                'student_enrollment_id',
                DB::raw('SUM(net_amount) as expected'),
                DB::raw('SUM(paid_amount) as collected'),
                DB::raw('SUM(balance) as outstanding')
            )
            ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))
            ->groupBy('student_enrollment_id')
            ->get();

        // Regroup by class section
        $classStats = [];
        foreach ($byClass as $row) {
            $enrollment = StudentEnrollment::with('classSection.form')->find($row->student_enrollment_id);
            if (!$enrollment || !$enrollment->classSection) continue;
            $className = $enrollment->classSection->name;
            if (!isset($classStats[$className])) {
                $classStats[$className] = ['expected' => 0, 'collected' => 0, 'outstanding' => 0, 'students' => 0];
            }
            $classStats[$className]['expected'] += $row->expected;
            $classStats[$className]['collected'] += $row->collected;
            $classStats[$className]['outstanding'] += $row->outstanding;
            $classStats[$className]['students']++;
        }
        ksort($classStats);

        // Payment method breakdown
        $byMethod = Payment::select(
                'payment_method',
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))
            ->groupBy('payment_method')
            ->get();

        // Monthly collection trend
        $monthlyTrend = Payment::select(
                DB::raw("DATE_FORMAT(payment_date, '%Y-%m') as month"),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Defaulters (students with outstanding balance)
        $defaulters = StudentFee::with(['enrollment.student', 'enrollment.classSection.form'])
            ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId)->where('status', 'active'))
            ->where('balance', '>', 0)
            ->get()
            ->groupBy('student_enrollment_id')
            ->map(function ($fees) {
                $first = $fees->first();
                return [
                    'student' => $first->enrollment->student,
                    'class' => $first->enrollment->classSection->name ?? 'N/A',
                    'total_balance' => $fees->sum('balance'),
                    'total_fees' => $fees->sum('net_amount'),
                    'total_paid' => $fees->sum('paid_amount'),
                ];
            })
            ->sortByDesc('total_balance')
            ->take(50);

        return view('admin.fees.reports.index', compact(
            'sessions', 'sessionId', 'totalExpected', 'totalCollected',
            'totalOutstanding', 'totalDiscounts', 'collectionRate',
            'byCategory', 'classStats', 'byMethod', 'monthlyTrend', 'defaulters'
        ));
    }
}
