@extends('layouts.admin')
@section('title', $section->name.' · '.$term->name.' — '.$year->name)
@section('breadcrumb', __('Academic > Past Years').' > '.$year->name.' > '.$section->name.' > '.$term->name)

@php $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); @endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ $section->name }} · {{ $term->name }} · {{ $year->name }}</h2>
            <p class="text-sm text-gray-500">
                @if($sheet->isVerified())
                    <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">{{ __('Verified') }}</span>
                    {{ __('by :name on :date', ['name' => $sheet->verifiedBy?->full_name, 'date' => $sheet->verified_at?->format('d/m/Y')]) }}
                @elseif($sheet->entered_by)
                    <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">{{ __('To verify') }}</span>
                    {{ __('entered by :name on :date', ['name' => $sheet->enteredBy?->full_name, 'date' => $sheet->entered_at?->format('d/m/Y')]) }}
                @else
                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500">{{ __('Not entered') }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.past-years.show', $year) }}" class="px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Back') }}</a>
    </div>

    @if(session('upload_errors'))
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p class="text-sm font-medium text-amber-800 mb-2">{{ __('Lines to correct:') }}</p>
            <ul class="text-xs text-amber-700 space-y-1 max-h-72 overflow-y-auto">@foreach(session('upload_errors') as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif
    @foreach(session('upload_warnings', []) as $warning)
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-sm text-blue-800">{{ $warning }}</div>
    @endforeach

    @unless($sheet->isVerified())
    @can('past-year.record')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">{{ __('Enter the mark sheet') }}</h3>
        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
            <li>{{ __('Download the template: it lists the :count pupils placed in :class and one column per subject.', ['count' => $enrollments->count(), 'class' => $section->name]) }}</li>
            <li>{{ __('Copy each pupil\'s mark per subject from the mark sheet. Leave a subject empty if the pupil did not take it.') }}</li>
            <li>{{ __('Fill average and rank as written on the report card. If you leave them empty, they are worked out from the marks and coefficients.') }}</li>
            @if($isLastTerm)<li>{{ __('This is the last term: also fill the annual average and annual rank if the report card gives them.') }}</li>@endif
            <li>{{ __('Sending a corrected file replaces the whole sheet.') }}</li>
        </ul>
        <div class="flex flex-wrap items-end gap-3">
            <a href="{{ route('admin.past-years.template', [$year, $section, $term]) }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Download CSV Template') }}</a>
            <form method="POST" action="{{ route('admin.past-years.upload', [$year, $section, $term]) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                @csrf
                <label class="text-sm">
                    <span class="block text-gray-700 font-medium mb-1">{{ __('Marks are out of') }}</span>
                    <input type="number" name="out_of" min="1" max="1000" step="1" required value="{{ (int) $sheet->out_of }}" class="w-24 px-3 py-2 border border-gray-300 rounded-lg">
                </label>
                <input type="file" name="csv_file" accept=".csv,.txt" required class="text-sm">
                <button class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">{{ __('Record this sheet') }}</button>
            </form>
        </div>
        <form method="POST" action="{{ route('admin.past-years.evidence.store', [$year, $section, $term]) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100">
            @csrf
            <span class="text-sm text-gray-700">{{ __('Scan or photo of the paper sheet (optional):') }}</span>
            <input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png" required class="text-sm">
            <button class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50">{{ __('Attach') }}</button>
        </form>
    </div>
    @endcan
    @endunless

    @if($sheet->evidence_path)
        <a href="{{ route('admin.past-years.evidence', [$year, $section, $term]) }}" target="_blank" class="inline-block text-sm text-blue-700 hover:underline">{{ __('Open the scan of the paper sheet') }}</a>
    @endif

    @if($results->isNotEmpty())
        @if($discrepancies)
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-900">
                <p class="font-semibold mb-1">{{ __('Check these against the paper first:') }}</p>
                <ul class="list-disc ml-5 space-y-0.5">@foreach($discrepancies as $line)<li>{{ $line }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left sticky left-0 bg-gray-50">{{ __('Pupil') }}</th>
                        @foreach($subjects as $subject)<th class="px-2 py-2 text-center" title="{{ $subject->name }}">{{ $subject->code ?: $subject->name }}</th>@endforeach
                        <th class="px-2 py-2 text-right">{{ __('Average') }}</th>
                        <th class="px-2 py-2 text-right">{{ __('Rank') }}</th>
                        @if($isLastTerm)<th class="px-2 py-2 text-right">{{ __('Year') }}</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($enrollments as $e)
                        @php $r = $results[$e->id] ?? null; $marks = $r?->subjectResults->keyBy('subject_id'); @endphp
                        <tr>
                            <td class="px-3 py-1.5 sticky left-0 bg-white">
                                <a href="{{ route('admin.past-years.record', [$year, $e->student]) }}" class="font-medium text-gray-800 hover:underline">{{ $e->student->full_name }}</a>
                            </td>
                            @foreach($subjects as $subject)
                                <td class="px-2 py-1.5 text-center tabular-nums">{{ $fmt($marks?->get($subject->id)?->term_average) }}</td>
                            @endforeach
                            <td class="px-2 py-1.5 text-right font-semibold tabular-nums">{{ $fmt($r?->term_average) }}</td>
                            <td class="px-2 py-1.5 text-right tabular-nums">{{ $r?->class_rank ? $r->class_rank.'/'.$r->total_students : '—' }}</td>
                            @if($isLastTerm)
                                <td class="px-2 py-1.5 text-right tabular-nums">{{ $fmt($e->final_average) }}{{ $e->final_rank ? ' ('.$e->final_rank.')' : '' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap gap-4">
            @can('past-year.verify')
                @unless($sheet->isVerified())
                    <form method="POST" action="{{ route('admin.past-years.verify', [$year, $section, $term]) }}" class="bg-white rounded-xl border border-emerald-200 p-5 space-y-2 flex-1 min-w-[260px]"
                          onsubmit="return confirm(@js(__('Have you compared every line with the paper mark sheet?')))">
                        @csrf
                        <h3 class="font-semibold text-emerald-800">{{ __('Verify against the paper') }}</h3>
                        <p class="text-xs text-gray-600">{{ __('Compare each line with the paper mark sheet. Once verified the sheet is locked and the results reach each pupil\'s history and the parent portal. The person who entered it cannot verify it.') }}</p>
                        <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">{{ __('Verify and lock') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.past-years.reopen', [$year, $section, $term]) }}" class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 flex-1 min-w-[260px]">
                        @csrf
                        <h3 class="font-semibold text-gray-800">{{ __('Reopen for correction') }}</h3>
                        <p class="text-xs text-gray-600">{{ __('Parents stop seeing this sheet until it is verified again. The reason is kept in the audit trail.') }}</p>
                        <input type="text" name="reason" required minlength="3" maxlength="500" placeholder="{{ __('Reason') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <button class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Reopen') }}</button>
                    </form>
                @endunless
            @endcan
        </div>
    @endif
</div>
@endsection
