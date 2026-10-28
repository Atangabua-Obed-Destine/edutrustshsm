@extends('layouts.admin')
@section('title', __('Pupils outside this school\'s level'))
@section('breadcrumb', __('Students > Outside this level'))

@section('content')
<div class="space-y-4" x-data="{ picked: {} }">

    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-gray-800">{{ __('Pupils outside this school\'s level') }}</h2>
            <p class="text-sm text-gray-500 mt-1 max-w-3xl">
                {{ __('This school runs :runs. The classes below belong to :hidden, so they and their pupils are left out of every list, count and report. They are not lost — they are waiting to be closed out properly.', [
                    'runs' => collect(\App\Support\LevelContext::availableLabels())->join(', '),
                    'hidden' => collect($hiddenLabels)->join(', '),
                ]) }}
            </p>
        </div>
        @can('school-settings.view')
        <a href="{{ route('admin.settings.index') }}#level-mode" class="text-sm text-gray-600 hover:text-gray-800 whitespace-nowrap border border-gray-300 rounded-lg px-3 py-2">
            {{ __('Change school level') }}
        </a>
        @endcan
    </div>

    {{-- The figures --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Hidden pupils') }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $summary['pupils'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Hidden classes') }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $summary['classes'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 col-span-2 sm:col-span-1">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Empty classes') }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $classes->filter(fn ($f) => $f->hiddenPupils->isEmpty())->count() }}</p>
        </div>
    </div>

    @if($classes->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">
            {{ __('Nothing is hidden. Every class and pupil belongs to a level this school runs.') }}
        </div>
    @else

    @can('student-exit.create')
    {{-- How to close them out. One form; the tick boxes in the tables below belong to it. --}}
    <form id="exit-form" method="POST" action="{{ route('admin.students.out-of-scope.exit') }}"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-5"
          x-data="{ type: '{{ old('type', 'graduated') }}' }"
          onsubmit="return confirm('{{ __('Take the ticked pupils off the roll?') }}')">
        @csrf
        <h3 class="text-sm font-semibold text-gray-700">{{ __('Close out the ticked pupils') }}</h3>
        <p class="text-xs text-gray-500 mt-1 mb-4">
            {{ __('A class that has finished is graduated; one that moved to another school is transferred. Each pupil gets the same record as a single leaving, with a leaving certificate, and stays in Former Pupils.') }}
        </p>

        @if($errors->any())
            <div class="bg-rose-50 border border-rose-200 rounded-lg p-3 mb-4 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('How they left') }}</label>
                <select name="type" x-model="type" required style="appearance:auto;" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', 'graduated') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Last day') }}</label>
                <input type="date" name="left_on" value="{{ old('left_on', now()->toDateString()) }}" required max="{{ now()->toDateString() }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div x-show="type === 'transferred'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Transferring to') }}</label>
                <input type="text" name="destination_school" value="{{ old('destination_school') }}" maxlength="200"
                       placeholder="{{ __('Name of the receiving school') }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Note') }}</label>
            <input type="text" name="reason" value="{{ old('reason', __('School now runs :level only.', ['level' => collect(\App\Support\LevelContext::availableLabels())->join(', ')])) }}" maxlength="500"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>

        <div class="flex items-center justify-between mt-5">
            <p class="text-sm text-gray-500">
                <span x-text="Object.values(picked).filter(Boolean).length"></span> {{ __('ticked') }}
            </p>
            <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2 rounded-lg text-sm font-medium transition"
                    :disabled="Object.values(picked).filter(Boolean).length === 0"
                    :class="{ 'opacity-50 cursor-not-allowed': Object.values(picked).filter(Boolean).length === 0 }">
                {{ __('Record the leaving') }}
            </button>
        </div>
    </form>
    @endcan

    {{-- Class by class --}}
    @foreach($classes as $form)
        @php($pupils = $form->hiddenPupils)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50/60">
                <div class="flex items-center gap-3">
                    <h3 class="font-semibold text-gray-800">{{ $form->name }}</h3>
                    <span class="text-xs text-gray-500">{{ trans_choice('{0} nobody enrolled|{1} :count pupil|[2,*] :count pupils', $pupils->count(), ['count' => $pupils->count()]) }}</span>
                </div>
                @can('student-exit.create')
                @if($pupils->isNotEmpty())
                    <label class="text-xs text-gray-600 inline-flex items-center gap-1.5 cursor-pointer select-none">
                        <input type="checkbox" class="rounded border-gray-300"
                               @change="@foreach($pupils as $p) picked[{{ $p->id }}] = $event.target.checked; @endforeach">
                        {{ __('Tick the whole class') }}
                    </label>
                @endif
                @endcan
            </div>

            @if($pupils->isEmpty())
                <p class="px-5 py-4 text-sm text-gray-500">{{ __('Nobody is enrolled here this year. It can be retired.') }}</p>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr>
                            @can('student-exit.create')<th class="py-2 pl-5 pr-2 w-8"></th>@endcan
                            <th class="py-2 px-3 text-left">{{ __('Name') }}</th>
                            <th class="py-2 px-3 text-left">{{ __('Section') }}</th>
                            <th class="py-2 px-3 text-left">{{ __('Guardian Phone') }}</th>
                            <th class="py-2 pl-3 pr-5 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($pupils as $student)
                        <tr class="hover:bg-gray-50/60">
                            @can('student-exit.create')
                            <td class="py-2.5 pl-5 pr-2">
                                <input type="checkbox" name="students[]" value="{{ $student->id }}" form="exit-form"
                                       class="rounded border-gray-300" x-model="picked[{{ $student->id }}]">
                            </td>
                            @endcan
                            <td class="py-2.5 px-3">
                                <a href="{{ route('admin.students.show', $student) }}" class="font-medium text-gray-800 hover:text-teal-700">
                                    {{ $student->first_name }} {{ $student->last_name }}
                                </a>
                                <div class="text-xs text-gray-400">{{ $student->student_id }}</div>
                            </td>
                            <td class="py-2.5 px-3 text-gray-600">{{ $student->currentEnrollment?->classSection?->name ?? '—' }}</td>
                            <td class="py-2.5 px-3 text-gray-600">{{ $student->guardian?->primary_phone ?? '—' }}</td>
                            <td class="py-2.5 pl-3 pr-5 text-right whitespace-nowrap">
                                <a href="{{ route('admin.students.show', $student) }}" class="text-xs text-gray-500 hover:text-gray-700">{{ __('View') }}</a>
                                @can('student-exit.create')
                                    <a href="{{ route('admin.students.exit', $student) }}" class="text-xs text-teal-600 hover:text-teal-700 ml-3">{{ __('Record leaving') }}</a>
                                @endcan
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    @endforeach

    @can('form.edit')
    @if($classes->contains(fn ($f) => $f->hiddenPupils->isEmpty()))
        <form method="POST" action="{{ route('admin.students.out-of-scope.retire') }}"
              class="flex items-center justify-between bg-white rounded-xl shadow-sm border border-gray-200 p-5"
              onsubmit="return confirm('{{ __('Retire every empty class at this level?') }}')">
            @csrf
            <p class="text-sm text-gray-600">
                {{ __('Classes with nobody left can be retired. They are deactivated, not deleted: their old results and records stay readable.') }}
            </p>
            <button type="submit" class="ml-4 shrink-0 border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition">
                {{ __('Retire empty classes') }}
            </button>
        </form>
    @endif
    @endcan

    @endif
</div>
@endsection
