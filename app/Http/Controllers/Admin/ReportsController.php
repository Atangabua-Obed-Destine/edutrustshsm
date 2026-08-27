<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\Mark;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'report-and-analytics';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('report-and-analytics.view', ['index']),
        ];
    }

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
                // term_results has no class_section_id / overall_average / decision.
                // The class is reached through the enrollment, the score column is
                // `term_average`, and "pass" is derived from the configured pass mark.
                $passMark = (float) (SchoolSetting::current()?->pass_mark ?? 10);

                $classPerformance = TermResult::query()
                    ->join('student_enrollments', 'term_results.student_enrollment_id', '=', 'student_enrollments.id')
                    ->join('class_sections', 'student_enrollments.class_section_id', '=', 'class_sections.id')
                    ->select(
                        'student_enrollments.class_section_id',
                        DB::raw('MAX(class_sections.name) as class_section_name'),
                        DB::raw('AVG(term_results.term_average) as avg_score'),
                        DB::raw('COUNT(*) as student_count'),
                        DB::raw('SUM(CASE WHEN term_results.term_average >= ' . $passMark . ' THEN 1 ELSE 0 END) as pass_count')
                    )
                    ->where('term_results.term_id', $currentTerm->id)
                    ->whereHas('enrollment.classSection.form', fn ($q) => $q->forCurrentLevel())
                    ->groupBy('student_enrollments.class_section_id')
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
