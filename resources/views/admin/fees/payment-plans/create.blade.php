@extends('layouts.admin')

@section('title', __('Create Payment Plan'))
@section('breadcrumb', __('Fees > Payment Plans > Create'))

@section('content')
<div style="max-width: 900px;">

    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">{{ __('Create Payment Plan') }}</h2>
            <p style="font-size: 0.825rem; color: #64748b; margin-top: 2px;">{{ __('Set up an instalment plan for a student\'s fee.') }}</p>
        </div>
        <a href="{{ route('admin.payment-plans.index') }}"
           style="padding: 9px 18px; background: #f1f5f9; color: #475569; border-radius: 8px; font-size: 0.85rem; font-weight: 500; text-decoration: none;"
           onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
            {{ __('← Back to Plans') }}
        </a>
    </div>

    @if($errors->any())
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem;">
            <ul style="margin: 0; padding-left: 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.payment-plans.store') }}" id="paymentPlanForm">
        @csrf

        {{-- Student & Fee Selection --}}
        <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: #334155; margin-bottom: 16px;">{{ __('Student & Fee Selection') }}</h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                {{-- Student --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Student') }} <span style="color: #dc2626;">*</span></label>
                    <select id="studentSelect"
                            style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">{{ __('-- Select Student --') }}</option>
                        @foreach($students as $s)
                            <option value="{{ $s->id }}">{{ $s->student_id }} — {{ $s->full_name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Fee --}}
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Fee') }} <span style="color: #dc2626;">*</span></label>
                    <select id="feeSelect" name="student_fee_id"
                            style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; appearance: auto; -webkit-appearance: menulist; background: #fff;">
                        <option value="">{{ __('Select a student first') }}</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Fee Details Card (hidden until fee selected) --}}
        <div id="feeDetailsCard" style="display: none; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: #334155; margin-bottom: 16px;">{{ __('Fee Details') }}</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
                <div>
                    <span style="display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 4px;">{{ __('Fee Amount') }}</span>
                    <span id="feeAmount" style="font-size: 1rem; font-weight: 700; color: #1e293b;">0.00</span>
                </div>
                <div>
                    <span style="display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 4px;">{{ __('Discount') }}</span>
                    <span id="feeDiscount" style="font-size: 1rem; font-weight: 700; color: #dc2626;">0.00</span>
                </div>
                <div>
                    <span style="display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 4px;">{{ __('Fine') }}</span>
                    <span id="feeFine" style="font-size: 1rem; font-weight: 700; color: #f59e0b;">0.00</span>
                </div>
                <div>
                    <span style="display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 4px;">{{ __('Total Amount') }}</span>
                    <span id="feeTotalAmount" style="font-size: 1.1rem; font-weight: 700; color: #059669;">0.00</span>
                    <span id="feeAlreadyPaid" style="display: none; font-size: 0.75rem; color: #64748b; margin-left: 6px;"></span>
                </div>
            </div>
        </div>

        {{-- Plan Configuration --}}
        <div id="planConfigCard" style="display: none; background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: #334155; margin-bottom: 16px;">{{ __('Plan Configuration') }}</h3>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Number of Instalments') }} <span style="color: #dc2626;">*</span></label>
                    <input type="number" name="number_of_installments" id="numInstallments" min="2" max="24" value="{{ old('number_of_installments', 3) }}"
                           style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Late Fee Percentage (%)') }}</label>
                    <input type="number" name="late_fee_percentage" id="lateFee" min="0" max="100" step="0.01" value="{{ old('late_fee_percentage', 0) }}"
                           style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Grace Period (Days)') }}</label>
                    <input type="number" name="grace_period_days" id="gracePeriod" min="0" max="365" value="{{ old('grace_period_days', 7) }}"
                           style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff;">
                </div>
            </div>

            <button type="button" id="generateBtn"
                    style="padding: 10px 24px; background: #059669; color: #fff; border-radius: 8px; border: none; font-size: 0.85rem; font-weight: 600; cursor: pointer;"
                    onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#059669'">
                {{ __('Generate Schedule') }}
            </button>
        </div>

        <input type="hidden" name="total_amount" id="totalAmountInput" value="0">

        {{-- Installment Schedule (hidden until generated) --}}
        <div id="scheduleCard" style="display: none; background: #ffffff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 20px;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: #334155; margin-bottom: 16px;">{{ __('Instalment Schedule') }}</h3>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 10px 14px; text-align: center; font-weight: 600; color: #475569; width: 100px;">{{ __('Instalment #') }}</th>
                            <th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #475569;">{{ __('Amount') }}</th>
                            <th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #475569;">{{ __('Due Date') }}</th>
                        </tr>
                    </thead>
                    <tbody id="installmentBody">
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid #e2e8f0; background: #f8fafc;">
                            <td style="padding: 12px 14px; font-weight: 700; color: #334155; text-align: center;">{{ __('Total') }}</td>
                            <td style="padding: 12px 14px; font-weight: 700; color: #1e293b;" id="installmentTotalCell">0.00</td>
                            <td style="padding: 12px 14px;" id="validationCell"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Notes --}}
            <div style="margin-top: 20px;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Notes') }}</label>
                <textarea name="notes" rows="3" placeholder="{{ __('Optional notes about this payment plan...') }}"
                          style="width: 100%; padding: 9px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.85rem; outline: none; background: #fff; resize: vertical;">{{ old('notes') }}</textarea>
            </div>

            {{-- Buttons --}}
            <div style="display: flex; align-items: center; gap: 12px; margin-top: 20px;">
                <button type="submit" id="submitBtn"
                        style="padding: 10px 28px; background: #1e293b; color: #fff; border-radius: 8px; border: none; font-size: 0.85rem; font-weight: 600; cursor: pointer;"
                        onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">
                    {{ __('Create Payment Plan') }}
                </button>
                <a href="{{ route('admin.payment-plans.index') }}"
                   style="padding: 10px 24px; background: #f1f5f9; color: #475569; border-radius: 8px; font-size: 0.85rem; font-weight: 500; text-decoration: none;"
                   onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                    {{ __('Cancel') }}
                </a>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const studentSelect = document.getElementById('studentSelect');
    const feeSelect     = document.getElementById('feeSelect');
    const feeDetailsCard = document.getElementById('feeDetailsCard');
    const planConfigCard = document.getElementById('planConfigCard');
    const scheduleCard   = document.getElementById('scheduleCard');
    const generateBtn    = document.getElementById('generateBtn');
    const installmentBody = document.getElementById('installmentBody');
    const totalAmountInput = document.getElementById('totalAmountInput');
    const baseUrl = @json(url('admin/payment-plans'));

    let selectedFee = null;
    let feesData = [];

    // Student changed: load fees via AJAX
    studentSelect.addEventListener('change', function () {
        feeSelect.innerHTML = '<option value="">{{ __("Loading...") }}</option>';
        feeDetailsCard.style.display = 'none';
        planConfigCard.style.display = 'none';
        scheduleCard.style.display = 'none';
        selectedFee = null;
        feesData = [];

        const studentId = this.value;
        if (!studentId) {
            feeSelect.innerHTML = '<option value="">{{ __("Select a student first") }}</option>';
            return;
        }

        fetch(baseUrl + '/student-fees/' + studentId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            feesData = data;
            let html = '<option value="">{{ __("-- Select Fee --") }}</option>';
            data.forEach(f => {
                html += '<option value="' + f.id + '">' + escapeHtml(f.label) + ' — Balance: ' + numberFormat(f.balance) + ' XAF</option>';
            });
            feeSelect.innerHTML = html;
        })
        .catch(() => {
            feeSelect.innerHTML = '<option value="">{{ __("Error loading fees") }}</option>';
        });
    });

    // Fee changed: show fee details
    feeSelect.addEventListener('change', function () {
        scheduleCard.style.display = 'none';
        const feeId = parseInt(this.value);
        selectedFee = feesData.find(f => f.id === feeId) || null;

        if (!selectedFee) {
            feeDetailsCard.style.display = 'none';
            planConfigCard.style.display = 'none';
            return;
        }

        document.getElementById('feeAmount').textContent = numberFormat(selectedFee.net_amount);
        document.getElementById('feeDiscount').textContent = numberFormat(selectedFee.discount_amount);
        document.getElementById('feeFine').textContent = '0.00';
        document.getElementById('feeTotalAmount').textContent = numberFormat(selectedFee.balance);

        const alreadyPaidEl = document.getElementById('feeAlreadyPaid');
        if (selectedFee.paid_amount > 0) {
            alreadyPaidEl.textContent = '(Already paid: ' + numberFormat(selectedFee.paid_amount) + ')';
            alreadyPaidEl.style.display = 'inline';
        } else {
            alreadyPaidEl.style.display = 'none';
        }

        totalAmountInput.value = selectedFee.balance;
        feeDetailsCard.style.display = 'block';
        planConfigCard.style.display = 'block';
    });

    // Generate schedule
    generateBtn.addEventListener('click', function () {
        if (!selectedFee) return;

        const n = parseInt(document.getElementById('numInstallments').value) || 3;
        if (n < 2 || n > 24) { alert('{{ __("Instalments must be between 2 and 24.") }}'); return; }

        const totalAmount = parseFloat(selectedFee.balance);
        if (totalAmount <= 0) { alert('{{ __("No balance to plan.") }}'); return; }

        const perInstallment = Math.floor((totalAmount / n) * 100) / 100;
        const remainder = Math.round((totalAmount - perInstallment * n) * 100) / 100;

        let html = '';
        const today = new Date();
        for (let i = 0; i < n; i++) {
            const amt = (i === n - 1) ? (perInstallment + remainder).toFixed(2) : perInstallment.toFixed(2);
            const dueDate = new Date(today);
            dueDate.setMonth(dueDate.getMonth() + i + 1);
            const dueDateStr = dueDate.toISOString().split('T')[0];

            html += '<tr style="border-bottom: 1px solid #f1f5f9;">';
            html += '<td style="padding: 10px 14px; text-align: center; font-weight: 600; color: #475569;">' + (i + 1) + '</td>';
            html += '<td style="padding: 10px 14px;"><input type="number" name="installments[' + i + '][amount]" value="' + amt + '" min="0" step="0.01" class="inst-amount" data-index="' + i + '" style="width: 160px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem; outline: none; background: #fff;"></td>';
            html += '<td style="padding: 10px 14px;"><input type="date" name="installments[' + i + '][due_date]" value="' + dueDateStr + '" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem; outline: none; background: #fff;"></td>';
            html += '</tr>';
        }
        installmentBody.innerHTML = html;
        scheduleCard.style.display = 'block';
        recalcTotal();

        // Listen for amount changes
        document.querySelectorAll('.inst-amount').forEach(input => {
            input.addEventListener('input', recalcTotal);
        });
    });

    function recalcTotal() {
        let sum = 0;
        document.querySelectorAll('.inst-amount').forEach(input => {
            sum += parseFloat(input.value) || 0;
        });
        document.getElementById('installmentTotalCell').textContent = numberFormat(sum);

        const target = parseFloat(selectedFee.balance);
        const cell = document.getElementById('validationCell');
        if (Math.abs(sum - target) < 0.02) {
            cell.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 4px; color: #059669; font-size: 0.85rem; font-weight: 600;"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> {{ __("Total matches fee amount") }}</span>';
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('submitBtn').style.opacity = '1';
        } else {
            const diff = (sum - target).toFixed(2);
            const label = sum > target ? '{{ __("Over by") }} ' + numberFormat(Math.abs(diff)) : '{{ __("Under by") }} ' + numberFormat(Math.abs(diff));
            cell.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 4px; color: #dc2626; font-size: 0.85rem; font-weight: 600;"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> ' + label + '</span>';
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').style.opacity = '0.5';
        }
    }

    function numberFormat(num) {
        return parseFloat(num).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
</script>
@endsection
