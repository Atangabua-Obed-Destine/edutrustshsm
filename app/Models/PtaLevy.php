<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PtaLevy extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'academic_session_id', 'form_id',
        'amount', 'due_date', 'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function academicSession() { return $this->belongsTo(AcademicSession::class); }
    public function form() { return $this->belongsTo(Form::class); }
    public function payments() { return $this->hasMany(PtaLevyPayment::class); }
}
