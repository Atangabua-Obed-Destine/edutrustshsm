<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
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

    public function store(Request $request)
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

        $payment = DB::transaction(function () use ($validated, $request) {
            // Generate receipt number
            $settings = SchoolSetting::current();
            $prefix = $settings ? $settings->receipt_prefix : 'RCP';
            $lastPayment = Payment::where('receipt_number', 'like', "{$prefix}-%")
                ->orderByDesc('id')
                ->first();
            $nextNum = 1;
            if ($lastPayment) {
                $parts = explode('-', $lastPayment->receipt_number);
                $nextNum = (int) end($parts) + 1;
            }
            $receiptNumber = sprintf('%s-%06d', $prefix, $nextNum);

            $payment = Payment::create([
                'receipt_number' => $receiptNumber,
                'student_enrollment_id' => $validated['student_enrollment_id'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'payment_date' => $validated['payment_date'],
                'payer_name' => $validated['payer_name'] ?? null,
                'payer_phone' => $validated['payer_phone'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'transaction_ref' => $validated['transaction_ref'] ?? null,
                'verification_status' => 'verified',
                'received_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Auto-allocate payment to outstanding fees (FIFO)
            $remaining = $validated['amount'];
            if (!empty($validated['allocations'])) {
                foreach ($validated['allocations'] as $alloc) {
                    if ($alloc['amount'] <= 0 || $remaining <= 0) continue;

                    $fee = StudentFee::find($alloc['fee_id']);
                    if (! $fee) continue;

                    // Cap at the fee's OUTSTANDING balance as well as at what is
                    // left of the payment. Without the balance cap an operator could
                    // post 100,000 against a 10,000 fee, driving `balance` to
                    // -90,000 and corrupting every downstream SUM(balance).
                    $allocAmount = min((float) $alloc['amount'], (float) $remaining, (float) $fee->balance);
                    if ($allocAmount <= 0) continue;

                    PaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'student_fee_id' => $fee->id,
                        'amount' => $allocAmount,
                    ]);

                    $fee->paid_amount += $allocAmount;
                    $fee->balance = $fee->net_amount - $fee->paid_amount;
                    $fee->status = $fee->balance <= 0 ? 'paid' : ($fee->paid_amount > 0 ? 'partial' : 'unpaid');
                    $fee->save();

                    $remaining -= $allocAmount;
                }
            } else {
                // Auto FIFO allocation
                $outstandingFees = StudentFee::where('student_enrollment_id', $validated['student_enrollment_id'])
                    ->where('balance', '>', 0)
                    ->orderBy('id')
                    ->get();

                foreach ($outstandingFees as $fee) {
                    if ($remaining <= 0) break;
                    $allocAmount = min($fee->balance, $remaining);

                    PaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'student_fee_id' => $fee->id,
                        'amount' => $allocAmount,
                    ]);

                    $fee->paid_amount += $allocAmount;
                    $fee->balance = $fee->net_amount - $fee->paid_amount;
                    $fee->status = $fee->balance <= 0 ? 'paid' : 'partial';
                    $fee->save();

                    $remaining -= $allocAmount;
                }
            }

            return $payment;
        });

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', "Payment recorded. Receipt: {$payment->receipt_number}");
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
