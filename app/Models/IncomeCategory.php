<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class IncomeCategory extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['branch_id', 'title', 'slug', 'description', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function incomes()
    {
        return $this->hasMany(Income::class, 'category_id');
    }
}
