@extends('layouts.admin')

@section('title', __('Budget Detail'))
@section('breadcrumb', __('Budgets > :code', ['code' => $budget->budget_code]))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')
@php($uColor = ['green'=>'#22c55e','yellow'=>'#eab308','amber'=>'#f59e0b','red'=>'#ef4444'][$budget->utilization_color])
@php($locked = $budget->allocationsLocked())

@section('content')
<div class="space-y-4">
    {{-- Header + lifecycle actions --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <h3 class="text-lg font-semibold text-gray-800">{{ $budget->title }}</h3>
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $budget->status_badge }}">{{ ucfirst(str_replace('_',' ',$budget->status)) }}</span>
                </div>
                <p class="text-sm text-gray-500 mt-1">{{ $budget->budget_code }} · {{ ucfirst($budget->type) }} · {{ __('FY') }} {{ $budget->fiscal_year }}
                    @if($budget->department) · {{ $budget->department->name }} @endif
                    · {{ $budget->start_date->format('d/m/Y') }} → {{ $budget->end_date->format('d/m/Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($budget->isEditable())
                    <a href="{{ route('admin.budget.edit', $budget) }}" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm">{{ __('Edit') }}</a>
                @endif
                @if($budget->status === 'draft')
                    @include('admin.budget._action', ['route' => 'submit', 'label' => __('Submit for Approval'), 'color' => 'amber'])
                    <form method="POST" action="{{ route('admin.budget.destroy', $budget) }}" onsubmit="return confirm('{{ __('Delete this budget?') }}')">@csrf @method('DELETE')<button class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-sm">{{ __('Delete') }}</button></form>
                @elseif($budget->status === 'pending_approval')
                    @include('admin.budget._action', ['route' => 'approve', 'label' => __('Approve'), 'color' => 'green'])
                @elseif($budget->status === 'approved')
                    @include('admin.budget._action', ['route' => 'activate', 'label' => __('Activate'), 'color' => 'green'])
                @elseif($budget->status === 'active')
                    @include('admin.budget._action', ['route' => 'close', 'label' => __('Close'), 'color' => 'slate'])
                @endif
                @if(!in_array($budget->status, ['closed','cancelled']))
                    @include('admin.budget._action', ['route' => 'cancel', 'label' => __('Cancel'), 'color' => 'red', 'confirm' => true])
                @endif
            </div>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Total') }}</p><p class="text-lg font-bold text-gray-800">{{ number_format($budget->total_amount, 2) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Allocated') }}</p><p class="text-lg font-bold text-blue-700">{{ number_format($budget->allocated_amount, 2) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Spent') }}</p><p class="text-lg font-bold text-red-700">{{ number_format($budget->spent_amount, 2) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Remaining') }}</p><p class="text-lg font-bold text-green-700">{{ number_format($budget->remaining_amount, 2) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500">{{ __('Utilization') }}</p>
            <p class="text-lg font-bold" style="color: {{ $uColor }};">{{ $budget->utilization_percentage }}%</p>
            <div class="bg-gray-200 rounded-full h-1.5 mt-1 overflow-hidden"><div class="h-1.5 rounded-full" style="width: {{ min($budget->utilization_percentage, 100) }}%; background: {{ $uColor }};"></div></div>
        </div>
    </div>

    @if($budget->isOverBudget())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ __('⚠ This budget is over its total amount.') }}</div>
    @endif

    {{-- Revise (approved/active only) --}}
    @if(in_array($budget->status, ['approved','active']))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h4 class="text-base font-semibold text-gray-700 mb-3">{{ __('Revise Total Amount') }}</h4>
        <form method="POST" action="{{ route('admin.budget.revise', $budget) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('New Total') }}</label>
                <input type="number" step="0.01" min="0" name="new_amount" value="{{ old('new_amount', $budget->total_amount) }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Reason') }}</label>
                <input type="text" name="reason" value="{{ old('reason') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            </div>
            <div>
                <button type="submit" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Apply Revision') }}</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Allocations --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-base font-semibold text-gray-700">{{ __('Allocations') }}</h4>
            <span class="text-sm text-gray-500">{{ __('Available to allocate') }}: <span class="font-semibold">{{ number_format($budget->total_amount - $budget->allocated_amount, 2) }} {{ $currency }}</span></span>
        </div>

        @unless($locked)
        <form method="POST" action="{{ route('admin.budget.allocations.store', $budget) }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end mb-5 pb-5 border-b border-gray-100">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Title') }}</label>
                <input type="text" name="title" value="{{ old('title') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Category') }}</label>
                <select name="expense_category_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                    <option value="">{{ __('Select') }}</option>
                    @foreach(\App\Models\ExpenseCategory::where('status',true)->orderBy('title')->get() as $c)
                        <option value="{{ $c->id }}">{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Period') }}</label>
                <select name="period" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    @foreach(['yearly','q1','q2','q3','q4','semester1','semester2'] as $p)
                        <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Amount') }}</label>
                <input type="number" step="0.01" min="0.01" name="allocated_amount" value="{{ old('allocated_amount') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Add Allocation') }}</button>
            </div>
        </form>
        @endunless

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-y border-gray-200">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Title') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Category') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Period') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Allocated') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Spent') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Remaining') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Used') }}</th>
                        @unless($locked)<th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>@endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($budget->allocations as $a)
                    @php($aColor = ['green'=>'#22c55e','yellow'=>'#eab308','amber'=>'#f59e0b','red'=>'#ef4444'][$a->status_color])
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm font-medium text-gray-900">{{ $a->title }}</td>
                        <td class="px-3 py-2 text-sm text-gray-600">{{ $a->expenseCategory?->title }}</td>
                        <td class="px-3 py-2 text-sm text-gray-600">{{ ucfirst($a->period) }}</td>
                        <td class="px-3 py-2 text-sm text-right text-gray-900">{{ number_format($a->allocated_amount, 2) }}</td>
                        <td class="px-3 py-2 text-sm text-right text-red-700">{{ number_format($a->spent_amount, 2) }}</td>
                        <td class="px-3 py-2 text-sm text-right text-green-700">{{ number_format($a->remaining_amount, 2) }}</td>
                        <td class="px-3 py-2 text-sm" style="color: {{ $aColor }};">{{ $a->utilization_percentage }}%</td>
                        @unless($locked)
                        <td class="px-3 py-2 text-right">
                            <form method="POST" action="{{ route('admin.budget.allocations.destroy', $a) }}" onsubmit="return confirm('{{ __('Remove this allocation?') }}')">@csrf @method('DELETE')<button class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Remove') }}</button></form>
                        </td>
                        @endunless
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500 text-sm">{{ __('No allocations yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent expenses + Revisions --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-base font-semibold text-gray-700 mb-3">{{ __('Recent Expenses') }}</h4>
            <div class="space-y-2">
                @forelse($expenses as $e)
                <div class="flex items-center justify-between text-sm border-b border-gray-50 pb-1.5">
                    <span class="text-gray-700">{{ $e->date->format('d/m/Y') }} · {{ $e->title }}</span>
                    <span class="font-medium text-red-700">{{ number_format($e->amount, 2) }}</span>
                </div>
                @empty
                <p class="text-sm text-gray-500">{{ __('No expenses tagged to this budget yet.') }}</p>
                @endforelse
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h4 class="text-base font-semibold text-gray-700 mb-3">{{ __('Revision History') }}</h4>
            <div class="space-y-2">
                @forelse($budget->revisions as $r)
                <div class="text-sm border-b border-gray-50 pb-1.5">
                    <span class="font-medium text-gray-800">#{{ $r->revision_number }}</span>
                    <span class="text-gray-600">{{ number_format($r->previous_amount, 2) }} → {{ number_format($r->new_amount, 2) }}</span>
                    <span class="text-xs px-1.5 py-0.5 rounded {{ $r->change_type === 'increase' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $r->change_type }}</span>
                    <p class="text-xs text-gray-500">{{ $r->reason }}</p>
                </div>
                @empty
                <p class="text-sm text-gray-500">{{ __('No revisions.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
