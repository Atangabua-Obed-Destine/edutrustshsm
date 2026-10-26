@extends('layouts.admin')
@section('title', __('Record a pupil leaving'))
@section('breadcrumb', __('Students > Leaving'))

@section('content')
<div class="max-w-3xl space-y-4">

    {{-- Who --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-start gap-4">
            @if($student->photo)
                <img src="{{ asset('storage/'.$student->photo) }}" alt="" class="w-14 h-14 rounded-xl object-cover border border-gray-200">
            @else
                <div class="w-14 h-14 rounded-xl bg-gray-100 flex items-center justify-center text-gray-400 font-bold text-lg">
                    {{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1)) }}
                </div>
            @endif
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-gray-800">{{ $student->first_name }} {{ $student->last_name }}</h2>
                <p class="text-sm text-gray-500">
                    {{ $student->student_id }}
                    @if($student->currentEnrollment?->classSection) · {{ $student->currentEnrollment->classSection->name }} @endif
                    @if($student->admission_date) · {{ __('Admitted :date', ['date' => \Illuminate\Support\Carbon::parse($student->admission_date)->format('d M Y')]) }} @endif
                </p>
            </div>
        </div>
    </div>

    {{-- What this does, said plainly before it is done --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-sm text-amber-800">
            {{ __('Recording this closes their enrolment. They stop counting in class lists, fee reports and attendance figures from now on. Everything already recorded about them is kept.') }}
        </p>
    </div>

    @if($balance > 0)
        <div class="bg-rose-50 border border-rose-200 rounded-xl p-4">
            <p class="text-sm text-rose-800">
                {{ __('This pupil still owes :amount. That does not stop them leaving — it is here so nobody finds out afterwards.', [
                    'amount' => number_format($balance, 0).' FCFA',
                ]) }}
            </p>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.students.exit.store', $student) }}"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-5"
          x-data="{ type: '{{ old('type', 'graduated') }}' }">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Why they are leaving') }} <span class="text-red-500">*</span></label>
                <select name="type" x-model="type" required style="appearance:auto;"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Last day') }} <span class="text-red-500">*</span></label>
                <input type="date" name="left_on" value="{{ old('left_on', now()->toDateString()) }}" required max="{{ now()->toDateString() }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                @error('left_on') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-4" x-show="type === 'transferred'" x-cloak>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Transferring to') }}</label>
            <input type="text" name="destination_school" value="{{ old('destination_school') }}" maxlength="200"
                   placeholder="{{ __('Name of the receiving school') }}"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            <p class="text-xs text-gray-400 mt-1">{{ __('Printed on the leaving certificate.') }}</p>
            @error('destination_school') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Note') }}</label>
            <textarea name="reason" rows="3" maxlength="500"
                      placeholder="{{ __('Anything the school should remember about this departure.') }}"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg">{{ old('reason') }}</textarea>
            @error('reason') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-3 mt-6">
            <a href="{{ route('admin.students.show', $student) }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
            <button type="submit"
                    onclick="return confirm('{{ __('Take this pupil off the roll?') }}')"
                    class="bg-[#1e293b] hover:bg-[#334155] text-white px-6 py-2 rounded-lg text-sm font-medium transition">
                {{ __('Record the leaving') }}
            </button>
        </div>
    </form>
</div>
@endsection
