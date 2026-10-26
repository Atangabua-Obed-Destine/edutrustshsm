<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Term;
use App\Services\ExamReadinessService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * Whether this term's results can honestly be published.
 *
 * Read-only on purpose. It reports what is outstanding and who it sits with;
 * the fixing happens on the screens that own the work.
 */
class ExamReadinessController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'academic-analytics';

    public function __construct(private ExamReadinessService $readiness) {}

    public function index(Request $request)
    {
        $term = $this->term($request);
        $formId = $request->integer('form_id') ?: null;
        $classSectionId = $request->integer('class_section_id') ?: null;

        return view('admin.analytics.readiness', [
            'report' => $term ? $this->readiness->report($term->id, $formId, $classSectionId) : null,
            'term' => $term,
            'terms' => Term::orderBy('term_number')->get(),
            'forms' => Form::active()->forCurrentLevel()->ordered()->get(),
            'sections' => ClassSection::where('is_active', true)
                ->whereHas('form', fn ($q) => $q->forCurrentLevel())
                ->when($formId, fn ($q) => $q->where('form_id', $formId))
                ->orderBy('name')->get(),
            'formId' => $formId,
            'classSectionId' => $classSectionId,
        ]);
    }

    private function term(Request $request): ?Term
    {
        if ($id = $request->integer('term_id')) {
            return Term::find($id);
        }

        return Term::where('is_current', true)->first() ?? Term::orderBy('term_number')->first();
    }
}
