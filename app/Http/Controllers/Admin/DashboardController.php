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
            'total_teachers' => User::where('role', 'teacher')->where('is_active', true)->inBranchContext()->count(),
            'total_staff' => User::whereIn('role', ['staff', 'admin', 'accountant'])->where('is_active', true)->inBranchContext()->count(),
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
     * A teacher's first screen: their classes, and for each what is due —
     * today's register, this term's marks per subject per sequence, the
     * nursery sheets — each a link straight into the work.
     */
    private function teacher(\App\Services\TeachingScope $scope, $school, $currentSession)
    {
        $user = auth()->user();
        $term = \App\Models\Term::current() ?? \App\Models\Term::orderBy('term_number')->first();
        $classes = $scope->myClasses($user);
        $todo = 0;

        $rows = $classes->map(function (array $c) use ($user, $term, $currentSession, &$todo) {
            $section = $c['section'];
            $enrolments = StudentEnrollment::where('class_section_id', $section->id)
                ->when($currentSession, fn ($q) => $q->where('academic_session_id', $currentSession->id))
                ->where('status', 'active')->pluck('id');

            $today = Attendance::whereIn('student_enrollment_id', $enrolments)->whereDate('date', today());
            $registerTaken = (clone $today)->exists();
            if ($c['class_teacher'] && ! $registerTaken && $enrolments->isNotEmpty() && ! today()->isWeekend()) {
                $todo++;
            }

            $nursery = $section->form->level === 'nursery';
            $sheetsDone = 0;
            $marks = [];

            if ($nursery && $term) {
                $total = app(\App\Services\NurseryAssessmentService::class)->competencesFor($section->form)->sum(fn ($a) => $a->competences->count());
                $sheetsDone = $total > 0 ? \App\Models\NurseryAssessment::whereIn('student_enrollment_id', $enrolments)->where('term_id', $term->id)
                    ->selectRaw('student_enrollment_id, count(*) n')->groupBy('student_enrollment_id')->get()->where('n', '>=', $total)->count() : 0;
                $todo += max(0, $enrolments->count() - $sheetsDone) > 0 ? 1 : 0;
            } elseif ($term) {
                $sequences = \App\Models\Sequence::whereIn('id', \Illuminate\Support\Facades\DB::table('form_sequence')->where('form_id', $section->form_id)->where('term_id', $term->id)->pluck('sequence_id'))
                    ->orderBy('sequence_number')->get();
                $submissions = \App\Models\MarksSubmission::forSession($currentSession?->id)->where('class_section_id', $section->id)->whereIn('sequence_id', $sequences->pluck('id'))->get()
                    ->keyBy(fn ($s) => $s->subject_id.':'.$s->sequence_id);

                foreach ($c['subjects'] as $subject) {
                    $cells = [];
                    foreach ($sequences as $seq) {
                        $sub = $submissions->get($subject->id.':'.$seq->id);
                        $status = $sub?->status ?? 'none';
                        if (in_array($status, ['none', 'draft', 'returned'], true) && $seq->id === $sequences->first()?->id || $status === 'returned') {
                            $todo++;
                        }
                        $cells[] = ['sequence' => $seq, 'status' => $status, 'entered' => (int) ($sub?->marks_entered ?? 0)];
                    }
                    $marks[] = ['subject' => $subject, 'sequences' => $cells];
                }
            }

            return $c + [
                'pupils' => $enrolments->count(),
                'register_taken' => $registerTaken,
                'absent_today' => (clone $today)->where('status', 'absent')->count(),
                'nursery' => $nursery,
                'sheets_done' => $sheetsDone,
                'marks' => $marks,
            ];
        });

        $notices = Announcement::published()->forStaff()->orderByDesc('published_at')->take(5)->get();

        return view('admin.dashboard-teacher', [
            'school' => $school,
            'currentSession' => $currentSession,
            'term' => $term,
            'classes' => $rows,
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
            foreach ($marked as $s) {
                $seqs = \Illuminate\Support\Facades\DB::table('form_sequence')->where('form_id', $s->form_id)->where('term_id', $term->id)->pluck('sequence_id');
                $subjects = \Illuminate\Support\Facades\DB::table('form_subject')->where('form_id', $s->form_id)->count();
                $expected += $seqs->count() * $subjects;
                $done += ($approved->get($s->id) ?? collect())->whereIn('sequence_id', $seqs)->count();
            }
        }

        $fees = \App\Models\StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $session->id))
            ->selectRaw('COALESCE(SUM(net_amount),0) as billed, COALESCE(SUM(paid_amount),0) as paid')->first();
        $today = (float) \App\Models\Payment::where('verification_status', 'verified')->where('payment_method', '!=', 'student_credit')
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
