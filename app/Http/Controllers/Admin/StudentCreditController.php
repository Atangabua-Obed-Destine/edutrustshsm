<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\StudentCredit;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Services\StudentCreditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use RuntimeException;

class StudentCreditController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'student-credit';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('student-credit.apply', ['apply']),
        ];
    }

    public function index(Request $request)
    {
        $credits = StudentCredit::with([
            'enrollment.student:id,student_id,first_name,last_name',
            'enrollment.classSection:id,name',
            'payment:id,receipt_number',
        ])
            ->when($request->input('only_available'), fn ($q) => $q->available())
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.fees.credits.index', [
            'credits' => $credits,
            'totalAvailable' => (float) StudentCredit::where('balance', '>', 0)->sum('balance'),
            'studentsHolding' => StudentCredit::where('balance', '>', 0)
                ->distinct('student_enrollment_id')->count('student_enrollment_id'),
        ]);
    }

    /** Apply a student's available credit to their outstanding fees. */
    public function apply(Request $request, StudentCreditService $credits)
    {
        $validated = $request->validate([
            'student_enrollment_id' => ['required', 'exists:student_enrollments,id'],
            'student_fee_id' => ['nullable', 'exists:student_fees,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $enrollment = StudentEnrollment::findOrFail($validated['student_enrollment_id']);

        try {
            $payment = $credits->apply(
                $enrollment,
                $validated['student_fee_id'] ?? null,
                isset($validated['amount']) ? (float) $validated['amount'] : null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Credit applied. Receipt: :r', ['r' => $payment->receipt_number]));
    }
}
