<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Term;
use App\Services\AcademicAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the term's results say about the school.
 *
 * Everything on this screen is computed from published results, so it agrees
 * with the report cards by construction and there is nothing to regenerate
 * when a mark is corrected.
 */
class AcademicAnalyticsController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'academic-analytics';

    public function __construct(private AcademicAnalyticsService $analytics) {}

    public function index(Request $request)
    {
        $term = $this->term($request);
        $formId = $request->integer('form_id') ?: null;
        $classSectionId = $request->integer('class_section_id') ?: null;

        return view('admin.analytics.index', [
            'report' => $term ? $this->analytics->report($term->id, $formId, $classSectionId) : null,
            'term' => $term,
            'terms' => Term::orderBy('term_number')->get(),
            'forms' => Form::active()->forCurrentLevel()->ordered()->get(),
            'sections' => ClassSection::where('is_active', true)
                ->whereHas('form', fn ($q) => $q->forCurrentLevel())
                ->when($formId, fn ($q) => $q->where('form_id', $formId))
                ->orderBy('name')->get(),
            'currentSession' => AcademicSession::current(),
            'formId' => $formId,
            'classSectionId' => $classSectionId,
        ]);
    }

    /**
     * The subject table as a workbook-friendly CSV.
     *
     * Built from the same report the screen renders, so an exported figure
     * cannot disagree with the one that was on screen.
     */
    public function export(Request $request): StreamedResponse
    {
        $term = $this->term($request);

        abort_if($term === null, 404);

        $report = $this->analytics->report(
            $term->id,
            $request->integer('form_id') ?: null,
            $request->integer('class_section_id') ?: null
        );

        $rows = $this->analytics->subjectRows($report);
        $filename = 'subject-performance-'.str($term->name)->slug().'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // Excel reads the file as the system codepage without this, which
            // turns every accented French heading into mojibake.
            fwrite($handle, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The term being looked at — the one asked for, else the current one. */
    private function term(Request $request): ?Term
    {
        if ($id = $request->integer('term_id')) {
            return Term::find($id);
        }

        return Term::where('is_current', true)->first() ?? Term::orderBy('term_number')->first();
    }
}
