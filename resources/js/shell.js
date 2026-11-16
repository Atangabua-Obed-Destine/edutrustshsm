// The desktop shell around every admin screen: toasts, a yes/no dialog in
// place of the browser's confirm(), a busy bar that also stops double saves,
// the session kept alive while someone is working (and a warning before it
// ends when they are not), the connection state, the status bar clock, row
// density and a sidebar that stays as it was left.
//
// Settings and words come from window.EDUTRUST_SHELL, written by the layout.

const cfg = window.EDUTRUST_SHELL || {};
const T = cfg.text || {};
const store = {
    get: (k) => { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } },
};

// ── Toasts ──────────────────────────────────────────────────────────────

const TOAST_STYLE = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    error: 'border-red-200 bg-red-50 text-red-900',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    info: 'border-slate-200 bg-white text-slate-800',
};
// An error stays until it is read and closed.
const TOAST_LIFE = { success: 6000, warning: 10000, info: 6000, error: 0 };

function stack() {
    let el = document.getElementById('toast-stack');
    if (!el) {
        el = document.createElement('div');
        el.id = 'toast-stack';
        el.className = 'toast-stack';
        el.setAttribute('aria-live', 'polite');
        document.body.appendChild(el);
    }
    return el;
}

function arm(el) {
    const kind = el.dataset.toast || 'info';
    el.querySelector('[data-toast-close]')?.addEventListener('click', () => dismiss(el));
    const life = el.dataset.toastLife !== undefined ? Number(el.dataset.toastLife) : TOAST_LIFE[kind];
    if (life > 0) {
        let timer = setTimeout(() => dismiss(el), life);
        // Hovering keeps it up long enough to read.
        el.addEventListener('mouseenter', () => clearTimeout(timer));
        el.addEventListener('mouseleave', () => { timer = setTimeout(() => dismiss(el), 2500); });
    }
}

function dismiss(el) {
    el.classList.add('toast-leaving');
    setTimeout(() => el.remove(), 180);
}

window.toast = function (message, kind = 'success', options = {}) {
    const el = document.createElement('div');
    el.dataset.toast = kind;
    if (options.life !== undefined) el.dataset.toastLife = options.life;
    el.className = 'toast ' + (TOAST_STYLE[kind] || TOAST_STYLE.info);
    el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    const text = document.createElement('div');
    text.className = 'flex-1 whitespace-pre-line';
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button';
    close.dataset.toastClose = '';
    close.className = 'toast-close';
    close.setAttribute('aria-label', T.close || 'Close');
    close.textContent = '×';
    el.append(text, close);
    stack().appendChild(el);
    arm(el);
    return el;
};

document.querySelectorAll('#toast-stack [data-toast]').forEach(arm);

// ── Yes / no ────────────────────────────────────────────────────────────
// data-ask="Delete this term?" on a form or on its submit button.

let askOpen = null;

window.askAction = function (message, { danger = false, yes } = {}) {
    if (askOpen) askOpen(false);
    return new Promise((resolve) => {
        const back = document.createElement('div');
        back.className = 'fixed inset-0 flex items-center justify-center bg-black/40 p-4';
        back.style.zIndex = '10060';
        back.setAttribute('role', 'alertdialog');
        back.setAttribute('aria-modal', 'true');
        back.innerHTML = `
            <div class="w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-2xl">
                <p class="px-5 pt-5 pb-4 text-sm text-gray-800 whitespace-pre-line" data-ask-text></p>
                <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-4 py-3">
                    <button type="button" data-ask-no class="rounded-lg border border-gray-300 bg-white px-4 py-1.5 text-sm text-gray-700 hover:bg-gray-100"></button>
                    <button type="button" data-ask-yes class="rounded-lg px-4 py-1.5 text-sm font-semibold text-white ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-slate-800 hover:bg-slate-900'}"></button>
                </div>
            </div>`;
        back.querySelector('[data-ask-text]').textContent = message;
        back.querySelector('[data-ask-no]').textContent = T.cancel || 'Cancel';
        back.querySelector('[data-ask-yes]').textContent = yes || (danger ? (T.delete || 'Delete') : (T.yes || 'Yes'));
        back.setAttribute('aria-label', message);

        const finish = (answer) => {
            askOpen = null;
            document.removeEventListener('keydown', onKey, true);
            back.remove();
            resolve(answer);
        };
        const onKey = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); finish(false); }
        };
        askOpen = finish;
        back.addEventListener('click', (e) => { if (e.target === back) finish(false); });
        back.querySelector('[data-ask-no]').addEventListener('click', () => finish(false));
        back.querySelector('[data-ask-yes]').addEventListener('click', () => finish(true));
        document.addEventListener('keydown', onKey, true);
        document.body.appendChild(back);
        // Cancel has the focus: Enter on a delete must not be a reflex.
        setTimeout(() => back.querySelector(danger ? '[data-ask-no]' : '[data-ask-yes]').focus(), 20);
    });
};

