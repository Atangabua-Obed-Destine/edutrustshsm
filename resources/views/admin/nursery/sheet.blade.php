@extends('layouts.admin')
@section('title', __('Nursery Assessment'))
@section('breadcrumb', __('Examinations > Nursery Assessment > Sheet'))

@section('content')
@php
    $student = $enrollment->student;
    $section = $enrollment->classSection;
    $tones = [
        'acquired' => 'peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600',
        'developing' => 'peer-checked:bg-amber-500 peer-checked:text-white peer-checked:border-amber-500',
        'not_yet' => 'peer-checked:bg-gray-500 peer-checked:text-white peer-checked:border-gray-500',
    ];
    $listUrl = route('admin.nursery.index', ['class_section_id' => $section->id, 'term_id' => $term->id]);
@endphp
<form method="POST" action="{{ route('admin.nursery.update', [$enrollment, $term]) }}"
      x-data="{
          ratings: @js((object) $ratings),
          total: {{ $total }},
          get rated() { return Object.values(this.ratings).filter(Boolean).length },
          fillRest(value) { document.querySelectorAll('[data-competence]').forEach(el => { const id = el.dataset.competence; if (!this.ratings[id]) this.ratings[id] = value }) },
      }"
      class="space-y-5 pb-24">
    @csrf

    {{-- Who, where, when --}}
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ $listUrl }}" class="text-xs text-gray-500 hover:text-gray-800">&larr; {{ __('Back to the class') }}</a>
            <h2 class="text-xl font-bold text-gray-800 mt-1">{{ $student->last_name }} {{ $student->first_name }}</h2>
            <p class="text-sm text-gray-500">{{ $section->form->name }} — {{ $section->name }} · {{ $term->name }} · {{ $student->student_id }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-4 py-3 w-full lg:w-80">
            <div class="flex items-baseline justify-between">
                <span class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Rated') }}</span>
                <span class="text-sm font-semibold text-gray-800"><span x-text="rated"></span>/{{ $total }}</span>
            </div>
            <div class="mt-2 h-2 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-2 rounded-full bg-teal-500 transition-all" :style="`width: ${total ? Math.round(rated / total * 100) : 0}%`"></div>
            </div>
            @if($report?->is_published)
                <p class="text-xs text-emerald-700 mt-2">{{ __('This report is published. Changes show to the parent at once.') }}</p>
            @endif
        </div>
    </div>

    @if($areas->isEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900">
            {{ __('No competence applies to :class. Check the list: every competence is either shared or kept to one class.', ['class' => $section->form->name]) }}
        </div>
    @else
        {{-- Legend and the shortcut --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-gray-600">
            <div class="flex flex-wrap items-center gap-4">
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>{{ __('Acquired') }} — {{ __('does it alone, reliably') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>{{ __('In progress') }} — {{ __('does it with help or sometimes') }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-gray-500"></span>{{ __('Not yet') }} — {{ __('not seen yet this term') }}</span>
            </div>
            <button type="button" @click="fillRest('acquired')" x-show="rated < total" class="text-teal-700 hover:text-teal-900 font-medium whitespace-nowrap">
                {{ __('Mark the rest as acquired') }}
            </button>
        </div>

        @foreach($areas as $area)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/60">
                    <h3 class="font-semibold text-gray-800">{{ $area->name }}</h3>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach($area->competences as $competence)
                        <li class="px-5 py-3 flex flex-col sm:flex-row sm:items-center gap-3" data-competence="{{ $competence->id }}">
                            <span class="flex-1 text-sm text-gray-800">
                                {{ $competence->description }}
                                @if($competence->form_id)
                                    <span class="ml-1 text-[11px] text-gray-400">({{ $competence->form?->name }})</span>
                                @endif
                            </span>
                            <div class="flex rounded-lg border border-gray-200 overflow-hidden shrink-0 text-xs font-medium" role="radiogroup" aria-label="{{ $competence->description }}">
                                @foreach($labels as $value => $label)
                                    <label class="relative cursor-pointer">
                                        <input type="radio" name="ratings[{{ $competence->id }}]" value="{{ $value }}" class="peer sr-only"
                                               x-model="ratings[{{ $competence->id }}]">
                                        <span class="block px-3 py-1.5 border-r last:border-r-0 border-gray-200 text-gray-600 hover:bg-gray-50 peer-focus-visible:ring-2 peer-focus-visible:ring-teal-400 {{ $tones[$value] }}">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        {{-- Remark and attendance --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __("Class Teacher's Remark") }}</label>
                <textarea name="class_teacher_remark" rows="3" maxlength="1000"
                          class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-teal-400 focus:border-teal-400 outline-none"
                          placeholder="{{ __('What the child enjoys, where they have grown, what to encourage at home.') }}">{{ old('class_teacher_remark', $report?->class_teacher_remark) }}</textarea>
                @error('class_teacher_remark') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <p class="text-sm font-medium text-gray-700 mb-2">{{ __('Attendance this term') }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ __('Days present') }}</label>
                        <input type="number" name="days_present" min="0" max="200" value="{{ old('days_present', $days_present) }}"
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">{{ __('Days absent') }}</label>
                        <input type="number" name="days_absent" min="0" max="200" value="{{ old('days_absent', $days_absent) }}"
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                @if($from_register)
                    <p class="text-xs text-gray-400 mt-2">{{ __('Pre-filled from the attendance register. Correct it if the register is incomplete.') }}</p>
                @endif
            </div>
        </div>
    @endif

    {{-- Always within reach --}}
    <div class="fixed bottom-0 left-0 right-0 lg:left-64 bg-white/95 backdrop-blur border-t border-gray-200 px-4 sm:px-6 py-3 z-20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-xs text-gray-500">
                <span x-show="rated < total" x-cloak>{{ __('Competences left blank stay blank; you can come back to them.') }}</span>
                <span x-show="rated >= total" x-cloak class="text-emerald-700 font-medium">{{ __('Every competence is rated.') }}</span>
            </p>
            <div class="flex items-center gap-2">
                <a href="{{ $listUrl }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                <button type="submit" name="then" value="stay" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Save') }}</button>
                @if($next)
                    <button type="submit" name="then" value="next" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">
                        {{ __('Save and next: :name', ['name' => $next->student->last_name]) }} &rarr;
                    </button>
                @else
                    <button type="submit" name="then" value="list" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">{{ __('Save and back to the class') }}</button>
                @endif
            </div>
        </div>
    </div>
</form>
@endsection
