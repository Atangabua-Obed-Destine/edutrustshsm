<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Why a tuition line was budgeted at what it was: pupils expected at a rate.
 * The product is stored beside the inputs so a later change to the fees is
 * visible as a difference rather than a figure that moved on its own.
 */
class BudgetLineForecast extends Model
{
    protected $fillable = ['budget_id', 'budget_line_id', 'student_count', 'rate', 'computed_amount', 'note'];

    protected function casts(): array
    {
        return [
            'student_count' => 'integer',
            'rate' => 'decimal:2',
            'computed_amount' => 'decimal:2',
        ];
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function line()
    {
        return $this->belongsTo(BudgetLine::class, 'budget_line_id');
    }
}
