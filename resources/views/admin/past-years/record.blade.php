@extends('layouts.admin')
@section('title', $student->full_name.' — '.$year->name)
@section('breadcrumb', __('Academic > Past Years').' > '.$year->name)

@section('content')
<style>
    @media print {
        @page { size: A4 portrait; margin: 12mm; }
        aside, header, .no-print, #flash-success, #flash-error { display: none !important; }
        #main-content { margin-left: 0 !important; }
        main { padding: 0 !important; }
        .record { box-shadow: none !important; border: 0 !important; padding: 0 !important; }
    }
</style>
<div class="max-w-4xl mx-auto space-y-4">
    <div class="no-print flex flex-wrap justify-between gap-3">
        <a href="{{ route('admin.students.history', $student) }}" class="px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Academic history') }}</a>
        <button type="button" onclick="window.print()" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">{{ __('Print') }}</button>
    </div>
    @if($results->contains(fn ($r) => ! $r->is_published))
        <p class="no-print text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">{{ __('Some terms are not verified yet: parents do not see them.') }}</p>
    @endif
    @include('admin.past-years._record')
</div>
@endsection
