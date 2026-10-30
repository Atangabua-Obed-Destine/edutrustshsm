<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Services\SchoolSituationReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * The school's own report card, once a term.
 *
 * Read-only and assembled on demand: the figures come from the same services
 * the individual screens use, so the report cannot say something the screens
 * would contradict.
 */
class SchoolReportController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'academic-analytics';

    public function __construct(private SchoolSituationReportService $reports) {}

    public function index(Request $request)
    {
        $term = $this->term($request);

        return view('admin.reports.school-situation', [
            'report' => $term ? $this->reports->forTerm($term) : null,
            'term' => $term,
            'terms' => Term::orderBy('term_number')->get(),
            'school' => SchoolSetting::current(),
        ]);
    }

    /** The same report as a document, which is the form it is asked for in. */
    public function download(Request $request)
    {
        $term = $this->term($request);

        abort_if($term === null, 404);

        return Pdf::loadView('admin.reports.school-situation-pdf', [
            'report' => $this->reports->forTerm($term),
            'term' => $term,
            'school' => SchoolSetting::current(),
        ])->setPaper('a4')->download(
            'state-of-the-school-'.str($term->name)->slug().'-'.now()->format('Y-m-d').'.pdf'
        );
    }

    private function term(Request $request): ?Term
    {
        if ($id = $request->integer('term_id')) {
            return Term::find($id);
        }

        return Term::where('is_current', true)->first() ?? Term::orderBy('term_number')->first();
    }
}