function isDelete(form) {
    const spoofed = form.querySelector('input[name="_method"]');
    return !!spoofed && spoofed.value.toUpperCase() === 'DELETE';
}

document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    const submitter = e.submitter;
    const message = submitter?.dataset.ask || form.dataset.ask;
    if (!message) return;
    if (form.dataset.asked === '1') { delete form.dataset.asked; return; }
    e.preventDefault();
    e.stopImmediatePropagation();
    if (await window.askAction(message, { danger: isDelete(form) })) {
        form.dataset.asked = '1';
        submitter ? form.requestSubmit(submitter) : form.requestSubmit();
    }
}, true);

// ── Busy bar and double-save guard ──────────────────────────────────────

const bar = document.createElement('div');
bar.id = 'busy-bar';
bar.setAttribute('aria-hidden', 'true');
document.body.appendChild(bar);
let barTimer = null;

const busy = {
    start() {
        bar.className = 'busy-on';
        clearTimeout(barTimer);
        // A download answers without leaving the page; do not spin forever.
        barTimer = setTimeout(busy.stop, 12000);
    },
    stop() {
        clearTimeout(barTimer);
        bar.className = bar.className ? 'busy-done' : '';
        setTimeout(() => { if (bar.className === 'busy-done') bar.className = ''; }, 300);
    },
};
window.shellBusy = busy;

function release(form) {
    delete form.dataset.sending;
    [...form.elements].forEach((el) => {
        if (el.dataset.busyDisabled) { el.disabled = false; delete el.dataset.busyDisabled; }
    });
}

// Bubble phase: a page's own handler (a fetch, or a "return false") has
// already had its say, and anything it stopped is left alone.
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-busy') || (form.target && form.target !== '_self')) return;

    const method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'get') {
        if (form.dataset.sending === '1') { e.preventDefault(); return; }
        form.dataset.sending = '1';
        // After this tick, so the button's own name and value still go.
        setTimeout(() => {
            [...form.elements].forEach((el) => {
                if (el.type === 'submit' && !el.disabled) { el.disabled = true; el.dataset.busyDisabled = '1'; }
            });
        }, 0);
        setTimeout(() => release(form), 12000);
    }
    busy.start();
});

document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('a[href]');
    if (!a || a.target || a.hasAttribute('download') || a.hasAttribute('data-no-busy')) return;
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
    if (a.origin !== location.origin) return;
    busy.start();
});

// Back to a page from the browser's cache: nothing is being sent any more.
window.addEventListener('pageshow', (e) => {
    if (e.persisted) {
        busy.stop();
        document.querySelectorAll('form[data-sending]').forEach(release);
    }
});

// ── Connection ──────────────────────────────────────────────────────────

const conn = document.getElementById('sb-conn');
let connState = 'online';

