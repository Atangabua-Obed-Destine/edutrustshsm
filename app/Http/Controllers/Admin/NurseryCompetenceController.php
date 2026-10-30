<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\NurseryCompetence;
use App\Models\NurseryLearningArea;
use App\Services\NurseryAssessmentService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The school's own list of what a nursery child should be able to do,
 * grouped by learning area. Seeded from the standard list, then edited here.
 *
 * Gated with school settings: the list is a curriculum decision, not a
 * teacher's daily work.
 */
class NurseryCompetenceController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'school-settings';

    public function __construct(private NurseryAssessmentService $nursery) {}

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('school-settings.view', ['index']),
            static::can('school-settings.edit', ['seed', 'storeArea', 'updateArea', 'destroyArea', 'storeCompetence', 'updateCompetence', 'destroyCompetence']),
        ];
    }

    public function index()
    {
        $areas = NurseryLearningArea::ordered()
            ->with(['competences' => fn ($q) => $q->withCount('assessments')->with('form')])
            ->get();

        $forms = Form::active()->forCurrentLevel()->where('level', 'nursery')->ordered()->get();

        return view('admin.nursery.competences', compact('areas', 'forms'));
    }

    public function seed()
    {
        if (! $this->nursery->catalogueIsEmpty()) {
            return back()->with('error', __('The list already has entries. Add to it below rather than loading the standard list over it.'));
        }

        $count = $this->nursery->seedDefaults();

        return back()->with('success', __('The standard list is loaded: :count competences. Edit it to match what this school teaches.', ['count' => $count]));
    }

    public function storeArea(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);

        NurseryLearningArea::create([
            'name' => $data['name'],
            'display_order' => (NurseryLearningArea::max('display_order') ?? 0) + 1,
        ]);

        return back()->with('success', __('Learning area added.'));
    }

    public function updateArea(Request $request, NurseryLearningArea $area)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $area->update(['name' => $data['name'], 'is_active' => (bool) ($data['is_active'] ?? true)]);

        return back()->with('success', __('Learning area updated.'));
    }

    public function destroyArea(NurseryLearningArea $area)
    {
        // A rating already given points at a competence under this area; the
        // sheet it came from must keep reading. Hide instead of delete.
        $rated = NurseryCompetence::where('nursery_learning_area_id', $area->id)->whereHas('assessments')->exists();

        if ($rated) {
            $area->update(['is_active' => false]);

            return back()->with('success', __('Pupils have already been rated under this area, so it is hidden from new sheets rather than deleted.'));
        }

        $area->delete();

        return back()->with('success', __('Learning area removed.'));
    }

    public function storeCompetence(Request $request, NurseryLearningArea $area)
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'form_id' => ['nullable', 'exists:forms,id'],
        ]);

        $area->competences()->create([
            'description' => $data['description'],
            'form_id' => $data['form_id'] ?: null,
            'display_order' => ($area->competences()->max('display_order') ?? 0) + 1,
        ]);

        return back()->with('success', __('Competence added.'));
    }

    public function updateCompetence(Request $request, NurseryCompetence $competence)
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'form_id' => ['nullable', 'exists:forms,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $competence->update([
            'description' => $data['description'],
            'form_id' => $data['form_id'] ?: null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('success', __('Competence updated.'));
    }

    public function destroyCompetence(NurseryCompetence $competence)
    {
        if ($competence->assessments()->exists()) {
            $competence->update(['is_active' => false]);

            return back()->with('success', __('Pupils have already been rated on this competence, so it is hidden from new sheets rather than deleted.'));
        }

        $competence->delete();

        return back()->with('success', __('Competence removed.'));
    }
}
