<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'academic_session_id', 'fee_category_id', 'form_id',
        'stream_id', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function feeCategory()
    {
        return $this->belongsTo(FeeCategory::class);
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }

    public function breakdowns()
    {
        return $this->hasMany(FeeBreakdown::class)->orderBy('sort_order');
    }
}