function setConnection(state) {
    if (state === connState) return;
    const was = connState;
    connState = state;
    if (conn) {
        conn.dataset.state = state;
        conn.querySelector('[data-conn-text]').textContent = T['conn_' + state] || state;
    }
    if (state === 'offline') window.toast(T.offline_toast, 'error');
    if (state === 'unreachable') window.toast(T.unreachable_toast, 'error');
    if (state === 'online' && was !== 'online') window.toast(T.back_online, 'success');
}

window.addEventListener('offline', () => setConnection('offline'));
window.addEventListener('online', () => ping(true));

// ── Session ─────────────────────────────────────────────────────────────

const lifetime = Math.max(5, Number(cfg.lifetime || 120)) * 60000;
const WARN_BEFORE = Math.min(2 * 60000, lifetime / 4);
const TOUCH_KEY = 'edutrust.sessionTouch';
let lastActivity = Date.now();
let pinging = false;
let warning = null;

store.set(TOUCH_KEY, String(Date.now()));
['keydown', 'mousedown', 'touchstart', 'wheel'].forEach((ev) =>
    window.addEventListener(ev, () => { lastActivity = Date.now(); }, { passive: true }));

function lastTouch() {
    return Number(store.get(TOUCH_KEY) || 0) || Date.now();
}

// Signing in again changes the form token, so forms already on the page are
// given the new one and can still be saved.
function refreshToken(token) {
    if (!token) return;
    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
    document.querySelectorAll('input[name="_token"]').forEach((i) => { i.value = token; });
    if (window.axios) window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

// A page's own requests (a payment, a marks save) reach the server too, and
// so keep the session alive just as a ping would.
const nativeFetch = window.fetch.bind(window);
window.fetch = async (...args) => {
    const res = await nativeFetch(...args);
    try {
        const url = new URL(res.url || String(args[0]), location.href);
        if (url.origin === location.origin && res.ok) store.set(TOUCH_KEY, String(Date.now()));
    } catch (e) { /* not a URL we can read */ }
    return res;
};

let lastPingAt = 0;

async function ping(force = false) {
    if (pinging || !cfg.keepAlive) return;
    // When the server is not answering, try again every 15 seconds, not every tick.
    if (!force && Date.now() - lastPingAt < 15000) return;
    lastPingAt = Date.now();
    pinging = true;
    try {
        const res = await nativeFetch(cfg.keepAlive, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (res.status === 401 || res.status === 419) {
            setConnection('online');
            showExpired();
            return;
        }
        if (!res.ok) { setConnection('unreachable'); return; }
        const data = await res.json().catch(() => ({}));
        store.set(TOUCH_KEY, String(Date.now()));
        refreshToken(data.token);
        setConnection('online');
        hideWarning();
    } catch (e) {
        setConnection(navigator.onLine === false ? 'offline' : 'unreachable');
    } finally {
        pinging = false;
    }
}

function dialog(html) {
    const back = document.createElement('div');
    back.className = 'fixed inset-0 flex items-center justify-center bg-black/50 p-4';
    back.style.zIndex = '10070';
    back.setAttribute('role', 'alertdialog');
    back.setAttribute('aria-modal', 'true');
    back.innerHTML = `<div class="w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-2xl">${html}</div>`;
    document.body.appendChild(back);
    return back;
}

function showWarning(left) {
    const mins = Math.floor(left / 60000);
    const secs = String(Math.floor((left % 60000) / 1000)).padStart(2, '0');
    if (!warning) {
        warning = dialog(`
            <div class="px-5 pt-5 pb-4">
                <h3 class="text-base font-semibold text-gray-900" data-w-title></h3>
                <p class="mt-2 text-sm text-gray-600" data-w-text></p>
                <p class="mt-3 text-center text-3xl font-bold tabular-nums text-gray-900" data-w-left></p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-4 py-3">
                <button type="button" data-w-stay class="rounded-lg bg-slate-800 px-4 py-1.5 text-sm font-semibold text-white hover:bg-slate-900"></button>
            </div>`);
        warning.querySelector('[data-w-title]').textContent = T.session_warn_title;
        warning.querySelector('[data-w-text]').textContent = T.session_warn_text;
        warning.querySelector('[data-w-stay]').textContent = T.session_stay;
        warning.querySelector('[data-w-stay]').addEventListener('click', () => { lastActivity = Date.now(); ping(true); });
        setTimeout(() => warning?.querySelector('[data-w-stay]').focus(), 20);
    }
    warning.querySelector('[data-w-left]').textContent = mins + ':' + secs;
}

function hideWarning() {
    warning?.remove();
    warning = null;
}

let expired = null;
function showExpired() {
    hideWarning();
    if (expired) return;
    expired = dialog(`
        <div class="px-5 pt-5 pb-4">
            <h3 class="text-base font-semibold text-gray-900" data-e-title></h3>
            <p class="mt-2 text-sm text-gray-600" data-e-text></p>
        </div>
        <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-4 py-3">
            <a data-e-login target="_blank" rel="opener" class="rounded-lg bg-slate-800 px-4 py-1.5 text-sm font-semibold text-white hover:bg-slate-900"></a>
        </div>`);
    expired.querySelector('[data-e-title]').textContent = T.session_ended_title;
    expired.querySelector('[data-e-text]').textContent = T.session_ended_text;
    const link = expired.querySelector('[data-e-login]');
    link.textContent = T.session_sign_in;
    link.href = cfg.login;
}

// Back from signing in on the other tab: carry on where they were.
window.addEventListener('focus', async () => {
    if (!expired) return;
    await ping(true);
    if (Date.now() - lastTouch() < 5000) {
        expired.remove();
        expired = null;
        window.toast(T.session_back, 'success');
    }
});

setInterval(() => {
    if (expired) return;
    const now = Date.now();
    const sinceTouch = now - lastTouch();
    // Someone is working on this page: keep them signed in, quietly.
    if (now - lastActivity < 5 * 60000 && sinceTouch > Math.min(5 * 60000, lifetime / 3)) {
        ping();
        return;
    }
    const left = lifetime - sinceTouch;
    if (left <= 0) { ping(); return; }
    if (left <= WARN_BEFORE) showWarning(left);
    else hideWarning();
}, 1000);

// ── Status bar clock ────────────────────────────────────────────────────

const clock = document.getElementById('sb-clock');
if (clock) {
    const fmt = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
        weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
    });
    const tickClock = () => { clock.textContent = fmt.format(new Date()); };
    tickClock();
    setInterval(tickClock, 15000);
}

