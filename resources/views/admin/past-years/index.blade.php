@extends('layouts.admin')
@section('title', __('Past Years'))
@section('breadcrumb', __('Academic > Past Years'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">{{ __('Past Years') }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ __('A school year that happened before the school used this system. Each pupil\'s class, marks and decision are recorded from the school\'s own report cards and mark sheets, exactly as they were given, so their history starts where it really started.') }}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-3">
        <h3 class="font-semibold text-gray-800">{{ __('How it works') }}</h3>
        <ol class="list-decimal list-inside text-sm text-gray-600 space-y-1.5">
            <li>{{ __('Create the year with its real dates. It must end before the current year starts.') }}</li>
            <li>{{ __('For each class of this year, say which class each pupil was in. Most pupils simply moved up one class; change the repeaters and the newcomers.') }}</li>
            <li>{{ __('For each class and term, upload the mark sheet: the marks per subject, and the average and rank as written on the report card if you have them.') }}</li>
            <li>{{ __('The head teacher compares each sheet with the paper and verifies it. Only then do the results show in the pupil\'s history and to parents.') }}</li>
            <li>{{ __('Optionally, record what each family still owed at the end of that year: it is collected as arrears, oldest first.') }}</li>
        </ol>
        <p class="text-xs text-gray-500">{{ __('Nothing here bills fees, opens registers or changes this year\'s classes. Results are never recomputed: what the report card said is what is kept.') }}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50"><h3 class="font-semibold text-gray-700 text-sm">{{ __('Recorded years') }}</h3></div>
        @forelse($years as $year)
            <a href="{{ route('admin.past-years.show', $year) }}" class="flex items-center justify-between px-5 py-3 border-b border-gray-100 last:border-0 hover:bg-gray-50">
                <span class="font-medium text-gray-800">{{ $year->name }}</span>
                <span class="text-sm text-gray-500">{{ $year->start_date->format('d/m/Y') }} – {{ $year->end_date->format('d/m/Y') }}</span>
            </a>
        @empty
            <p class="px-5 py-6 text-sm text-gray-400">{{ __('No past year recorded yet.') }}</p>
        @endforelse
    </div>

    @can('past-year.record')
    <form method="POST" action="{{ route('admin.past-years.store') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        @csrf
        <h3 class="font-semibold text-gray-800">{{ __('Record a past year') }}</h3>
        @if($current)
            <p class="text-xs text-gray-500">{{ __('The current year, :name, starts on :date.', ['name' => $current->name, 'date' => $current->start_date->format('d/m/Y')]) }}</p>
        @endif
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <label class="block text-sm">
                <span class="text-gray-700 font-medium">{{ __('Name') }}</span>
                <input type="text" name="name" required maxlength="20" placeholder="2025/2026" value="{{ old('name') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg">
            </label>
            <label class="block text-sm">
                <span class="text-gray-700 font-medium">{{ __('Start Date') }}</span>
                <input type="date" name="start_date" required value="{{ old('start_date') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg">
            </label>
            <label class="block text-sm">
                <span class="text-gray-700 font-medium">{{ __('End Date') }}</span>
                <input type="date" name="end_date" required value="{{ old('end_date') }}" class="mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg">
            </label>
        </div>
        <button type="submit" class="px-6 py-2.5 rounded-lg bg-[#1e293b] text-white text-sm font-medium hover:bg-[#334155]">{{ __('Record this year') }}</button>
    </form>
    @endcan
</div>
@endsection
