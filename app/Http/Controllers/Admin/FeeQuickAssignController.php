<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FeeQuickAssignController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'quick-assign-fee';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('quick-assign-fee.view', ['create', 'searchStudents', 'checkFee']),
            static::can('quick-assign-fee.assign', ['store']),
        ];
    }

    public function create()
    {
        $currentSession = AcademicSession::current();
        $categories = FeeCategory::where('is_active', true)->orderBy('name')->get();

        return view('admin.fees.quick-assign.create', compact('currentSession', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_enrollment_id' => 'required|exists:student_enrollments,id',
            'fee_category_id' => 'required|exists:fee_categories,id',
            'amount' => 'required|numeric|min:1',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check if this fee is already assigned
        $existing = StudentFee::where('student_enrollment_id', $validated['student_enrollment_id'])
            ->where('fee_category_id', $validated['fee_category_id'])
            ->first();

        if ($existing && !$request->boolean('override')) {
            return back()->withInput()->with('duplicate_warning', true)->with('existing_fee', $existing);
        }

        if ($existing && $request->boolean('override')) {
            // Same rules as correcting a fee from its own page: raised freely,
            // lowered only while nothing is paid, and the change is noted.
            try {
                $adjust = app(\App\Services\FeeAdjustment::class);
                if (round((float) $validated['amount']) !== round((float) $existing->original_amount)) {
                    $adjust->correctAmount($existing, (float) $validated['amount'], ($validated['notes'] ?? null) ?: __('Changed on Quick Assign'));
                }
                if (($validated['due_date'] ?? null) && $existing->due_date?->toDateString() !== $validated['due_date'] && (float) $existing->balance > 0) {
                    $adjust->correctDueDate($existing, $validated['due_date'], ($validated['notes'] ?? null) ?: __('Changed on Quick Assign'));
                }
            } catch (\RuntimeException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }

            return redirect()->route('admin.quick-assign.create')
                ->with('success', __('Fee updated successfully for the student.'));
        }

        // Create new fee assignment
        $netAmount = $validated['amount'];
        StudentFee::create([
            'student_enrollment_id' => $validated['student_enrollment_id'],
            'fee_category_id' => $validated['fee_category_id'],
            'original_amount' => $validated['amount'],
            'discount_amount' => 0,
            'waiver_amount' => 0,
            'net_amount' => $netAmount,
            'paid_amount' => 0,
            'balance' => $netAmount,
            'status' => 'unpaid',
            'due_date' => $validated['due_date'],
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('admin.quick-assign.create')
            ->with('success', __('Fee assigned successfully to the student.'));
    }

    /**
     * AJAX: Search students with active enrollment in current session.
     */
    public function searchStudents(Request $request)
    {
        $search = $request->input('q', '');
        $currentSession = AcademicSession::current();

        if (!$currentSession || strlen($search) < 2) {
            return response()->json([]);
        }

        $enrollments = StudentEnrollment::with(['student', 'classSection.form', 'stream'])
            ->where('academic_session_id', $currentSession->id)
            ->where('status', 'active')
            ->whereHas('student', function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('other_names', 'like', "%{$search}%");
            })
            ->limit(15)
            ->get();

        $results = $enrollments->map(function ($enrollment) {
            $student = $enrollment->student;
            $class = $enrollment->classSection->name ?? '';
            $stream = $enrollment->stream->name ?? '';
            $formId = $enrollment->classSection->form_id ?? null;

            return [
                'enrollment_id' => $enrollment->id,
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'class' => $class,
                'stream' => $stream,
                'form_id' => $formId,
                'stream_id' => $enrollment->stream_id,
                'label' => $student->student_id . ' — ' . $student->full_name . ' (' . $class . ($stream ? ' / ' . $stream : '') . ')',
            ];
        });

        return response()->json($results);
    }

    /**
     * AJAX: Check if fee already assigned + get fee structure amount.
     */
    public function checkFee(Request $request)
    {
        $enrollmentId = $request->input('enrollment_id');
        $categoryId = $request->input('category_id');
        $formId = $request->input('form_id');
        $streamId = $request->input('stream_id');

        $result = ['exists' => false, 'suggested_amount' => null];

        // Check existing assignment
        $existing = StudentFee::where('student_enrollment_id', $enrollmentId)
            ->where('fee_category_id', $categoryId)
            ->first();

        if ($existing) {
            $result['exists'] = true;
            $result['existing'] = [
                'original_amount' => $existing->original_amount,
                'paid_amount' => $existing->paid_amount,
                'balance' => $existing->balance,
                'status' => $existing->status,
            ];
        }

        // Look up fee structure for suggested amount
        $currentSession = AcademicSession::current();
        if ($currentSession && $formId && $categoryId) {
            $structure = FeeStructure::where('academic_session_id', $currentSession->id)
                ->where('form_id', $formId)
                ->where('fee_category_id', $categoryId)
                ->where(function ($q) use ($streamId) {
                    $q->where('stream_id', $streamId)
                      ->orWhereNull('stream_id');
                })
                ->orderByRaw('stream_id IS NULL ASC')
                ->first();

            if ($structure) {
                $result['suggested_amount'] = $structure->amount;
            }
        }

        return response()->json($result);
    }
}
