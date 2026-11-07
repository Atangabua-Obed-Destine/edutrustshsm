{{--
    Read the figures back before money is recorded. An expense or income takes
    effect the moment it is saved: the ledger entry posts and the account moves.
    A mistyped 150,000 for 15,000 is then a reversal, not a correction, so the
    operator confirms what they typed first. Clicking twice cannot record it
    twice either.

    Include inside the <form>, which carries:
      x-data="confirmFigures(@js($fields))" @submit="check($event)"
    $fields: [field name => label]. Text of a select is read back, not its id.
--}}
<div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="open = false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6" @click.outside="open = false">
        <h3 class="text-lg font-semibold text-gray-800 mb-1">{{ __('Check the figures') }}</h3>
        <p class="text-sm text-gray-500 mb-4">{{ __('Once saved, this is posted to the books and the account balance moves.') }}</p>

        <dl class="divide-y divide-gray-100 border border-gray-200 rounded-lg text-sm mb-4">
            <template x-for="row in rows" :key="row.label">
                <div class="flex justify-between gap-4 px-3 py-2" :class="row.changed ? 'bg-amber-50' : ''">
                    <dt class="text-gray-500" x-text="row.label"></dt>
                    <dd class="text-right font-medium text-gray-800 break-words">
                        <span x-show="row.changed" class="text-gray-400 line-through mr-1" x-text="row.was"></span>
                        <span x-text="row.value"></span>
                    </dd>
                </div>
            </template>
        </dl>

        <p x-show="reposts" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-4">
            {{ __('The amount, category or date changed: the old ledger entry is reversed and a new one posted.') }}
        </p>

        <label class="flex items-start gap-2 text-sm text-gray-700 mb-5">
            <input type="checkbox" x-model="ticked" class="mt-0.5 rounded border-gray-300">
            <span>{{ __('I have checked these figures against the receipt.') }}</span>
        </label>

        <div class="flex justify-end gap-3">
            <button type="button" @click="open = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('Go back') }}</button>
            <button type="button" @click="confirm()" :disabled="!ticked || sending"
                    class="bg-[#1e293b] hover:bg-[#334155] disabled:opacity-40 disabled:cursor-not-allowed text-white px-5 py-2 rounded-lg text-sm font-medium">
                <span x-show="!sending">{{ __('Confirm and save') }}</span>
                <span x-show="sending">{{ __('Saving…') }}</span>
            </button>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    function confirmFigures(fields) {
        const MONEY = ['amount'];
        const POSTING = ['amount', 'category_id', 'date'];

        return {
            open: false, ticked: false, sending: false, confirmed: false,
            rows: [], reposts: false, original: {},

            init() {
                // What the record said when the page opened, so an edit shows
                // what is about to change.
                if (this.$root.querySelector('input[name="_method"]')) {
                    Object.keys(fields).forEach((name) => this.original[name] = this.read(name));
                }
            },

            read(name) {
                const el = this.$root.elements[name];
                if (!el) return '';
                if (el.tagName === 'SELECT') return el.value ? el.options[el.selectedIndex].text.trim() : '—';
                if (MONEY.includes(name) && el.value !== '') return Number(el.value).toLocaleString('fr-FR') + ' FCFA';
                return el.value.trim() || '—';
            },

            check(event) {
                if (this.confirmed) return;
                event.preventDefault();
                if (!this.$root.reportValidity()) return;

                const editing = Object.keys(this.original).length > 0;
                this.rows = Object.entries(fields).map(([name, label]) => {
                    const value = this.read(name);
                    const changed = editing && this.original[name] !== value;
                    return { name, label, value, was: this.original[name], changed };
                });
                this.reposts = this.rows.some((r) => r.changed && POSTING.includes(r.name));
                this.ticked = false;
                this.open = true;
            },

            confirm() {
                if (!this.ticked || this.sending) return;
                this.sending = true;
                this.confirmed = true;
                this.$root.requestSubmit();
            },
        };
    }
</script>
@endpush
@endonce
