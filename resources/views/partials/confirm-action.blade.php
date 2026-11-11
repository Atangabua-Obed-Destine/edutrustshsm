{{--
    Read it back before it is recorded.

    The reference system's rule, applied across the school: an act that moves
    money or cannot simply be undone is shown back to the person doing it, with
    any problem spelled out, and goes through only after they tick that they
    have checked it. The confirm button stays disabled until the tick, which is
    cleared every time, so it is a decision rather than a second reflex click.
    A problem marked "stop" cannot be confirmed past at all.

    Two ways in:

    1. A form opts in with data-confirm='{...}' (see readOptions below). Its
       submit is held until confirmed, then sent as it would have been.
    2. A script asks first:  if (!(await confirmAction({...}))) return;

    Options: title, warning, amount (number) or amountField, amountLabel,
    currency, rows [[label, value | "field:name"]], issues [{level, text}]
    or check (name of a global function (form) => issues), tick, confirm, back.
--}}
<div id="confirm-action" style="z-index: 10050" class="fixed inset-0 hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="confirm-action-title">
    <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl">
        <div class="bg-[#1f3a5f] px-5 py-4 text-white">
            <h3 id="confirm-action-title" class="text-lg font-semibold">{{ __('Check this before it is recorded') }}</h3>
        </div>
        <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
            <p id="confirm-action-warning" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900"></p>

            <div id="confirm-action-amount-box" class="mb-4 text-center">
                <p id="confirm-action-amount-label" class="text-xs uppercase tracking-wide text-gray-500"></p>
                <p id="confirm-action-amount" class="text-4xl font-bold tabular-nums text-gray-900"></p>
                <p id="confirm-action-currency" class="text-sm text-gray-500"></p>
            </div>

            <dl id="confirm-action-rows" class="mb-4 divide-y divide-gray-100 rounded-lg border border-gray-200 text-sm"></dl>

            <ul id="confirm-action-issues" class="mb-4 space-y-2"></ul>

            <label id="confirm-action-tick-row" class="flex items-start gap-2 text-sm font-medium text-gray-800">
                <input type="checkbox" id="confirm-action-tick" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-[#1f3a5f]">
                <span id="confirm-action-tick-text"></span>
            </label>
        </div>
        <div class="flex justify-end gap-3 border-t border-gray-100 px-5 py-3">
            <button type="button" id="confirm-action-back" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"></button>
            <button type="button" id="confirm-action-go" disabled
                    class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"></button>
        </div>
    </div>
</div>

<script>
(() => {
    const T = {
        title: @json(__('Check this before it is recorded')),
        warning: @json(__('This is recorded as soon as you confirm. Correcting it later leaves a correction on the record, so check it now.')),
        tick: @json(__('I have checked this and it is right.')),
        confirm: @json(__('Yes, record this')),
        back: @json(__('Go back and edit')),
        anyway: @json(__('Record it anyway')),
        stop: @json(__('This cannot be recorded as it is. Go back and correct it.')),
    };
    const $ = (id) => document.getElementById(id);
    const modal = $('confirm-action');
    let settle = null;

    const money = (n) => Number(n).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    const text = (s) => document.createTextNode(s == null || s === '' ? '—' : String(s));

    function close(result) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.removeEventListener('keydown', onKey);
        if (settle) { const s = settle; settle = null; s(result); }
    }
    function onKey(e) { if (e.key === 'Escape') close(false); }

    window.confirmAction = function (o = {}) {
        if (settle) close(false);
        $('confirm-action-title').textContent = o.title || T.title;
        $('confirm-action-warning').textContent = o.warning || T.warning;

        const hasAmount = o.amount !== undefined && o.amount !== null && o.amount !== '';
        $('confirm-action-amount-box').classList.toggle('hidden', !hasAmount);
        if (hasAmount) {
            $('confirm-action-amount-label').textContent = o.amountLabel || '';
            $('confirm-action-amount').textContent = money(o.amount);
            $('confirm-action-currency').textContent = o.currency || 'FCFA';
        }

        const rows = $('confirm-action-rows');
        rows.replaceChildren();
        (o.rows || []).forEach(([label, value]) => {
            const row = document.createElement('div');
            row.className = 'flex justify-between gap-4 px-3 py-2';
            const dt = document.createElement('dt'); dt.className = 'text-gray-500'; dt.appendChild(text(label));
            const dd = document.createElement('dd'); dd.className = 'text-right font-semibold text-gray-800 break-words'; dd.appendChild(text(value));
            row.append(dt, dd);
            rows.appendChild(row);
        });
        rows.classList.toggle('hidden', !(o.rows || []).length);

        const issues = (o.issues || []).filter((i) => i && i.text);
        const blocked = issues.some((i) => i.level === 'stop');
        const list = $('confirm-action-issues');
        list.replaceChildren();
        issues.forEach((i) => {
            const li = document.createElement('li');
            li.className = 'flex gap-2 rounded-lg px-3 py-2 text-sm ' + (i.level === 'stop' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-amber-50 text-amber-900 border border-amber-200');
            li.setAttribute('role', 'alert');
            const mark = document.createElement('span'); mark.className = 'font-bold'; mark.textContent = '!';
            const span = document.createElement('span'); span.textContent = i.text;
            li.append(mark, span);
            list.appendChild(li);
        });
        if (blocked) {
            const li = document.createElement('li');
            li.className = 'text-sm font-semibold text-red-700';
            li.textContent = T.stop;
            list.appendChild(li);
        }

        const tick = $('confirm-action-tick');
        const go = $('confirm-action-go');
        tick.checked = false;
        tick.disabled = blocked;
        $('confirm-action-tick-row').classList.toggle('opacity-50', blocked);
        $('confirm-action-tick-text').textContent = o.tick || T.tick;
        go.disabled = true;
        go.classList.toggle('hidden', blocked);
        go.textContent = issues.length && !blocked ? (o.confirmAnyway || T.anyway) : (o.confirm || T.confirm);
        go.className = go.className.replace(/bg-(emerald|amber)-[67]00/g, '').replace(/hover:bg-(emerald|amber)-[78]00/g, '')
            + (issues.length ? ' bg-amber-600 hover:bg-amber-700' : ' bg-emerald-600 hover:bg-emerald-700');
        $('confirm-action-back').textContent = o.back || T.back;

        tick.onchange = () => { go.disabled = !tick.checked; };
        go.onclick = () => { if (tick.checked && !blocked) close(true); };
        $('confirm-action-back').onclick = () => close(false);
        modal.onclick = (e) => { if (e.target === modal) close(false); };

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.addEventListener('keydown', onKey);
        setTimeout(() => (blocked ? $('confirm-action-back') : tick).focus(), 30);

        return new Promise((resolve) => { settle = resolve; });
    };

    // A field's value as a person reads it: a select's text, not its id.
    function fieldText(form, name) {
        const el = form.elements[name];
        if (!el) return '';
        if (el instanceof RadioNodeList) return el.value;
        if (el.tagName === 'SELECT') return el.value ? el.options[el.selectedIndex].text.trim() : '';
        if (el.type === 'checkbox') return el.checked ? '✓' : '';
        if (el.type === 'file') return el.files && el.files.length ? el.files[0].name : '';
        return el.value.trim();
    }
    window.confirmField = fieldText;

    // Today on this computer's calendar, as a form's date field writes it.
    // (toISOString gives the UTC date, which in Cameroon is still yesterday
    // until 1 a.m., so a payment made just after midnight would read as future.)
    function localToday() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    window.localToday = localToday;

    function readOptions(form) {
        let o = {};
        try { o = JSON.parse(form.dataset.confirm || '{}'); } catch (e) { o = {}; }
        if (o.amountFn && typeof window[o.amountFn] === 'function') o.amount = window[o.amountFn](form);
        if (o.amountField) {
            const raw = (form.elements[o.amountField] || {}).value;
            o.amount = raw === undefined || raw === '' ? null : Number(raw);
        }
        o.rows = (o.rows || []).map(([label, value]) => [label, typeof value === 'string' && value.startsWith('field:') ? fieldText(form, value.slice(6)) : value]);
        o.issues = [...(o.issues || []), ...checkRules(form, o.rules || [])];
        // A page's own check returns problems, or {rows, issues} to add both.
        if (o.check && typeof window[o.check] === 'function') {
            const found = window[o.check](form) || [];
            const extra = Array.isArray(found) ? { issues: found } : found;
            o.rows = [...o.rows, ...(extra.rows || [])];
            o.issues = [...o.issues, ...(extra.issues || [])];
        }
        return o;
    }

    // The checks most forms need, declared rather than scripted:
    //   {rule: 'future', field}                  a date after today
    //   {rule: 'max', field, max | maxFrom}      more than a limit; maxFrom names a
    //                                            select whose option carries data-balance
    //   {rule: 'same', fields: [a, b]}           two fields that must differ
    //   {rule: 'empty', field}                   left blank
    //   {rule: 'percent', field, typeField}      a percentage over 100
    // Each may carry when: {field, is | not}, level ('warn' or 'stop') and text,
    // in which :amount becomes the figure concerned.
    function checkRules(form, rules) {
        const val = (n) => { const el = form.elements[n]; return el ? String(el.value ?? '') : ''; };
        const today = localToday();
        const out = [];
        rules.forEach((r) => {
            if (r.when && (r.when.is !== undefined ? val(r.when.field) !== r.when.is : val(r.when.field) === r.when.not)) return;
            let hit = false, figure = null;
            if (r.rule === 'future') hit = val(r.field) !== '' && val(r.field) > today;
            if (r.rule === 'same') hit = val(r.fields[0]) !== '' && val(r.fields[0]) === val(r.fields[1]);
            if (r.rule === 'empty') hit = val(r.field).trim() === '';
            if (r.rule === 'percent') hit = val(r.typeField) === 'percentage' && Number(val(r.field)) > 100;
            if (r.rule === 'max') {
                let max = r.max;
                if (r.maxFrom) {
                    const sel = form.elements[r.maxFrom];
                    const opt = sel && sel.selectedOptions ? sel.selectedOptions[0] : null;
                    max = opt && opt.dataset.balance !== undefined ? Number(opt.dataset.balance) : null;
                }
                const n = Number(val(r.field));
                hit = max !== null && max !== undefined && val(r.field) !== '' && n > max;
                figure = hit ? n - max : null;
            }
            if (hit) out.push({ level: r.level || 'warn', text: String(r.text).replace(':amount', figure === null ? '' : money(figure) + ' FCFA') });
        });
        return out;
    }

    // Forms that opt in are held at submit until confirmed.
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
        e.preventDefault();
        e.stopImmediatePropagation();
        if (!form.reportValidity()) return;
        const submitter = e.submitter;
        if (await window.confirmAction(readOptions(form))) {
            form.dataset.confirmed = '1';
            submitter ? form.requestSubmit(submitter) : form.requestSubmit();
        }
    }, true);
})();
</script>
