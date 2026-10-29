<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\BudgetLine;
use App\Models\FeeCategory;
use Illuminate\Support\Facades\DB;

/**
 * Budgeting tuition from the pupils you expect, rather than a typed number.
 *
 * A bursar plans by reasoning "about a hundred and twenty pupils in primary,
 * and they pay 150,000 a year". The amount is a consequence of that
 * assumption, and a sheet that records only the amount throws the reasoning
 * away.
 *
 * A tuition line reports one school level, and the classes at that level
 * may charge different fees, so the rate is weighted by today's enrolment
 * per class — exact whenever next year keeps this year's mix.
 */
class TuitionForecastService
{
    /**
     * For every tuition line: pupils now, the weighted fee per year, and the
     * classes behind it.
     *
     * @return array<int, array{students: int, rate: float, forms: array<int, array{name: string, students: int, fee: float}>}>
     */
    public function ratesByLine(): array
    {
        $session = AcademicSession::current();
        $lines = BudgetLine::active()->postable()->whereNotNull('school_level')->get();

        if (! $session || $lines->isEmpty()) {
            return [];
        }

        $tuitionCategoryIds = FeeCategory::where('is_tuition', true)->pluck('id');

        // Fee per form for the year: every tuition category priced for the
        // form (stream-less rows), summed.
        $feeByForm = DB::table('fee_structures')
            ->where('academic_session_id', $session->id)
            ->whereIn('fee_category_id', $tuitionCategoryIds)
            ->whereNull('stream_id')
            ->selectRaw('form_id, SUM(amount) as fee')
            ->groupBy('form_id')
            ->pluck('fee', 'form_id');

        // Pupils per form, active this year.
        $pupilsByForm = DB::table('student_enrollments as se')
            ->join('class_sections as cs', 'cs.id', '=', 'se.class_section_id')
            ->where('se.academic_session_id', $session->id)
            ->where('se.status', 'active')
            ->selectRaw('cs.form_id, COUNT(*) as n')
            ->groupBy('cs.form_id')
            ->pluck('n', 'form_id');

        $forms = DB::table('forms')->where('is_active', true)->get(['id', 'name', 'level']);

        $out = [];
        foreach ($lines as $line) {
            $rows = [];
            $students = 0;
            $weighted = 0.0;
            $plain = [];

            foreach ($forms->where('level', $line->school_level) as $form) {
                $n = (int) ($pupilsByForm[$form->id] ?? 0);
                $fee = (float) ($feeByForm[$form->id] ?? 0);
                $rows[] = ['name' => $form->name, 'students' => $n, 'fee' => $fee];
                $students += $n;
                $weighted += $n * $fee;
                if ($fee > 0) {
                    $plain[] = $fee;
                }
            }

            $rate = $students > 0 && $weighted > 0
                ? $weighted / $students
                : ($plain ? array_sum($plain) / count($plain) : 0.0);

            $out[$line->id] = ['students' => $students, 'rate' => round($rate, 2), 'forms' => $rows];
        }

        return $out;
    }
}
