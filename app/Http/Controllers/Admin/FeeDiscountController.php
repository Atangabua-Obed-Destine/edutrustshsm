<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\FeeCategory;
use App\Models\FeeDiscount;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class FeeDiscountController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'fee-discount';

    public function index(Request $request)
    {
        $currentSession = AcademicSession::current();
        $query = FeeDiscount::with(['enrollment.student', 'enrollment.classSection.form', 'feeCategory', 'approvedBy']);

        if ($currentSession) {
            $query->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $currentSession->id));
        }

        if ($reason = $request->input('reason')) {
            $query->where('reason', $reason);
        }

        $discounts = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $stats = [
            'total' => FeeDiscount::when($currentSession, fn ($q) => $q->whereHas('enrollment', fn ($sq) => $sq->where('academic_session_id', $currentSession->id)))->count(),
            'scholarship' => FeeDiscount::where('reason', 'scholarship')->when($currentSession, fn ($q) => $q->whereHas('enrollment', fn ($sq) => $sq->where('academic_session_id', $currentSession->id)))->count(),
            'staff_child' => FeeDiscount::where('reason', 'staff_child')->when($currentSession, fn ($q) => $q->whereHas('enrollment', fn ($sq) => $sq->where('academic_session_id', $currentSession->id)))->count(),
            'hardship' => FeeDiscount::where('reason', 'financial_hardship')->when($currentSession, fn ($q) => $q->whereHas('enrollment', fn ($sq) => $sq->where('academic_session_id', $currentSession->id)))->count(),
        ];

        return view('admin.fees.discounts.index', compact('discounts', 'stats'));
    }

    public function create(Request $request)
    {
        $currentSession = AcademicSession::current();
        $enrollments = collect();
        $categories = FeeCategory::where('is_active', true)->orderBy('name')->get();

        if ($currentSession) {
            $enrollments = StudentEnrollment::with(['student', 'classSection.form'])
                ->where('academic_session_id', $currentSession->id)
                ->where('status', 'active')
                ->get()
                ->sortBy(fn ($e) => $e->student->last_name);
        }

        return view('admin.fees.discounts.create', compact('enrollments', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_enrollment_id' => ['required', 'exists:student_enrollments,id'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'in:scholarship,staff_child,sibling,financial_hardship,merit,other'],
            'description' => ['nullable', 'string', 'max:500'],
            'apply_to' => ['required', 'in:all_fees,specific_category'],
            'fee_category_id' => ['required_if:apply_to,specific_category', 'nullable', 'exists:fee_categories,id'],
        ]);

        if ($validated['discount_type'] === 'percentage' && $validated['value'] > 100) {
            return back()->withInput()->withErrors(['value' => 'Percentage discount cannot exceed 100%.']);
        }

        // A discount that would bring a fee below what has already been paid
        // used to floor the balance at zero and the family's excess vanished.
        // Refuse it here; the excess has to go back as a credit, which is a
        // deliberate act, not a side effect.
        $targets = StudentFee::where('student_enrollment_id', $validated['student_enrollment_id'])
            ->when($validated['apply_to'] === 'specific_category', fn ($q) => $q->where('fee_category_id', $validated['fee_category_id']))
            ->get();
        foreach ($targets as $fee) {
            $cut = $this->discountOn($fee, $validated);
            if ((float) $fee->net_amount - $cut < (float) $fee->paid_amount - 0.5) {
                return back()->withInput()->with('error', __('This discount would bring :fee below the :paid already paid. Record the refund or credit for the difference first, then apply a smaller discount.', [
                    'fee' => $fee->feeCategory?->name ?? __('a fee'),
                    'paid' => number_format((float) $fee->paid_amount),
                ]));
            }
        }

        DB::transaction(function () use ($validated) {
            $discount = FeeDiscount::create(array_merge($validated, [
                'approved_by' => auth()->id(),
            ]));

            // Apply discount to student fees
            $query = StudentFee::where('student_enrollment_id', $validated['student_enrollment_id']);
            if ($validated['apply_to'] === 'specific_category') {
                $query->where('fee_category_id', $validated['fee_category_id']);
            }

            foreach ($query->get() as $fee) {
                $discountAmount = $this->discountOn($fee, $validated);

                $fee->discount_amount = $fee->discount_amount + $discountAmount;
                $fee->recalculate()->save();
            }
        });

        return redirect()->route('admin.fee-discounts.index')
            ->with('success', 'Discount applied successfully.');
    }

    public function destroy(FeeDiscount $feeDiscount)
    {
        DB::transaction(function () use ($feeDiscount) {
            // Reverse the discount from student fees
            $query = StudentFee::where('student_enrollment_id', $feeDiscount->student_enrollment_id);
            if ($feeDiscount->apply_to === 'specific_category') {
                $query->where('fee_category_id', $feeDiscount->fee_category_id);
            }

            foreach ($query->get() as $fee) {
                $discountAmount = $feeDiscount->discount_type === 'percentage'
                    ? round($fee->original_amount * $feeDiscount->value / 100, 2)
                    : min($feeDiscount->value, $fee->original_amount);

                $fee->discount_amount = max(0, $fee->discount_amount - $discountAmount);
                $fee->recalculate()->save();
            }

            $feeDiscount->delete();
        });

        return redirect()->route('admin.fee-discounts.index')
            ->with('success', 'Discount removed and fees recalculated.');
    }

    /**
     * The discount on one fee, in whole francs: FCFA has no sub-unit, and a
     * 0.50 balance left a fee "partial" forever, on the defaulters list and
     * open to fines.
     */
    private function discountOn(StudentFee $fee, array $validated): float
    {
        return $validated['discount_type'] === 'percentage'
            ? round((float) $fee->original_amount * (float) $validated['value'] / 100, 0)
            : min(round((float) $validated['value'], 0), (float) $fee->original_amount);
    }
}
