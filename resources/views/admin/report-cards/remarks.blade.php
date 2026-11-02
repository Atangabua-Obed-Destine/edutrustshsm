@extends('layouts.admin')
@section('title', __('Report Card Remarks'))
@section('breadcrumb', __('Examinations > Report Cards > Remarks'))

@section('content')
@php
    $conducts = ['excellent' => __('Excellent'), 'very_good' => __('Very good'), 'good' => __('Good'), 'fair' => __('Fair'), 'poor' => __('Poor')];
    $headTitle = $section->form->school_level === 'nursery_primary' ? __('Head Teacher') : __('Principal');
@endphp
<form method="POST" action="{{ route('admin.report-cards.remarks.save') }}" class="space-y-5 pb-24">
    @csrf
    <input type="hidden" name="class_section_id" value="{{ $section->id }}">
    <input type="hidden" name="term_id" value="{{ $term->id }}">

    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <a href="{{ route('admin.report-cards.index', ['academic_session_id' => $session->id, 'form_id' => $section->form_id, 'class_section_id' => $section->id, 'term_id' => $term->id]) }}" class="text-xs text-gray-500 hover:text-gray-800">&larr; {{ __('Back to report cards') }}</a>
            <h2 class="text-xl font-bold text-gray-800 mt-1">{{ __('Remarks — :class, :term', ['class' => $section->name, 'term' => $term->name]) }}</h2>
            <p class="text-sm text-gray-500 max-w-3xl">{{ __('What goes in the remark boxes of each report card. Write them before or after the cards are generated: generating again keeps what is written here.') }}</p>
        </div>
    </div>

    @if($rows->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center text-sm text-gray-500">{{ __('No active pupil is enrolled in this class for the current year.') }}</div>
    @else
        <datalist id="remark-suggestions">
            @foreach($suggestions as $s)
                <option value="{{ $s }}">
            @endforeach
        </datalist>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left w-48">{{ __('Pupil') }}</th>
                            <th class="px-4 py-2 text-right w-20">{{ __('Average') }}</th>
                            <th class="px-4 py-2 text-left">{{ __("Class Teacher's Remark") }}</th>
                            <th class="px-4 py-2 text-left w-36">{{ __('Conduct') }}</th>
                            <th class="px-4 py-2 text-left">{{ __(":title's Remark", ['title' => $headTitle]) }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $row)
                            @php $r = $row['result']; $id = $row['enrollment']->id; @endphp
                            <tr class="align-top">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-800">{{ $row['enrollment']->student->last_name }} {{ $row['enrollment']->student->first_name }}</span>
                                    <span class="block text-xs text-gray-400">{{ $row['enrollment']->student->student_id }}</span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $r?->term_average !== null && (float) $r->term_average < $passMark ? 'text-red-600' : 'text-gray-800' }}">
                                    {{ $r?->term_average !== null ? number_format((float) $r->term_average, 2) : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="remarks[{{ $id }}][class_teacher_remark]" value="{{ old("remarks.$id.class_teacher_remark", $r?->class_teacher_remark) }}" maxlength="500" list="remark-suggestions"
                                           {{ $canClassTeacher ? '' : 'readonly' }}
                                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 {{ $canClassTeacher ? '' : 'bg-gray-50 text-gray-500' }}" placeholder="{{ __('e.g. Good work, keep it up') }}">
                                </td>
                                <td class="px-4 py-3">
                                    <select name="remarks[{{ $id }}][conduct]" {{ $canClassTeacher ? '' : 'disabled' }} style="appearance:auto;" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5 bg-white">
                                        <option value="">—</option>
                                        @foreach($conducts as $value => $label)
                                            <option value="{{ $value }}" @selected(old("remarks.$id.conduct", $r?->conduct) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="text" name="remarks[{{ $id }}][principal_remark]" value="{{ old("remarks.$id.principal_remark", $r?->principal_remark) }}" maxlength="500" list="remark-suggestions"
                                           {{ $canHead ? '' : 'readonly' }}
                                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 {{ $canHead ? '' : 'bg-gray-50 text-gray-500' }}" placeholder="{{ $canHead ? __('e.g. Promoted with encouragement') : __('Written by the :title', ['title' => mb_strtolower($headTitle)]) }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="fixed bottom-0 left-0 right-0 lg:left-64 bg-white/95 backdrop-blur border-t border-gray-200 px-4 sm:px-6 py-3 z-20 flex items-center justify-end gap-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">{{ __('Save remarks') }}</button>
        </div>
    @endif
</form>
@endsection
