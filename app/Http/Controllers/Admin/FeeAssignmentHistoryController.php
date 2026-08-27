<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\StudentFee;
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
            static::can('assignment-history.view', ['index', 'streamsByForm']),
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

        // Summary stats
        $totalAssigned = $assignments->sum('original_amount');
        $totalNet = $assignments->sum('net_amount');
        $totalPaid = $assignments->sum('paid_amount');
        $totalBalance = $assignments->sum('balance');

        return view('admin.fees.assignment-history.index', compact(
            'sessions', 'forms', 'categories', 'streams',
            'sessionId', 'formId', 'streamId', 'categoryId', 'dateFrom', 'dateTo',
            'assignments', 'loaded',
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
     * Delete (revoke) a fee assignment — only if no payments have been made.
     */
    public function destroy(StudentFee $studentFee)
    {
        if ((float) $studentFee->paid_amount > 0) {
            return back()->with('error', __('Cannot delete a fee that has payments. Reverse the payments first.'));
        }

        $studentFee->delete();

        return back()->with('success', __('Fee assignment deleted successfully.'));
    }
}
