@extends('layouts.admin')

@section('title', __('Monthly Summary'))
@section('breadcrumb', __('Budgets > :code > Monthly Summary', ['code' => $budget->budget_code]))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ $budget->title }} · {{ __('Monthly Summary') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Budget · This month · Brought forward · Cumulative · Balance, line by line. Spend is read from the expenses themselves, by category and date.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <select name="month" style="appearance:auto;-webkit-appearance:menulist;" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="this.form.submit()">
                    @foreach($summary['months'] as $m)
                        <option value="{{ $m['value'] }}" @selected($m['value'] === $summary['month']->format('Y-m'))>{{ $m['label'] }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('admin.budget.show', $budget) }}" class="text-sm border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg">{{ __('Back to budget') }}</a>
        </div>
    </div>

    @include('admin.budget._pacing', ['pacing' => $pacing])

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('Line') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Budget') }}</th>
                        <th class="px-4 py-3 text-right">{{ $summary['month']->translatedFormat('M Y') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Brought forward') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Cumulative') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Balance') }}</th>
                        <th class="px-4 py-3 text-left w-32">{{ __('Used') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($summary['lines'] as $line)
                    @php($used = $line['budget'] > 0 ? $line['cumulative'] / $line['budget'] * 100 : 0)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <span class="font-medium text-gray-800">{{ $line['title'] }}</span>
                            @if($line['category'] && $line['category'] !== $line['title'])<span class="text-xs text-gray-400"> · {{ $line['category'] }}</span>@endif
                        </td>
                        <td class="px-4 py-2 text-right text-gray-700">{{ number_format($line['budget'], 0) }}</td>
                        <td class="px-4 py-2 text-right text-gray-700">{{ $line['monthly'] ? number_format($line['monthly'], 0) : '—' }}</td>
                        <td class="px-4 py-2 text-right text-gray-500">{{ $line['brought'] ? number_format($line['brought'], 0) : '—' }}</td>
                        <td class="px-4 py-2 text-right font-medium text-gray-800">{{ number_format($line['cumulative'], 0) }}</td>
                        <td class="px-4 py-2 text-right font-medium {{ $line['balance'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ number_format($line['balance'], 0) }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden"><div class="h-1.5 {{ $used > 100 ? 'bg-red-500' : ($used > 90 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min(100, round($used)) }}%"></div></div>
                                <span class="text-xs text-gray-500 w-10 text-right">{{ number_format($used, 0) }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('This budget has no allocations yet.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t border-gray-200 font-semibold text-gray-800">
                    <tr>
                        <td class="px-4 py-3">{{ __('Total') }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($summary['totals']['budget'], 0) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($summary['totals']['monthly'], 0) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($summary['totals']['brought'], 0) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($summary['totals']['cumulative'], 0) }}</td>
                        <td class="px-4 py-3 text-right {{ $summary['totals']['balance'] < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ number_format($summary['totals']['balance'], 0) }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400 font-normal">{{ $currency }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Money the lines above cannot see --}}
    @if(count($summary['outside']) || $summary['salaries']['cumulative'] > 0)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
        <h4 class="text-sm font-semibold text-amber-900 mb-1">{{ __('Spending outside these lines') }}</h4>
        <p class="text-xs text-amber-800 mb-3">{{ __('Recorded in the period, but on no line of this budget. It is shown so the total spend is honest; give the category an allocation and it will be counted next time.') }}</p>
        <table class="w-full text-sm">
            <thead class="text-xs text-amber-700 uppercase">
                <tr><th class="text-left py-1">{{ __('Heading') }}</th><th class="text-right py-1">{{ $summary['month']->translatedFormat('M Y') }}</th><th class="text-right py-1">{{ __('Cumulative') }}</th></tr>
            </thead>
            <tbody class="text-amber-900">
                @foreach($summary['outside'] as $o)
                <tr><td class="py-1">{{ $o['heading'] }}</td><td class="py-1 text-right">{{ $o['monthly'] ? number_format($o['monthly'], 0) : '—' }}</td><td class="py-1 text-right font-medium">{{ number_format($o['cumulative'], 0) }}</td></tr>
                @endforeach
                @if($summary['salaries']['cumulative'] > 0)
                <tr><td class="py-1">{{ __('Salaries paid') }} <span class="text-xs text-amber-700">· {{ __('net, from payroll') }}</span></td><td class="py-1 text-right">{{ $summary['salaries']['monthly'] ? number_format($summary['salaries']['monthly'], 0) : '—' }}</td><td class="py-1 text-right font-medium">{{ number_format($summary['salaries']['cumulative'], 0) }}</td></tr>
                @endif
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
