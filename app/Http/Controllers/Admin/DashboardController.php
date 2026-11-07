<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\Event;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;

class DashboardController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'dashboard';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('dashboard.view', ['index']),
        ];
    }

    public function index()
    {
        $school = SchoolSetting::current();
        $currentSession = AcademicSession::current();

        $scope = app(\App\Services\TeachingScope::class);
        if ($scope->restricted(auth()->user())) {
            return $this->teacher($scope, $school, $currentSession);
        }

        $stats = [
            'total_students' => 0,
            'active_students' => 0,
            'total_teachers' => User::holdingRole('teacher')->where('is_active', true)->inBranchContext()->count(),
            // Everyone else on the staff: a teacher who also keeps the bursary
            // is counted once, as a teacher.
            'total_staff' => User::holdingRole('staff', 'admin', 'accountant', 'receptionist', 'librarian')
                ->whereNot(fn ($q) => $q->holdingRole('teacher'))->where('is_active', true)->inBranchContext()->count(),
            'total_classes' => 0,
            'present_today' => 0,
            'absent_today' => 0,
        ];

        if ($currentSession) {
            $stats['total_students'] = StudentEnrollment::where('academic_session_id', $currentSession->id)->whereHas('classSection.form', fn($q) => $q->forCurrentLevel())->count();
            $stats['active_students'] = StudentEnrollment::where('academic_session_id', $currentSession->id)
                ->whereHas('classSection.form', fn($q) => $q->forCurrentLevel())
                ->where('status', 'active')->count();
            $stats['total_classes'] = ClassSection::where('is_active', true)->whereHas('form', fn($q) => $q->forCurrentLevel())->count();

            $todayAttendance = Attendance::whereDate('date', today())
                ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $currentSession->id)->whereHas('classSection.form', fn($q2) => $q2->forCurrentLevel()));
            $stats['present_today'] = (clone $todayAttendance)->where('status', 'present')->count();
            $stats['absent_today'] = (clone $todayAttendance)->where('status', 'absent')->count();
        }

        // What the school has told its own staff. Without this a notice
        // addressed to staff could be written and filtered for, but never
        // actually read by anyone.
        $notices = Announcement::with('category')
            ->published()
            ->forStaff()
            ->orderByDesc('published_at')
            ->take(5)
            ->get();

        $events = Event::with('category')
            ->published()
            ->forStaff()
            ->upcoming()
            ->orderBy('starts_on')
            ->take(5)
            ->get();

        $glance = $currentSession ? $this->glance($currentSession) : null;

        return view('admin.dashboard', compact('school', 'currentSession', 'stats', 'notices', 'events', 'glance'));
    }

    /**
     * A teacher's first screen, as in the reference system: how the day and
     * the week look, today's periods, and every subject they teach as a card
     * that opens straight into its marks. A class teacher also finds the
     * class's register and report-card remarks.
     */
    private function teacher(\App\Services\TeachingScope $scope, $school, $currentSession)
    {
        $user = auth()->user();
        $term = \App\Models\Term::current() ?? \App\Models\Term::orderBy('term_number')->first();
        $cards = app(\App\Services\TeacherSubjects::class)->cards($user, $currentSession, $term);

        // What only the class teacher does: the register and the remarks.
        $duties = $scope->myClasses($user)->filter(fn ($c) => $c['class_teacher'])->map(function (array $c) use ($currentSession) {
            $enrolments = StudentEnrollment::where('class_section_id', $c['section']->id)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->where('status', 'active')->pluck('id');
            $today = Attendance::whereIn('student_enrollment_id', $enrolments)->whereDate('date', today());

            return $c + [
                'pupils' => $enrolments->count(),
                'register_taken' => (clone $today)->exists(),
                'absent_today' => (clone $today)->where('status', 'absent')->count(),
            ];
        })->values();

        $week = \App\Models\TimetableEntry::with(['subject', 'classSection', 'room', 'slot'])
            ->where('teacher_id', $user->id)
            ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
            ->get();
        $today = $week->where('day_of_week', strtolower(now()->englishDayOfWeek))->sortBy(fn ($e) => (string) ($e->start_time ?? $e->slot?->start_time))->values();

        // What is waiting: a register not taken on a school day, a returned
        // sheet, a first sequence not started, nursery sheets unfinished.
        $todo = $duties->filter(fn ($d) => ! $d['register_taken'] && $d['pupils'] > 0 && ! today()->isWeekend())->count()
            + $cards->sum(fn ($card) => $card['nursery']
                ? (int) (! $card['done'] && $card['pupils'] > 0)
                : collect($card['sequences'])->filter(fn ($cell, $i) => $cell['status'] === 'returned' || ($i === 0 && in_array($cell['status'], ['none', 'draft'], true)))->count());

        $notices = Announcement::published()->forStaff()->orderByDesc('published_at')->take(5)->get();

        return view('admin.dashboard-teacher', [
            'school' => $school,
            'currentSession' => $currentSession,
            'term' => $term,
            'cards' => $cards,
            'duties' => $duties,
            'today' => $today,
            'week' => $week,
            'todo' => $todo,
            'notices' => $notices,
        ]);
    }

    /**
     * The head teacher's first questions: registers today, this term's marks,
     * money in, and what is waiting for a decision.
     *
     * @return array<string, mixed>
     */
    private function glance(AcademicSession $session): array
    {
        $sections = ClassSection::where('is_active', true)->whereHas('form', fn ($q) => $q->forCurrentLevel())->with('form')->get();
        $sectionIds = $sections->pluck('id');

        $todayBySection = Attendance::whereDate('date', today())
            ->join('student_enrollments as se', 'se.id', '=', 'attendances.student_enrollment_id')
            ->whereIn('se.class_section_id', $sectionIds)
            ->where('se.academic_session_id', $session->id)
            ->selectRaw("se.class_section_id, SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->groupBy('se.class_section_id')
            ->pluck('absent', 'se.class_section_id');

        $withPupils = StudentEnrollment::where('academic_session_id', $session->id)->where('status', 'active')
            ->whereIn('class_section_id', $sectionIds)->distinct()->pluck('class_section_id');
        $classes = $sections->whereIn('id', $withPupils);

        // Marks: every subject × sequence of this term at every marked class.
        $term = \App\Models\Term::current() ?? \App\Models\Term::orderBy('term_number')->first();
        $expected = 0;
        $done = 0;
        if ($term) {
            $marked = $classes->filter(fn ($s) => $s->form->level !== 'nursery');
            $approved = \App\Models\MarksSubmission::forSession($session->id)->whereIn('class_section_id', $marked->pluck('id'))
                ->whereIn('status', ['approved', 'published'])->get()->groupBy('class_section_id');
            // The subjects the class's pupils actually take this term: a Sixth
            // Form class is one stream, so the other stream's subjects are not
            // its sheets to hand in.
            $taken = \Illuminate\Support\Facades\DB::table('student_subjects as ss')
                ->join('student_enrollments as se', 'se.id', '=', 'ss.student_enrollment_id')
                ->where('se.academic_session_id', $session->id)->where('se.term_id', $term->id)->where('se.status', 'active')
                ->whereIn('se.class_section_id', $marked->pluck('id'))
                ->selectRaw('se.class_section_id, COUNT(DISTINCT ss.subject_id) as n')->groupBy('se.class_section_id')
                ->pluck('n', 'se.class_section_id');
            foreach ($marked as $s) {
                $seqs = \Illuminate\Support\Facades\DB::table('form_sequence')->where('form_id', $s->form_id)->where('term_id', $term->id)->pluck('sequence_id');
                $subjects = (int) ($taken[$s->id] ?? \Illuminate\Support\Facades\DB::table('form_subject')->where('form_id', $s->form_id)->distinct()->count('subject_id'));
                $expected += $seqs->count() * $subjects;
                $done += ($approved->get($s->id) ?? collect())->whereIn('sequence_id', $seqs)->count();
            }
        }

        // Only the levels this school runs, like every other figure on the page.
        $fees = \App\Models\StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $session->id)
                ->whereHas('classSection.form', fn ($f) => $f->forCurrentLevel()))
            ->selectRaw('COALESCE(SUM(net_amount),0) as billed, COALESCE(SUM(paid_amount),0) as paid')->first();
        $today = (float) \App\Models\Payment::where('verification_status', 'verified')->whereNotIn('payment_method', \App\Models\Payment::NON_CASH_METHODS)
            ->whereDate('payment_date', today())->sum('amount');

        return [
            'registers' => [
                'classes' => $classes->count(),
                'taken' => $classes->filter(fn ($s) => $todayBySection->has($s->id))->count(),
                'missing' => $classes->reject(fn ($s) => $todayBySection->has($s->id))->pluck('name')->values(),
                'absent' => (int) $todayBySection->sum(),
            ],
            'marks' => [
                'term' => $term,
                'expected' => $expected,
                'done' => min($done, $expected),
                'percent' => $expected > 0 ? (int) floor(min($done, $expected) / $expected * 100) : 0,
            ],
            'fees' => [
                'billed' => (float) $fees->billed,
                'paid' => (float) $fees->paid,
                'rate' => (float) $fees->billed > 0 ? (int) round((float) $fees->paid / (float) $fees->billed * 100) : 0,
                'today' => $today,
            ],
            'waiting' => [
                'marks' => \App\Models\MarksSubmission::forSession($session->id)->where('status', 'submitted')->count(),
                'parent_payments' => \App\Models\ParentPaymentSubmission::where('status', 'pending')->count(),
                'applications' => \App\Models\AdmissionApplication::whereIn('status', ['submitted', 'pending', 'under_review'])->count(),
            ],
        ];
    }
}
