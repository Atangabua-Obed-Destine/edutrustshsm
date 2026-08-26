<?php

namespace App\Observers;

use App\Models\Income;
use App\Services\TransactionAutoMapService;

class IncomeObserver
{
    public function __construct(private TransactionAutoMapService $mapper)
    {
    }

    public function created(Income $income): void
    {
        $this->mapper->autoMap('income', $income->id, $income->category_id, $this->data($income));
    }

    public function updated(Income $income): void
    {
        if ($income->wasChanged(['amount', 'category_id', 'date'])) {
            $this->mapper->remap('income', $income->id, $income->category_id, $this->data($income));
        }
    }

    public function deleted(Income $income): void
    {
        $this->mapper->reverse('income', $income->id);
    }

    private function data(Income $income): array
    {
        return [
            'amount' => $income->amount,
            'date' => optional($income->date)->toDateString() ?? now()->toDateString(),
            'description' => 'Income - ' . $income->title,
        ];
    }
}
