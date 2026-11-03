@extends('layouts.admin')
@section('title', $year->name.' — '.__('Past Years'))
@section('breadcrumb', __('Academic > Past Years').' > '.$year->name)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ $year->name }} <span class="ml-1 px-2 py-0.5 text-xs rounded-full bg-indigo-100 text-indigo-700 align-middle">{{ __('Recorded') }}</span></h2>
            <p class="text-sm text-gray-500">{{ $year->start_date->format('d/m/Y') }} – {{ $year->end_date->format('d/m/Y') }}</p>
        </div>
        <a href="{{ route('admin.past-years.index') }}" class="px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Back') }}</a>
    </div>

    @if(session('upload_errors'))
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p class="text-sm font-medium text-amber-800 mb-2">{{ __('Lines not imported:') }}</p>
            <ul class="text-xs text-amber-700 space-y-1 max-h-72 overflow-y-auto">@foreach(session('upload_errors') as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Step 1: where each of this year's pupils was --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="font-semibold text-gray-700 text-sm">{{ __('1. Where was each pupil in :year?', ['year' => $year->name]) }}</h3>
            <p class="text-xs text-gray-500">{{ __('Open each class of this year and confirm the class each pupil was in.') }}</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 p-4">
            @foreach($placement as $row)
                @php $done = $row['placed'] === $row['pupils']; @endphp
                <a href="{{ route('admin.past-years.placement', [$year, $row['section']]) }}" class="block rounded-lg border p-3 hover:shadow-sm {{ $done ? 'border-emerald-200 bg-emerald-50' : 'border-gray-200' }}">
                    <span class="block font-medium text-gray-800">{{ $row['section']->name }}</span>
                    <span class="block text-xs {{ $done ? 'text-emerald-700' : 'text-gray-500' }}">{{ __(':placed of :pupils placed', ['placed' => $row['placed'], 'pupils' => $row['pupils']]) }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Step 2: the results, class by class, term by term --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50">
            <h3 class="font-semibold text-gray-700 text-sm">{{ __('2. Results, from the mark sheets') }}</h3>
            <p class="text-xs text-gray-500">{{ __('One sheet per class and term. Entered, then verified by someone else against the paper.') }}</p>
        </div>
        @if($lastYearSections->isEmpty())
            <p class="px-5 py-6 text-sm text-gray-400">{{ __('Place the pupils first: the classes of :year appear here once they have pupils.', ['year' => $year->name]) }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">{{ __('Class in :year', ['year' => $year->name]) }}</th>
                            @foreach($terms as $term)<th class="px-4 py-2 text-center">{{ $term->name }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($lastYearSections as $section)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-700">{{ $section->name }} <span class="text-xs text-gray-400">({{ $counts[$section->id] ?? 0 }})</span></td>
                                @foreach($terms as $term)
                                    @php
                                        $sheet = $sheets[$section->id.':'.$term->id] ?? null;
                                        [$label, $tone] = match (true) {
                                            $sheet?->status === 'verified' => [__('Verified'), 'bg-emerald-100 text-emerald-700'],
                                            (bool) $sheet?->entered_by => [__('To verify'), 'bg-amber-100 text-amber-700'],
                                            default => [__('Not entered'), 'bg-gray-100 text-gray-500'],
                                        };
                                    @endphp
                                    <td class="px-4 py-2 text-center">
                                        <a href="{{ route('admin.past-years.sheet', [$year, $section, $term]) }}" class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $tone }}">{{ $label }}</a>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Step 3 (optional): what was left unpaid --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-3">
        <h3 class="font-semibold text-gray-800">{{ __('3. Fees left unpaid in :year (optional)', ['year' => $year->name]) }}</h3>
        <p class="text-sm text-gray-600">{{ __('One line per pupil and fee: the matricule, the fee category (Tuition if left blank) and the amount still owed at the switch. It is collected as arrears, before this year\'s fees, and shows on receipts, the defaulters list and the parent portal.') }}</p>
        <p class="text-sm text-gray-700">{{ __(':pupils pupils owe :owed from :year.', ['pupils' => $arrearsSummary['pupils'], 'owed' => number_format($arrearsSummary['owed']), 'year' => $year->name]) }}</p>
        @can('past-year.record')
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.past-years.arrears.template', $year) }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Download CSV Template') }}</a>
            <form method="POST" action="{{ route('admin.past-years.arrears', $year) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="csv_file" accept=".csv,.txt" required class="text-sm">
                <button class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">{{ __('Record unpaid fees') }}</button>
            </form>
        </div>
        @endcan
    </div>
</div>
@endsection
