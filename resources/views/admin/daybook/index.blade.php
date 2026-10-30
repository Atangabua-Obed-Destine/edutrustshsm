@extends('layouts.admin')

@section('title', __('Cash Book'))
@section('breadcrumb', __('Finance > Cash Book'))

@section('content')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ __('Cash Book') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Every franc in and out, in date order, from the fees, incomes, expenses and salaries already recorded. Nothing here is typed twice.') }}</p>
        </div>
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('admin.daybook.year', ['month' => $month->format('Y-m')]) }}" class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg">{{ __('The year') }}</a>
            @can('daybook.export')
            <a href="{{ route('admin.daybook.pdf', ['month' => $month->format('Y-m')]) }}" class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg">PDF</a>
            <a href="{{ route('admin.daybook.csv', ['month' => $month->format('Y-m')]) }}" class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg">CSV</a>
            @endcan
        </div>
    </div>

    {{-- Month navigation --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.daybook.index', ['month' => $previous]) }}" class="text-gray-500 hover:text-gray-800 px-2" aria-label="{{ __('Previous month') }}">&larr;</a>
            <form method="GET" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg text-sm font-medium">{{ __('Open') }}</button>
            </form>
            <a href="{{ route('admin.daybook.index', ['month' => $next]) }}" class="text-gray-500 hover:text-gray-800 px-2" aria-label="{{ __('Next month') }}">&rarr;</a>
        </div>
        <p class="text-sm text-gray-500">
            <span class="font-medium text-gray-700">{{ $month->translatedFormat('F Y') }}</span>
            · {{ __('Year :label, from :start', ['label' => $year['label'], 'start' => $year['start']->format('d M Y')]) }}
        </p>
    </div>

    @if($book['unanalysed']['count'] > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900 flex flex-col sm:flex-row sm:items-center gap-3">
            <p class="flex-1">{{ trans_choice('{1} :count entry worth :amount has no budget line. It happened and the book has it, but the Income & Expenditure sheet cannot report it.|[2,*] :count entries worth :amount have no budget line. They happened and the book has them, but the Income & Expenditure sheet cannot report them.', $book['unanalysed']['count'], ['count' => $book['unanalysed']['count'], 'amount' => number_format($book['unanalysed']['amount'])]) }}</p>
            @can('budget-line.edit')
                <a href="{{ route('admin.budget-line.index') }}" class="font-medium underline underline-offset-2 whitespace-nowrap">{{ __('Point the categories at lines') }}</a>
            @endcan
        </div>
    @endif

    {{-- Totals --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Brought forward') }}</p>
            <p class="text-xl font-bold {{ $book['brought_forward'] < 0 ? 'text-red-700' : 'text-gray-800' }}">{{ number_format($book['brought_forward'], 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Money in') }}</p>
            <p class="text-xl font-bold text-emerald-700">{{ number_format($book['total_in'], 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Money out') }}</p>
            <p class="text-xl font-bold text-red-700">{{ number_format($book['total_out'], 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Closing balance') }}</p>
            <p class="text-xl font-bold {{ $book['closing'] < 0 ? 'text-red-700' : 'text-gray-800' }}">{{ number_format($book['closing'], 0) }} <span class="text-xs font-normal text-gray-400">{{ $currency }}</span></p>
        </div>
    </div>

    {{-- The book --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('Date') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Reference') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Description') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Heading') }}</th>
                        <th class="px-4 py-3 text-left" title="{{ __('The budget line this movement belongs to on the Income & Expenditure sheet.') }}">{{ __('Analysis') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('In') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Out') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr class="bg-gray-50/60 text-gray-500 italic">
                        <td class="px-4 py-2">{{ $month->copy()->startOfMonth()->format('d/m/Y') }}</td>
                        <td class="px-4 py-2"></td>
                        <td class="px-4 py-2" colspan="4">{{ __('Balance brought forward') }}</td>
                        <td class="px-4 py-2 text-right font-medium {{ $book['brought_forward'] < 0 ? 'text-red-700' : '' }}">{{ number_format($book['brought_forward'], 0) }}</td>
                    </tr>
                    @forelse($book['rows'] as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 font-mono text-xs text-blue-600">{{ $row['ref'] }}</td>
                        <td class="px-4 py-2">
                            <span class="text-gray-800">{{ $row['description'] }}</span>
                            @if($row['party'])<span class="text-xs text-gray-400"> · {{ $row['party'] }}</span>@endif
                            @if($row['method'])<span class="text-xs text-gray-400"> · {{ ucwords(str_replace('_', ' ', $row['method'])) }}</span>@endif
                        </td>
                        <td class="px-4 py-2 text-gray-600">
                            {{ $row['heading'] }}
                            @if(count($row['split']) > 1)
                                <span class="text-xs text-gray-400" title="{{ collect($row['split'])->map(fn ($v, $k) => $k.': '.number_format($v, 0))->join(' · ') }}">({{ count($row['split']) }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs {{ $row['unanalysed'] > 0 ? 'text-amber-700 font-medium' : 'text-gray-500' }}" title="{{ $row['line']?->displayName() }}">{{ $row['analysis'] }}</td>
                        <td class="px-4 py-2 text-right text-emerald-700">{{ $row['direction'] === 'in' ? number_format($row['amount'], 0) : '' }}</td>
                        <td class="px-4 py-2 text-right text-red-700">{{ $row['direction'] === 'out' ? number_format($row['amount'], 0) : '' }}</td>
                        <td class="px-4 py-2 text-right font-medium {{ $row['balance'] < 0 ? 'text-red-700' : 'text-gray-800' }}">{{ number_format($row['balance'], 0) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">{{ __('No money moved this month.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t border-gray-200 font-semibold">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-gray-700">{{ __('Totals for :month', ['month' => $month->translatedFormat('F Y')]) }}</td>
                        <td class="px-4 py-3 text-right text-emerald-700">{{ number_format($book['total_in'], 0) }}</td>
                        <td class="px-4 py-3 text-right text-red-700">{{ number_format($book['total_out'], 0) }}</td>
                        <td class="px-4 py-3 text-right {{ $book['closing'] < 0 ? 'text-red-700' : 'text-gray-800' }}">{{ number_format($book['closing'], 0) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Analysis columns: the month summed under each heading --}}
    @if($book['rows']->isNotEmpty())
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach([['in', __('Money in, by heading'), 'bg-emerald-500', $book['total_in']], ['out', __('Money out, by heading'), 'bg-red-500', $book['total_out']]] as [$side, $title, $tone, $sideTotal])
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ $title }}</h4>
            @forelse($book['headings'][$side] as $heading => $amount)
                <div class="flex items-center justify-between text-sm py-1.5 border-b border-gray-50 last:border-0">
                    <span class="text-gray-700">{{ $heading }}</span>
                    <span class="flex items-center gap-3">
                        <span class="w-24 h-1.5 bg-gray-100 rounded-full overflow-hidden"><span class="block h-1.5 {{ $tone }}" style="width: {{ $sideTotal > 0 ? round($amount / $sideTotal * 100) : 0 }}%"></span></span>
                        <span class="font-medium text-gray-800 w-28 text-right">{{ number_format($amount, 0) }}</span>
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('Nothing this month.') }}</p>
            @endforelse
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
