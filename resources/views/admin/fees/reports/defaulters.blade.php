@extends('layouts.admin')
@section('title', __('Fee Defaulters'))
@section('breadcrumb', __('Fees > Reports > Defaulters'))

@section('content')
@php $byClass = $rows->groupBy('class'); @endphp
<style>
    @media print {
        aside, header, .no-print, #flash-success, #flash-error { display: none !important; }
        #main-content { margin-left: 0 !important; }
        main { padding: 0 !important; }
        .print-only { display: block !important; }
        .page-break { break-before: page; }
    }
    .print-only { display: none; }
</style>
<div class="space-y-5">
    <div class="no-print flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Fee Defaulters') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('Every pupil who still owes this year, class by class, with a number to call.') }}</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Class') }}</label>
                <select name="class_section_id" onchange="this.form.submit()" style="appearance:auto;" class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach($sections as $s)
                        <option value="{{ $s->id }}" @selected($sectionId === $s->id)>{{ $s->form->name }} — {{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" onclick="window.print()" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Print') }}</button>
            @can('fee-report.export')
                <a href="{{ route('admin.fee-reports.defaulters.csv', ['class_section_id' => $sectionId]) }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Export CSV') }}</a>
            @endcan
        </form>
    </div>

    <div class="print-only text-center mb-4">
        <div class="text-lg font-bold">{{ $school?->school_name }}</div>
        <div class="text-sm">{{ __('Fee Defaulters') }} — {{ $session?->name }} — {{ now()->translatedFormat('j F Y') }}</div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 no-print">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Pupils owing') }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $rows->count() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Total owed') }}</p>
            <p class="text-2xl font-bold text-red-600 mt-1 tabular-nums">{{ number_format($rows->sum('balance')) }}</p>
        </div>
    </div>

    @forelse($byClass as $class => $pupils)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden {{ ! $loop->first ? 'page-break' : '' }}">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">{{ $class }}</h3>
                <span class="text-sm text-gray-500">{{ trans_choice('{1} :count pupil owes :amount|[2,*] :count pupils owe :amount', $pupils->count(), ['count' => $pupils->count(), 'amount' => number_format($pupils->sum('balance'))]) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left w-10">#</th>
                            <th class="px-4 py-2 text-left">{{ __('Pupil') }}</th>
                            <th class="px-4 py-2 text-left">{{ __('Parent / Guardian') }}</th>
                            <th class="px-4 py-2 text-left">{{ __('Phone') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Fees') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Paid') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($pupils as $i => $r)
                            <tr>
                                <td class="px-4 py-2 text-gray-400">{{ $i + 1 }}</td>
                                <td class="px-4 py-2"><span class="font-medium text-gray-800">{{ $r['name'] }}</span> <span class="block text-xs text-gray-400">{{ $r['matricule'] }}</span></td>
                                <td class="px-4 py-2 text-gray-700">{{ $r['parent'] ?: '—' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">@if($r['phone'])<a href="tel:{{ preg_replace('/\s+/', '', $r['phone']) }}" class="text-teal-700">{{ $r['phone'] }}</a>@else — @endif</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format($r['fees']) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-emerald-700">{{ number_format($r['paid']) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums font-semibold text-red-600">
                                    {{ number_format($r['balance']) }}
                                    @if($r['arrears'] > 0)<span class="block text-xs font-normal">{{ __('incl. :amount from earlier years', ['amount' => number_format($r['arrears'])]) }}</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-emerald-700">{{ __('Nobody owes anything. Every pupil is up to date.') }}</div>
    @endforelse
</div>
@endsection
