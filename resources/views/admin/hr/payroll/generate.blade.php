@extends('layouts.admin')

@section('title', __('Generate Payroll'))
@section('breadcrumb', __('Human Resources > Payroll > Generate'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    {{-- Header --}}
    <div class="text-white rounded-xl p-5" style="background:linear-gradient(to right,#3b82f6,#4f46e5);">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">{{ $staff->full_name }}</h3>
                <p class="text-sm opacity-90">{{ $staff->staff_id }} · {{ $staff->designation?->title }} · {{ $staff->department?->name }}</p>
            </div>
            <div class="text-right"><p class="text-xs opacity-75">{{ __('Pay Period') }}</p><p class="font-bold">{{ \Illuminate\Support\Carbon::parse($salaryMonth.'-01')->format('M Y') }}</p></div>
            <div class="text-right"><p class="text-xs opacity-75">{{ __('Basic Salary') }}</p><p class="text-xl font-bold">{{ number_format($staff->basic_salary, 2) }} {{ $currency }}</p></div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.payroll.store', $staff) }}">
        @csrf
        <input type="hidden" name="salary_month" value="{{ $salaryMonth }}">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Allowances --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-3"><h4 class="font-semibold text-emerald-600">{{ __('Allowance') }}</h4><button type="button" onclick="addRow('allow')" class="bg-emerald-500 text-white px-3 py-1 rounded text-sm">+ {{ __('Add') }}</button></div>
                <div id="allow-rows" class="space-y-2"></div>
                <div class="mt-3"><label class="block text-xs text-gray-500 mb-1">{{ __('Bonus') }}</label><input type="number" step="0.01" name="bonus" id="bonus" value="{{ old('bonus', $existing->bonus ?? 0) }}" oninput="recalc()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            </div>
            {{-- Deductions --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-3"><h4 class="font-semibold text-red-600">{{ __('Deduction') }}</h4><button type="button" onclick="addRow('deduct')" class="bg-red-500 text-white px-3 py-1 rounded text-sm">+ {{ __('Add') }}</button></div>
                <div id="deduct-rows" class="space-y-2"></div>
            </div>
            {{-- Calculate --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h4 class="font-semibold text-gray-700 mb-3">{{ __('Calculate') }}</h4>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Earning') }}</dt><dd id="c-earning">{{ number_format($staff->basic_salary, 0) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Allowance') }}</dt><dd id="c-allow" class="text-emerald-600">0</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Deduction') }}</dt><dd id="c-deduct" class="text-red-600">0</dd></div>
                    <div class="flex justify-between font-semibold border-t pt-1.5"><dt>{{ __('Gross Salary') }}</dt><dd id="c-gross">{{ number_format($staff->basic_salary, 0) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Employee Tax') }}</dt><dd id="c-emptax" class="text-red-600">{{ number_format($taxBreakdown['employee_tax'], 0) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">{{ __('Employer Tax') }}</dt><dd id="c-emprtax" class="text-amber-600">{{ number_format($taxBreakdown['employer_tax'], 0) }}</dd></div>
                    <div class="flex justify-between font-bold text-green-700 border-t pt-1.5"><dt>{{ __('Net Salary') }}</dt><dd id="c-net">{{ number_format($staff->basic_salary - $taxBreakdown['employee_tax'], 0) }}</dd></div>
                    <div class="flex justify-between text-gray-500"><dt>{{ __('Total Labour Cost') }}</dt><dd id="c-cost">{{ number_format($staff->basic_salary - $taxBreakdown['employee_tax'] + $taxBreakdown['employee_tax'] + $taxBreakdown['employer_tax'], 0) }}</dd></div>
                </dl>

                <div class="mt-4 border-t pt-3">
                    <p class="text-xs font-semibold text-gray-500 mb-2">{{ __('Tax breakdown (current config)') }}</p>
                    @foreach($taxBreakdown['lines'] as $l)
                    <div class="flex justify-between text-xs py-0.5"><span class="text-gray-600">{{ $l['title'] }}</span><span>{{ number_format($l['employee'], 0) }} / {{ number_format($l['employer'], 0) }}</span></div>
                    @endforeach
                    <p class="text-[11px] text-gray-400 mt-1">{{ __('Tax is re-computed exactly on Save for the final gross.') }}</p>
                </div>

                <button type="submit" class="mt-4 w-full bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Save Payroll') }}</button>
            </div>
        </div>
    </form>

    {{-- Pay / Unpay --}}
    @if($existing)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-semibold text-gray-700">{{ __('Payment') }}</h4>
            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs {{ $existing->status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $existing->status ? __('Paid') : __('Unpaid') }}</span>
        </div>
        @if($existing->status)
            <p class="text-sm text-gray-600">{{ __('Paid on') }} {{ optional($existing->pay_date)->format('d/m/Y') }} · {{ $existing->payment_method }} · {{ __('Net') }} {{ number_format($existing->net_salary, 2) }}</p>
            <form method="POST" action="{{ route('admin.payroll.unpay', $existing) }}" class="mt-3" onsubmit="return confirm('{{ __('Un-pay and reverse the ledger entry?') }}')">@csrf<button class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm">{{ __('Un-pay') }}</button></form>
        @else
            <form method="POST" action="{{ route('admin.payroll.pay', $existing) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                @csrf
                <div><label class="block text-xs text-gray-500 mb-1">{{ __('Method') }}</label>
                    <select name="payment_method" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                        @foreach(['Cash','Bank','MTN Mobile Money','Orange Money'] as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs text-gray-500 mb-1">{{ __('Bank Account') }}</label>
                    <select name="bank_account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">—</option>
                        @foreach($staff->bankAccounts as $b)<option value="{{ $b->id }}">{{ $b->bank_name }} · {{ $b->account_number }}</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs text-gray-500 mb-1">{{ __('Pay Date') }}</label><input type="date" name="pay_date" value="{{ $payDate }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required></div>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Pay & Post to Ledger') }}</button>
            </form>
        @endif
    </div>
    @endif
</div>

@push('scripts')
<script>
    const basic = {{ (float) $staff->basic_salary }};
    const empTax = {{ (float) $taxBreakdown['employee_tax'] }};
    const emprTax = {{ (float) $taxBreakdown['employer_tax'] }};
    const allowTypes = @json($allowanceTypes->pluck('title'));
    const deductTypes = @json($deductionTypes->pluck('title'));
    let ai = 0, di = 0;

    function addRow(kind) {
        const isAllow = kind === 'allow';
        const i = isAllow ? ai++ : di++;
        const types = isAllow ? allowTypes : deductTypes;
        const name = isAllow ? 'allowances' : 'deductions';
        const wrap = document.getElementById(isAllow ? 'allow-rows' : 'deduct-rows');
        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 gap-2 items-center';
        row.innerHTML = `
            <input list="${kind}-types" name="${name}[${i}][title]" placeholder="{{ __('Title') }}" class="col-span-6 px-2 py-1.5 border border-gray-300 rounded text-sm" required>
            <input type="number" step="0.01" name="${name}[${i}][amount]" placeholder="0" oninput="recalc()" class="amt col-span-5 px-2 py-1.5 border border-gray-300 rounded text-sm text-right" required>
            <button type="button" class="col-span-1 text-red-500" onclick="this.parentElement.remove(); recalc();">&times;</button>`;
        wrap.appendChild(row);
        if (!document.getElementById(`${kind}-types`)) {
            const dl = document.createElement('datalist'); dl.id = `${kind}-types`;
            dl.innerHTML = types.map(t => `<option value="${t}">`).join(''); document.body.appendChild(dl);
        }
    }
    function sum(sel) { let t = 0; document.querySelectorAll(sel).forEach(e => t += parseFloat(e.value || 0)); return t; }
    function recalc() {
        const allow = sum('#allow-rows .amt');
        const deduct = sum('#deduct-rows .amt');
        const bonus = parseFloat(document.getElementById('bonus').value || 0);
        const gross = basic + allow + bonus - deduct;
        document.getElementById('c-allow').textContent = Math.round(allow).toLocaleString();
        document.getElementById('c-deduct').textContent = Math.round(deduct).toLocaleString();
        document.getElementById('c-gross').textContent = Math.round(gross).toLocaleString();
        // Tax preview held at base figures; server re-computes on save.
        document.getElementById('c-net').textContent = Math.round(gross - empTax).toLocaleString();
        document.getElementById('c-cost').textContent = Math.round(gross + emprTax).toLocaleString();
    }

    // Re-hydrate existing details
    @if($existing)
        @foreach($existing->details as $d)
            addRow('{{ $d->status ? 'allow' : 'deduct' }}');
        @endforeach
        (function(){
            const rows = [...document.querySelectorAll('#allow-rows .grid'), ...document.querySelectorAll('#deduct-rows .grid')];
            const data = @json($existing->details->map(fn($d)=>['title'=>$d->title,'amount'=>(float)$d->amount,'allow'=>$d->status==1]));
            let ag=0, dg=0;
            data.forEach(d => {
                const wrap = d.allow ? document.getElementById('allow-rows') : document.getElementById('deduct-rows');
                const idx = d.allow ? ag++ : dg++;
                const r = wrap.children[idx];
                if (r) { r.children[0].value = d.title; r.children[1].value = d.amount; }
            });
            recalc();
        })();
    @endif
</script>
@endpush
@endsection
