@extends('layouts.admin')

@section('title', __('Unlinked Transactions'))
@section('breadcrumb', __('Payment Accounts > Reports > Unlinked'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <div class="flex items-center gap-3">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Unlinked Transactions Report') }}</h3>
        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold bg-red-500 text-white">{{ $records->total() }} {{ __('Unlinked') }}</span>
    </div>
    <p class="text-sm text-gray-500">{{ __('View and link transactions to payment accounts') }}</p>

    <div class="bg-blue-50 text-blue-800 text-sm px-4 py-3 rounded-lg">
        {{ __('Showing :from to :to of :total Unlinked Transactions', ['from' => $records->firstItem() ?? 0, 'to' => $records->lastItem() ?? 0, 'total' => $records->total()]) }}
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Description') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Amount') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Method') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($records as $i => $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $records->firstItem() + $i }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ \Illuminate\Support\Carbon::parse($r['date'])->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $r['direction'] === 'credit' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ str_replace('_', ' ', $r['record_type']) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ $r['title'] }}</td>
                    <td class="px-4 py-3 text-sm text-right font-medium {{ $r['direction'] === 'credit' ? 'text-green-700' : 'text-red-700' }}">{{ number_format($r['amount'], 2) }} {{ $currency }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $r['method'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('admin.payment-account-report.link') }}" class="flex items-center gap-2 justify-end">
                            @csrf
                            <input type="hidden" name="record_type" value="{{ $r['record_type'] }}">
                            <input type="hidden" name="record_id" value="{{ $r['record_id'] }}">
                            <select name="payment_account_id" required style="appearance:auto;-webkit-appearance:menulist;" class="px-2 py-1 border border-gray-300 rounded text-sm">
                                <option value="">{{ __('Account…') }}</option>
                                @foreach($accounts as $a)
                                    <option value="{{ $a->id }}">{{ $a->title }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-medium whitespace-nowrap">{{ __('Link Account') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No unlinked transactions. All clear!') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $records->links() }}</div>
</div>
@endsection