// ── Row density ─────────────────────────────────────────────────────────

const densityBtn = document.getElementById('sb-density');
function showDensity() {
    if (!densityBtn) return;
    const compact = document.documentElement.dataset.density !== 'comfortable';
    densityBtn.textContent = compact ? T.density_compact : T.density_comfortable;
    densityBtn.setAttribute('aria-pressed', compact ? 'true' : 'false');
}
densityBtn?.addEventListener('click', () => {
    const next = document.documentElement.dataset.density === 'comfortable' ? 'compact' : 'comfortable';
    document.documentElement.dataset.density = next;
    store.set('edutrust.density', next);
    showDensity();
});
showDensity();

// ── Sidebar ─────────────────────────────────────────────────────────────

const nav = document.getElementById('sidebar-nav');
if (nav) {
    // The menu is long: keep its scroll across pages instead of jumping to
    // the top every time something near the bottom is opened.
    const saved = sessionStorage.getItem('edutrust.navScroll');
    if (saved !== null) nav.scrollTop = Number(saved);
    else nav.querySelector('.bg-white\\/10')?.scrollIntoView({ block: 'center' });
    window.addEventListener('pagehide', () => sessionStorage.setItem('edutrust.navScroll', String(nav.scrollTop)));
}

document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
    if (window.innerWidth >= 1024) {
        store.set('edutrust.sidebarClosed', document.body.classList.contains('sidebar-closed') ? '1' : '0');
    }
});

