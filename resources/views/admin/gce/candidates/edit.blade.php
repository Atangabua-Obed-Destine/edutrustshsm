@extends('layouts.admin')
@section('title', __('Edit Entry'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <div>
        <a href="{{ route('admin.gce.candidates.index', $session) }}" class="text-xs text-gray-400 hover:text-gray-600">&larr; {{ __('Candidates') }}</a>
        <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $candidate->student?->full_name }}</h1>
        <p class="text-sm text-gray-500 mt-1">
            {{ __('Candidate') }} {{ $candidate->candidate_number }} ·
            {{ $session->name }} · {{ $session->level_label }}
        </p>
    </div>

    @include('admin.gce._flash')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <form method="POST" action="{{ route('admin.gce.candidates.update', $candidate) }}"
              class="lg:col-span-2 bg-white rounded-xl border border-gray-100 p-6 space-y-5">
            @csrf @method('PUT')

            <div>
                <h2 class="text-sm font-semibold text-gray-700 mb-1">{{ __('Subjects') }}</h2>
                <p class="text-xs text-gray-500 mb-3">
                    {{ __('Between :min and :max for :level. The fee follows the subject count.', [
                        'min' => $session->min_subjects, 'max' => $session->max_subjects, 'level' => $session->level_label,
                    ]) }}
                </p>
                @php $chosen = old('gce_subject_ids', $candidate->subjects->pluck('id')->all()); @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach($gceSubjects as $gceSubject)
                    <label class="flex items-center gap-2 border border-gray-100 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="gce_subject_ids[]" value="{{ $gceSubject->id }}"
                               @checked(in_array($gceSubject->id, $chosen)) class="rounded text-indigo-600">
                        <span class="text-sm text-gray-700">
                            <span class="font-mono text-xs text-gray-400">{{ $gceSubject->code }}</span>
                            {{ $gceSubject->name }}
                        </span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end border-t border-gray-100 pt-5">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg px-5 py-2">{{ __('Save Subjects') }}</button>
            </div>
        </form>

        <div class="space-y-6">

            <div class="bg-white rounded-xl border border-gray-100 p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700">{{ __('Entry Fee') }}</h2>
                <div class="text-sm text-gray-600 space-y-1">
                    <div class="flex justify-between"><span>{{ __('Due') }}</span><span class="font-medium text-gray-900">{{ number_format((float) $candidate->fee_amount, 0, '.', ' ') }}</span></div>
                    <div class="flex justify-between"><span>{{ __('Paid') }}</span><span class="font-medium text-gray-900">{{ number_format((float) $candidate->amount_paid, 0, '.', ' ') }}</span></div>
                    <div class="flex justify-between border-t border-gray-100 pt-1">
                        <span>{{ __('Balance') }}</span>
                        <span class="font-bold {{ $candidate->isPaid() ? 'text-emerald-600' : 'text-amber-600' }}">{{ number_format($candidate->balance, 0, '.', ' ') }}</span>
                    </div>
                </div>
                @can('gce-registration.edit')
                <form method="POST" action="{{ route('admin.gce.candidates.payment', $candidate) }}" class="flex gap-2 pt-1">
                    @csrf
                    <input type="number" step="0.01" min="0" name="amount" value="{{ (float) $candidate->amount_paid }}"
                           class="w-full rounded-lg border-gray-200 text-sm">
                    <button type="submit" class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-3 py-2 whitespace-nowrap">{{ __('Set') }}</button>
                </form>
                @endcan
            </div>

            @can('gce-registration.edit')
            <div class="bg-white rounded-xl border border-gray-100 p-5 space-y-3">
                <h2 class="text-sm font-semibold text-gray-700">{{ __('Entry Status') }}</h2>
                <p class="text-xs text-gray-500">{{ __('An entry cannot be confirmed while its fee is unpaid.') }}</p>
                <form method="POST" action="{{ route('admin.gce.candidates.status', $candidate) }}" class="flex gap-2">
                    @csrf
                    <select name="status" class="w-full rounded-lg border-gray-200 text-sm">
                        @foreach(['draft', 'submitted', 'confirmed', 'withdrawn'] as $option)
                            <option value="{{ $option }}" @selected($candidate->status === $option)>{{ __(ucfirst($option)) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-3 py-2">{{ __('Apply') }}</button>
                </form>
            </div>
            @endcan

            @can('gce-registration.delete')
            <form method="POST" action="{{ route('admin.gce.candidates.destroy', $candidate) }}"
                  onsubmit="return confirm('{{ __('Remove this entry?') }}')">
                @csrf @method('DELETE')
                <button type="submit" class="w-full text-sm font-medium text-red-600 border border-red-200 hover:bg-red-50 rounded-lg px-4 py-2">
                    {{ __('Remove Entry') }}
                </button>
            </form>
            @endcan

        </div>
    </div>

</div>
@endsection
