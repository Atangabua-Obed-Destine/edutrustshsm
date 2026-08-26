<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PtaLevyPayment extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'pta_levy_id', 'guardian_id', 'student_id',
        'amount_paid', 'payment_date', 'payment_method',
        'receipt_ref', 'proof_path', 'status', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'payment_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function levy() { return $this->belongsTo(PtaLevy::class, 'pta_levy_id'); }
    public function guardian() { return $this->belongsTo(Guardian::class); }
    public function student() { return $this->belongsTo(Student::class); }
    public function verifiedBy() { return $this->belongsTo(User::class, 'verified_by'); }
}
