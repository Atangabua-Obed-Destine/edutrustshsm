<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\TransactionAutoMapService;

class ExpenseObserver
{
    public function __construct(private TransactionAutoMapService $mapper)
    {
    }

    public function created(Expense $expense): void
    {
        $this->mapper->autoMap('expense', $expense->id, $expense->category_id, $this->data($expense));
    }

    public function updated(Expense $expense): void
    {
        if ($expense->wasChanged(['amount', 'category_id', 'date'])) {
            $this->mapper->remap('expense', $expense->id, $expense->category_id, $this->data($expense));
        }
    }

    public function deleted(Expense $expense): void
    {
        $this->mapper->reverse('expense', $expense->id);
    }

    private function data(Expense $expense): array
    {
        return [
            'amount' => $expense->amount,
            'date' => optional($expense->date)->toDateString() ?? now()->toDateString(),
            'description' => 'Expense - ' . $expense->title,
        ];
    }
}
