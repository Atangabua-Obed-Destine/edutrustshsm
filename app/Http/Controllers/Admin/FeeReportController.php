<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Payment;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeReportController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'fee-report';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('fee-report.view', ['index', 'partial']),
            static::can('fee-report.export', ['partialCsv']),
        ];
    }

    public function index(Request $request)
    {
        $currentSession = AcademicSession::current();
        $sessionId = $request->input('session_id', $currentSession?->id);
        $sessions = AcademicSession::orderByDesc('start_date')->get();

        // Overall stats
        $totalExpected = StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))->sum('net_amount');
        $totalCollected = StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))->sum('paid_amount');
        $totalOutstanding = StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))->sum('balance');
        $totalDiscounts = StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))->sum('discount_amount');

        $collectionRate = $totalExpected > 0 ? round(($totalCollected / $totalExpected) * 100, 1) : 0;

        // Collection by category
        $byCategory = StudentFee::select(
                'fee_category_id',
                DB::raw('SUM(net_amount) as expected'),
                DB::raw('SUM(paid_amount) as collected'),
                DB::raw('SUM(balance) as outstanding'),
                DB::raw('COUNT(*) as student_count')
            )
            ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))
            ->groupBy('fee_category_id')
            ->with('feeCategory')
            ->get();

        // Collection by class. Enrollments are loaded once rather than one query
        // per student.
        $byClass = StudentFee::select(
                'student_enrollment_id',
                DB::raw('SUM(net_amount) as expected'),
                DB::raw('SUM(paid_amount) as collected'),
                DB::raw('SUM(balance) as outstanding')
            )
            ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))
            ->groupBy('student_enrollment_id')
            ->get();

        $enrollments = StudentEnrollment::with('classSection')
            ->whereIn('id', $byClass->pluck('student_enrollment_id'))
            ->get()
            ->keyBy('id');

        $classStats = [];
        foreach ($byClass as $row) {
            $enrollment = $enrollments->get($row->student_enrollment_id);
            if (! $enrollment || ! $enrollment->classSection) {
                continue;
            }
            $className = $enrollment->classSection->name;
            if (! isset($classStats[$className])) {
                $classStats[$className] = ['expected' => 0, 'collected' => 0, 'outstanding' => 0, 'students' => 0];
            }
            $classStats[$className]['expected'] += $row->expected;
            $classStats[$className]['collected'] += $row->collected;
            $classStats[$className]['outstanding'] += $row->outstanding;
            $classStats[$className]['students']++;
        }
        ksort($classStats);

        // Money actually received: a reversed receipt stays on file but is not
        // money, and counting it would overstate collections.
        $received = fn () => Payment::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId))
            ->where('verification_status', 'verified');

        $byMethod = $received()
            ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        // Grouped by month in PHP: DATE_FORMAT is MySQL-only, and this screen
        // could not run on any other database.
        $monthlyTrend = $received()
            ->get(['amount', 'payment_date'])
            ->groupBy(fn ($p) => $p->payment_date->format('Y-m'))
            ->map(fn ($payments, $month) => (object) [
                'month' => $month,
                'total' => $payments->sum('amount'),
                'count' => $payments->count(),
            ])
            ->sortKeys()
            ->values();

        // Defaulters (students with outstanding balance)
        $defaulters = StudentFee::with(['enrollment.student', 'enrollment.classSection.form'])
            ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $sessionId)->where('status', 'active'))
            ->where('balance', '>', 0)
            ->get()
            ->groupBy('student_enrollment_id')
            ->map(function ($fees) {
                $first = $fees->first();

                return [
                    'student' => $first->enrollment->student,
                    'class' => $first->enrollment->classSection->name ?? 'N/A',
                    'total_balance' => $fees->sum('balance'),
                    'total_fees' => $fees->sum('net_amount'),
                    'total_paid' => $fees->sum('paid_amount'),
                ];
            })
            ->sortByDesc('total_balance')
            ->take(50);

        return view('admin.fees.reports.index', compact(
            'sessions', 'sessionId', 'totalExpected', 'totalCollected',
            'totalOutstanding', 'totalDiscounts', 'collectionRate',
            'byCategory', 'classStats', 'byMethod', 'monthlyTrend', 'defaulters'
        ));
    }

    /**
     * Fees that have been started but not finished.
     *
     * Follows the reference system's partial payment report: the families who
     * are paying, just not all of it, which is a different conversation from
     * the ones who have paid nothing.
     */
    public function partial(Request $request)
    {
        $query = $this->partialQuery($request);

        $stats = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as fees, COALESCE(SUM(net_amount), 0) as due, COALESCE(SUM(paid_amount), 0) as paid, COALESCE(SUM(balance), 0) as remaining')
            ->first();

        $fees = $query
            ->with(['enrollment.student', 'enrollment.classSection', 'feeCategory'])
            ->withCount(['allocations as receipts_count' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('verification_status', 'verified'))])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->paginate(25)
            ->withQueryString();

        return view('admin.fees.reports.partial', [
            'fees' => $fees,
            'stats' => $stats,
            'sessions' => AcademicSession::orderByDesc('start_date')->get(['id', 'name']),
            'sessionId' => $this->sessionId($request),
            'sections' => ClassSection::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => FeeCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function partialCsv(Request $request): StreamedResponse
    {
        $fees = $this->partialQuery($request)
            ->with(['enrollment.student', 'enrollment.classSection', 'enrollment.academicSession', 'feeCategory'])
            ->withCount(['allocations as receipts_count' => fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('verification_status', 'verified'))])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get();

        return response()->streamDownload(function () use ($fees) {
            $out = fopen('php://output', 'w');

            // Excel reads UTF-8 as Latin-1 without this, mangling accented names.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                __('Student ID'), __('Student Name'), __('Session'), __('Class'), __('Fee Category'),
                __('Total Amount'), __('Paid Amount'), __('Remaining Balance'), __('Due Date'), __('Payments Count'),
            ]);

            foreach ($fees as $fee) {
                fputcsv($out, [
                    $fee->enrollment?->student?->student_id,
                    $fee->enrollment?->student?->full_name,
                    $fee->enrollment?->academicSession?->name,
                    $fee->enrollment?->classSection?->name,
                    $fee->feeCategory?->name,
                    number_format((float) $fee->net_amount, 2, '.', ''),
                    number_format((float) $fee->paid_amount, 2, '.', ''),
                    number_format((float) $fee->balance, 2, '.', ''),
                    $fee->due_date?->format('Y-m-d'),
                    $fee->receipts_count,
                ]);
            }

            fclose($out);
        }, 'partial-payments-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Partially paid fees with the screen's filters applied. */
    private function partialQuery(Request $request): Builder
    {
        $request->validate([
            'session_id' => ['nullable', 'exists:academic_sessions,id'],
            'class_section_id' => ['nullable', 'exists:class_sections,id'],
            'fee_category_id' => ['nullable', 'exists:fee_categories,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $sessionId = $this->sessionId($request);

        return StudentFee::query()
            // Started but not finished — by the numbers rather than the stored
            // status, so a fee whose status was never recalculated still shows.
            ->where('paid_amount', '>', 0)
            ->where('balance', '>', 0.005)
            ->whereHas('enrollment', function ($q) use ($request, $sessionId) {
                $q->when($sessionId, fn ($e) => $e->where('academic_session_id', $sessionId))
                    ->when($request->input('class_section_id'), fn ($e, $id) => $e->where('class_section_id', $id));
            })
            ->when($request->input('fee_category_id'), fn ($q, $id) => $q->where('fee_category_id', $id))
            ->when($request->input('search'), function ($q, $search) {
                $q->whereHas('enrollment.student', fn ($s) => $s->where(function ($w) use ($search) {
                    $w->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%");
                }));
            });
    }

    private function sessionId(Request $request): ?int
    {
        return $request->has('session_id')
            ? ($request->input('session_id') ? (int) $request->input('session_id') : null)
            : AcademicSession::current()?->id;
    }
}
