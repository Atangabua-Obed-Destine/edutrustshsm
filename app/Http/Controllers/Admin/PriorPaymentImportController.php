<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Services\PaymentRecorder;
use App\Support\SchoolCsv;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Fees parents paid before the school moved to this system.
 *
 * On migration day a school is part-way through the year: many parents have
 * already paid a first instalment, receipted in the old books. Each pupil's
 * balance must show what is really still owed, so those payments are
 * recorded against their fees, but as "paid before the switch": the cash was
 * received, banked and booked in the old system, so it is not counted in
 * today's takings, the cash book or the ledger here.
 */
class PriorPaymentImportController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'fee-collection';

    private const ALIASES = [
        'student_id' => 'matricule', 'mat' => 'matricule', 'matricule_no' => 'matricule',
        'montant' => 'amount', 'amount_paid' => 'amount', 'paid' => 'amount', 'montant_payé' => 'amount', 'montant_paye' => 'amount',
        'payment_date' => 'date', 'date_paid' => 'date', 'date_de_paiement' => 'date',
        'receipt' => 'old_receipt', 'receipt_no' => 'old_receipt', 'old_receipt_no' => 'old_receipt', 'reçu' => 'old_receipt', 'recu' => 'old_receipt', 'n°_reçu' => 'old_receipt',
        'payer' => 'payer_name', 'paid_by' => 'payer_name', 'payé_par' => 'payer_name',
    ];

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('fee-collection.view', ['index', 'template']),
            static::can('fee-collection.collect', ['upload']),
        ];
    }

    public function index()
    {
        $session = AcademicSession::current();
        $recorded = Payment::where('payment_method', 'prior_system')->where('verification_status', 'verified')
            ->whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $session?->id));

        return view('admin.fees.prior-payments', [
            'session' => $session,
            'count' => (clone $recorded)->count(),
            'total' => (float) (clone $recorded)->sum('amount'),
        ]);
    }

    public function template()
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "sep=,\n");
        fputcsv($out, ['matricule', 'amount', 'date', 'old_receipt', 'payer_name'], ',', '"', '');
        fputcsv($out, ['5SNPS260001', '50000', '08/09/2026', '0457', 'Ngwa Peter'], ',', '"', '');
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="fees_paid_before_template.csv"',
        ]);
    }

    public function upload(Request $request, PaymentRecorder $recorder)
    {
        $request->validate(['csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $session = AcademicSession::current();
        if (! $session) {
            return back()->with('error', __('No active academic session found.'));
        }

        [$headers, $rows] = SchoolCsv::read($request->file('csv_file')->getRealPath(), self::ALIASES);

        $missing = array_diff(['matricule', 'amount'], $headers);
        if ($missing) {
            return back()->with('error', __('The file has no column for: :columns. Start from the template.', ['columns' => implode(', ', $missing)]));
        }

        $recorded = 0;
        $total = 0.0;
        $skipped = [];
        $errors = [];
        $lineNum = null;

        DB::beginTransaction();

        try {
            foreach ($rows as $lineNum => $row) {
                $data = SchoolCsv::combine($headers, $row);
                $get = fn (string $key) => $data[$key] ?? '';

                if (implode('', $data) === '') {
                    continue;
                }

                $matricule = $get('matricule');
                $problems = [];

                $student = $matricule !== '' ? Student::where('student_id', $matricule)->first() : null;
                $enrollment = $student
                    ? StudentEnrollment::where('student_id', $student->id)->where('academic_session_id', $session->id)
                        ->where('status', 'active')->latest('id')->first()
                    : null;
                if (! $student) {
                    $problems[] = __('no pupil has matricule ":value"', ['value' => $matricule]);
                } elseif (! $enrollment) {
                    $problems[] = __('this pupil is not registered in a class this year');
                }

                $amount = SchoolCsv::amount($get('amount'));
                if (! $amount || $amount <= 0) {
                    $problems[] = __('amount ":value" is not a number of francs', ['value' => $get('amount')]);
                }

                $date = $get('date') !== '' ? SchoolCsv::date($get('date')) : now();
                if (! $date || $date->isFuture()) {
                    $problems[] = __('date ":value" is not a past date (use DD/MM/YYYY)', ['value' => $get('date')]);
                }

                $name = $student ? trim($student->last_name.' '.$student->first_name) : $matricule;

                if (! $problems && $enrollment) {
                    $owed = (float) StudentFee::where('student_enrollment_id', $enrollment->id)->sum('balance');
                    if ($owed <= 0) {
                        $problems[] = __('nothing is owed: set the class fees before recording what was paid');
                    } elseif ($amount > $owed + 0.01) {
                        $problems[] = __(':amount is more than the :owed still owed', ['amount' => number_format($amount), 'owed' => number_format($owed)]);
                    }
                }

                if ($problems) {
                    $errors[] = __('Line :line (:name): :problems', ['line' => $lineNum, 'name' => $name ?: '—', 'problems' => implode('; ', $problems)]);
                    continue;
                }

                // The same old receipt (or the same amount on the same day)
                // must not be recorded twice when the file is sent again.
                $oldReceipt = $get('old_receipt');
                $already = Payment::where('payment_method', 'prior_system')->where('verification_status', 'verified')
                    ->whereHas('enrollment', fn ($q) => $q->where('student_id', $student->id)->where('academic_session_id', $session->id))
                    ->when($oldReceipt !== '',
                        fn ($q) => $q->where('transaction_ref', $oldReceipt),
                        fn ($q) => $q->where('amount', $amount)->whereDate('payment_date', $date->toDateString()))
                    ->first();
                if ($already) {
                    $skipped[] = __('Line :line (:name): already recorded on :receipt', ['line' => $lineNum, 'name' => $name, 'receipt' => $already->receipt_number]);
                    continue;
                }

                $recorder->record([
                    'student_enrollment_id' => $enrollment->id,
                    'amount' => $amount,
                    'payment_method' => 'prior_system',
                    'payment_date' => $date->toDateString(),
                    'payer_name' => $get('payer_name') ?: null,
                    'transaction_ref' => $oldReceipt ?: null,
                    'notes' => __('Paid before the switch to this system'),
                ]);

                $recorded++;
                $total += $amount;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', __('Nothing was imported: line :line could not be saved (:reason).', ['line' => $lineNum ?? '?', 'reason' => $e->getMessage()]));
        }

        $message = trans_choice('{0} No payments were recorded.|{1} 1 payment of :total recorded.|[2,*] :count payments totalling :total recorded.', $recorded, ['count' => $recorded, 'total' => number_format($total)]);
        if ($skipped) {
            $message .= ' '.trans_choice('{1} 1 was already recorded and was left as it was.|[2,*] :count were already recorded and were left as they were.', count($skipped), ['count' => count($skipped)]);
        }
        if ($errors) {
            $message .= ' '.trans_choice('{1} 1 line needs correcting: fix it and upload the file again.|[2,*] :count lines need correcting: fix them and upload the file again.', count($errors), ['count' => count($errors)]);
        }

        return redirect()->route('admin.prior-payments.index')
            ->with($recorded > 0 ? 'success' : 'error', $message)
            ->with('upload_errors', array_merge($errors, $skipped));
    }
}
