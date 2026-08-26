@extends('layouts.admin')
@section('title', __('Fee Reports & Analytics'))

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ __('Fee Reports & Analytics') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Financial overview and collection tracking') }}</p>
        </div>
        <form class="flex items-center gap-2">
            <select name="session_id" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm shadow-sm">
                @foreach($sessions as $s)
                    <option value="{{ $s->id }}" {{ $sessionId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Overview Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs text-gray-500 uppercase tracking-wider font-medium">{{ __('Expected') }}</p>
                <span class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-gray-800 mt-2">{{ number_format($totalExpected) }}</p>
            <p class="text-xs text-gray-400">XAF</p>
        </div>
        <div class="bg-gradient-to-br from-emerald-50 to-white rounded-xl shadow-sm border border-emerald-100 p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs text-emerald-600 uppercase tracking-wider font-medium">{{ __('Collected') }}</p>
                <span class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-emerald-700 mt-2">{{ number_format($totalCollected) }}</p>
            <p class="text-xs text-emerald-400">XAF</p>
        </div>
        <div class="bg-gradient-to-br from-red-50 to-white rounded-xl shadow-sm border border-red-100 p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs text-red-600 uppercase tracking-wider font-medium">{{ __('Outstanding') }}</p>
                <span class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-red-700 mt-2">{{ number_format($totalOutstanding) }}</p>
            <p class="text-xs text-red-400">XAF</p>
        </div>
        <div class="bg-gradient-to-br from-amber-50 to-white rounded-xl shadow-sm border border-amber-100 p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs text-amber-600 uppercase tracking-wider font-medium">{{ __('Discounts') }}</p>
                <span class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-amber-700 mt-2">{{ number_format($totalDiscounts) }}</p>
            <p class="text-xs text-amber-400">XAF</p>
        </div>
        <div class="bg-gradient-to-br from-indigo-50 to-white rounded-xl shadow-sm border border-indigo-100 p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs text-indigo-600 uppercase tracking-wider font-medium">{{ __('Collection Rate') }}</p>
                <span class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </span>
            </div>
            <p class="text-xl font-bold text-indigo-700 mt-2">{{ $collectionRate }}%</p>
            <div class="w-full bg-indigo-100 rounded-full h-1.5 mt-2">
                <div class="bg-indigo-600 h-1.5 rounded-full" style="width: {{ min($collectionRate, 100) }}%"></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- By Category --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('Collection by Category') }}</h3>
            </div>
            <div class="p-6 space-y-4">
                @forelse($byCategory as $cat)
                @php $rate = $cat->expected > 0 ? round(($cat->collected / $cat->expected) * 100) : 0; @endphp
                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="font-medium text-gray-700">{{ $cat->feeCategory->name ?? __('Unknown') }}</span>
                        <span class="text-gray-500">{{ number_format($cat->collected) }} / {{ number_format($cat->expected) }} XAF</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5">
                        <div class="h-2.5 rounded-full {{ $rate >= 80 ? 'bg-emerald-500' : ($rate >= 50 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $rate }}%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">{{ $cat->student_count }} {{ __('students') }} &middot; {{ $rate }}% {{ __('collected') }}</p>
                </div>
                @empty
                <p class="text-gray-400 text-sm text-center py-4">{{ __('No fee data available.') }}</p>
                @endforelse
            </div>
        </div>

        {{-- By Payment Method --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('Payment Methods') }}</h3>
            </div>
            <div class="p-6">
                @php $methods = ['cash' => ['Cash', 'emerald'], 'bank_transfer' => ['Bank Transfer', 'blue'], 'mtn_momo' => ['MTN MoMo', 'yellow'], 'orange_money' => ['Orange Money', 'orange']]; @endphp
                @forelse($byMethod as $m)
                @php $info = $methods[$m->payment_method] ?? [ucfirst($m->payment_method), 'gray']; @endphp
                <div class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 rounded-full bg-{{ $info[1] }}-500"></div>
                        <span class="text-sm font-medium text-gray-700">{{ __($info[0]) }}</span>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-800">{{ number_format($m->total) }} XAF</p>
                        <p class="text-xs text-gray-400">{{ $m->count }} {{ __('transactions') }}</p>
                    </div>
                </div>
                @empty
                <p class="text-gray-400 text-sm text-center py-4">{{ __('No payments recorded.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Collection by Class --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('Collection by Class') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">{{ __('Class') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('Students') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Expected') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Collected') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Outstanding') }}</th>
                        <th class="px-6 py-3 text-center">{{ __('Rate') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($classStats as $className => $stat)
                    @php $rate = $stat['expected'] > 0 ? round(($stat['collected'] / $stat['expected']) * 100) : 0; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900">{{ $className }}</td>
                        <td class="px-6 py-3 text-center text-gray-600">{{ $stat['students'] }}</td>
                        <td class="px-6 py-3 text-right text-gray-600">{{ number_format($stat['expected']) }}</td>
                        <td class="px-6 py-3 text-right font-semibold text-emerald-600">{{ number_format($stat['collected']) }}</td>
                        <td class="px-6 py-3 text-right text-red-600">{{ number_format($stat['outstanding']) }}</td>
                        <td class="px-6 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full {{ $rate >= 80 ? 'bg-emerald-500' : ($rate >= 50 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $rate }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ $rate }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400">{{ __('No class data available.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Monthly Trend --}}
    @if($monthlyTrend->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('Monthly Collection Trend') }}</h3>
        </div>
        <div class="p-6">
            @php $maxMonth = $monthlyTrend->max('total') ?: 1; @endphp
            <div class="flex items-end gap-2 h-40">
                @foreach($monthlyTrend as $m)
                @php $height = ($m->total / $maxMonth) * 100; @endphp
                <div class="flex-1 flex flex-col items-center gap-1">
                    <span class="text-xs text-gray-500 font-medium">{{ number_format($m->total / 1000) }}K</span>
                    <div class="w-full bg-blue-500 rounded-t" style="height: {{ $height }}%"></div>
                    <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($m->month . '-01')->format('M') }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Top Defaulters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('Top Fee Defaulters') }}</h3>
            <span class="text-xs text-gray-400">{{ __('Showing top 50') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">#</th>
                        <th class="px-6 py-3 text-left">{{ __('Student') }}</th>
                        <th class="px-6 py-3 text-left">{{ __('Class') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Total Fees') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Paid') }}</th>
                        <th class="px-6 py-3 text-right">{{ __('Balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($defaulters as $i => $d)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-gray-400">{{ $i + 1 }}</td>
                        <td class="px-6 py-3 font-medium text-gray-900">
                            {{ $d['student']->full_name }}
                            <div class="text-xs text-gray-400">{{ $d['student']->student_id }}</div>
                        </td>
                        <td class="px-6 py-3 text-gray-600">{{ $d['class'] }}</td>
                        <td class="px-6 py-3 text-right text-gray-600">{{ number_format($d['total_fees']) }}</td>
                        <td class="px-6 py-3 text-right text-emerald-600">{{ number_format($d['total_paid']) }}</td>
                        <td class="px-6 py-3 text-right font-bold text-red-600">{{ number_format($d['total_balance']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400">{{ __('No defaulters found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
