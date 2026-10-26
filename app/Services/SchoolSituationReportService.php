<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassCouncil;
use App\Models\ClassCouncilEntry;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payroll;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The school's own report card, once a term.
 *
 * Every figure here already existed somewhere — results on one screen, fees on
 * another, attendance on a third, and who left nowhere at all until recently.
 * Nobody could answer "how did this term go" without opening six screens and
 * doing the arithmetic in their head, which is why the question was mostly
 * answered from memory.
 *
 * The part that makes it a report rather than a dashboard is `highlights()`:
 * the same figures compared against last term and written out as sentences,
 * with the class or subject that accounts for the movement named where one
 * does. A number that moved is data. A number that moved *because of Form 3*
 * is something a head can act on before the next term starts.
 */
class SchoolSituationReportService
{
    public function __construct(private AcademicAnalyticsService $analytics) {}

    /**
     * @return array<string, mixed>
     */
    public function forTerm(Term $term): array
    {
        $session = AcademicSession::current();
        $previous = $this->previousTerm($term);

        $current = $this->measure($term, $session);
        $before = $previous ? $this->measure($previous, $session) : null;

        return [
            'term' => $term,
            'previous_term' => $previous,
            'session' => $session,
            'window' => $this->window($term),
            'enrolment' => $this->enrolment($term, $session),
            'academics' => $current['academics'],
            'attendance' => $current['attendance'],
            'finance' => $current['finance'],
            'councils' => $this->councils($term),
            'comparison' => $before ? $this->compare($current, $before) : null,
            'highlights' => $before ? $this->highlights($current, $before, $previous) : [],
        ];
    }

    /**
     * The term's own dates, which every period figure is cut against.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    private function window(Term $term): array
    {
        return [
            'from' => Carbon::parse($term->start_date ?? now()->startOfYear()),
            // Never beyond today: a term still running should report what has
            // happened, not divide by days that have not come yet.
            'to' => Carbon::parse($term->end_date ?? now())->min(now()),
        ];
    }

    /**
     * The three measures that can be compared term to term.
     *
     * @return array<string, mixed>
     */
    private function measure(Term $term, ?AcademicSession $session): array
    {
        return [
            'academics' => $this->academics($term),
            'attendance' => $this->attendance($term),
            'finance' => $this->finance($term, $session),
        ];
    }

    /** @return array<string, mixed> */
    private function academics(Term $term): array
    {
        $report = $this->analytics->report($term->id);

        return [
            'students' => $report['headline']['students'],
            'average' => $report['headline']['average'],
            'pass_rate' => $report['headline']['pass_rate'],
            'coverage' => $report['coverage']['percent'],
            'classes' => $report['classes'],
            // Worst first; the analytics service already sorts by how a
            // subject sits against the pupils' own averages.
            'struggling_subjects' => array_slice($report['subjects'], 0, 3),
            'at_risk' => count($report['at_risk']),
        ];
    }

    /** @return array<string, mixed> */
    private function attendance(Term $term): array
    {
        ['from' => $from, 'to' => $to] = $this->window($term);

        $rows = Attendance::query()
            ->whereBetween('attendances.date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('enrollment.classSection.form', fn ($q) => $q->forCurrentLevel())
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $marked = $rows->sum();
        $present = (int) ($rows['present'] ?? 0);

        return [
            'marked' => $marked,
            'present' => $present,
            'absent' => (int) ($rows['absent'] ?? 0),
            'rate' => $marked > 0 ? round(($present / $marked) * 100, 1) : null,
        ];
    }

    /**
     * Money for the term's window.
     *
     * Fees are measured against the session rather than the term, because a
     * fee is billed for the year and paid whenever the family can — cutting
     * it at a term boundary would report a collection rate nobody recognises.
     *
     * @return array<string, mixed>
     */
    private function finance(Term $term, ?AcademicSession $session): array
    {
        ['from' => $from, 'to' => $to] = $this->window($term);

        $fees = StudentFee::query()
            ->when($session, fn ($q) => $q->whereHas('enrollment', fn ($e) => $e->where('academic_session_id', $session->id)))
            ->selectRaw('COALESCE(SUM(net_amount), 0) as billed, COALESCE(SUM(paid_amount), 0) as collected')
            ->first();

        $billed = (float) ($fees->billed ?? 0);
        $collected = (float) ($fees->collected ?? 0);

        $income = (float) Income::where('status', true)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->sum('amount');
        $expense = (float) Expense::where('status', true)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])->sum('amount');

