<?php

namespace App\Services;

use App\Models\AcademicSession;
use Illuminate\Support\Facades\DB;

/**
 * Who has paid, for one school year.
 *
 * The cash book says how money moved. This says where the year's billing
 * stands, and it counts PUPILS, not fees: a pupil with tuition paid and the
 * PTA levy still owed is one pupil who has paid in part. That is the question
 * a board meeting asks, and the fee report, which counts fees, answers a
 * different one.
 *
 * A pupil's fees can sit on several enrolment rows (one per term), so
 * everything is grouped by the pupil, never by the enrolment. Money paid on
 * one fee beyond what it owed is not allowed to make another unpaid fee look
 * settled: each fee counts at most what it was due.
 *
 * A fixed number of aggregate queries, whatever the size of the school.
 */
class FeeCollectionService
{
    /** Money is equal within half a franc: summed decimals do not land on zero. */
    private const EPSILON = 0.5;

    private const DEBTOR_LIMIT = 10;

    /** @return array<string, mixed> */
    public function forSession(?AcademicSession $session): array
    {
        if (! $session) {
            return ['has_data' => false];
        }

        $owed = 'CASE WHEN f.balance > 0 THEN f.balance ELSE 0 END';
        $settled = 'CASE WHEN f.paid_amount > f.net_amount THEN f.net_amount ELSE f.paid_amount END';

        $fees = fn () => DB::table('student_fees as f')
            ->join('student_enrollments as se', 'se.id', '=', 'f.student_enrollment_id')
            ->where('se.academic_session_id', $session->id)
            ->where('f.status', '!=', 'waived')
            ->where('f.net_amount', '>', 0);

        $pupils = $fees()
            ->groupBy('se.student_id')
            ->selectRaw("se.student_id, MAX(se.id) as enrollment_id, SUM(f.net_amount) as billed, SUM($settled) as collected, SUM($owed) as owed")
            ->get();

        $classOf = DB::table('student_enrollments as se')
            ->join('class_sections as cs', 'cs.id', '=', 'se.class_section_id')
            ->whereIn('se.id', $pupils->pluck('enrollment_id'))
            ->pluck('cs.name', 'se.id');

        $tally = function ($group): array {
            $billed = (float) $group->sum('billed');
            $collected = (float) $group->sum('collected');

            return [
                'pupils' => $group->count(),
                'full' => $group->filter(fn ($p) => $p->owed <= self::EPSILON)->count(),
                'part' => $group->filter(fn ($p) => $p->owed > self::EPSILON && $p->collected > self::EPSILON)->count(),
                'nothing' => $group->filter(fn ($p) => $p->owed > self::EPSILON && $p->collected <= self::EPSILON)->count(),
                'billed' => $billed,
                'collected' => $collected,
                'outstanding' => (float) $group->sum('owed'),
                'rate' => $billed > 0 ? $collected / $billed * 100 : 0.0,
            ];
        };

        $byClass = $pupils
            ->groupBy(fn ($p) => $classOf[$p->enrollment_id] ?? __('No class'))
            ->map($tally)
            ->sortKeys(SORT_NATURAL)
            ->all();

        $byCategory = $fees()
            ->join('fee_categories as c', 'c.id', '=', 'f.fee_category_id')
            ->groupBy('c.id', 'c.name')
            ->selectRaw("c.name, COUNT(DISTINCT se.student_id) as pupils, SUM(f.net_amount) as billed, SUM($settled) as collected, SUM($owed) as owed")
            ->orderByDesc('billed')
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'pupils' => (int) $c->pupils,
                'billed' => (float) $c->billed,
                'collected' => (float) $c->collected,
                'outstanding' => (float) $c->owed,
                'rate' => $c->billed > 0 ? $c->collected / $c->billed * 100 : 0.0,
            ])->all();

        $debtors = $pupils->where('owed', '>', self::EPSILON)->sortByDesc('owed')->take(self::DEBTOR_LIMIT);
        $names = DB::table('students')->whereIn('id', $debtors->pluck('student_id'))
            ->get(['id', 'first_name', 'last_name', 'student_id as matricule'])->keyBy('id');

        // Owed from an earlier year and still unpaid: without this, a pupil
        // carrying last year's balance appears nowhere on this year's page.
        $arrears = DB::table('student_fees as f')
            ->join('student_enrollments as se', 'se.id', '=', 'f.student_enrollment_id')
            ->join('academic_sessions as s', 's.id', '=', 'se.academic_session_id')
            ->where('s.start_date', '<', $session->start_date)
            ->where('f.balance', '>', 0)
            ->where('f.status', '!=', 'waived')
            ->selectRaw('COUNT(DISTINCT se.student_id) as pupils, SUM(f.balance) as owed')
            ->first();

        return [
            'session' => $session,
            'pupils' => $tally($pupils),
            'by_class' => $byClass,
            'by_category' => $byCategory,
            'debtors' => $debtors->map(fn ($p) => [
                'student_id' => $p->student_id,
                'name' => trim(($names[$p->student_id]->first_name ?? '').' '.($names[$p->student_id]->last_name ?? '')),
                'matricule' => $names[$p->student_id]->matricule ?? null,
                'class' => $classOf[$p->enrollment_id] ?? null,
                'billed' => (float) $p->billed,
                'owed' => (float) $p->owed,
            ])->values()->all(),
            'arrears' => ['pupils' => (int) ($arrears->pupils ?? 0), 'owed' => (float) ($arrears->owed ?? 0)],
            'has_data' => $pupils->isNotEmpty(),
        ];
    }
}
