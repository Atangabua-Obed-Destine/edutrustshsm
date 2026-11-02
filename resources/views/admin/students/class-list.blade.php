@extends('layouts.admin')
@section('title', __('Class List'))
@section('breadcrumb', __('Students > Class List'))

@section('content')
@php
    $boys = $rows->where('gender', 'male')->count();
    $girls = $rows->where('gender', 'female')->count();
    $repeaters = $rows->where('repeater', true)->count();
@endphp
<style>
    @media print {
        @page { size: A4 portrait; margin: 12mm; }
        aside, header, .no-print, #flash-success, #flash-error { display: none !important; }
        #main-content { margin-left: 0 !important; }
        main { padding: 0 !important; }
        .print-only { display: block !important; }
        .sheet { box-shadow: none !important; border: 0 !important; }
        .sheet table { font-size: 11px; }
        .sheet td, .sheet th { padding: 4px 6px !important; border: 1px solid #9ca3af; }
    }
    .print-only { display: none; }
</style>
<div class="space-y-5">
    <div class="no-print flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Class List') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('Every pupil in a class, with sex, date of birth and a parent to call. Print it for the register, an inspection or the ministry.') }}</p>
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Class') }}</label>
                <select name="class_section_id" onchange="this.form.submit()" style="appearance:auto;" class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white min-w-48">
                    <option value="">{{ __('Choose a class') }}</option>
                    @foreach($sections as $s)
                        <option value="{{ $s->id }}" @selected($section?->id === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($section)
                <button type="button" onclick="window.print()" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">{{ __('Print') }}</button>
                <a href="{{ route('admin.class-list.csv', ['class_section_id' => $section->id]) }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Export CSV') }}</a>
            @endif
        </form>
    </div>

    @if(! $section)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            {{ $sections->isEmpty() ? __('No classes yet.') : __('Choose a class to see its list.') }}
        </div>
    @else
        <div class="sheet bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="print-only text-center mb-3">
                <div class="text-base font-bold uppercase">{{ $school?->school_name }}</div>
                <div class="text-xs">{{ $school?->city }}{{ $school?->phone ? ' — '.__('Tel').': '.$school->phone : '' }}</div>
                <div class="text-sm font-semibold mt-2 uppercase">{{ __('Class List') }} — {{ $section->name }} — {{ $session?->name }}</div>
            </div>

            <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2 no-print">
                <h3 class="font-semibold text-gray-800">{{ $section->name }} <span class="text-gray-400 font-normal">· {{ $session?->name }}</span></h3>
                <span class="text-sm text-gray-500">{{ __('Class teacher') }}: {{ $section->classTeacher?->full_name ?? '—' }}</span>
            </div>

            @if($rows->isEmpty())
                <div class="p-10 text-center text-sm text-gray-500">{{ __('No pupils are registered in this class this year.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left w-10">#</th>
                                <th class="px-3 py-2 text-left">{{ __('Matricule') }}</th>
                                <th class="px-3 py-2 text-left">{{ __('Name') }}</th>
                                <th class="px-3 py-2 text-center">{{ __('Sex') }}</th>
                                <th class="px-3 py-2 text-left whitespace-nowrap">{{ __('Date of Birth') }}</th>
                                <th class="px-3 py-2 text-left hidden md:table-cell print:table-cell">{{ __('Place of Birth') }}</th>
                                <th class="px-3 py-2 text-center">{{ __('Repeater') }}</th>
                                <th class="px-3 py-2 text-left hidden lg:table-cell print:table-cell">{{ __('Parent / Guardian') }}</th>
                                <th class="px-3 py-2 text-left">{{ __('Phone') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($rows as $i => $r)
                                <tr>
                                    <td class="px-3 py-2 text-gray-400">{{ $i + 1 }}</td>
                                    <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $r['matricule'] }}</td>
                                    <td class="px-3 py-2 font-medium text-gray-800">{{ $r['name'] }}</td>
                                    <td class="px-3 py-2 text-center">{{ $r['sex'] }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $r['dob'] ?? '—' }}@if($r['age'] !== null) <span class="text-xs text-gray-400">({{ $r['age'] }})</span>@endif</td>
                                    <td class="px-3 py-2 hidden md:table-cell print:table-cell">{{ $r['pob'] ?: '—' }}</td>
                                    <td class="px-3 py-2 text-center">{{ $r['repeater'] ? __('Yes') : '' }}</td>
                                    <td class="px-3 py-2 hidden lg:table-cell print:table-cell">{{ $r['parent'] ?: '—' }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">@if($r['phone'])<a href="tel:{{ preg_replace('/\s+/', '', $r['phone']) }}" class="text-teal-700 print:text-black">{{ $r['phone'] }}</a>@else — @endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 border-t border-gray-100 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-700">
                    <span>{{ __('Boys') }}: <strong>{{ $boys }}</strong></span>
                    <span>{{ __('Girls') }}: <strong>{{ $girls }}</strong></span>
                    <span>{{ __('Total') }}: <strong>{{ $rows->count() }}</strong></span>
                    <span>{{ __('Repeaters') }}: <strong>{{ $repeaters }}</strong></span>
                </div>
                <div class="print-only mt-10">
                    <div style="display:flex; justify-content:space-between; font-size:11px;">
                        <div>{{ __('Class teacher') }}: {{ $section->classTeacher?->full_name }}<br><br>______________________</div>
                        <div style="text-align:right;">{{ __('Printed on') }} {{ now()->translatedFormat('j F Y') }}<br><br>{{ __('The Principal') }}: ______________________</div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
