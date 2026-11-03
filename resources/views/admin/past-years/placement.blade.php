@extends('layouts.admin')
@section('title', $section->name.' — '.$year->name)
@section('breadcrumb', __('Academic > Past Years').' > '.$year->name.' > '.$section->name)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">{{ __('Pupils now in :class: their class in :year', ['class' => $section->name, 'year' => $year->name]) }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ __('Each pupil is suggested in the class below this one. Change the repeaters (same class as now) and choose "Not here" for pupils who joined this year.') }}</p>
    </div>

    <form method="POST" action="{{ route('admin.past-years.place', [$year, $section]) }}" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @csrf
        <div class="divide-y divide-gray-100">
            @forelse($rows as $row)
                @php $chosen = old('placement.'.$row['student']->id, $row['recorded'] ?? $row['suggested']); @endphp
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-5 py-3">
                    <div>
                        <span class="font-medium text-gray-800">{{ $row['student']->full_name }}</span>
                        <span class="block text-xs text-gray-500">{{ $row['student']->student_id }}
                            @if($row['recorded']) · <span class="text-emerald-700">{{ __('saved') }}</span>@endif
                            @if($row['locked']) · <span class="text-amber-700">{{ __('results recorded, class fixed') }}</span>@endif
                        </span>
                    </div>
                    <select name="placement[{{ $row['student']->id }}]" @disabled($row['locked']) class="w-full sm:w-56 px-3 py-2 border border-gray-300 rounded-lg text-sm" style="appearance:auto;-webkit-appearance:menulist;" aria-label="{{ __('Class in :year', ['year' => $year->name]) }}">
                        <option value="">{{ __('Not here in :year', ['year' => $year->name]) }}</option>
                        @foreach($options as $option)
                            <option value="{{ $option->id }}" @selected((string) $chosen === (string) $option->id)>{{ $option->name }}{{ $option->form_id === $section->form_id ? ' — '.__('repeated') : '' }}</option>
                        @endforeach
                    </select>
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-gray-400">{{ __('No pupils in this class this year.') }}</p>
            @endforelse
        </div>
        @if($rows->isNotEmpty())
            <div class="flex justify-end gap-3 px-5 py-4 bg-gray-50 border-t">
                <a href="{{ route('admin.past-years.show', $year) }}" class="px-4 py-2 text-sm text-gray-600">{{ __('Cancel') }}</a>
                <button class="px-6 py-2 rounded-lg bg-[#1e293b] text-white text-sm font-medium hover:bg-[#334155]">{{ __('Save') }}</button>
            </div>
        @endif
    </form>
</div>
@endsection
