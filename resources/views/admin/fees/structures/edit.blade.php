@extends('layouts.admin')

@section('title', __('Edit Fee Amount'))
@section('breadcrumb', __('Fees > Fee Structures > Edit'))

@section('content')
<div style="max-width: 640px;">
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 24px;">
        <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 8px;">{{ __('Edit Fee Amount') }}</h3>
        <p style="font-size: 0.825rem; color: #64748b; margin-bottom: 20px;">
            <strong style="color: #334155;">{{ $feeStructure->feeCategory->name }}</strong> &mdash;
            {{ $feeStructure->form->name }}{{ $feeStructure->stream ? ' / ' . $feeStructure->stream->name : '' }}
            @if($feeStructure->academicSession)
                &mdash; {{ $feeStructure->academicSession->name }}
            @endif
        </p>

        <form method="POST" action="{{ route('admin.fee-structures.update', $feeStructure) }}">
            @csrf @method('PUT')

            {{-- Amount --}}
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Amount (XAF)') }} <span style="color: #ef4444;">*</span></label>
                <input type="number" name="amount" id="feeAmountInput"
                       value="{{ old('amount', $feeStructure->amount) }}" min="0" step="100" required
                       style="width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.95rem; font-family: monospace; text-align: right; outline: none;"
                       onfocus="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.1)'"
                       onblur="this.style.borderColor='#d1d5db'; this.style.boxShadow='none'"
                       oninput="updateBreakdownCheck()">
                @error('amount') <p style="font-size: 0.75rem; color: #dc2626; margin-top: 4px;">{{ $message }}</p> @enderror
            </div>

            {{-- Fee Breakdown --}}
            <div style="margin-bottom: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                    <label style="font-size: 0.8rem; font-weight: 600; color: #374151;">{{ __('Fee Breakdown') }}</label>
                    <button type="button" id="addBreakdownBtn"
                            style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; font-size: 0.75rem; font-weight: 600; color: #0ea5e9; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; cursor: pointer; transition: all .15s;"
                            onmouseover="this.style.background='#e0f2fe'" onmouseout="this.style.background='#f0f9ff'">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        {{ __('Add Item') }}
                    </button>
                </div>

                <div id="breakdownRows" style="display: flex; flex-direction: column; gap: 8px;">
                    @foreach($feeStructure->breakdowns as $i => $bd)
                    <div style="display: grid; grid-template-columns: 1fr 140px 36px; gap: 8px; align-items: center;">
                        <input type="text" name="breakdowns[{{ $i }}][name]" value="{{ old("breakdowns.{$i}.name", $bd->name) }}"
                               placeholder="{{ __('Item name') }}"
                               style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; outline: none;"
                               onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#d1d5db'">
                        <input type="number" name="breakdowns[{{ $i }}][amount]" value="{{ old("breakdowns.{$i}.amount", $bd->amount) }}"
                               min="0" step="100" placeholder="0"
                               class="bd-amount"
                               style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; font-family: monospace; text-align: right; outline: none;"
                               onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#d1d5db'"
                               oninput="updateBreakdownCheck()">
                        <button type="button" onclick="this.parentElement.remove(); reindexRows(); updateBreakdownCheck();"
                                style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 1px solid #fecaca; background: #fef2f2; border-radius: 6px; color: #dc2626; cursor: pointer;"
                                onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    @endforeach
                </div>

                <div id="breakdownSummary" style="{{ $feeStructure->breakdowns->count() ? '' : 'display:none;' }} margin-top: 10px; padding: 8px 12px; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="font-size: 0.78rem; color: #64748b;">{{ __('Breakdown Total:') }}</span>
                        <span id="bdTotal" style="font-family: monospace; font-weight: 600; color: #334155; font-size: 0.85rem;">0 XAF</span>
                    </div>
                    <div id="bdMismatch" style="display: none; margin-top: 4px; font-size: 0.75rem; color: #dc2626;">
                        {{ __('Breakdown total does not match the fee amount.') }}
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
                <a href="{{ route('admin.fee-structures.index') }}"
                   style="padding: 10px 18px; font-size: 0.85rem; color: #475569; text-decoration: none;"
                   onmouseover="this.style.color='#1e293b'" onmouseout="this.style.color='#475569'">{{ __('Cancel') }}</a>
                <button type="submit"
                        style="padding: 10px 24px; background: #1e293b; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all .15s;"
                        onmouseover="this.style.backgroundColor='#334155'" onmouseout="this.style.backgroundColor='#1e293b'">
                    {{ __('Update Amount') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rowsDiv = document.getElementById('breakdownRows');
    const addBtn = document.getElementById('addBreakdownBtn');

    addBtn.addEventListener('click', function() {
        const idx = rowsDiv.children.length;
        const row = document.createElement('div');
        row.style.cssText = 'display: grid; grid-template-columns: 1fr 140px 36px; gap: 8px; align-items: center;';
        row.innerHTML = `
            <input type="text" name="breakdowns[${idx}][name]" placeholder="{{ __('Item name') }}"
                   style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; outline: none;"
                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#d1d5db'">
            <input type="number" name="breakdowns[${idx}][amount]" min="0" step="100" placeholder="0"
                   class="bd-amount"
                   style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; font-family: monospace; text-align: right; outline: none;"
                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#d1d5db'"
                   oninput="updateBreakdownCheck()">
            <button type="button" onclick="this.parentElement.remove(); reindexRows(); updateBreakdownCheck();"
                    style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border: 1px solid #fecaca; background: #fef2f2; border-radius: 6px; color: #dc2626; cursor: pointer;"
                    onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        `;
        rowsDiv.appendChild(row);
        row.querySelector('input[type="text"]').focus();
        updateBreakdownCheck();
    });

    window.reindexRows = function() {
        Array.from(rowsDiv.children).forEach((row, i) => {
            const nameInput = row.querySelector('input[type="text"]');
            const amountInput = row.querySelector('input[type="number"]');
            if (nameInput) nameInput.name = `breakdowns[${i}][name]`;
            if (amountInput) amountInput.name = `breakdowns[${i}][amount]`;
        });
    };

    window.updateBreakdownCheck = function() {
        const amounts = document.querySelectorAll('.bd-amount');
        const summary = document.getElementById('breakdownSummary');
        const totalSpan = document.getElementById('bdTotal');
        const mismatch = document.getElementById('bdMismatch');
        const feeAmount = parseFloat(document.getElementById('feeAmountInput').value) || 0;

        if (amounts.length === 0) { summary.style.display = 'none'; return; }

        let total = 0;
        amounts.forEach(a => total += parseFloat(a.value) || 0);
        summary.style.display = 'block';
        totalSpan.textContent = Math.round(total).toLocaleString('en-US') + ' XAF';

        if (feeAmount > 0 && total > 0 && Math.abs(total - feeAmount) > 0.01) {
            mismatch.style.display = 'block';
            totalSpan.style.color = '#dc2626';
        } else {
            mismatch.style.display = 'none';
            totalSpan.style.color = '#334155';
        }
    };

    updateBreakdownCheck();
});
</script>
@endpush
