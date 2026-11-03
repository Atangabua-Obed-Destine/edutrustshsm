<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\StudentFee;
use App\Services\FeeAdjustment;
use RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FeeAssignmentHistoryController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'assignment-history';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('assignment-history.view', ['index', 'streamsByForm', 'show']),
            static::can('assignment-history.edit', ['updateAmount', 'updateDueDate']),
            static::can('assignment-history.transfer', ['transfer']),
            static::can('assignment-history.delete', ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $forms = Form::active()->forCurrentLevel()->ordered()->get();
        $categories = FeeCategory::where('is_active', true)->orderBy('name')->get();
        $currentSession = AcademicSession::current();

        $sessionId = $request->input('session_id', $currentSession?->id);
        $formId = $request->input('form_id');
        $streamId = $request->input('stream_id');
        $categoryId = $request->input('category_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $assignments = collect();
        $twinKeys = [];
        $loaded = false;

        $streams = $formId
            ? Form::find($formId)?->streams()->orderBy('name')->get() ?? collect()
            : collect();

        if ($request->has('session_id') || $request->has('form_id')) {
            $loaded = true;

            $query = StudentFee::with([
                    'enrollment.student',
                    'enrollment.classSection.form',
                    'enrollment.stream',
                    'feeCategory',
                ])
                ->whereHas('enrollment', function ($q) use ($sessionId, $formId, $streamId) {
                    if ($sessionId) {
                        $q->where('academic_session_id', $sessionId);
                    }
                    if ($formId) {
                        $q->whereHas('classSection', fn ($cs) => $cs->where('form_id', $formId));
                    }
                    if ($streamId) {
                        $q->where('stream_id', $streamId);
                    }
                });

            if ($categoryId) {
                $query->where('fee_category_id', $categoryId);
            }

            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            $assignments = $query->orderByDesc('created_at')->get();
        }

        // The same fee billed twice to one pupil in one year: shown so the
        // bursar can open it and put it right.
        $twinKeys = $assignments
            ->groupBy(fn ($f) => $f->enrollment?->student_id.'|'.$f->enrollment?->academic_session_id.'|'.$f->fee_category_id)
            ->filter(fn ($g) => $g->count() > 1)
            ->map(fn () => true)
            ->all();

        // Summary stats
        $totalAssigned = $assignments->sum('original_amount');
        $totalNet = $assignments->sum('net_amount');
        $totalPaid = $assignments->sum('paid_amount');
        $totalBalance = $assignments->sum('balance');

        return view('admin.fees.assignment-history.index', compact(
            'sessions', 'forms', 'categories', 'streams',
            'sessionId', 'formId', 'streamId', 'categoryId', 'dateFrom', 'dateTo',
            'assignments', 'loaded', 'twinKeys',
            'totalAssigned', 'totalNet', 'totalPaid', 'totalBalance'
        ));
    }

    public function streamsByForm(Form $form)
    {
        return response()->json(
            $form->streams()->where('is_active', true)->orderBy('name')->get(['streams.id', 'streams.name', 'streams.code'])
        );
    }

    /**
     * One fee, what happened to it, and what may still be changed. Each
     * change states why, and a change the rules forbid says what to do instead.
     */
    public function show(StudentFee $studentFee, FeeAdjustment $adjust)
    {
        $studentFee->load(['enrollment.student', 'enrollment.classSection', 'enrollment.academicSession', 'enrollment.term', 'feeCategory', 'allocations.payment', 'paymentPlan']);

        $blockers = [
            'raise' => $adjust->amountBlockers($studentFee, (float) $studentFee->original_amount + 1),
            'lower' => $adjust->amountBlockers($studentFee, max(1, (float) $studentFee->original_amount - 1)),
            'due' => $adjust->dueDateBlockers($studentFee),
            'remove' => $adjust->removalBlockers($studentFee),
        ];
        $targets = $adjust->targets($studentFee);

        // Other bills for the same thing in the same year: almost always a
        // double billing, which this page exists to put right.
        $twins = StudentFee::with(['enrollment.term', 'enrollment.academicSession'])
            ->where('fee_category_id', $studentFee->fee_category_id)
            ->where('id', '!=', $studentFee->id)
            ->whereHas('enrollment', fn ($q) => $q
                ->where('student_id', $studentFee->enrollment?->student_id)
                ->where('academic_session_id', $studentFee->enrollment?->academic_session_id))
            ->get();

        return view('admin.fees.assignment-history.show', [
            'fee' => $studentFee, 'blockers' => $blockers, 'targets' => $targets, 'twins' => $twins,
        ]);
    }

    public function updateAmount(Request $request, StudentFee $studentFee, FeeAdjustment $adjust)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return $this->attempt(fn () => $adjust->correctAmount($studentFee, (float) $data['amount'], $data['reason']), __('Amount corrected.'));
    }

    public function updateDueDate(Request $request, StudentFee $studentFee, FeeAdjustment $adjust)
    {
        $data = $request->validate([
            'due_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        return $this->attempt(fn () => $adjust->correctDueDate($studentFee, $data['due_date'] ?? null, $data['reason']), __('Due date changed.'));
    }

    public function transfer(Request $request, StudentFee $studentFee, FeeAdjustment $adjust)
    {
        $data = $request->validate([
            'target' => ['required', 'regex:/^(credit|[0-9]+)$/'],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $target = $data['target'] === 'credit' ? null : StudentFee::findOrFail((int) $data['target']);

        return $this->attempt(fn () => $adjust->move($studentFee, $target, (float) $data['amount'], $data['reason']), __('Money moved.'));
    }

    /**
     * Take a fee off the pupil. Only a fee nothing was paid on, and never
     * without a reason: the bill disappears from every statement.
     */
    public function destroy(Request $request, StudentFee $studentFee, FeeAdjustment $adjust)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $sessionId = $studentFee->enrollment?->academic_session_id;

        try {
            $adjust->remove($studentFee, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.assignment-history.index', array_filter(['session_id' => $sessionId]))
            ->with('success', __('Fee removed from the pupil.'));
    }

    private function attempt(callable $change, string $done)
    {
        try {
            $change();
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $done);
    }
}
