<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Form;
use App\Models\Sequence;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class SequenceEnrollmentController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'sequence-enrollment';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('sequence-enrollment.view', ['index', 'configure']),
            static::can('sequence-enrollment.enroll', ['save']),
        ];
    }

    public function index()
    {
        $forms = Form::withCount('streams')
            ->forCurrentLevel()
            ->ordered()
            ->get();

        // Count total sequence assignments per form
        $enrollmentCounts = DB::table('form_sequence')
            ->selectRaw('form_id, COUNT(*) as total')
            ->groupBy('form_id')
            ->pluck('total', 'form_id');

        // Count distinct terms configured per form
        $termCounts = DB::table('form_sequence')
            ->selectRaw('form_id, COUNT(DISTINCT term_id) as total')
            ->groupBy('form_id')
            ->pluck('total', 'form_id');

        $totalTerms = Term::count();
        $totalSequences = Sequence::count();

        return view('admin.sequence-enrollments.index', compact(
            'forms', 'enrollmentCounts', 'termCounts', 'totalTerms', 'totalSequences'
        ));
    }

    public function configure(Form $form)
    {
        $streams = $form->streams()
            ->where('streams.is_active', true)
            ->orderBy('streams.name')
            ->get();

        $terms = Term::orderBy('term_number')->get();
        $sequences = Sequence::orderBy('sequence_number')->get();

        // Existing enrollments for this form
        $enrollments = DB::table('form_sequence')
            ->where('form_id', $form->id)
            ->get();

        $termsJson = $terms->map(fn($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'term_number' => $t->term_number,
            'is_current' => $t->is_current,
        ]);

        $streamsJson = $streams->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'code' => $s->code,
            'is_general' => $s->is_general,
        ]);

        $sequencesJson = $sequences->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'sequence_number' => $s->sequence_number,
        ]);

        return view('admin.sequence-enrollments.configure', compact(
            'form', 'streams', 'terms', 'sequences',
            'enrollments', 'termsJson', 'streamsJson', 'sequencesJson'
        ));
    }

    public function save(Request $request, Form $form)
    {
        $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'assignments' => ['present', 'array'],
            'assignments.*.stream_id' => ['nullable', 'integer', 'exists:streams,id'],
            'assignments.*.sequence_id' => ['required', 'integer', 'exists:sequences,id'],
        ]);

        $termId = $request->input('term_id');
        $assignments = $request->input('assignments', []);

        DB::transaction(function () use ($form, $termId, $assignments) {
            // Delete existing assignments for this form + term
            DB::table('form_sequence')
                ->where('form_id', $form->id)
                ->where('term_id', $termId)
                ->delete();

            $rows = [];
            $now = now();
            foreach ($assignments as $item) {
                $rows[] = [
                    'form_id' => $form->id,
                    'stream_id' => $item['stream_id'] ?? null,
                    'term_id' => $termId,
                    'sequence_id' => $item['sequence_id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                DB::table('form_sequence')->insert($rows);
            }
        });

        return response()->json([
            'success' => true,
            'message' => __('Sequence enrollment saved successfully.'),
            'count' => count($assignments),
        ]);
    }
}
