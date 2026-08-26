@extends('parent.layouts.app')
@section('title', __('Submit Payment'))
@section('heading', __('Submit a Payment'))

@section('content')
@php $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA'); @endphp

<div style="max-width: 640px;">
    <div class="pp-card" style="padding: 14px 18px; margin-bottom: 18px; background:#f0fdfa; border:1px solid #ccfbf1;">
        <div style="display:flex; gap:10px;">
            <svg width="18" height="18" style="color:#0d9488; flex-shrink:0; margin-top:1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p style="font-size:0.8rem; color:#115e59; line-height:1.5;">
                {{ __('Make your payment via bank deposit or mobile money, then upload the receipt here. The school will verify it and update your child’s fee balance — usually within 24 hours.') }}
            </p>
        </div>
    </div>

    @if($errors->any())
    <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:9px; padding:12px 14px; margin-bottom:18px;">
        @foreach($errors->all() as $error)
        <div style="font-size:0.8rem; color:#dc2626;">• {{ $error }}</div>
        @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('parent.payments.store') }}" enctype="multipart/form-data" class="pp-card" style="padding: 24px;"
          x-data="{ student: '{{ $selectedStudent->id ?? '' }}' }">
        @csrf

        {{-- Carry the targeted fee, if any --}}
        <input type="hidden" name="fee_id" value="{{ $selectedFee->id ?? '' }}">

        {{-- Targeted fee banner --}}
        @if($selectedFee)
        <div style="margin-bottom:16px; background:#f0fdfa; border:1px solid #ccfbf1; border-radius:9px; padding:13px 15px;">
            <div style="font-size:0.7rem; color:#0d9488; text-transform:uppercase; letter-spacing:0.04em;">{{ __('Paying for') }}</div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:3px;">
                <span style="font-size:0.9rem; font-weight:700; color:#0f172a;">{{ $selectedFee->feeCategory->name ?? __('Fee') }}</span>
                <span style="font-size:0.85rem; font-weight:700; color:#dc2626;">{{ number_format($selectedFee->balance, 0) }} {{ $currency }} {{ __('due') }}</span>
            </div>
        </div>
        @endif

        {{-- Child --}}
        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:0.78rem; font-weight:600; color:#374151; margin-bottom:6px;">{{ __('Which child?') }} <span style="color:#ef4444;">*</span></label>
            <select name="student_id" required x-model="student"
                    style="width:100%; padding:11px 13px; border:1px solid #d1d5db; border-radius:8px; font-size:0.88rem; background:#fff;"
                    {{ $selectedFee ? 'readonly onmousedown=return(false) onkeydown=return(false)' : '' }}>
                <option value="">{{ __('Select a child...') }}</option>
                @foreach($children as $child)
                    <option value="{{ $child->id }}" {{ (string)($selectedStudent->id ?? '') === (string)$child->id ? 'selected' : '' }}>
                        {{ $child->first_name }} {{ $child->last_name }} ({{ $child->student_id }})
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Outstanding fees hint (only when not targeting a specific fee) --}}
        @if(!$selectedFee && $selectedStudent && $fees->isNotEmpty())
        <div style="margin-bottom:16px; background:#f8fafc; border-radius:8px; padding:12px 14px;">
            <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase; margin-bottom:6px;">{{ __('Outstanding for') }} {{ $selectedStudent->first_name }}</div>
            @foreach($fees as $fee)
                <div style="display:flex; justify-content:space-between; font-size:0.8rem; padding:3px 0;">
                    <span style="color:#475569;">{{ $fee->feeCategory->name ?? '—' }}</span>
                    <span style="color:#dc2626; font-weight:600;">{{ number_format($fee->balance, 0) }} {{ $currency }}</span>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Amount (pre-filled with the targeted fee's balance) --}}
        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:0.78rem; font-weight:600; color:#374151; margin-bottom:6px;">{{ __('Amount Paid') }} ({{ $currency }}) <span style="color:#ef4444;">*</span></label>
            <input type="number" name="amount" value="{{ old('amount', $selectedFee ? (int) $selectedFee->balance : '') }}" min="1" step="any" required
                   style="width:100%; padding:11px 13px; border:1px solid #d1d5db; border-radius:8px; font-size:0.88rem;">
            @if($selectedFee)
            <p style="font-size:0.7rem; color:#94a3b8; margin-top:4px;">{{ __('You can pay the full balance or a part of it.') }}</p>
            @endif
        </div>

        {{-- Method --}}
        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:0.78rem; font-weight:600; color:#374151; margin-bottom:6px;">{{ __('Payment Method') }} <span style="color:#ef4444;">*</span></label>
            <select name="payment_method" required
                    style="width:100%; padding:11px 13px; border:1px solid #d1d5db; border-radius:8px; font-size:0.88rem; background:#fff;">
                <option value="mtn_momo" {{ old('payment_method')==='mtn_momo'?'selected':'' }}>{{ __('MTN Mobile Money') }}</option>
                <option value="orange_money" {{ old('payment_method')==='orange_money'?'selected':'' }}>{{ __('Orange Money') }}</option>
                <option value="bank_transfer" {{ old('payment_method')==='bank_transfer'?'selected':'' }}>{{ __('Bank Transfer / Deposit') }}</option>
                <option value="cash" {{ old('payment_method')==='cash'?'selected':'' }}>{{ __('Cash') }}</option>
            </select>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:16px;">
            <div>
                <label style="display:block; font-size:0.78rem; font-weight:600; color:#374151; margin-bottom:6px;">{{ __('Payment Date') }} <span style="color:#ef4444;">*</span></label>
                <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required
                       style="width:100%; padding:11px 13px; border:1px solid #d1d5db; border-radius:8px; font-size:0.88rem;">
            </div>
            <div>
                <label style="display:block; font-size:0.78rem; font-weight:600; color:#374151; margin-bottom:6px;">{{ __('Reference / Txn ID') }}</label>
                <input type="text" name="transaction_ref" value="{{ old('transaction_ref') }}" placeholder="{{ __('Optional') }}"
                       style="width:100%; padding:11px 13px; border:1px solid #d1d5db; border-radius:8px; font-size:0.88rem;">
            </div>
        </div>

        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:0.78rem; font-weight:600; color:#374151; margin-bottom:6px;">{{ __('Bank Name') }}</label>
            <input type="text" name="bank_name" value="{{ old('bank_name') }}" placeholder="{{ __('If bank transfer (optional)') }}"
                   style="width:100%; padding:11px 13px; border:1px solid #d1d5db; border-radius:8px; font-size:0.88rem;">
        </div>

        {{-- Receipt file --}}
        <div style="margin-bottom:16px;">
            <label style="display:block; font-size:0.78rem; font-weight:600; color:#374151; margin-bottom:6px;">{{ __('Upload Receipt') }} <span style="color:#ef4444;">*</span></label>
            <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png" required
                   style="width:100%; padding:9px; border:1px dashed #cbd5e1; border-radius:8px; font-size:0.82rem; background:#f8fafc;">
            <p style="font-size:0.7rem; color:#94a3b8; margin-top:4px;">{{ __('PDF, JPG or PNG. Maximum 5 MB.') }}</p>
        </div>

        <div style="margin-bottom:22px;">
            <label style="display:block; font-size:0.78rem; font-weight:600; color:#374151; margin-bottom:6px;">{{ __('Note to School') }}</label>
            <textarea name="notes" rows="2" placeholder="{{ __('Optional message...') }}"
                      style="width:100%; padding:11px 13px; border:1px solid #d1d5db; border-radius:8px; font-size:0.88rem; resize:vertical;">{{ old('notes') }}</textarea>
        </div>

        <div style="display:flex; gap:10px;">
            <a href="{{ route('parent.payments.index') }}" style="flex:0 0 auto; padding:12px 18px; background:#f1f5f9; color:#475569; border-radius:9px; font-size:0.85rem; font-weight:600;">{{ __('Cancel') }}</a>
            <button type="submit" style="flex:1; padding:12px; background:#14b8a6; color:#fff; border:none; border-radius:9px; font-size:0.88rem; font-weight:600; cursor:pointer;"
                    onmouseover="this.style.background='#0d9488'" onmouseout="this.style.background='#14b8a6'">{{ __('Submit Receipt') }}</button>
        </div>
    </form>
</div>
@endsection