// ── Lists: sort, page and filter without reloading the page ─────────────
// A list wrapped in data-list-region="name" is redrawn in place: the server
// renders the whole page as usual and only that part is swapped in. Without
// the region, or if anything goes wrong, the browser simply goes there.

function sameScreen(url) {
    return url.origin === location.origin && url.pathname === location.pathname;
}

async function swapList(region, href, push = true) {
    const name = region.dataset.listRegion;
    const focused = region.contains(document.activeElement) ? document.activeElement : null;
    const focusName = focused?.name;
    const caret = typeof focused?.selectionStart === 'number' ? focused.selectionStart : null;
    busy.start();
    try {
        const res = await nativeFetch(href, {
            headers: { Accept: 'text/html', 'X-List-Swap': '1' },
            credentials: 'same-origin',
        });
        const landed = new URL(res.url || href, location.href);
        if (!res.ok || !sameScreen(landed)) throw new Error('left the screen');
        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        const fresh = doc.querySelector('[data-list-region="' + CSS.escape(name) + '"]');
        if (!fresh) throw new Error('no list');

        region.innerHTML = fresh.innerHTML;
        if (push) history.pushState({ listRegion: name }, '', landed.href);
        store.set(TOUCH_KEY, String(Date.now()));

        if (focusName) {
            const again = region.querySelector('[name="' + CSS.escape(focusName) + '"]');
            if (again) {
                again.focus();
                if (caret !== null && typeof again.setSelectionRange === 'function') {
                    try { again.setSelectionRange(caret, caret); } catch (e) { /* not a text field */ }
                }
            }
        }
        const top = region.getBoundingClientRect().top;
        if (top < 0) region.scrollIntoView({ block: 'start' });
        busy.stop();
    } catch (e) {
        console.warn('List redraw fell back to a full page load:', e);
        location.href = href;
    }
}

document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('[data-list-region] a[href]');
    if (!a || a.target || a.hasAttribute('download') || a.hasAttribute('data-no-swap')) return;
    const url = new URL(a.href, location.href);
    if (!sameScreen(url)) return;
    e.preventDefault();
    swapList(a.closest('[data-list-region]'), url.href);
}, true);

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement)) return;
    const region = form.closest('[data-list-region]');
    if (!region || form.hasAttribute('data-no-swap') || (form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
    const url = new URL(form.getAttribute('action') || location.href, location.href);
    if (!sameScreen(url)) return;
    e.preventDefault();
    const data = new FormData(form);
    if (e.submitter?.name) data.append(e.submitter.name, e.submitter.value);
    url.search = new URLSearchParams([...data].filter(([, v]) => typeof v === 'string')).toString();
    swapList(region, url.href);
});

// Filters that apply as soon as they are chosen.
document.addEventListener('change', (e) => {
    const field = e.target;
    const form = field.closest?.('form[data-auto-submit]');
    if (form && field.matches('select, input[type="checkbox"], input[type="radio"], input[type="date"]')) form.requestSubmit();
});

window.addEventListener('popstate', () => {
    const region = document.querySelector('[data-list-region]');
    if (region) swapList(region, location.href, false);
});

// ── Ticking rows for one action on all of them ──────────────────────────
// Inside [data-bulk-scope]: row boxes input[data-bulk-item] (value = id),
// a header box input[data-bulk-all], a bar [data-bulk-bar] shown while any
// row is ticked, and forms form[data-bulk-form] that are sent with ids[] of
// the ticked rows. Shift-click ticks every row between two clicks.

let lastBulkBox = null;

function bulkScope(el) { return el.closest('[data-bulk-scope]'); }
function bulkItems(scope) { return [...scope.querySelectorAll('input[data-bulk-item]')]; }

