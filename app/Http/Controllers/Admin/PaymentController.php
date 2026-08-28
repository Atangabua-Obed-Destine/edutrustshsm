<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Services\PaymentRecorder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PaymentController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'payment';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('payment.view', ['index', 'show', 'studentFees']),
            static::can('fee-collection.collect', ['create', 'store']),
        ];
    }

    public function index(Request $request)
    {
        $query = Payment::with(['enrollment.student', 'enrollment.classSection.form', 'receivedBy']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                  ->orWhereHas('enrollment.student', fn ($sq) => $sq->where('student_id', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.fees.payments.index', compact('payments'));
    }

    public function create(Request $request)
    {
        $student = null;
        $fees = collect();
        $enrollment = null;

        if ($studentId = $request->input('student_id')) {
            $student = Student::find($studentId);
            if ($student) {
                $enrollment = $student->currentEnrollment;
                if ($enrollment) {
                    $fees = StudentFee::with('feeCategory')
                        ->where('student_enrollment_id', $enrollment->id)
                        ->where('balance', '>', 0)
                        ->get();
                }
            }
        }

        return view('admin.fees.payments.create', compact('student', 'enrollment', 'fees'));
    }

    public function store(Request $request, PaymentRecorder $recorder)
    {
        $validated = $request->validate([
            'student_enrollment_id' => ['required', 'exists:student_enrollments,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:cash,bank_transfer,mtn_momo,orange_money,edutrustpay'],
            'payment_date' => ['required', 'date'],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'payer_phone' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.fee_id' => ['required', 'exists:student_fees,id'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        // Recording, allocation, balance updates and payment-plan advancement
        // all live in PaymentRecorder — this screen used to carry its own copy,
        // which was the one that never capped an allocation at the fee balance
        // and never advanced a payment plan.
        $payment = $recorder->record([
            'student_enrollment_id' => $validated['student_enrollment_id'],
            'amount'                => $validated['amount'],
            'payment_method'        => $validated['payment_method'],
            'payment_date'          => $validated['payment_date'],
            'payer_name'            => $validated['payer_name'] ?? null,
            'payer_phone'           => $validated['payer_phone'] ?? null,
            'bank_name'             => $validated['bank_name'] ?? null,
            'transaction_ref'       => $validated['transaction_ref'] ?? null,
            'notes'                 => $validated['notes'] ?? null,
            'allocations'           => $validated['allocations'] ?? [],
        ]);

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', __('Payment recorded. Receipt: :r', ['r' => $payment->receipt_number]));
    }

    public function show(Payment $payment)
    {
        $payment->load([
            'enrollment.student',
            'enrollment.classSection.form',
            'receivedBy',
            'allocations.studentFee.feeCategory',
        ]);

        return view('admin.fees.payments.show', compact('payment'));
    }

    /**
     * Student fee summary (all fees + payments for a student)
     */
    public function studentFees(Student $student)
    {
        $student->load(['currentEnrollment.classSection.form']);
        $enrollment = $student->currentEnrollment;

        $fees = $enrollment
            ? StudentFee::with('feeCategory')->where('student_enrollment_id', $enrollment->id)->get()
            : collect();

        $payments = $enrollment
            ? Payment::with('allocations.studentFee.feeCategory')
                ->where('student_enrollment_id', $enrollment->id)
                ->orderByDesc('payment_date')
                ->get()
            : collect();

        $totalFees = $fees->sum('net_amount');
        $totalPaid = $fees->sum('paid_amount');
        $totalBalance = $fees->sum('balance');

        return view('admin.fees.student-fees', compact('student', 'enrollment', 'fees', 'payments', 'totalFees', 'totalPaid', 'totalBalance'));
    }
}
