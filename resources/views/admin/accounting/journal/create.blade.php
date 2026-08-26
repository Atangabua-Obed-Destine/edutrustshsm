@extends('layouts.admin')

@section('title', __('Add Journal Entry'))
@section('breadcrumb', __('Accounting > Journal Entries > Add'))

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h3 class="text-lg font-semibold text-gray-700 mb-6">{{ __('Add Journal Entry') }}</h3>

    <form method="POST" action="{{ route('admin.journal-entries.store') }}">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Date') }} <span class="text-red-500">*</span></label>
                <input type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Journal Type') }} <span class="text-red-500">*</span></label>
                <select name="journal_type" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
                    @foreach(['general','sales','purchase','cash','bank','adjustment','opening'] as $t)<option value="{{ $t }}" {{ old('journal_type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
                <input type="text" name="description" value="{{ old('description') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full" id="lines-table">
                <thead class="bg-gray-50 border-y border-gray-200">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account') }}</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Description') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase w-40">{{ __('Debit') }}</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase w-40">{{ __('Credit') }}</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody id="lines-body"></tbody>
                <tfoot class="border-t border-gray-200">
                    <tr>
                        <td colspan="2" class="px-3 py-2 text-right text-sm font-semibold">{{ __('Totals') }}</td>
                        <td class="px-3 py-2 text-right text-sm font-bold" id="total-debit">0.00</td>
                        <td class="px-3 py-2 text-right text-sm font-bold" id="total-credit">0.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-3 py-2 text-right text-sm font-semibold">{{ __('Difference') }}</td>
                        <td class="px-3 py-2 text-right text-sm font-bold" id="diff">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <button type="button" id="add-line" class="mt-3 text-sm text-blue-600 hover:underline">+ {{ __('Add Line') }}</button>

        <div class="flex items-center justify-end mt-6 space-x-3">
            <span id="balance-flag" class="text-sm font-medium text-red-600">{{ __('Not balanced') }}</span>
            <a href="{{ route('admin.journal-entries.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Cancel') }}</a>
            <button type="submit" id="save-btn" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium">{{ __('Save Entry') }}</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    const accounts = @json($accounts->map(fn($a) => ['id' => $a->id, 'label' => $a->account_code.' — '.$a->account_name]));
    const body = document.getElementById('lines-body');
    let idx = 0;

    function accountOptions() {
        return '<option value="">{{ __('Select account') }}</option>' + accounts.map(a => `<option value="${a.id}">${a.label}</option>`).join('');
    }
    function addLine() {
        const i = idx++;
        const tr = document.createElement('tr');
        tr.className = 'border-b border-gray-100';
        tr.innerHTML = `
            <td class="px-3 py-2"><select name="lines[${i}][account_id]" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">${accountOptions()}</select></td>
            <td class="px-3 py-2"><input type="text" name="lines[${i}][description]" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm"></td>
            <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[${i}][debit]" class="debit w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-right"></td>
            <td class="px-3 py-2"><input type="number" step="0.01" min="0" name="lines[${i}][credit]" class="credit w-full px-2 py-1.5 border border-gray-300 rounded text-sm text-right"></td>
            <td class="px-3 py-2 text-center"><button type="button" class="remove text-red-500 hover:text-red-700">&times;</button></td>`;
        body.appendChild(tr);
        tr.querySelectorAll('.debit,.credit').forEach(el => el.addEventListener('input', recalc));
        tr.querySelector('.remove').addEventListener('click', () => { tr.remove(); recalc(); });
    }
    function recalc() {
        let d = 0, c = 0;
        body.querySelectorAll('.debit').forEach(el => d += parseFloat(el.value || 0));
        body.querySelectorAll('.credit').forEach(el => c += parseFloat(el.value || 0));
        document.getElementById('total-debit').textContent = d.toFixed(2);
        document.getElementById('total-credit').textContent = c.toFixed(2);
        const diff = d - c;
        document.getElementById('diff').textContent = diff.toFixed(2);
        const balanced = Math.abs(diff) < 0.01 && d > 0;
        const flag = document.getElementById('balance-flag');
        flag.textContent = balanced ? '{{ __('Balanced') }}' : '{{ __('Not balanced') }}';
        flag.className = 'text-sm font-medium ' + (balanced ? 'text-green-600' : 'text-red-600');
    }
    document.getElementById('add-line').addEventListener('click', addLine);
    addLine(); addLine(); // start with two lines
</script>
@endpush
@endsection