function bulkRefresh(scope) {
    const items = bulkItems(scope);
    const ticked = items.filter((i) => i.checked);
    const all = scope.querySelector('input[data-bulk-all]');
    if (all) {
        all.checked = items.length > 0 && ticked.length === items.length;
        all.indeterminate = ticked.length > 0 && ticked.length < items.length;
    }
    items.forEach((i) => i.closest('tr')?.classList.toggle('bg-sky-50', i.checked));
    const bar = scope.querySelector('[data-bulk-bar]');
    if (bar) {
        bar.hidden = ticked.length === 0;
        const count = bar.querySelector('[data-bulk-count]');
        if (count) count.textContent = (count.dataset.template || ':count').replace(':count', ticked.length);
    }
}

document.addEventListener('click', (e) => {
    const box = e.target.closest?.('input[data-bulk-item]');
    if (!box) return;
    const scope = bulkScope(box);
    if (e.shiftKey && lastBulkBox && lastBulkBox !== box && scope?.contains(lastBulkBox)) {
        const items = bulkItems(scope);
        const [a, b] = [items.indexOf(lastBulkBox), items.indexOf(box)].sort((x, y) => x - y);
        items.slice(a, b + 1).forEach((i) => { i.checked = box.checked; });
    }
    lastBulkBox = box;
});

document.addEventListener('change', (e) => {
    const box = e.target;
    if (!box.matches?.('input[data-bulk-item], input[data-bulk-all]')) return;
    const scope = bulkScope(box);
    if (!scope) return;
    if (box.matches('[data-bulk-all]')) bulkItems(scope).forEach((i) => { i.checked = box.checked; });
    bulkRefresh(scope);
});

document.addEventListener('click', (e) => {
    const clear = e.target.closest?.('[data-bulk-clear]');
    if (!clear) return;
    const scope = bulkScope(clear);
    if (!scope) return;
    bulkItems(scope).forEach((i) => { i.checked = false; });
    bulkRefresh(scope);
});

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-bulk-form')) return;
    const scope = bulkScope(form);
    const ids = scope ? bulkItems(scope).filter((i) => i.checked).map((i) => i.value) : [];
    form.querySelectorAll('input[data-bulk-id]').forEach((i) => i.remove());
    if (!ids.length) {
        e.preventDefault();
        window.toast(T.bulk_none || 'Tick at least one row first.', 'warning');
        return;
    }
    ids.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        input.setAttribute('data-bulk-id', '');
        form.appendChild(input);
    });
}, true);

// ── Keys ────────────────────────────────────────────────────────────────

const isTyping = (el) => !!el?.closest?.('input, textarea, select, [contenteditable="true"]');

document.addEventListener('keydown', (e) => {
    if (e.defaultPrevented) return;
    const ctrl = e.ctrlKey || e.metaKey;

    // Ctrl+K: go to any screen, or find a pupil.
    if (ctrl && !e.altKey && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        openPalette();
        return;
    }

    // Ctrl+S: save the form being typed in, through its usual checks.
    if (ctrl && !e.altKey && e.key.toLowerCase() === 's') {
        const form = isTyping(e.target) ? e.target.form || e.target.closest('form') : null;
        if (form && (form.getAttribute('method') || 'get').toLowerCase() === 'post') {
            e.preventDefault();
            const button = [...form.elements].find((el) => el.type === 'submit' && !el.disabled && !el.dataset.ask);
            button ? form.requestSubmit(button) : form.requestSubmit();
        }
        return;
    }

    // "/" to find a pupil.
    if (e.key === '/' && !isTyping(e.target) && !ctrl && !e.altKey) {
        const search = document.getElementById('global-pupil-search');
        if (search && search.offsetParent !== null) {
            e.preventDefault();
            search.focus();
            search.select();
        }
    }
});

// ── Grids: marks and registers taken from the keyboard ──────────────────
// In a table marked data-grid, Up and Down (and Enter in a box) move to the
// same column one row up or down, the way a spreadsheet does, instead of
// nudging a mark by a quarter point. In a register, the letter on a status
// (data-key) marks it and moves to the next pupil.

