<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\Stream;
use App\Models\PaymentPlan;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Services\PaymentRecorder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class FeeCollectionController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'fee-collection';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('fee-collection.view', ['index', 'streamsByForm', 'sectionsByForm']),
            static::can('fee-collection.collect', ['storePayment']),
            static::can('fee-collection.receipt', ['receipt']),
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
        $sectionId = $request->input('section_id');
        $categoryId = $request->input('category_id');
        $status = $request->input('status');

        $studentFees = collect();
        $loaded = false;

        // Pre-populate cascade dropdowns
        $streams = $formId ? Form::find($formId)?->streams()->orderBy('name')->get() ?? collect() : collect();
        $sections = $formId
            ? ClassSection::where('form_id', $formId)->where('is_active', true)->orderBy('name')->get()
            : collect();

        if ($request->has('form_id') || $request->has('session_id')) {
            $loaded = true;

            $query = StudentFee::with([
                    'enrollment.student',
                    'enrollment.classSection',
                    'enrollment.academicSession',
                    'enrollment.stream',
                    'feeCategory',
                    'allocations.payment',
                    'paymentPlan.installments',
                ])
                ->whereHas('enrollment', function ($q) use ($sessionId, $formId, $streamId, $sectionId) {
                    $q->where('status', 'active');
                    if ($sessionId) {
                        $q->where('academic_session_id', $sessionId);
                    }
                    if ($sectionId) {
                        $q->where('class_section_id', $sectionId);
                    } elseif ($formId) {
                        $q->whereHas('classSection', fn ($cs) => $cs->where('form_id', $formId));
                    }
                    if ($streamId) {
                        $q->where('stream_id', $streamId);
                    }
                });

            if ($categoryId) {
                $query->where('fee_category_id', $categoryId);
            }

            if ($status) {
                $query->where('status', $status);
            }

            $studentFees = $query
                ->orderBy('fee_category_id')
                ->get()
                ->sortBy([
                    fn ($a, $b) => ($a->enrollment->student->last_name ?? '') <=> ($b->enrollment->student->last_name ?? ''),
                    fn ($a, $b) => ($a->enrollment->student->first_name ?? '') <=> ($b->enrollment->student->first_name ?? ''),
                ]);
        }

        // Summary stats
        $totalExpected = $studentFees->sum('net_amount');
        $totalPaid = $studentFees->sum('paid_amount');
        $totalBalance = $studentFees->sum('balance');

        return view('admin.fees.collect.index', compact(
            'sessions', 'forms', 'categories', 'streams', 'sections',
            'sessionId', 'formId', 'streamId', 'sectionId', 'categoryId', 'status',
            'studentFees', 'loaded',
            'totalExpected', 'totalPaid', 'totalBalance'
        ));
    }

    public function streamsByForm(Form $form)
    {
        return response()->json(
            $form->streams()->where('is_active', true)->orderBy('name')->get(['streams.id', 'streams.name', 'streams.code'])
        );
    }

    public function sectionsByForm(Form $form)
    {
        return response()->json(
            ClassSection::where('form_id', $form->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'section'])
        );
    }

    public function storePayment(Request $request, PaymentRecorder $recorder)
    {
        $validated = $request->validate([
            'student_fee_id' => ['required', 'exists:student_fees,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:cash,bank_transfer,mtn_momo,orange_money,edutrustpay'],
            'payment_date' => ['required', 'date'],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'payer_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $studentFee = StudentFee::findOrFail($validated['student_fee_id']);

        // Recording, allocation, balances and payment-plan advancement all live
        // in PaymentRecorder so this screen, the payments screen and parent
        // portal approval cannot drift apart.
        $payment = $recorder->record([
            'student_enrollment_id' => $studentFee->student_enrollment_id,
            'target_fee_id'         => $studentFee->id,
            'amount'                => $validated['amount'],
            'payment_method'        => $validated['payment_method'],
            'payment_date'          => $validated['payment_date'],
            'payer_name'            => $validated['payer_name'] ?? null,
            'payer_phone'           => $validated['payer_phone'] ?? null,
            'notes'                 => $validated['notes'] ?? null,
        ]);

        $studentFee->refresh();

        $plan = PaymentPlan::where('student_fee_id', $studentFee->id)->first();

        // Plan snapshot for the JSON response.
        $planInfo = null;
        if ($plan) {
            $plan->refresh();
            $plan->load('installments');
            $nextInst = $plan->installments
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->sortBy('installment_number')
                ->first();
            $planInfo = [
                'id' => $plan->id,
                'status' => $plan->status,
                'paidCount' => $plan->installments->where('status', 'paid')->count(),
                'totalInstallments' => $plan->number_of_installments,
                'progress' => $plan->progress,
                'nextNum' => $nextInst?->installment_number,
                'nextAmount' => $nextInst ? (float) $nextInst->amount - (float) $nextInst->paid_amount : 0,
                'nextDue' => $nextInst?->due_date?->format('d M Y'),
            ];
        }

        return response()->json([
            'success' => true,
            'receipt_number' => $payment->receipt_number,
            'paid_amount' => $studentFee->paid_amount,
            'balance' => $studentFee->balance,
            'status' => $studentFee->status,
            'payment_id' => $payment->id,
            'plan' => $planInfo,
        ]);
    }

    public function receipt(Payment $payment)
    {
        $payment->load([
            'enrollment.student.guardian',
            'enrollment.classSection.form',
            'enrollment.stream',
            'enrollment.academicSession',
            'enrollment.term',
            'receivedBy',
            'allocations.studentFee.feeCategory',
            'allocations.studentFee.paymentPlan.installments',
        ]);

        $school = SchoolSetting::current();

        // Get ALL fees for this enrollment to show a full account summary
        $allFees = StudentFee::with('feeCategory')
            ->where('student_enrollment_id', $payment->student_enrollment_id)
            ->orderBy('fee_category_id')
            ->get();

        // Get ALL payments for this enrollment up to and including this payment's date
        $allPayments = Payment::where('student_enrollment_id', $payment->student_enrollment_id)
            ->where('id', '<=', $payment->id)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        $totalFees = $allFees->sum('net_amount');
        $totalPaidToDate = $allPayments->sum('amount');
        $overallBalance = $totalFees - $totalPaidToDate;

        return view('admin.fees.collect.receipt', compact(
            'payment', 'school', 'allFees', 'allPayments',
            'totalFees', 'totalPaidToDate', 'overallBalance'
        ));
    }
}
