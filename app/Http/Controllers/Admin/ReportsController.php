<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\Mark;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function index()
    {
        $currentSession = AcademicSession::current();

        // Key school-wide KPIs
        $totalStudents = $currentSession
            ? StudentEnrollment::where('academic_session_id', $currentSession->id)->whereHas('classSection.form', fn($q) => $q->forCurrentLevel())->where('status', 'active')->count()
            : 0;
        $totalTeachers = User::where('role', 'teacher')->where('is_active', true)->inBranchContext()->count();
        $totalClasses = ClassSection::where('is_active', true)->whereHas('form', fn($q) => $q->forCurrentLevel())->count();

        // Fee KPIs
        $feeExpected = $currentSession
            ? StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $currentSession->id)->whereHas('classSection.form', fn($q2) => $q2->forCurrentLevel()))->sum('net_amount')
            : 0;
        $feeCollected = $currentSession
            ? StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $currentSession->id)->whereHas('classSection.form', fn($q2) => $q2->forCurrentLevel()))->sum('paid_amount')
            : 0;

        // Attendance KPI (last 30 days)
        $attendanceRate = 0;
        if ($currentSession) {
            $totalRecords = Attendance::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $currentSession->id)->whereHas('classSection.form', fn($q2) => $q2->forCurrentLevel()))
                ->where('date', '>=', now()->subDays(30))
                ->count();
            $presentRecords = Attendance::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $currentSession->id)->whereHas('classSection.form', fn($q2) => $q2->forCurrentLevel()))
                ->where('date', '>=', now()->subDays(30))
                ->where('status', 'present')
                ->count();
            $attendanceRate = $totalRecords > 0 ? round(($presentRecords / $totalRecords) * 100, 1) : 0;
        }

        // Academic performance: average by class
        $classPerformance = [];
        if ($currentSession) {
            $currentTerm = $currentSession->currentTerm();
            if ($currentTerm) {
                $classPerformance = TermResult::select(
                        'class_section_id',
                        DB::raw('AVG(overall_average) as avg_score'),
                        DB::raw('COUNT(*) as student_count'),
                        DB::raw('SUM(CASE WHEN decision = \'pass\' THEN 1 ELSE 0 END) as pass_count')
                    )
                    ->where('term_id', $currentTerm->id)
                    ->whereHas('classSection.form', fn($q) => $q->forCurrentLevel())
                    ->groupBy('class_section_id')
                    ->with('classSection.form')
                    ->get();
            }
        }

        // Gender distribution
        $genderStats = $currentSession
            ? StudentEnrollment::where('student_enrollments.academic_session_id', $currentSession->id)
                ->where('student_enrollments.status', 'active')
                ->whereHas('classSection.form', fn($q) => $q->forCurrentLevel())
                ->join('students', 'student_enrollments.student_id', '=', 'students.id')
                ->select('students.gender', DB::raw('COUNT(*) as count'))
                ->groupBy('students.gender')
                ->pluck('count', 'gender')
            : collect();

        // Enrollment per form
        $enrollmentByForm = ClassSection::where('is_active', true)
                ->whereHas('form', fn($q) => $q->forCurrentLevel())
                ->withCount(['studentEnrollments' => fn ($q) => $q->where('status', 'active')])
                ->with('form')
                ->get()
                ->groupBy(fn ($cs) => $cs->form->short_name)
                ->map(fn ($sections) => $sections->sum('student_enrollments_count'));

        // Recent payments (last 10)
        $recentPayments = Payment::with(['enrollment.student'])
            ->whereHas('enrollment.classSection.form', fn($q) => $q->forCurrentLevel())
            ->orderByDesc('payment_date')
            ->limit(10)
            ->get();

        return view('admin.reports.index', compact(
            'currentSession', 'totalStudents', 'totalTeachers', 'totalClasses',
            'feeExpected', 'feeCollected', 'attendanceRate',
            'classPerformance', 'genderStats', 'enrollmentByForm', 'recentPayments'
        ));
    }
}
