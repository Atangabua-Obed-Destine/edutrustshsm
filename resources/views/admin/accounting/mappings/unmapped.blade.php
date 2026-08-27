@extends('layouts.admin')

@section('title', __('Unmapped Transactions'))
@section('breadcrumb', __('Accounting > Transaction Mappings > Unmapped'))

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Unmapped Transactions') }} <span class="text-sm text-red-500">({{ $transactions->count() }})</span></h3>
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.account-mappings.index') }}" class="text-sm text-blue-600 hover:underline">{{ __('← Mapping Settings') }}</a>
            @if($transactions->isNotEmpty())
            <form method="POST" action="{{ route('admin.account-mappings.post-unmapped') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 text-sm font-medium transition">
                    {{ __('Post to Ledger') }}
                </button>
            </form>
            @endif
        </div>
    </div>
    <p class="text-sm text-gray-500">{{ __('Records not yet posted to the ledger. Configure a mapping for their category, then use "Post to Ledger" to post them all.') }}</p>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Reference') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Amount') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Date') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($transactions as $t)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700">{{ ucfirst(str_replace('_',' ',$t->type)) }}</span></td>
                    <td class="px-4 py-3 text-sm text-gray-800">{{ $t->label }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($t->amount, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $t->date ? \Illuminate\Support\Carbon::parse($t->date)->format('d/m/Y') : '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">{{ __('All transactions are mapped and posted.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
