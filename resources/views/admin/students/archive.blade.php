@extends('layouts.admin')
@section('title', __('Former Pupils'))
@section('breadcrumb', __('Students > Former Pupils'))

@section('content')
<div class="space-y-4">

    <div>
        <h2 class="text-lg font-semibold text-gray-800">{{ __('Former Pupils') }}</h2>
        <p class="text-sm text-gray-500 mt-0.5">
            {{ __('Everyone who has left. Their records are kept in full — they simply no longer count in this term\'s figures.') }}
        </p>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                   placeholder="{{ __('Name or matricule') }}"
                   class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('How they left') }}</label>
            <select name="status" style="appearance:auto;" class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                <option value="">{{ __('Any') }}</option>
                <option value="graduated" @selected(($filters['status'] ?? '') === 'graduated')>{{ __('Graduated') }}</option>
                <option value="withdrawn" @selected(($filters['status'] ?? '') === 'withdrawn')>{{ __('Withdrawn') }}</option>
                <option value="expelled" @selected(($filters['status'] ?? '') === 'expelled')>{{ __('Expelled') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Year') }}</label>
            <select name="year" style="appearance:auto;" class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                <option value="">{{ __('Any') }}</option>
                @foreach($years as $year)
                    <option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
        <a href="{{ route('admin.students.archive') }}" class="text-sm text-gray-500 hover:text-gray-700 px-2 py-2">{{ __('Clear') }}</a>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 border-b border-gray-200 bg-gray-50">
                        <th class="py-2.5 pl-5 pr-3">{{ __('Pupil') }}</th>
                        <th class="py-2.5 px-3">{{ __('Last class') }}</th>
                        <th class="py-2.5 px-3">{{ __('How they left') }}</th>
                        <th class="py-2.5 px-3">{{ __('Last day') }}</th>
                        <th class="py-2.5 px-3">{{ __('Note') }}</th>
                        <th class="py-2.5 pl-3 pr-5 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        @php($last = $student->enrollments->sortByDesc('enrollment_date')->first())
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/60">
                            <td class="py-2.5 pl-5 pr-3">
                                <a href="{{ route('admin.students.show', $student) }}" class="font-medium text-gray-800 hover:text-teal-700">
                                    {{ $student->first_name }} {{ $student->last_name }}
                                </a>
                                <div class="text-xs text-gray-400">{{ $student->student_id }}</div>
                            </td>
                            <td class="py-2.5 px-3 text-gray-600">{{ $last?->classSection?->name ?? '—' }}</td>
                            <td class="py-2.5 px-3">
                                {{-- Expression form throughout this view. A block form here
                                     would pair with the expression directive above it, and
                                     everything between would be read as one raw block. --}}
                                @php($tone = match($student->status) {
                                    'graduated' => 'bg-emerald-100 text-emerald-700',
                                    'expelled' => 'bg-rose-100 text-rose-700',
                                    default => 'bg-gray-100 text-gray-600',
                                })
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $tone }}">
                                    {{ __(ucfirst($student->status)) }}
                                </span>
                                @if($student->destination_school)
                                    <div class="text-xs text-gray-500 mt-1">→ {{ $student->destination_school }}</div>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-gray-600">
                                {{ $student->withdrawal_date ? \Illuminate\Support\Carbon::parse($student->withdrawal_date)->format('d M Y') : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($student->withdrawal_reason, 60) ?: '—' }}</td>
                            <td class="py-2.5 pl-3 pr-5 text-right whitespace-nowrap">
                                <a href="{{ route('admin.students.history', $student) }}" class="text-xs text-gray-500 hover:text-gray-700">{{ __('Record') }}</a>
                                @can('student-exit.view')
                                    <a href="{{ route('admin.students.leaving-certificate', $student) }}" class="text-xs text-teal-600 hover:text-teal-700 ml-3">{{ __('Certificate') }}</a>
                                @endcan
                                @can('student-exit.readmit')
                                    <form method="POST" action="{{ route('admin.students.readmit', $student) }}" class="inline ml-3"
                                          onsubmit="return confirm('{{ __('Bring this pupil back onto the roll?') }}')">
                                        @csrf
                                        <button class="text-xs text-amber-600 hover:text-amber-700">{{ __('Re-admit') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-gray-500">
                                {{ __('Nobody has been recorded as having left.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $students->links() }}
</div>
@endsection