        // Payroll is stamped with a month, not a date, so it is matched on the
        // months the term actually covers.
        $staffCost = (float) Payroll::whereIn('salary_month', $this->monthsIn($from, $to))
            ->where('status', 'paid')
            ->sum('total_cost');

        return [
            'billed' => $billed,
            'collected' => $collected,
            'outstanding' => max(0, $billed - $collected),
            'collection_rate' => $billed > 0 ? round(($collected / $billed) * 100, 1) : null,
            'income' => $income,
            'expense' => $expense,
            'surplus' => $income - $expense,
            'staff_cost' => $staffCost,
            'staff_share' => $income > 0 ? round(($staffCost / $income) * 100, 1) : null,
        ];
    }

    /**
     * The YYYY-MM stamps a window covers.
     *
     * @return array<int, string>
     */
    private function monthsIn(Carbon $from, Carbon $to): array
    {
        $months = [];
        $cursor = $from->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($to)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Who joined and who left, which no screen could answer before pupils
     * could leave at all.
     *
     * @return array<string, mixed>
     */
    private function enrolment(Term $term, ?AcademicSession $session): array
    {
        ['from' => $from, 'to' => $to] = $this->window($term);

        $active = StudentEnrollment::query()
            ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
            ->where('status', 'active')
            ->whereHas('classSection.form', fn ($q) => $q->forCurrentLevel())
            ->count();

        $admitted = Student::whereBetween('admission_date', [$from->toDateString(), $to->toDateString()])->count();

        $departures = Student::whereBetween('withdrawal_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $left = (int) $departures->sum();

        return [
            'active' => $active,
            'admitted' => $admitted,
            'left' => $left,
            'by_reason' => $departures->all(),
            'net' => $admitted - $left,
            'by_form' => StudentEnrollment::query()
                ->when($session, fn ($q) => $q->where('academic_session_id', $session->id))
                ->where('student_enrollments.status', 'active')
                ->join('class_sections', 'student_enrollments.class_section_id', '=', 'class_sections.id')
                ->join('forms', 'class_sections.form_id', '=', 'forms.id')
                ->whereIn('forms.school_level', \App\Support\LevelContext::options())
                ->selectRaw('forms.name as form, COUNT(*) as total')
                ->groupBy('forms.name')
                ->orderBy('forms.name')
                ->pluck('total', 'form')
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function councils(Term $term): array
    {
        $councils = ClassCouncil::where('term_id', $term->id)
            ->whereHas('classSection.form', fn ($q) => $q->forCurrentLevel())
            ->get();

        $awards = ClassCouncilEntry::whereIn('class_council_id', $councils->pluck('id'))
            ->selectRaw('award, COUNT(*) as total')
            ->groupBy('award')
            ->pluck('total', 'award')
            ->all();

        return [
            'held' => $councils->count(),
            'concluded' => $councils->where('status', ClassCouncil::CONCLUDED)->count(),
            'awards' => $awards,
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $before
     * @return array<string, array{now: float|null, then: float|null, change: float|null}>
     */
    private function compare(array $current, array $before): array
    {
        $pairs = [
            'average' => [$current['academics']['average'], $before['academics']['average']],
            'pass_rate' => [$current['academics']['pass_rate'], $before['academics']['pass_rate']],
            'attendance' => [$current['attendance']['rate'], $before['attendance']['rate']],
            'collection_rate' => [$current['finance']['collection_rate'], $before['finance']['collection_rate']],
            'surplus' => [$current['finance']['surplus'], $before['finance']['surplus']],
        ];

        $out = [];

        foreach ($pairs as $key => [$now, $then]) {
            $out[$key] = [
                'now' => $now,
                'then' => $then,
                'change' => ($now !== null && $then !== null) ? round($now - $then, 2) : null,
            ];
        }

        return $out;
    }

    /**
     * The report's sentences.
     *
     * A dashboard shows a number; a report says what the number means and,
     * where it can, what accounts for it. Each highlight carries a tone so
     * the page can be read at a glance without reading every word.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $before
     * @return array<int, array{tone: string, text: string}>
     */
    private function highlights(array $current, array $before, ?Term $previous): array
    {
        $out = [];
        $since = $previous?->name ?? __('last term');

        // ── Results, with the class that accounts for the movement ──────────
        $passNow = $current['academics']['pass_rate'];
        $passThen = $before['academics']['pass_rate'];

        if ($passNow !== null && $passThen !== null) {
            $change = round($passNow - $passThen, 1);

            if (abs($change) >= 2) {
                $mover = $this->biggestMover($current['academics']['classes'], $before['academics']['classes']);

                $text = $change > 0
                    ? __('The pass rate is up :points points on :since, at :rate%.', ['points' => abs($change), 'since' => $since, 'rate' => $passNow])
                    : __('The pass rate is down :points points on :since, at :rate%.', ['points' => abs($change), 'since' => $since, 'rate' => $passNow]);

                if ($mover) {
                    $text .= ' '.__(':class moved most, by :points points.', [
                        'class' => $mover['name'],
                        'points' => abs($mover['change']),
                    ]);
                }

                $out[] = ['tone' => $change > 0 ? 'good' : 'bad', 'text' => $text];
            } else {
                $out[] = ['tone' => 'neutral', 'text' => __('The pass rate held steady at :rate%.', ['rate' => $passNow])];
            }
        }

        // ── The subject most worth a head of department's attention ─────────
        $worst = $current['academics']['struggling_subjects'][0] ?? null;

        if ($worst && $worst['delta'] <= -1) {
            $out[] = ['tone' => 'bad', 'text' => __(
                ':subject sits :points points below what the same pupils average elsewhere — the widest gap of any subject.',
                ['subject' => $worst['name'], 'points' => abs($worst['delta'])]
            )];
        }

        // ── Attendance ──────────────────────────────────────────────────────
        $attNow = $current['attendance']['rate'];
        $attThen = $before['attendance']['rate'];

        if ($attNow !== null) {
            if ($attThen !== null && abs($attNow - $attThen) >= 2) {
                $change = round($attNow - $attThen, 1);
                $out[] = ['tone' => $change > 0 ? 'good' : 'bad', 'text' => $change > 0
                    ? __('Attendance improved to :rate%, up :points points.', ['rate' => $attNow, 'points' => abs($change)])
                    : __('Attendance fell to :rate%, down :points points.', ['rate' => $attNow, 'points' => abs($change)])];
            } elseif ($attNow < 85) {
                $out[] = ['tone' => 'bad', 'text' => __('Attendance stands at :rate%, which is low enough to be showing up in results.', ['rate' => $attNow])];
            }
        }

        // ── Money ───────────────────────────────────────────────────────────
        $collection = $current['finance']['collection_rate'];

        if ($collection !== null) {
            $outstanding = $current['finance']['outstanding'];
            $tone = $collection >= 80 ? 'good' : ($collection >= 60 ? 'neutral' : 'bad');

            $out[] = ['tone' => $tone, 'text' => __(
                ':rate% of fees billed have been collected, leaving :amount outstanding.',
                ['rate' => $collection, 'amount' => number_format($outstanding, 0).' FCFA']
            )];
        }

        if ($current['finance']['surplus'] < 0) {
            $out[] = ['tone' => 'bad', 'text' => __(
                'Spending exceeded income this term by :amount.',
                ['amount' => number_format(abs($current['finance']['surplus']), 0).' FCFA']
            )];
        }

        $share = $current['finance']['staff_share'];

        if ($share !== null && $share > 70) {
            $out[] = ['tone' => 'bad', 'text' => __(
                'Staff costs took :share% of income, which leaves little for anything else.',
                ['share' => $share]
            )];
        }

        // ── Honesty about what the report rests on ──────────────────────────
        $coverage = $current['academics']['coverage'];

        if ($coverage !== null && $coverage < 90) {
            $out[] = ['tone' => 'warning', 'text' => __(
                'These academic figures cover only :coverage% of pupils — the rest have no published result, so read them as partial.',
                ['coverage' => $coverage]
            )];
        }

        return $out;
    }

    /**
     * The class whose average moved furthest between the two terms.
     *
     * @param  array<int, array<string, mixed>>  $now
     * @param  array<int, array<string, mixed>>  $then
     * @return array{name: string, change: float}|null
     */
    private function biggestMover(array $now, array $then): ?array
    {
        $before = collect($then)->keyBy('id');
        $best = null;

        foreach ($now as $class) {
            $was = $before->get($class['id']);

            if (! $was || $was['average'] === null || $class['average'] === null) {
                continue;
            }

            $change = round($class['average'] - $was['average'], 2);

            if ($best === null || abs($change) > abs($best['change'])) {
                $best = ['name' => $class['name'], 'change' => $change];
            }
        }

        return $best && abs($best['change']) >= 0.5 ? $best : null;
    }

    /** The term before this one, by number. */
    private function previousTerm(Term $term): ?Term
    {
        if ($term->term_number <= 1) {
            return null;
        }

        return Term::where('term_number', $term->term_number - 1)->first();
    }
}
