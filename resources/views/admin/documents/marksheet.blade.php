@extends('layouts.admin')
@section('title', __('Class Marksheet'))

@section('content')
<div class="max-w-full mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ __('Class Marksheet') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('Every subject average for a whole class, on one sheet') }}</p>
    </div>

    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <p class="text-sm text-red-700 font-medium">{{ session('error') }}</p>
    </div>
    @endif

    <form method="GET" action="{{ route('admin.documents.marksheet') }}" class="bg-white rounded-xl border border-gray-100 p-5">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Academic Year') }}</label>
                <select name="academic_session_id" class="w-full rounded-lg border-gray-200 text-sm">
                    @foreach($sessions as $option)
                        <option value="{{ $option->id }}" @selected($session?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Class') }}</label>
                <select name="form_id" onchange="this.form.submit()" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">{{ __('Select...') }}</option>
                    @foreach($forms as $form)
                        <option value="{{ $form->id }}" @selected(request('form_id') == $form->id)>{{ $form->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Section') }}</label>
                <select name="class_section_id" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">{{ __('Select...') }}</option>
                    @foreach($classSections as $option)
                        <option value="{{ $option->id }}" @selected($section?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Term') }}</label>
                <select name="term_id" class="w-full rounded-lg border-gray-200 text-sm">
                    <option value="">{{ __('Select...') }}</option>
                    @foreach($terms as $option)
                        <option value="{{ $option->id }}" @selected($term?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg px-4 py-2">
                    {{ __('Show') }}
                </button>
            </div>
        </div>
    </form>

    @if($section && $term)
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $section->name }} — {{ $term->name }}</h2>
                @if($class && $class['count'] > 0)
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ __('Students') }}: {{ $class['count'] }} ·
                    {{ __('Class average') }}: {{ number_format($class['average'], 2) }} ·
                    {{ __('Highest') }}: {{ number_format($class['highest'], 2) }} ·
                    {{ __('Lowest') }}: {{ number_format($class['lowest'], 2) }}
                </p>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.documents.marksheet.pdf', request()->query()) }}" target="_blank"
                   class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-4 py-2">{{ __('Print PDF') }}</a>
                <a href="{{ route('admin.documents.marksheet.csv', request()->query()) }}"
                   class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-4 py-2">{{ __('Export CSV') }}</a>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-3 text-center">#</th>
                        <th class="px-3 py-3 text-left">{{ __('Student') }}</th>
                        @foreach($subjects as $subject)
                            <th class="px-2 py-3 text-center whitespace-nowrap">
                                {{ $subject->name }}
                                <span class="block text-[10px] font-normal normal-case text-gray-400">
                                    x{{ rtrim(rtrim(number_format((float) $subject->coefficient, 2, '.', ''), '0'), '.') }}
                                </span>
                            </th>
                        @endforeach
                        <th class="px-3 py-3 text-center">{{ __('Average') }}</th>
                        <th class="px-3 py-3 text-center">{{ __('Grade') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-center text-gray-400">{{ $row->rank ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <div class="font-medium text-gray-900 whitespace-nowrap">{{ $row->student?->last_name }} {{ $row->student?->first_name }}</div>
                            <div class="text-xs text-gray-400">{{ $row->student?->student_id }}</div>
                        </td>
                        @foreach($subjects as $subject)
                            @php $cell = $row->by_subject[$subject->id]['term_average'] ?? null; @endphp
                            <td class="px-2 py-2 text-center {{ $cell !== null && $cell < $passMark ? 'text-red-600 font-medium' : 'text-gray-700' }}">
                                {{ $cell !== null ? number_format((float) $cell, 2) : '—' }}
                            </td>
                        @endforeach
                        <td class="px-3 py-2 text-center font-bold text-gray-900">
                            {{ $row->average !== null ? number_format((float) $row->average, 2) : '—' }}
                        </td>
                        <td class="px-3 py-2 text-center text-gray-600">{{ $row->grade }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $subjects->count() + 4 }}" class="px-4 py-10 text-center text-sm text-gray-400">
                            {{ __('No students are enrolled in this class for this term.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-100 p-10 text-center text-sm text-gray-400">
            {{ __('Choose a class and term to see the marksheet.') }}
        </div>
    @endif

</div>
@endsection
