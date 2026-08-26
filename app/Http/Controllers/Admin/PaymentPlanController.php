<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\Student;
use App\Models\StudentFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentPlan::with([
            'studentFee.enrollment.student',
            'studentFee.enrollment.classSection',
            'studentFee.enrollment.academicSession',
            'studentFee.feeCategory',
            'installments',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('studentFee.enrollment.student', function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('other_names', 'like', "%{$search}%");
            });
        }

        $plans = $query->latest()->paginate(20)->withQueryString();

        return view('admin.fees.payment-plans.index', compact('plans'));
    }

    public function create()
    {
        $students = Student::where('status', 'active')
            ->orderBy('first_name')
            ->get(['id', 'student_id', 'first_name', 'last_name', 'other_names']);

        return view('admin.fees.payment-plans.create', compact('students'));
    }

    /**
     * AJAX: Get unpaid/partial fees for a student.
     */
    public function studentFees(Student $student)
    {
        $fees = StudentFee::whereHas('enrollment', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->whereIn('status', ['unpaid', 'partial'])
            ->whereDoesntHave('paymentPlan', function ($q) {
                $q->whereIn('status', ['active']);
            })
            ->with(['feeCategory', 'enrollment.academicSession', 'enrollment.term', 'enrollment.classSection'])
            ->get();

        return response()->json($fees->map(function ($fee) {
            return [
                'id' => $fee->id,
                'label' => $fee->feeCategory->name
                    . ' — ' . ($fee->enrollment->academicSession->name ?? '')
                    . ' ' . ($fee->enrollment->term->name ?? '')
                    . ' (' . ($fee->enrollment->classSection->name ?? '') . ')',
                'original_amount' => (float) $fee->original_amount,
                'discount_amount' => (float) $fee->discount_amount,
                'waiver_amount'   => (float) $fee->waiver_amount,
                'net_amount'      => (float) $fee->net_amount,
                'paid_amount'     => (float) $fee->paid_amount,
                'balance'         => (float) $fee->balance,
            ];
        }));
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_fee_id'          => 'required|exists:student_fees,id',
            'total_amount'            => 'required|numeric|min:1',
            'number_of_installments'  => 'required|integer|min:2|max:24',
            'late_fee_percentage'     => 'nullable|numeric|min:0|max:100',
            'grace_period_days'       => 'nullable|integer|min:0|max:365',
            'notes'                   => 'nullable|string|max:1000',
            'installments'            => 'required|array|min:2',
            'installments.*.amount'   => 'required|numeric|min:0',
            'installments.*.due_date' => 'required|date',
        ]);

        // Verify the fee still qualifies
        $fee = StudentFee::findOrFail($request->student_fee_id);
        $existingActive = PaymentPlan::where('student_fee_id', $fee->id)
            ->where('status', 'active')
            ->exists();
        if ($existingActive) {
            return back()->withInput()->withErrors(['student_fee_id' => 'An active payment plan already exists for this fee.']);
        }

        // Validate total matches
        $installmentTotal = collect($request->installments)->sum('amount');
        if (abs($installmentTotal - $request->total_amount) > 0.01) {
            return back()->withInput()->withErrors(['installments' => 'Installment amounts must equal the total amount.']);
        }

        DB::transaction(function () use ($request, $fee) {
            $plan = PaymentPlan::create([
                'student_fee_id'         => $fee->id,
                'total_amount'           => $request->total_amount,
                'number_of_installments' => $request->number_of_installments,
                'late_fee_percentage'    => $request->late_fee_percentage ?? 0,
                'grace_period_days'      => $request->grace_period_days ?? 0,
                'notes'                  => $request->notes,
                'status'                 => 'active',
                'created_by'             => Auth::id(),
            ]);

            foreach ($request->installments as $i => $inst) {
                PaymentPlanInstallment::create([
                    'payment_plan_id'    => $plan->id,
                    'installment_number' => $i + 1,
                    'amount'             => $inst['amount'],
                    'due_date'           => $inst['due_date'],
                ]);
            }
        });

        return redirect()->route('admin.payment-plans.index')
            ->with('success', 'Payment plan created successfully.');
    }

    public function show(PaymentPlan $paymentPlan)
    {
        $paymentPlan->load([
            'studentFee.enrollment.student',
            'studentFee.enrollment.classSection',
            'studentFee.enrollment.academicSession',
            'studentFee.enrollment.term',
            'studentFee.feeCategory',
            'installments.payment',
            'creator',
        ]);

        return view('admin.fees.payment-plans.show', compact('paymentPlan'));
    }

    public function cancel(PaymentPlan $paymentPlan)
    {
        if ($paymentPlan->status !== 'active') {
            return back()->with('error', 'Only active plans can be cancelled.');
        }

        DB::transaction(function () use ($paymentPlan) {
            $paymentPlan->update(['status' => 'cancelled']);
            $paymentPlan->installments()
                ->whereIn('status', ['pending', 'overdue'])
                ->update(['status' => 'cancelled']);
        });

        return back()->with('success', 'Payment plan cancelled.');
    }
}
