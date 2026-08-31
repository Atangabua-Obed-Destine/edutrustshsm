@extends('layouts.admin')
@section('title', __('GCE Candidates'))

@section('content')
<div class="max-w-full mx-auto space-y-6">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.gce.sessions.index') }}" class="text-xs text-gray-400 hover:text-gray-600">&larr; {{ __('Exam Series') }}</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ $session->name }}</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $session->level_label }} · {{ $session->exam_year }}
                @if($session->centre_number) · {{ __('Centre') }} {{ $session->centre_number }} @endif
                · {{ __(ucfirst($session->status)) }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('gce-registration.export')
            <a href="{{ route('admin.gce.candidates.export', $session) }}" class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-4 py-2">{{ __('Board CSV') }}</a>
            <a href="{{ route('admin.gce.candidates.entry-list', $session) }}" target="_blank" class="text-sm font-medium border border-gray-200 hover:bg-gray-50 rounded-lg px-4 py-2">{{ __('Entry List') }}</a>
            @endcan
            @can('gce-registration.create')
            <a href="{{ route('admin.gce.candidates.create', $session) }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg px-4 py-2">{{ __('Enter Candidate') }}</a>
            @endcan
        </div>
    </div>

    @include('admin.gce._flash')

    @unless($session->isOpen())
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-sm text-amber-800">
            {{ __('This series is :status, so entries cannot be added or changed.', ['status' => __(ucfirst($session->status))]) }}
        </p>
    </div>
    @endunless

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        @foreach([
            ['label' => __('Candidates'), 'value' => $totals['candidates'], 'tone' => 'text-gray-900'],
            ['label' => __('Subject Entries'), 'value' => $totals['entries'], 'tone' => 'text-gray-900'],
            ['label' => __('Fees Due'), 'value' => number_format($totals['fees'], 0, '.', ' '), 'tone' => 'text-gray-900'],
            ['label' => __('Fees Paid'), 'value' => number_format($totals['paid'], 0, '.', ' '), 'tone' => 'text-emerald-600'],
            ['label' => __('Not Yet Entered'), 'value' => $outstanding, 'tone' => $outstanding > 0 ? 'text-amber-600' : 'text-gray-300'],
        ] as $card)
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ $card['label'] }}</p>
            <p class="text-2xl font-bold {{ $card['tone'] }} mt-1">{{ $card['value'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-100 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('Candidate No.') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Student') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Subjects') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Fee') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Paid') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($candidates as $candidate)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-gray-900">{{ $candidate->candidate_number ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900 whitespace-nowrap">{{ $candidate->student?->last_name }} {{ $candidate->student?->first_name }}</div>
                        <div class="text-xs text-gray-400">{{ $candidate->student?->student_id }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="text-xs text-gray-600">{{ $candidate->subjects->pluck('code')->join(', ') }}</div>
                        <div class="text-[11px] text-gray-400">{{ trans_choice(':count subject|:count subjects', $candidate->subjects->count(), ['count' => $candidate->subjects->count()]) }}</div>
                    </td>
                    <td class="px-4 py-3 text-right text-gray-700">{{ number_format((float) $candidate->fee_amount, 0, '.', ' ') }}</td>
                    <td class="px-4 py-3 text-right {{ $candidate->isPaid() ? 'text-emerald-600 font-medium' : 'text-amber-600' }}">
                        {{ number_format((float) $candidate->amount_paid, 0, '.', ' ') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @php $tone = ['draft' => 'bg-gray-100 text-gray-600', 'submitted' => 'bg-blue-50 text-blue-700', 'confirmed' => 'bg-emerald-50 text-emerald-700', 'withdrawn' => 'bg-red-50 text-red-700'][$candidate->status] ?? 'bg-gray-100 text-gray-600'; @endphp
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $tone }}">{{ __(ucfirst($candidate->status)) }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-3 text-sm">
                            @can('gce-registration.export')
                            <a href="{{ route('admin.gce.candidates.slip', $candidate) }}" target="_blank" class="text-gray-500 hover:text-gray-700">{{ __('Slip') }}</a>
                            @endcan
                            @can('gce-registration.edit')
                            <a href="{{ route('admin.gce.candidates.edit', $candidate) }}" class="text-indigo-600 hover:text-indigo-700 font-medium">{{ __('Edit') }}</a>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">
                        {{ __('No candidates entered for this series yet.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
