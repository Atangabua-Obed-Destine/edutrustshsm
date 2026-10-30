<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\ClassCouncil;
use App\Models\ClassCouncilEntry;
use App\Models\ClassCouncilSignature;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Models\User;
use App\Services\ClassCouncilService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * The class council: the meeting that turns a term's marks into decisions.
 *
 * Concluding is a separate permission from deliberating. Filling in the
 * awards is the work of the meeting; closing it freezes the figures onto the
 * record and is the head's act.
 */
class ClassCouncilController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'class-council';

    public function __construct(private ClassCouncilService $councils) {}

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('class-council.conclude', ['conclude', 'reopen']),
        ];
    }

    public function index(Request $request)
    {
        $termId = $request->integer('term_id') ?: Term::where('is_current', true)->value('id');

        $councils = ClassCouncil::with(['classSection.form', 'term', 'chair'])
            ->withCount('entries')
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->whereHas('classSection.form', fn ($q) => $q->forCurrentLevel())
            ->orderByDesc('id')
            ->get();

        // Classes with no sitting yet for this term, so the gap is visible
        // rather than something you have to notice is absent.
        $without = ClassSection::where('is_active', true)
            ->whereHas('form', fn ($q) => $q->forCurrentLevel())
            ->when($termId, fn ($q) => $q->whereDoesntHave('councils', fn ($c) => $c->where('term_id', $termId)))
            ->with('form:id,name')
            ->orderBy('name')
            ->get();

        return view('admin.councils.index', [
            'councils' => $councils,
            'without' => $termId ? $without : collect(),
            'terms' => Term::orderBy('term_number')->get(),
            'termId' => $termId,
        ]);
    }

    /** Open a sitting for a class, or jump to the one already open. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_section_id' => ['required', 'exists:class_sections,id'],
            'term_id' => ['required', 'exists:terms,id'],
        ]);

        $section = ClassSection::findOrFail($validated['class_section_id']);
        $term = Term::findOrFail($validated['term_id']);

        $council = $this->councils->open($section, $term);

        if ($council->entries->isEmpty()) {
            return back()->with('error', __('No published results for :class in :term yet, so there is nothing to deliberate on.', [
                'class' => $section->name,
                'term' => $term->name,
            ]));
        }

        return redirect()->route('admin.councils.show', $council);
    }

    public function show(ClassCouncil $council)
    {
        $council->load([
            'classSection.form', 'term', 'chair', 'secretary', 'concludedBy',
            'entries.student', 'signatures',
        ]);

        return view('admin.councils.show', [
            'council' => $council,
            'entries' => $council->entries->sortBy([
                // Rank order, with the unranked last rather than first.
                fn ($a, $b) => ($a->rank ?? PHP_INT_MAX) <=> ($b->rank ?? PHP_INT_MAX),
            ])->values(),
            'statistics' => $council->isConcluded()
                ? $this->frozenStatistics($council)
                : $this->councils->statistics($council),
            'tally' => $this->councils->awardTally($council),
            'awards' => ClassCouncilEntry::awards(),
            'staff' => User::whereIn('role', ['super_admin', 'admin', 'teacher', 'staff'])
                ->where('is_active', true)->orderBy('first_name')->get(),
            'passMark' => (float) (SchoolSetting::current()?->pass_mark ?: 10),
        ]);
    }

    /** The meeting's own details — who chaired, where, what was discussed. */
    public function update(Request $request, ClassCouncil $council)
    {
        abort_unless($council->isOpen(), 403, __('This council is concluded. Reopen it to make changes.'));

        $council->update($request->validate([
            'meeting_date' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:150'],
            'chaired_by' => ['nullable', 'exists:users,id'],
            'secretary_id' => ['nullable', 'exists:users,id'],
            'deliberations' => ['nullable', 'string'],
            'action_items' => ['nullable', 'string'],
        ]));

        return back()->with('success', __('Council details saved.'));
    }

    /** The decisions themselves, saved as one sheet. */
    public function decisions(Request $request, ClassCouncil $council)
    {
        abort_unless($council->isOpen(), 403, __('This council is concluded. Reopen it to make changes.'));

        $validated = $request->validate([
            'entries' => ['required', 'array'],
            'entries.*.award' => ['required', Rule::in(array_keys(ClassCouncilEntry::awards()))],
            'entries.*.conduct' => ['nullable', 'in:excellent,very_good,good,fair,poor'],
            'entries.*.remark' => ['nullable', 'string', 'max:500'],
        ]);

        // Only this council's own entries, whatever ids were posted.
        $entries = $council->entries()->get()->keyBy('id');
        $saved = 0;

        foreach ($validated['entries'] as $id => $values) {
            $entry = $entries->get((int) $id);

            if (! $entry) {
                continue;
            }

            $entry->update([
                'award' => $values['award'],
                'conduct' => $values['conduct'] ?? null,
                'remark' => $values['remark'] ?? null,
            ]);

            $saved++;
        }

        return back()->with('success', __(':count decision(s) saved.', ['count' => $saved]));
    }

    public function addSignature(Request $request, ClassCouncil $council)
    {
        abort_unless($council->isOpen(), 403, __('This council is concluded. Reopen it to make changes.'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $council->signatures()->create($validated + ['signed_at' => now()]);

        return back()->with('success', __('Signature added.'));
    }

    public function removeSignature(ClassCouncil $council, ClassCouncilSignature $signature)
    {
        abort_unless($council->isOpen(), 403);
        abort_unless($signature->class_council_id === $council->id, 404);

        $signature->delete();

        return back()->with('success', __('Signature removed.'));
    }

    public function conclude(ClassCouncil $council)
    {
        abort_unless($council->isOpen(), 403);

        if ($council->entries()->count() === 0) {
            return back()->with('error', __('There is nothing to conclude — this council has no pupils.'));
        }

        $this->councils->conclude($council);

        return back()->with('success', __('Council concluded. Its figures are now fixed to what the meeting saw.'));
    }

    public function reopen(ClassCouncil $council)
    {
        abort_unless($council->isConcluded(), 403);

        $this->councils->reopen($council);

        return back()->with('success', __('Council reopened.'));
    }

    public function destroy(ClassCouncil $council)
    {
        abort_if($council->isConcluded(), 403, __('A concluded council is a record of a meeting and cannot be deleted. Reopen it first.'));

        $council->delete();

        return redirect()->route('admin.councils.index')->with('success', __('Council deleted.'));
    }

    /** The minutes, as the school would file them. */
    public function minutes(ClassCouncil $council)
    {
        $council->load(['classSection.form', 'term', 'chair', 'secretary', 'entries.student', 'signatures']);

        return Pdf::loadView('admin.councils.minutes', [
            'council' => $council,
            'entries' => $council->entries->sortBy([
                fn ($a, $b) => ($a->rank ?? PHP_INT_MAX) <=> ($b->rank ?? PHP_INT_MAX),
            ])->values(),
            'statistics' => $council->isConcluded()
                ? $this->frozenStatistics($council)
                : $this->councils->statistics($council),
            'tally' => $this->councils->awardTally($council),
            'awards' => ClassCouncilEntry::awards(),
            'school' => SchoolSetting::current(),
        ])->setPaper('a4')->download(
            'council-'.str($council->reference ?: $council->id)->slug().'.pdf'
        );
    }

    /**
     * What the meeting saw, read back off the record rather than recomputed.
     *
     * @return array<string, mixed>
     */
    private function frozenStatistics(ClassCouncil $council): array
    {
        return [
            'students' => $council->students,
            'passed' => $council->passed,
            'failed' => $council->failed,
            'class_average' => $council->class_average !== null ? (float) $council->class_average : null,
            'pass_rate' => $council->pass_rate !== null ? (float) $council->pass_rate : null,
            'highest_average' => $council->highest_average !== null ? (float) $council->highest_average : null,
            'lowest_average' => $council->lowest_average !== null ? (float) $council->lowest_average : null,
        ];
    }
}
