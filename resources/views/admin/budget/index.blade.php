@extends('layouts.admin')

@section('title', __('Budgets'))
@section('breadcrumb', __('Budgets'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Budgets') }}</h3>
        <a href="{{ route('admin.budget.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Create Budget') }}</a>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Type') }}</label>
            <select name="type" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach(['annual','departmental','project'] as $t)
                    <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
            <select name="status" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach(['draft','pending_approval','approved','active','closed','cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Fiscal Year') }}</label>
            <select name="fiscal_year" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($fiscalYears as $fy)
                    <option value="{{ $fy }}" {{ request('fiscal_year') === $fy ? 'selected' : '' }}>{{ $fy }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Department') }}</label>
            <select name="department_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" {{ (string) request('department_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
            <a href="{{ route('admin.budget.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Reset') }}</a>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('ID') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Budget Code') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Title') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Fiscal Year') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Department') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Total Amount') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Utilization') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($budgets as $budget)
                @php($uColor = ['green'=>'#22c55e','yellow'=>'#eab308','amber'=>'#f59e0b','red'=>'#ef4444'][$budget->utilization_color])
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $budget->id }}</td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-700">{{ $budget->budget_code }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $budget->title }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 capitalize">{{ $budget->type }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $budget->fiscal_year }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $budget->department?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($budget->total_amount, 2) }}</td>
                    <td class="px-4 py-3 text-sm w-40">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-gray-200 rounded-full h-2 overflow-hidden">
                                <div class="h-2 rounded-full" style="width: {{ min($budget->utilization_percentage, 100) }}%; background: {{ $uColor }};"></div>
                            </div>
                            <span class="text-xs text-gray-600">{{ $budget->utilization_percentage }}%</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $budget->status_badge }}">{{ ucfirst(str_replace('_',' ',$budget->status)) }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.budget.show', $budget) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">{{ __('View') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">{{ __('No budgets found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $budgets->links() }}</div>
</div>
@endsection
