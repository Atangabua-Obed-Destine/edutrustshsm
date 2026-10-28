@extends('layouts.admin')

@section('title', __('Cash Book — The Year'))
@section('breadcrumb', __('Finance > Cash Book > The Year'))

@section('content')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ __('The year in cash') }} · {{ $year['label'] }}</h3>
            <p class="text-sm text-gray-500">{{ __(':start to :end. A month of the book says what happened; this says whether the year is going well.', ['start' => $year['start']->format('d M Y'), 'end' => $year['end']->format('d M Y')]) }}</p>
        </div>
        <a href="{{ route('admin.daybook.index', ['month' => $year['start']->format('Y-m')]) }}" class="text-sm border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg">{{ __('Back to the book') }}</a>
    </div>

    @if(! $analysis['has_data'])
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            {{ __('No money has moved in this year yet.') }}
        </div>
    @else

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Money in') }}</p>
            <p class="text-xl font-bold text-emerald-700">{{ number_format($analysis['total_in'], 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Money out') }}</p>
            <p class="text-xl font-bold text-red-700">{{ number_format($analysis['total_out'], 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 col-span-2 md:col-span-1">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Net for the year') }}</p>
            <p class="text-xl font-bold {{ $analysis['net'] < 0 ? 'text-red-700' : 'text-gray-800' }}">{{ number_format($analysis['net'], 0) }} <span class="text-xs font-normal text-gray-400">{{ $currency }}</span></p>
        </div>
    </div>

    {{-- Month by month --}}
    @php($peak = max(1, collect($analysis['months'])->max(fn ($m) => max($m['in'], $m['out']))))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700">{{ __('Month by month') }}</h4>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">{{ __('Month') }}</th>
                        <th class="px-4 py-2 text-left w-1/3">{{ __('In / Out') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('In') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Out') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Net') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($analysis['months'] as $m)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 whitespace-nowrap"><a href="{{ route('admin.daybook.index', ['month' => $m['month']]) }}" class="text-blue-600 hover:underline">{{ $m['label'] }}</a></td>
                        <td class="px-4 py-2">
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden mb-1"><div class="h-1.5 bg-emerald-500" style="width: {{ round($m['in'] / $peak * 100) }}%"></div></div>
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden"><div class="h-1.5 bg-red-400" style="width: {{ round($m['out'] / $peak * 100) }}%"></div></div>
                        </td>
                        <td class="px-4 py-2 text-right text-emerald-700">{{ $m['in'] ? number_format($m['in'], 0) : '—' }}</td>
                        <td class="px-4 py-2 text-right text-red-700">{{ $m['out'] ? number_format($m['out'], 0) : '—' }}</td>
                        <td class="px-4 py-2 text-right {{ $m['net'] < 0 ? 'text-red-700' : 'text-gray-700' }}">{{ number_format($m['net'], 0) }}</td>
                        <td class="px-4 py-2 text-right font-medium {{ $m['balance'] < 0 ? 'text-red-700' : 'text-gray-800' }}">{{ number_format($m['balance'], 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach([[__('Where the money came from'), $analysis['top_in'], 'bg-emerald-500'], [__('Where the money went'), $analysis['top_out'], 'bg-red-500']] as [$title, $rows, $tone])
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-sm font-semibold text-gray-700 mb-3">{{ $title }}</h4>
            @forelse($rows as $r)
                <div class="flex items-center justify-between text-sm py-1.5 border-b border-gray-50 last:border-0">
                    <span class="text-gray-700">{{ $r['heading'] }}</span>
                    <span class="flex items-center gap-3">
                        <span class="text-xs text-gray-400 w-10 text-right">{{ number_format($r['share'], 0) }}%</span>
                        <span class="w-24 h-1.5 bg-gray-100 rounded-full overflow-hidden"><span class="block h-1.5 {{ $tone }}" style="width: {{ round($r['share']) }}%"></span></span>
                        <span class="font-medium text-gray-800 w-28 text-right">{{ number_format($r['amount'], 0) }}</span>
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">{{ __('Nothing yet.') }}</p>
            @endforelse
        </div>
        @endforeach
    </div>

    {{-- The ten biggest single movements: the numbers a meeting asks about --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-700">{{ __('Largest single movements') }}</h4>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @foreach($analysis['largest'] as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 whitespace-nowrap text-gray-600">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                    <td class="px-4 py-2 font-mono text-xs text-blue-600">{{ $row['ref'] }}</td>
                    <td class="px-4 py-2 text-gray-800">{{ $row['description'] }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $row['heading'] }}</td>
                    <td class="px-4 py-2 text-right font-medium {{ $row['direction'] === 'in' ? 'text-emerald-700' : 'text-red-700' }}">
                        {{ $row['direction'] === 'in' ? '+' : '−' }}{{ number_format($row['amount'], 0) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @endif
</div>
@endsection
