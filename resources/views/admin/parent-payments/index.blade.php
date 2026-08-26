@extends('layouts.admin')
@section('title', __('Payment Submissions'))

@section('content')
@php
    $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA');
    $statusColors = [
        'pending' => ['bg-amber-100', 'text-amber-700'],
        'approved' => ['bg-green-100', 'text-green-700'],
        'rejected' => ['bg-red-100', 'text-red-700'],
    ];
@endphp

<div class="max-w-6xl mx-auto">
    @if(session('success'))
        <div class="mb-5 bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-5 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">{{ __('Parent Payment Submissions') }}</h3>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('Verify receipts uploaded by parents. Approving records an official payment and updates fee balances.') }}</p>

            {{-- Status tabs --}}
            <div class="flex gap-2 mt-4">
                @foreach(['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'all' => __('All')] as $key => $label)
                    <a href="{{ route('admin.parent-payments.index', ['status' => $key]) }}"
                       class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $status === $key ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        {{ $label }}
                        @if($key === 'pending' && $pendingCount > 0)
                            <span class="ml-1 inline-flex items-center justify-center px-1.5 rounded-full {{ $status === 'pending' ? 'bg-amber-400 text-white' : 'bg-amber-500 text-white' }} text-xs">{{ $pendingCount }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                        <th class="px-6 py-3 font-medium">{{ __('Submitted') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Parent / Child') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Method') }}</th>
                        <th class="px-6 py-3 font-medium text-right">{{ __('Amount') }}</th>
                        <th class="px-6 py-3 font-medium text-center">{{ __('Status') }}</th>
                        <th class="px-6 py-3 font-medium text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($submissions as $s)
                        @php $sc = $statusColors[$s->status] ?? ['bg-gray-100', 'text-gray-600']; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-gray-600">{{ $s->created_at?->format('d M Y, H:i') }}</td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-800">{{ $s->guardian->display_name ?? '—' }}</div>
                                <div class="text-xs text-gray-400">{{ $s->enrollment->student->full_name ?? '—' }} · {{ $s->enrollment->classSection->name ?? '' }}</div>
                                @if($s->studentFee)
                                    <div class="text-xs text-teal-600 mt-0.5">{{ __('For') }}: {{ $s->studentFee->feeCategory->name ?? __('fee') }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-600 capitalize">{{ str_replace('_', ' ', $s->payment_method) }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-gray-800">{{ number_format($s->amount, 0) }} {{ $currency }}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2.5 py-1 rounded-full {{ $sc[0] }} {{ $sc[1] }} text-xs font-medium capitalize">{{ $s->status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.parent-payments.show', $s) }}" class="text-sm text-teal-600 font-medium hover:text-teal-700">{{ __('Review') }} →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                <p class="text-sm">{{ __('No submissions in this view.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($submissions->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">{{ $submissions->links() }}</div>
        @endif
    </div>
</div>
@endsection