function gridCell(row, col) {
    const cell = row?.cells?.[col];
    if (!cell) return null;
    return cell.querySelector('input[type="radio"]:checked:not(:disabled)')
        || cell.querySelector('input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled)');
}

function gridMove(from, step) {
    const cell = from.closest('td, th');
    let row = from.closest('tr');
    const col = cell ? cell.cellIndex : -1;
    while (row && col >= 0) {
        row = step > 0 ? row.nextElementSibling : row.previousElementSibling;
        const target = gridCell(row, col);
        if (target) {
            target.focus();
            if (typeof target.select === 'function' && target.type !== 'radio') target.select();
            return true;
        }
    }
    return false;
}

document.addEventListener('keydown', (e) => {
    if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;
    const el = e.target;
    if (!(el instanceof HTMLElement) || !el.closest('table[data-grid] tbody')) return;

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') return;
        e.preventDefault();
        gridMove(el, e.key === 'ArrowDown' ? 1 : -1);
        return;
    }

    if (e.key === 'Enter' && el.tagName === 'INPUT' && el.type !== 'checkbox' && el.type !== 'radio') {
        e.preventDefault();
        gridMove(el, e.shiftKey ? -1 : 1);
        return;
    }

    if (el.type === 'radio' && e.key.length === 1) {
        const group = el.closest('td')?.querySelectorAll('input[type="radio"][name="' + CSS.escape(el.name) + '"]') || [];
        const pick = [...group].find((r) => (r.dataset.key || '').toLowerCase() === e.key.toLowerCase() && !r.disabled);
        if (pick) {
            e.preventDefault();
            pick.checked = true;
            pick.dispatchEvent(new Event('change', { bubbles: true }));
            if (!gridMove(pick, 1)) pick.focus();
        }
    }
});

// ── Ctrl+K palette ──────────────────────────────────────────────────────

let palette = null;
window.openPalette = () => openPalette();

// Every screen in the sidebar: what this person may open, and nothing else.
function screens() {
    const seen = new Set();
    const out = [];
    document.querySelectorAll('#sidebar a[href]').forEach((a) => {
        const name = a.textContent.replace(/\s+/g, ' ').trim();
        if (!name || seen.has(a.href)) return;
        seen.add(a.href);
        const group = a.closest('.sidebar-group')?.querySelector('button')?.textContent.replace(/\s+/g, ' ').trim() || '';
        out.push({ name, detail: group, url: a.href, hay: (name + ' ' + group).toLowerCase() });
    });
    return out;
}

// A word found in the screen's own name counts for more than one found only
// in its menu group, and more again at the start of a word.
function score(item, words) {
    const name = item.name.toLowerCase();
    let total = 0;
    for (const w of words) {
        const inName = name.indexOf(w);
        if (inName >= 0) {
            total += inName === 0 || name[inName - 1] === ' ' ? 4 : 3;
            continue;
        }
        if (item.hay.indexOf(w) < 0) return -1;
        total += 1;
    }
    return total;
}

