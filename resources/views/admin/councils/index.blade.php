@extends('layouts.admin')
@section('title', __('Class Councils'))
@section('breadcrumb', __('Academics > Class Councils'))

@section('content')
<div class="space-y-5">

    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Class Councils') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('The meeting that turns a term of marks into decisions the school stands behind.') }}</p>
        </div>

        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Term') }}</label>
                <select name="term_id" onchange="this.form.submit()" style="appearance:auto;"
                        class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}" @selected($termId === $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    {{-- Sittings held --}}
    @if($councils->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($councils as $council)
                <a href="{{ route('admin.councils.show', $council) }}"
                   class="group bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:border-teal-300 hover:shadow transition">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-800 group-hover:text-teal-700 transition">{{ $council->classSection?->name }}</h3>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $council->reference }} · {{ $council->term?->name }}</p>
                        </div>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $council->isConcluded() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $council->statusLabel() }}
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-gray-100">
                        <div>
                            <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ __('Pupils') }}</p>
                            <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $council->entries_count }}</p>
                        </div>
                        <div>
                            <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ __('Average') }}</p>
                            <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $council->class_average !== null ? number_format($council->class_average, 2) : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ __('Pass rate') }}</p>
                            <p class="text-sm font-semibold {{ ($council->pass_rate ?? 0) >= 50 ? 'text-emerald-600' : 'text-gray-800' }} mt-0.5">
                                {{ $council->pass_rate !== null ? number_format($council->pass_rate, 1).'%' : '—' }}
                            </p>
                        </div>
                    </div>

                    @if($council->meeting_date || $council->chair)
                        <p class="text-xs text-gray-400 mt-3">
                            @if($council->meeting_date){{ $council->meeting_date->format('d M Y') }}@endif
                            @if($council->chair) · {{ __('Chaired by :name', ['name' => $council->chair->first_name.' '.$council->chair->last_name]) }}@endif
                        </p>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    {{-- Classes still to sit --}}
    @if($without->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="text-sm font-semibold text-gray-700">{{ __('Classes with no sitting yet') }}</h4>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">
                {{ __('A council can only sit on published results. If a class is missing here, check Exam Readiness first.') }}
            </p>

            <div class="flex flex-wrap gap-2">
                @can('class-council.create')
                    @foreach($without as $section)
                        <form method="POST" action="{{ route('admin.councils.store') }}">
                            @csrf
                            <input type="hidden" name="class_section_id" value="{{ $section->id }}">
                            <input type="hidden" name="term_id" value="{{ $termId }}">
                            <button type="submit"
                                    class="px-3 py-1.5 rounded-lg border border-dashed border-gray-300 text-sm text-gray-600 hover:border-teal-400 hover:text-teal-700 hover:bg-teal-50 transition">
                                + {{ $section->name }}
                            </button>
                        </form>
                    @endforeach
                @else
                    @foreach($without as $section)
                        <span class="px-3 py-1.5 rounded-lg border border-dashed border-gray-200 text-sm text-gray-400">{{ $section->name }}</span>
                    @endforeach
                @endcan
            </div>
        </div>
    @endif

    @if($councils->isEmpty() && $without->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <p class="text-sm text-gray-500">{{ __('No classes are set up for this level yet.') }}</p>
        </div>
    @endif
</div>
@endsection
