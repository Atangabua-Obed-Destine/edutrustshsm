@extends('layouts.admin')
@section('title', __('Fee Discounts & Waivers'))

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ __('Discounts & Waivers') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Manage scholarships, staff discounts, and financial aid') }}</p>
        </div>
        <a href="{{ route('admin.fee-discounts.create') }}" class="px-4 py-2.5 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] text-sm font-medium">{{ __('+ Apply Discount') }}</a>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Total Discounts') }}</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-white rounded-xl shadow-sm border border-purple-100 p-4">
            <p class="text-xs text-purple-600 uppercase tracking-wider">{{ __('Scholarships') }}</p>
            <p class="text-2xl font-bold text-purple-700 mt-1">{{ $stats['scholarship'] }}</p>
        </div>
        <div class="bg-gradient-to-br from-blue-50 to-white rounded-xl shadow-sm border border-blue-100 p-4">
            <p class="text-xs text-blue-600 uppercase tracking-wider">{{ __('Staff Children') }}</p>
            <p class="text-2xl font-bold text-blue-700 mt-1">{{ $stats['staff_child'] }}</p>
        </div>
        <div class="bg-gradient-to-br from-amber-50 to-white rounded-xl shadow-sm border border-amber-100 p-4">
            <p class="text-xs text-amber-600 uppercase tracking-wider">{{ __('Financial Hardship') }}</p>
            <p class="text-2xl font-bold text-amber-700 mt-1">{{ $stats['hardship'] }}</p>
        </div>
    </div>

    {{-- Filter --}}
    <form class="flex items-center gap-3 bg-white rounded-lg shadow-sm border px-4 py-3">
        <select name="reason" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
            <option value="">{{ __('All Reasons') }}</option>
            @foreach(['scholarship' => 'Scholarship', 'staff_child' => 'Staff Child', 'sibling' => 'Sibling', 'financial_hardship' => 'Financial Hardship', 'merit' => 'Merit', 'other' => 'Other'] as $val => $label)
                <option value="{{ $val }}" {{ request('reason') == $val ? 'selected' : '' }}>{{ __($label) }}</option>
            @endforeach
        </select>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Student') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Class') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Reason') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Type') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Value') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Applies To') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Approved By') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($discounts as $d)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium text-gray-900">
                        {{ $d->enrollment->student->full_name ?? __('N/A') }}
                        <div class="text-xs text-gray-400">{{ $d->enrollment->student->student_id ?? '' }}</div>
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ $d->enrollment->classSection->name ?? __('N/A') }}</td>
                    <td class="px-6 py-4">
                        @php $reasonColors = ['scholarship' => 'purple', 'staff_child' => 'blue', 'sibling' => 'cyan', 'financial_hardship' => 'amber', 'merit' => 'emerald', 'other' => 'gray']; $c = $reasonColors[$d->reason] ?? 'gray'; @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $c }}-100 text-{{ $c }}-800">
                            {{ ucfirst(str_replace('_', ' ', $d->reason)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $d->discount_type }}</span>
                    </td>
                    <td class="px-6 py-4 text-right font-semibold">
                        {{ $d->discount_type === 'percentage' ? number_format($d->value, 1) . '%' : number_format($d->value) . ' XAF' }}
                    </td>
                    <td class="px-6 py-4 text-gray-600 text-xs">
                        {{ $d->apply_to === 'all_fees' ? __('All fees') : ($d->feeCategory->name ?? __('Specific')) }}
                    </td>
                    <td class="px-6 py-4 text-gray-500 text-xs">{{ $d->approvedBy->full_name ?? __('System') }}</td>
                    <td class="px-6 py-4 text-right">
                        <form action="{{ route('admin.fee-discounts.destroy', $d) }}" method="POST" onsubmit="return confirm('{{ __('Remove this discount? Student fees will be recalculated.') }}')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Remove') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-6 py-12 text-center text-gray-400">{{ __('No discounts applied yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>

        @if($discounts->hasPages())
        <div class="px-6 py-3 border-t">{{ $discounts->links() }}</div>
        @endif
    </div>
</div>
@endsection
