@extends('layouts.admin')
@section('title', __('Fees Paid Before the Switch'))
@section('breadcrumb', __('Fees > Fees Paid Before the Switch'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">{{ __('Fees Paid Before the Switch') }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ __('Parents who paid in the old system, before the school started using this one, must not be asked again. Record those payments here so every balance is right from the first day.') }}</p>
    </div>

    @if(session('upload_errors'))
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p class="text-sm font-medium text-amber-800 mb-2">{{ __('Lines not imported:') }}</p>
            <ul class="text-xs text-amber-700 space-y-1 max-h-72 overflow-y-auto">
                @foreach(session('upload_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Recorded so far this year') }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($count) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __('Total') }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-1 tabular-nums">{{ number_format($total) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        <h3 class="font-semibold text-gray-800">{{ __('How it works') }}</h3>
        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1.5">
            <li>{{ __('Register the pupils and set the class fees first: a payment is set against what the pupil owes.') }}</li>
            <li>{{ __('One line per old receipt: the matricule, the amount, the date it was paid (DD/MM/YYYY) and the old receipt number.') }}</li>
            <li>{{ __('Each line settles the pupil\'s fees like any payment, oldest first, and gets a receipt here.') }}</li>
            <li>{{ __('This money is not counted in today\'s takings, the cash book or the ledger: it was received and booked in the old system. The accountant brings it in once, with the opening balances.') }}</li>
            <li>{{ __('An old receipt already recorded is left as it is, so a corrected file can be sent again.') }}</li>
        </ul>
        <a href="{{ route('admin.prior-payments.template') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Download CSV Template') }}</a>
    </div>

    @can('fee-collection.collect')
    <form action="{{ route('admin.prior-payments.upload') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('CSV File') }} <span class="text-red-500">*</span></label>
            <input type="file" name="csv_file" accept=".csv,.txt" required class="w-full rounded-lg border border-gray-300 bg-white shadow-sm text-sm file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-100 file:text-slate-700">
            @error('csv_file') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="px-6 py-2.5 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">{{ __('Record these payments') }}</button>
    </form>
    @endcan
</div>
@endsection
