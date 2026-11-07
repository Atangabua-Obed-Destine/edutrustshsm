@extends('layouts.admin')

@section('title', __('Adjust a Fee'))
@section('breadcrumb', __('Fees > Assignment History > Adjust'))

@php
    $student = $fee->enrollment?->student;
    $money = fn ($v) => number_format((float) $v, 0, '.', ' ');
    $input = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none';
    $button = 'px-4 py-2 rounded-lg text-sm font-medium text-white';
    $blocked = fn (array $reasons) => implode(' ', $reasons);
@endphp

@section('content')
<div class="max-w-5xl space-y-5">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-gray-800">{{ $fee->feeCategory?->name }} — {{ $student?->full_name }}</h2>
            <p class="text-sm text-gray-500 mt-0.5">
                {{ $student?->student_id }} · {{ $fee->enrollment?->classSection?->name }} · {{ $fee->enrollment?->academicSession?->name }}{{ $fee->enrollment?->term ? ' · '.$fee->enrollment->term->name : '' }}
            </p>
        </div>
        <div class="flex gap-2">
            @if($student)
                <a href="{{ route('admin.student-fees', $student) }}" class="px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('All fees of this pupil') }}</a>
            @endif
            <a href="{{ route('admin.assignment-history.index', ['session_id' => $fee->enrollment?->academic_session_id]) }}" class="px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">{{ __('Back') }}</a>
        </div>
    </div>

    @if($twins->isNotEmpty())
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold mb-1">{{ __('This pupil was billed :fee more than once for :year.', ['fee' => $fee->feeCategory?->name, 'year' => $fee->enrollment?->academicSession?->name]) }}</p>
            <ul class="list-disc ml-5 space-y-0.5">
                @foreach($twins as $twin)
                    <li>
                        <a href="{{ route('admin.assignment-history.show', $twin) }}" class="underline font-medium">{{ $money($twin->net_amount) }} FCFA{{ $twin->enrollment?->term ? ' — '.$twin->enrollment->term->name : '' }}</a>,
                        {{ __('paid :paid, owes :owed', ['paid' => $money($twin->paid_amount), 'owed' => $money($twin->balance)]) }}
                    </li>
                @endforeach
            </ul>
            <p class="mt-2">{{ __('Keep one. On the other, move any money paid to the fee you keep (or to the pupil\'s credit if that one is already paid), then remove it.') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([
            [__('Billed'), $fee->original_amount, 'text-gray-800'],
            [__('Discount'), (float) $fee->discount_amount + (float) $fee->waiver_amount, 'text-amber-600'],
            [__('Paid'), $fee->paid_amount, 'text-green-700'],
            [__('Still owed'), $fee->balance, (float) $fee->balance > 0 ? 'text-red-600' : 'text-green-700'],
        ] as [$label, $value, $tone])
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-xs text-gray-500">{{ $label }}</p>
                <p class="text-lg font-bold {{ $tone }} font-mono">{{ $money($value) }}</p>
            </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-500 -mt-2">
        {{ __('Due date') }}: {{ $fee->due_date?->format('d/m/Y') ?? '—' }}
        @if((float) $fee->fine_amount > 0) · {{ __('Late fine') }}: {{ $money($fee->fine_amount) }} @endif
        @if($fee->paymentPlan && $fee->paymentPlan->status === 'active') · <span class="text-amber-700 font-medium">{{ __('On a payment plan') }}</span> @endif
    </p>

    <div class="grid md:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-700 mb-3">{{ __('Receipts on this fee') }}</h3>
            @forelse($fee->allocations->sortBy('id') as $allocation)
                @php $p = $allocation->payment; @endphp
                <div class="flex justify-between gap-3 py-1.5 border-b border-gray-100 last:border-0 text-sm {{ $p?->verification_status === 'reversed' ? 'line-through text-gray-400' : '' }}">
                    <span>
                        @if($p)
                            <a href="{{ route('admin.collect-fees.receipt', $p) }}" target="_blank" class="font-medium text-blue-700 hover:underline">{{ $p->receipt_number }}</a>
                            <span class="text-gray-500">· {{ $p->payment_date?->format('d/m/Y') }} · {{ \App\Models\Payment::methodLabel($p->payment_method) }}</span>
                        @endif
                    </span>
                    <span class="font-mono">{{ $money($allocation->amount) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">{{ __('Nothing has been paid on this fee.') }}</p>
            @endforelse
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-700 mb-3">{{ __('History') }}</h3>
            <p class="text-xs text-gray-500 mb-2">{{ __('Billed on :date', ['date' => $fee->created_at?->format('d/m/Y')]) }}</p>
            @if($fee->notes)
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $fee->notes }}</p>
            @else
                <p class="text-sm text-gray-400">{{ __('No changes recorded.') }}</p>
            @endif
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-5">
        @can('assignment-history.edit')
        <form method="POST" action="{{ route('admin.assignment-history.amount', $fee) }}" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            @csrf @method('PUT')
            <h3 class="font-semibold text-gray-700">{{ __('Correct the amount') }}</h3>
            <p class="text-xs text-gray-500">
                {{ __('A fee can always be raised. It can be lowered only while nothing has been paid on it; after that, a reduction is a discount.') }}
            </p>
            @if($blockers['lower'] && ! $blockers['raise'])
                <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2">{{ $blocked($blockers['lower']) }}</p>
            @endif
            @if($blockers['raise'])
                <p class="text-xs text-red-700 bg-red-50 rounded-lg px-3 py-2">{{ $blocked($blockers['raise']) }}</p>
            @else
                <input type="number" name="amount" min="1" step="1" required value="{{ old('amount', (int) $fee->original_amount) }}" class="{{ $input }}" aria-label="{{ __('New amount') }}">
                <input type="text" name="reason" required minlength="3" maxlength="500" value="{{ old('reason') }}" placeholder="{{ __('Reason (kept on the fee)') }}" class="{{ $input }}">
                <button class="{{ $button }} bg-[#1e293b] hover:bg-[#334155]">{{ __('Save the amount') }}</button>
            @endif
        </form>

        <form method="POST" action="{{ route('admin.assignment-history.due-date', $fee) }}" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            @csrf @method('PUT')
            <h3 class="font-semibold text-gray-700">{{ __('Change the due date') }}</h3>
            <p class="text-xs text-gray-500">{{ __('Late fines are counted from the due date.') }}</p>
            @if($blockers['due'])
                <p class="text-xs text-red-700 bg-red-50 rounded-lg px-3 py-2">{{ $blocked($blockers['due']) }}</p>
            @else
                <input type="date" name="due_date" value="{{ old('due_date', $fee->due_date?->toDateString()) }}" class="{{ $input }}" aria-label="{{ __('Due date') }}">
                <input type="text" name="reason" required minlength="3" maxlength="500" placeholder="{{ __('Reason (kept on the fee)') }}" class="{{ $input }}">
                <button class="{{ $button }} bg-[#1e293b] hover:bg-[#334155]">{{ __('Save the due date') }}</button>
            @endif
        </form>
        @endcan

        @can('assignment-history.transfer')
        <form method="POST" action="{{ route('admin.assignment-history.transfer', $fee) }}" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            @csrf
            <h3 class="font-semibold text-gray-700">{{ __('Move money paid on this fee') }}</h3>
            <p class="text-xs text-gray-500">{{ __('For money taken on the wrong fee, or on a fee billed by mistake. The receipts stay as they are; the money now pays the other fee, and the books follow.') }}</p>
            @if((float) $fee->paid_amount <= 0)
                <p class="text-xs text-gray-400">{{ __('Nothing has been paid on this fee.') }}</p>
            @else
                <select name="target" required class="{{ $input }}" style="appearance:auto;-webkit-appearance:menulist;" aria-label="{{ __('Move to') }}">
                    @foreach($targets as $t)
                        <option value="{{ $t->id }}">{{ $t->feeCategory?->name }} — {{ $t->enrollment?->academicSession?->name }}{{ $t->enrollment?->term ? ', '.$t->enrollment->term->name : '' }} ({{ __('owes :owed', ['owed' => $money($t->balance)]) }})</option>
                    @endforeach
                    <option value="credit">{{ __('The pupil\'s credit (an advance for later fees, or a refund)') }}</option>
                </select>
                <input type="number" name="amount" min="1" step="1" max="{{ (int) $fee->paid_amount }}" required value="{{ old('amount', (int) $fee->paid_amount) }}" class="{{ $input }}" aria-label="{{ __('Amount to move') }}">
                <input type="text" name="reason" required minlength="3" maxlength="500" placeholder="{{ __('Reason (kept on both fees)') }}" class="{{ $input }}">
                <button class="{{ $button }} bg-blue-700 hover:bg-blue-800">{{ __('Move the money') }}</button>
            @endif
        </form>
        @endcan

        @can('assignment-history.delete')
        <form method="POST" action="{{ route('admin.assignment-history.destroy', $fee) }}" class="bg-white rounded-xl border border-red-200 p-5 space-y-3"
              onsubmit="return confirm(@js(__('Remove this fee from the pupil? It disappears from every statement.')))">
            @csrf @method('DELETE')
            <h3 class="font-semibold text-red-700">{{ __('Remove this fee') }}</h3>
            <p class="text-xs text-gray-500">{{ __('Only for a fee billed by mistake. A fee with money on it cannot be removed.') }}</p>
            @if($blockers['remove'])
                <p class="text-xs text-red-700 bg-red-50 rounded-lg px-3 py-2">{{ $blocked($blockers['remove']) }}</p>
            @else
                <input type="text" name="reason" required minlength="3" maxlength="500" placeholder="{{ __('Reason (kept in the audit trail)') }}" class="{{ $input }}">
                <button class="{{ $button }} bg-red-600 hover:bg-red-700">{{ __('Remove the fee') }}</button>
            @endif
        </form>
        @endcan
    </div>
</div>
@endsection
