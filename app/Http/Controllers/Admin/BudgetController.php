<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetRevision;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $query = Budget::with('department')
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->fiscal_year, fn ($q, $v) => $q->where('fiscal_year', $v))
            ->when($request->department_id, fn ($q, $v) => $q->where('department_id', $v));

        $budgets = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $departments = Department::orderBy('name')->get();
        $fiscalYears = Budget::select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year');

        return view('admin.budget.index', compact('budgets', 'departments', 'fiscalYears'));
    }

    public function create()
    {
        $departments = Department::orderBy('name')->get();
        return view('admin.budget.create', compact('departments'));
    }

    public function store(Request $request)
    {
        $data = $this->validateBudget($request);

        $budget = Budget::create($data + ['status' => 'draft', 'created_by' => auth()->id()]);
        AuditLog::log('created', Budget::class, $budget->id, null, $budget->toArray());

        return redirect()->route('admin.budget.show', $budget)
            ->with('success', __('Budget created successfully.'));
    }

    public function show(Budget $budget)
    {
        $budget->load(['department', 'allocations.expenseCategory', 'allocations.department', 'revisions.requestedBy', 'createdBy', 'approvedBy']);
        $expenses = $budget->expenses()->with('category')->orderByDesc('date')->limit(50)->get();

        return view('admin.budget.show', compact('budget', 'expenses'));
    }

    public function edit(Budget $budget)
    {
        if (! $budget->isEditable()) {
            return redirect()->route('admin.budget.show', $budget)
                ->with('error', __('Only draft or pending budgets can be edited.'));
        }
        $departments = Department::orderBy('name')->get();
        return view('admin.budget.edit', compact('budget', 'departments'));
    }

    public function update(Request $request, Budget $budget)
    {
        if (! $budget->isEditable()) {
            return redirect()->route('admin.budget.show', $budget)
                ->with('error', __('Only draft or pending budgets can be edited.'));
        }

        $data = $this->validateBudget($request);
        $old = $budget->toArray();

        // Keep remaining in step if the total changed pre-activation.
        $data['remaining_amount'] = bcsub((string) $data['total_amount'], (string) $budget->spent_amount, 2);
        $data['updated_by'] = auth()->id();
        $budget->update($data);

        AuditLog::log('updated', Budget::class, $budget->id, $old, $budget->toArray());

        return redirect()->route('admin.budget.show', $budget)
            ->with('success', __('Budget updated successfully.'));
    }

    public function destroy(Budget $budget)
    {
        if ($budget->status !== 'draft') {
            return back()->with('error', __('Only draft budgets can be deleted.'));
        }

        $old = $budget->toArray();
        DB::transaction(function () use ($budget) {
            $budget->allocations()->delete();
            $budget->delete();
        });
        AuditLog::log('deleted', Budget::class, $old['id'], $old, null);

        return redirect()->route('admin.budget.index')->with('success', __('Budget deleted.'));
    }

    // ── Lifecycle transitions ──
    public function submitForApproval(Budget $budget)
    {
        return $this->transition($budget, from: ['draft'], to: 'pending_approval', msg: __('Budget submitted for approval.'));
    }

    public function approve(Budget $budget)
    {
        return $this->transition($budget, from: ['pending_approval'], to: 'approved', msg: __('Budget approved.'), extra: [
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
    }

    public function activate(Budget $budget)
    {
        return $this->transition($budget, from: ['approved'], to: 'active', msg: __('Budget activated.'));
    }

    public function close(Budget $budget)
    {
        return $this->transition($budget, from: ['active'], to: 'closed', msg: __('Budget closed.'));
    }

    public function cancel(Budget $budget)
    {
        return $this->transition($budget, from: ['draft', 'pending_approval', 'approved', 'active'], to: 'cancelled', msg: __('Budget cancelled.'));
    }

    /** Revise the total of an approved/active budget (apply-immediately, logged). */
    public function revise(Request $request, Budget $budget)
    {
        if (! in_array($budget->status, ['approved', 'active'], true)) {
            return back()->with('error', __('Only approved or active budgets can be revised.'));
        }

        $validated = $request->validate([
            'new_amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
            'justification' => ['nullable', 'string'],
        ]);

        $new = $validated['new_amount'];
        if (bccomp((string) $new, (string) $budget->allocated_amount, 2) < 0) {
            return back()->with('error', __('New total cannot be below the already allocated amount (:amt).', ['amt' => number_format($budget->allocated_amount, 2)]));
        }
        if (bccomp((string) $new, (string) $budget->spent_amount, 2) < 0) {
            return back()->with('error', __('New total cannot be below the already spent amount (:amt).', ['amt' => number_format($budget->spent_amount, 2)]));
        }
        if (bccomp((string) $new, (string) $budget->total_amount, 2) === 0) {
            return back()->with('error', __('New total must differ from the current total.'));
        }

        DB::transaction(function () use ($budget, $validated, $new) {
            $revision = BudgetRevision::create([
                'budget_id' => $budget->id,
                'previous_amount' => $budget->total_amount,
                'new_amount' => $new,
                'reason' => $validated['reason'],
                'justification' => $validated['justification'] ?? null,
                'requested_by' => auth()->id(),
            ]);
            $revision->approve(auth()->id());
            AuditLog::log('revised', Budget::class, $budget->id, ['total' => $revision->previous_amount], ['total' => $revision->new_amount]);
        });

        return back()->with('success', __('Budget total revised successfully.'));
    }

    private function transition(Budget $budget, array $from, string $to, string $msg, array $extra = [])
    {
        if (! in_array($budget->status, $from, true)) {
            return back()->with('error', __('Invalid status transition.'));
        }

        $old = $budget->status;
        $budget->update(['status' => $to] + $extra);
        AuditLog::log('status_changed', Budget::class, $budget->id, ['status' => $old], ['status' => $to]);

        return back()->with('success', $msg);
    }

    private function validateBudget(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'type' => ['required', 'in:annual,departmental,project'],
            'department_id' => ['nullable', 'required_if:type,departmental', 'exists:departments,id'],
            'fiscal_year' => ['required', 'string', 'max:10'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
        ]);
    }
}
