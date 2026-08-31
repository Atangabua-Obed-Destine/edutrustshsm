<?php

namespace App\Support;

use App\Models\AdmissionApplication;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Leave;
use App\Models\ParentPaymentSubmission;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentAccountTransfer;
use App\Models\PtaMeeting;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;

/**
 * The registry of files that must never be served straight off the web root.
 *
 * Birth certificates, medical certificates and transfer certificates were
 * written to the PUBLIC disk, and public/storage is a real directory — so
 * anyone who guessed a filename could download a minor's medical record with no
 * session at all. Financial attachments and parent payment receipts sat beside
 * them.
 *
 * Every entry names the model, the permission required to read it, and the
 * exact columns that may be served. The column whitelist is what stops the
 * route being pointed at an arbitrary attribute.
 */
class PrivateFiles
{
    private const STUDENT_DOCUMENTS = [
        'birth_certificate', 'primary_certificate', 'gce_ol_certificate',
        'transfer_certificate', 'medical_certificate',
    ];

    /**
     * source => [model, permission, allowed columns]
     *
     * @var array<string, array{0: class-string<Model>, 1: string, 2: array<int, string>}>
     */
    public const SOURCES = [
        'student' => [Student::class, 'student.view', self::STUDENT_DOCUMENTS],
        'application' => [AdmissionApplication::class, 'admission.view', self::STUDENT_DOCUMENTS],
        'income' => [Income::class, 'income.view', ['attach']],
        'expense' => [Expense::class, 'expense.view', ['attach']],
        'transaction' => [PaymentAccountTransaction::class, 'payment-account.view', ['attach']],
        'transfer' => [PaymentAccountTransfer::class, 'fund-transfer.view', ['attach']],
        'leave' => [Leave::class, 'staff-leave.view', ['attachment']],
        'parent-receipt' => [ParentPaymentSubmission::class, 'parent-payment.view', ['receipt_path']],
        'pta-minutes' => [PtaMeeting::class, 'pta.view', ['minutes_path']],
    ];

    /** The prefixes the securing command moves off the public disk. */
    public const PRIVATE_PREFIXES = [
        'students/documents',
        'applications/documents',
        'accounts/income',
        'accounts/expense',
        'accounts/transactions',
        'accounts/transfers',
        'leave',
        'parent-receipts',
        'pta-minutes',
    ];

    /** @return array{0: class-string<Model>, 1: string, 2: array<int, string>}|null */
    public static function source(string $source): ?array
    {
        return self::SOURCES[$source] ?? null;
    }

    /** Whether a column may be served for a source. */
    public static function allows(string $source, string $field): bool
    {
        return in_array($field, self::SOURCES[$source][2] ?? [], true);
    }
}