function openPalette() {
    if (palette) { palette.input.focus(); return; }
    const all = screens();
    const back = document.createElement('div');
    back.className = 'fixed inset-0 flex items-start justify-center bg-black/40 p-4 pt-[12vh]';
    back.style.zIndex = '10065';
    back.setAttribute('role', 'dialog');
    back.setAttribute('aria-modal', 'true');
    back.setAttribute('aria-label', T.palette_placeholder || 'Go to');
    back.innerHTML = `
        <div class="w-full max-w-xl overflow-hidden rounded-xl bg-white shadow-2xl">
            <input type="text" data-p-input autocomplete="off" spellcheck="false" role="combobox" aria-expanded="true" aria-controls="palette-list"
                   class="w-full border-0 border-b border-gray-200 px-4 py-3 text-base outline-none focus:ring-0">
            <ul id="palette-list" data-p-list role="listbox" class="max-h-[50vh] overflow-y-auto py-1"></ul>
            <div class="border-t border-gray-100 bg-gray-50 px-4 py-1.5 text-xs text-gray-500" data-p-hint></div>
        </div>`;
    const input = back.querySelector('[data-p-input]');
    const list = back.querySelector('[data-p-list]');
    input.placeholder = T.palette_placeholder || '';
    back.querySelector('[data-p-hint]').textContent = T.palette_hint || '';
    document.body.appendChild(back);

    let items = [];
    let active = 0;
    let pupils = [];
    let timer = null;
    let asked = '';

    const close = () => { back.remove(); palette = null; };
    const go = (item) => { if (!item) return; close(); busy.start(); location.href = item.url; };

    function draw() {
        const q = input.value.trim().toLowerCase();
        const words = q.split(/\s+/).filter(Boolean);
        const found = words.length
            ? all.map((i) => [i, score(i, words)]).filter(([, n]) => n >= 0).sort((a, b) => b[1] - a[1]).map(([i]) => i).slice(0, 8)
            : all.slice(0, 8);
        items = [...found.map((i) => ({ ...i, kind: 'screen' })), ...pupils.map((p) => ({ ...p, kind: 'pupil' }))];
        if (active >= items.length) active = 0;
        list.replaceChildren();
        let heading = null;
        items.forEach((item, n) => {
            if (item.kind !== heading) {
                heading = item.kind;
                const h = document.createElement('li');
                h.className = 'px-4 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400';
                h.textContent = item.kind === 'pupil' ? T.palette_pupils : T.palette_screens;
                h.setAttribute('role', 'presentation');
                list.appendChild(h);
            }
            const li = document.createElement('li');
            li.id = 'palette-item-' + n;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', n === active ? 'true' : 'false');
            li.className = 'flex cursor-pointer items-baseline justify-between gap-3 px-4 py-1.5 text-sm ' + (n === active ? 'bg-slate-800 text-white' : 'text-gray-800 hover:bg-gray-100');
            const name = document.createElement('span');
            name.textContent = item.name;
            const detail = document.createElement('span');
            detail.className = 'truncate text-xs ' + (n === active ? 'text-slate-300' : 'text-gray-400');
            detail.textContent = item.detail || '';
            li.append(name, detail);
            li.addEventListener('mousedown', (ev) => { ev.preventDefault(); go(item); });
            list.appendChild(li);
        });
        if (!items.length) {
            const li = document.createElement('li');
            li.className = 'px-4 py-3 text-sm text-gray-500';
            li.textContent = T.palette_empty || '';
            list.appendChild(li);
        }
        input.setAttribute('aria-activedescendant', items.length ? 'palette-item-' + active : '');
        list.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
    }

    function findPupils() {
        clearTimeout(timer);
        const q = input.value.trim();
        if (!cfg.pupils || q.length < 2) { pupils = []; asked = ''; return; }
        timer = setTimeout(async () => {
            asked = q;
            try {
                const res = await nativeFetch(cfg.pupils + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const found = res.ok ? await res.json() : [];
                if (palette && asked === q) { pupils = found; draw(); }
            } catch (err) { /* the screens still show */ }
        }, 200);
    }

    input.addEventListener('input', () => { active = 0; draw(); findPupils(); });
    input.addEventListener('keydown', (ev) => {
        if (ev.key === 'ArrowDown') { ev.preventDefault(); active = Math.min(items.length - 1, active + 1); draw(); }
        else if (ev.key === 'ArrowUp') { ev.preventDefault(); active = Math.max(0, active - 1); draw(); }
        else if (ev.key === 'Enter') { ev.preventDefault(); go(items[active]); }
        else if (ev.key === 'Escape') { ev.preventDefault(); close(); }
    });
    back.addEventListener('mousedown', (ev) => { if (ev.target === back) close(); });

    palette = { input };
    draw();
    input.focus();
}
