<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Support\CheckCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One certificate or record handed out, in the school's register: its serial,
 * its check code, and what it said. Never edited; a reprint is a new entry.
 */
class IssuedDocument extends Model
{
    use BelongsToBranch;

    public const LEAVING_CERTIFICATE = 'leaving_certificate';
    public const CUMULATIVE_RECORD = 'cumulative_record';

    /** Serial prefix per kind of paper. */
    public const PREFIX = [
        self::LEAVING_CERTIFICATE => 'LC',
        self::CUMULATIVE_RECORD => 'CR',
    ];

    protected $fillable = ['branch_id', 'type', 'student_id', 'serial', 'verify_code', 'details', 'issued_by', 'issued_at'];

    protected function casts(): array
    {
        return ['details' => 'array', 'issued_at' => 'datetime'];
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::LEAVING_CERTIFICATE => __('Leaving Certificate'),
            self::CUMULATIVE_RECORD => __('Cumulative Record'),
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * Enter a paper in the register as it is printed.
     *
     * The serial runs per kind of paper per calendar year: LC-2027-0001,
     * LC-2027-0002… Taken under a lock on the year's last entry, so two
     * people printing at once cannot get the same number.
     *
     * @param  array<string, string|null>  $details  what the paper says, label => value
     */
    public static function issue(string $type, Student $student, array $details): self
    {
        return DB::transaction(function () use ($type, $student, $details) {
            $prefix = (self::PREFIX[$type] ?? 'DOC').'-'.now()->year.'-';

            $last = static::withoutBranchScope()
                ->where('serial', 'like', $prefix.'%')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('serial');
            $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            while (static::withoutBranchScope()->where('serial', $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT))->exists()) {
                $next++;
            }

            return static::create([
                'branch_id' => $student->branch_id,
                'type' => $type,
                'student_id' => $student->id,
                'serial' => $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT),
                'verify_code' => CheckCode::generate(fn (string $code) => static::withoutBranchScope()->where('verify_code', $code)->exists()),
                'details' => array_filter($details, fn ($v) => $v !== null && $v !== ''),
                'issued_by' => auth()->id(),
                'issued_at' => now(),
            ]);
        });
    }

    public function verifyUrl(): string
    {
        return route('document.verify', $this->verify_code);
    }

    public function verifyCodeForPrint(): string
    {
        return CheckCode::forPrint($this->verify_code);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
