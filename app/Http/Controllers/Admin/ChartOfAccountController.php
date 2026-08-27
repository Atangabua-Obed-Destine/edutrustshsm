<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ChartOfAccountController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'chart-of-accounts';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('chart-of-accounts.edit', ['toggleStatus']),
            static::can('chart-of-accounts.view', ['getByClass']),
        ];
    }

    public function index(Request $request)
    {
        $class = $request->input('class');
        $accounts = ChartOfAccount::with('parent')
            ->when($class, fn ($q) => $q->where('class_number', $class))
            ->orderBy('account_code')->get();

        $stats = [
            'total' => ChartOfAccount::count(),
            'active' => ChartOfAccount::where('is_active', true)->count(),
            'detail' => ChartOfAccount::where('account_category', 'detail')->count(),
            'balance' => ChartOfAccount::sum('current_balance'),
        ];

        return view('admin.accounting.chart.index', compact('accounts', 'stats', 'class'));
    }

    public function create()
    {
        $parents = ChartOfAccount::orderBy('account_code')->get();
        return view('admin.accounting.chart.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $data = $this->validateAccount($request);
        $account = ChartOfAccount::create($data + ['created_by' => auth()->id()]);
        AuditLog::log('created', ChartOfAccount::class, $account->id, null, $account->toArray());

        return redirect()->route('admin.chart-of-accounts.index')->with('success', __('Account created.'));
    }

    public function edit(ChartOfAccount $chart_of_account)
    {
        $parents = ChartOfAccount::where('id', '!=', $chart_of_account->id)->orderBy('account_code')->get();
        return view('admin.accounting.chart.edit', ['account' => $chart_of_account, 'parents' => $parents]);
    }

    public function update(Request $request, ChartOfAccount $chart_of_account)
    {
        if ($chart_of_account->is_system) {
            // System accounts: only allow renaming/reordering, not structural changes.
            $request->merge([
                'account_code' => $chart_of_account->account_code,
                'class_number' => $chart_of_account->class_number,
                'account_type' => $chart_of_account->account_type,
                'account_category' => $chart_of_account->account_category,
                'normal_balance' => $chart_of_account->normal_balance,
            ]);
        }

        $data = $this->validateAccount($request, $chart_of_account->id);
        $old = $chart_of_account->toArray();
        $chart_of_account->update($data + ['updated_by' => auth()->id()]);
        AuditLog::log('updated', ChartOfAccount::class, $chart_of_account->id, $old, $chart_of_account->toArray());

        return redirect()->route('admin.chart-of-accounts.index')->with('success', __('Account updated.'));
    }

    public function toggleStatus(ChartOfAccount $chart_of_account)
    {
        if ($chart_of_account->is_system) {
            return back()->with('error', __('System accounts cannot be deactivated.'));
        }
        $chart_of_account->update(['is_active' => ! $chart_of_account->is_active]);

        return back()->with('success', __('Account status updated.'));
    }

    public function destroy(ChartOfAccount $chart_of_account)
    {
        if ($chart_of_account->is_system) {
            return back()->with('error', __('System accounts cannot be deleted.'));
        }
        if ($chart_of_account->children()->exists()) {
            return back()->with('error', __('Cannot delete an account that has sub-accounts.'));
        }
        if ($chart_of_account->lines()->exists()) {
            return back()->with('error', __('Cannot delete an account that has journal entries.'));
        }

        $old = $chart_of_account->toArray();
        $chart_of_account->delete();
        AuditLog::log('deleted', ChartOfAccount::class, $old['id'], $old, null);

        return back()->with('success', __('Account deleted.'));
    }

    /** AJAX: postable accounts (optionally by class) for journal-entry/mapping selects. */
    public function getByClass(Request $request)
    {
        return response()->json(
            ChartOfAccount::postable()
                ->when($request->class, fn ($q) => $q->where('class_number', $request->class))
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'account_name'])
                ->map(fn ($a) => ['id' => $a->id, 'label' => $a->account_code . ' — ' . $a->account_name])
        );
    }

    private function validateAccount(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'account_code' => ['required', 'string', 'max:20', 'unique:chart_of_accounts,account_code' . ($id ? ',' . $id : '')],
            'account_name' => ['required', 'string', 'max:191'],
            'account_name_fr' => ['nullable', 'string', 'max:191'],
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'class_number' => ['required', 'integer', 'between:1,8'],
            'account_type' => ['required', 'in:asset,liability,equity,revenue,expense,other'],
            'account_category' => ['required', 'in:detail,heading,total,subtotal'],
            'normal_balance' => ['required', 'in:debit,credit'],
        ]);
    }
}
