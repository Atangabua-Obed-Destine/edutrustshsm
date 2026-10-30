@extends('layouts.admin')
@section('title', __('Nursery Assessment'))
@section('breadcrumb', __('Examinations > Nursery Assessment'))

@section('content')
<div class="space-y-5">

    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Nursery Assessment') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5 max-w-2xl">
                {{ __('Nursery children are observed, not examined. For each competence the teacher says whether it is acquired, in progress or not yet — no marks, no average, no rank.') }}
            </p>
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Class') }}</label>
                <select name="class_section_id" onchange="this.form.submit()" style="appearance:auto;"
                        class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white min-w-[12rem]">
                    <option value="">{{ __('— Select Class —') }}</option>
                    @foreach($sections as $s)
                        <option value="{{ $s->id }}" @selected($section?->id === $s->id)>{{ $s->form->name }} — {{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Term') }}</label>
                <select name="term_id" onchange="this.form.submit()" style="appearance:auto;"
                        class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}" @selected($term?->id === $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    @if($catalogueEmpty)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900 flex flex-col sm:flex-row sm:items-center gap-3">
            <p class="flex-1">{{ __('There is no list of competences yet, so there is nothing to rate a child against.') }}</p>
            @can('school-settings.edit')
                <a href="{{ route('admin.nursery.competences.index') }}" class="font-medium underline underline-offset-2 whitespace-nowrap">{{ __('Set up the list') }}</a>
            @endcan
        </div>
    @elseif($sections->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            {{ __('No nursery class is active. Classes with the level "Nursery" appear here.') }}
        </div>
    @elseif(! $session)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            {{ __('No current academic year is set.') }}
        </div>
    @elseif(! $progress)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            {{ __('Choose a class and a term to see the pupils.') }}
        </div>
    @else
        @php
            $pupils = $progress['pupils'];
            $total = $progress['total'];
            $complete = $pupils->filter(fn ($p) => $total > 0 && $p['rated'] >= $total)->count();
            $published = $pupils->filter(fn ($p) => $p['report']?->is_published)->count();
        @endphp

        {{-- Where the class stands --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Pupils') }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $pupils->count() }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Competences') }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $total }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Sheets complete') }}</p>
                <p class="text-2xl font-bold {{ $complete === $pupils->count() && $pupils->isNotEmpty() ? 'text-emerald-600' : 'text-gray-800' }} mt-1">{{ $complete }}<span class="text-base text-gray-400 font-medium">/{{ $pupils->count() }}</span></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Published') }}</p>
                <p class="text-2xl font-bold text-gray-800 mt-1">{{ $published }}<span class="text-base text-gray-400 font-medium">/{{ $pupils->count() }}</span></p>
            </div>
        </div>

        @if($total === 0)
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-900">
                {{ __('No competence applies to :class. Check the list: every competence is either shared or kept to one class.', ['class' => $section->form->name]) }}
                @can('school-settings.edit')
                    <a href="{{ route('admin.nursery.competences.index') }}" class="font-medium underline underline-offset-2 ml-1">{{ __('Open the list') }}</a>
                @endcan
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-gray-800">{{ $section->form->name }} — {{ $section->name }} · {{ $term->name }}</h3>
                    @if($section->classTeacher)
                        <p class="text-xs text-gray-400">{{ __('Class teacher') }}: {{ $section->classTeacher->first_name }} {{ $section->classTeacher->last_name }}</p>
                    @endif
                </div>
                @can('report-card.view')
                    @if($pupils->isNotEmpty() && $complete === $pupils->count())
                        <form method="POST" action="{{ route('admin.report-cards.publish') }}"
                              onsubmit="return confirm('{{ __('Publish every report in this class to parents?') }}')">
                            @csrf
                            <input type="hidden" name="class_section_id" value="{{ $section->id }}">
                            <input type="hidden" name="term_id" value="{{ $term->id }}">
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ __('Publish to parents') }}
                            </button>
                        </form>
                    @elseif($pupils->isNotEmpty())
                        <p class="text-xs text-gray-400">{{ __('Publishing opens once every sheet is complete.') }}</p>
                    @endif
                @endcan
            </div>

            @if($pupils->isEmpty())
                <p class="p-8 text-center text-sm text-gray-500">{{ __('No active pupil is enrolled in this class for the current year.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-5 py-2 text-left w-10">#</th>
                            <th class="px-5 py-2 text-left">{{ __('Pupil') }}</th>
                            <th class="px-5 py-2 text-left w-64">{{ __('Sheet') }}</th>
                            <th class="px-5 py-2 text-left">{{ __('Last saved') }}</th>
                            <th class="px-5 py-2 text-left">{{ __('Report') }}</th>
                            <th class="px-5 py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($pupils as $i => $p)
                            @php $pct = $total > 0 ? (int) round($p['rated'] / $total * 100) : 0; @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-400">{{ $i + 1 }}</td>
                                <td class="px-5 py-3">
                                    <span class="font-medium text-gray-800">{{ $p['student']->last_name }} {{ $p['student']->first_name }}</span>
                                    <span class="block text-xs text-gray-400">{{ $p['student']->student_id }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-2 rounded-full {{ $pct >= 100 ? 'bg-emerald-500' : ($pct > 0 ? 'bg-sky-500' : 'bg-gray-200') }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="text-xs text-gray-500 w-14 text-right">{{ $p['rated'] }}/{{ $total }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-500">
                                    {{ $p['last'] ? \Carbon\Carbon::parse($p['last'])->diffForHumans() : '—' }}
                                </td>
                                <td class="px-5 py-3 text-xs">
                                    @if($p['report']?->is_published)
                                        <span class="inline-flex items-center gap-1 text-emerald-700 font-medium">{{ __('Published') }}</span>
                                    @elseif($p['report'])
                                        <span class="text-gray-500">{{ __('Draft') }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if($p['report'])
                                        @can('report-card.view')
                                            <a href="{{ route('admin.report-cards.show', $p['report']) }}" target="_blank" class="text-xs text-gray-500 hover:text-gray-800 mr-3">{{ __('Report') }}</a>
                                        @endcan
                                    @endif
                                    <a href="{{ route('admin.nursery.edit', [$p['enrollment'], $term]) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium {{ $pct >= 100 ? 'bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-teal-600 text-white hover:bg-teal-700' }}">
                                        {{ $pct >= 100 ? __('Review') : ($pct > 0 ? __('Continue') : __('Assess')) }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif
</div>
@endsection
