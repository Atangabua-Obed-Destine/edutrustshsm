@extends('layouts.admin')

@section('title', __('Enrol Sequences') . ' — ' . $form->name)
@section('breadcrumb', __('Academic > Enrol Sequences') . ' > ' . $form->name)

@section('content')
<div class="max-w-6xl">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.sequence-enrollments.index') }}"
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ __('Back') }}
            </a>
            <div>
                <h3 class="text-lg font-semibold text-gray-700">
                    {{ $form->name }}
                    <span class="font-mono text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded ml-2">{{ $form->short_name }}</span>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ml-2 {{ $form->level === 'first_cycle' ? 'bg-sky-100 text-sky-700' : 'bg-violet-100 text-violet-700' }}">
                        {{ $form->level === 'first_cycle' ? __('1st Cycle') : __('2nd Cycle') }}
                    </span>
                </h3>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('Assign exam sequences to streams for each term') }}</p>
            </div>
        </div>
        <button id="btn-save" onclick="saveAll()" disabled
                class="px-5 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span id="btn-save-text">{{ __('Save') }}</span>
        </button>
    </div>

    @if($terms->count() === 0 || $sequences->count() === 0)
    <div class="bg-amber-50 border border-amber-200 text-amber-700 px-5 py-4 rounded-xl text-sm space-y-1">
        @if($terms->count() === 0)
            <p>{{ __('No terms defined yet.') }} <a href="{{ route('admin.terms.index') }}" class="underline font-medium">{{ __('Create terms first') }}</a>.</p>
        @endif
        @if($sequences->count() === 0)
            <p>{{ __('No exam sequences defined yet.') }} <a href="{{ route('admin.sequences.index') }}" class="underline font-medium">{{ __('Create sequences first') }}</a>.</p>
        @endif
    </div>
    @else

    {{-- Term Tabs --}}
    <div class="mb-4">
        <div class="flex flex-wrap gap-2" id="term-tabs">
            @foreach($terms as $term)
            <button onclick="selectTerm({{ $term->id }})"
                    data-term="{{ $term->id }}"
                    class="term-tab relative px-4 py-2 rounded-lg text-sm font-medium border transition
                           {{ $loop->first ? 'bg-[#1e293b] text-white border-[#1e293b]' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                {{ $term->name }}
                @if($term->is_current)
                    <span class="ml-1 text-xs bg-emerald-200 text-emerald-800 px-1 rounded">{{ __('CURRENT') }}</span>
                @endif
                {{-- Dirty indicator dot --}}
                <span class="dirty-dot hidden absolute -top-1 -right-1 w-2.5 h-2.5 bg-amber-400 rounded-full border-2 border-white"></span>
                {{-- Count badge --}}
                <span class="count-badge ml-1.5 text-xs opacity-60">0</span>
            </button>
            @endforeach
        </div>
    </div>

    {{-- Stats Bar --}}
    <div class="bg-white rounded-lg border border-gray-200 px-4 py-3 mb-4 flex items-center justify-between">
        <div class="flex items-center gap-6 text-sm">
            <span class="text-gray-500">
                {{ __('Sequences enrolled:') }} <strong id="stat-enrolled" class="text-gray-800">0</strong> / {{ $sequences->count() }}
            </span>
            @if($streams->count() > 0)
            <span class="text-gray-500">
                {{ __('Streams:') }} <strong class="text-gray-800">{{ $streams->count() }}</strong>
            </span>
            @endif
            <span class="text-gray-500">
                {{ __('Total assignments:') }} <strong id="stat-total" class="text-gray-800">0</strong>
            </span>
        </div>
        <span id="unsaved-badge" class="hidden text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">
            {{ __('Unsaved changes') }}
        </span>
    </div>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <button onclick="bulkAction('select-all')" class="px-3 py-1.5 text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
            {{ __('Select All') }}
        </button>
        <button onclick="bulkAction('clear')" class="px-3 py-1.5 text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-100 transition">
            {{ __('Clear All') }}
        </button>
        <button onclick="bulkAction('copy-to-all-terms')" class="px-3 py-1.5 text-xs font-medium bg-violet-50 text-violet-700 border border-violet-200 rounded-lg hover:bg-violet-100 transition" title="{{ __('Copy current term configuration to all other terms') }}">
            {{ __('Copy to All Terms') }}
        </button>
    </div>

    {{-- Matrix Table --}}
    @if($streams->count() > 0)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm" id="matrix-table">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase sticky left-0 bg-gray-50">{{ __('Stream') }}</th>
                    @foreach($sequences as $seq)
                    <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500 uppercase min-w-[100px]">
                        <div>{{ $seq->name }}</div>
                        <label class="inline-flex items-center gap-1 mt-1 cursor-pointer text-[10px] text-gray-400 font-normal normal-case">
                            <input type="checkbox" class="col-check-all rounded border-gray-300 w-3.5 h-3.5"
                                   data-seq="{{ $seq->id }}"
                                   onchange="toggleColumn({{ $seq->id }}, this.checked)">
                            {{ __('all') }}
                        </label>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" id="matrix-body">
                @foreach($streams as $stream)
                <tr class="stream-row hover:bg-gray-50 transition-colors" data-stream="{{ $stream->id }}">
                    <td class="px-4 py-3 sticky left-0 bg-white">
                        <span class="font-medium text-gray-800">{{ $stream->name }}</span>
                        <span class="font-mono text-xs text-gray-400 ml-1">({{ $stream->code }})</span>
                        @if($stream->is_general)
                            <span class="ml-1 text-[10px] bg-blue-100 text-blue-700 px-1 rounded">{{ __('General') }}</span>
                        @endif
                    </td>
                    @foreach($sequences as $seq)
                    <td class="px-3 py-3 text-center">
                        <input type="checkbox" class="cell-check rounded border-gray-300"
                               data-stream="{{ $stream->id }}"
                               data-seq="{{ $seq->id }}"
                               onchange="toggleCell({{ $stream->id }}, {{ $seq->id }}, this.checked)">
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    {{-- No streams — show simple sequence checklist --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="w-12 px-4 py-3 text-center">
                        <input type="checkbox" id="check-all-no-stream" class="rounded border-gray-300"
                               onchange="toggleAllNoStream(this.checked)">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Sequence') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($sequences as $seq)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 text-center">
                        <input type="checkbox" class="cell-check-no-stream rounded border-gray-300"
                               data-seq="{{ $seq->id }}"
                               onchange="toggleCellNoStream({{ $seq->id }}, this.checked)">
                    </td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $seq->name }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Bottom Save --}}
    <div class="mt-4 flex justify-end">
        <button onclick="saveAll()" id="btn-save-bottom" disabled
                class="px-5 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed">
            {{ __('Save Changes') }}
        </button>
    </div>

    @endif
</div>

{{-- Toast Notification --}}
<div id="toast" class="fixed bottom-6 right-6 z-50 hidden transform transition-all duration-300 translate-y-4 opacity-0">
    <div class="flex items-center gap-3 px-5 py-3 rounded-lg shadow-lg border" id="toast-inner">
        <svg id="toast-icon-success" class="w-5 h-5 text-emerald-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <svg id="toast-icon-error" class="w-5 h-5 text-red-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        <span id="toast-message" class="text-sm font-medium"></span>
    </div>
</div>

<script>
// ── Server Data ──
const formId = {{ $form->id }};
const terms = @json($termsJson);
const streams = @json($streamsJson);
const sequences = @json($sequencesJson);
const serverEnrollments = @json($enrollments);
const hasStreams = streams.length > 0;
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// ── State ──
// enrollmentMap: { termId: Set of "streamId:seqId" }
let enrollmentMap = {};
let currentTermId = terms.length > 0 ? terms[0].id : null;
let dirtyTerms = new Set();
let saving = false;

// ── Initialize ──
function init() {
    // Build enrollment map from server data
    terms.forEach(t => { enrollmentMap[t.id] = new Set(); });

    serverEnrollments.forEach(e => {
        const key = makeKey(e.stream_id, e.sequence_id);
        if (enrollmentMap[e.term_id]) {
            enrollmentMap[e.term_id].add(key);
        }
    });

    renderTerm();
    updateTermCounts();
}

function makeKey(streamId, seqId) {
    return (streamId || 'null') + ':' + seqId;
}

function parseKey(key) {
    const parts = key.split(':');
    return {
        streamId: parts[0] === 'null' ? null : parseInt(parts[0]),
        seqId: parseInt(parts[1])
    };
}

// ── Term Selection ──
function selectTerm(termId) {
    currentTermId = termId;
    renderTerm();

    document.querySelectorAll('.term-tab').forEach(tab => {
        const tid = parseInt(tab.dataset.term);
        if (tid === termId) {
            tab.classList.add('bg-[#1e293b]', 'text-white', 'border-[#1e293b]');
            tab.classList.remove('bg-white', 'text-gray-600', 'border-gray-200', 'hover:border-gray-300', 'hover:bg-gray-50');
        } else {
            tab.classList.remove('bg-[#1e293b]', 'text-white', 'border-[#1e293b]');
            tab.classList.add('bg-white', 'text-gray-600', 'border-gray-200', 'hover:border-gray-300', 'hover:bg-gray-50');
        }
    });
}

// ── Render Matrix for Current Term ──
function renderTerm() {
    const data = enrollmentMap[currentTermId] || new Set();

    if (hasStreams) {
        // Update all cell checkboxes
        document.querySelectorAll('.cell-check').forEach(cb => {
            const key = makeKey(parseInt(cb.dataset.stream), parseInt(cb.dataset.seq));
            cb.checked = data.has(key);
        });
        updateColumnCheckAll();
    } else {
        document.querySelectorAll('.cell-check-no-stream').forEach(cb => {
            const key = makeKey(null, parseInt(cb.dataset.seq));
            cb.checked = data.has(key);
        });
        updateCheckAllNoStream();
    }

    updateStats();
}

// ── Cell Toggle (with streams) ──
function toggleCell(streamId, seqId, checked) {
    const data = enrollmentMap[currentTermId];
    const key = makeKey(streamId, seqId);
    if (checked) {
        data.add(key);
    } else {
        data.delete(key);
    }
    markDirty();
    updateStats();
    updateColumnCheckAll();
}

// ── Cell Toggle (no streams) ──
function toggleCellNoStream(seqId, checked) {
    const data = enrollmentMap[currentTermId];
    const key = makeKey(null, seqId);
    if (checked) {
        data.add(key);
    } else {
        data.delete(key);
    }
    markDirty();
    updateStats();
    updateCheckAllNoStream();
}

// ── Column Toggle (select all streams for a sequence) ──
function toggleColumn(seqId, checked) {
    const data = enrollmentMap[currentTermId];
    streams.forEach(s => {
        const key = makeKey(s.id, seqId);
        if (checked) { data.add(key); } else { data.delete(key); }
    });

    document.querySelectorAll(`.cell-check[data-seq="${seqId}"]`).forEach(cb => {
        cb.checked = checked;
    });

    markDirty();
    updateStats();
}

function updateColumnCheckAll() {
    const data = enrollmentMap[currentTermId] || new Set();
    document.querySelectorAll('.col-check-all').forEach(cb => {
        const seqId = parseInt(cb.dataset.seq);
        const allChecked = streams.every(s => data.has(makeKey(s.id, seqId)));
        cb.checked = allChecked && streams.length > 0;
    });
}

// ── No-stream toggle all ──
function toggleAllNoStream(checked) {
    const data = enrollmentMap[currentTermId];
    sequences.forEach(seq => {
        const key = makeKey(null, seq.id);
        if (checked) { data.add(key); } else { data.delete(key); }
    });
    document.querySelectorAll('.cell-check-no-stream').forEach(cb => { cb.checked = checked; });
    markDirty();
    updateStats();
}

function updateCheckAllNoStream() {
    const data = enrollmentMap[currentTermId] || new Set();
    const allChecked = sequences.every(seq => data.has(makeKey(null, seq.id)));
    const el = document.getElementById('check-all-no-stream');
    if (el) el.checked = allChecked && sequences.length > 0;
}

// ── Bulk Actions ──
function bulkAction(action) {
    const data = enrollmentMap[currentTermId];

    if (action === 'select-all') {
        if (hasStreams) {
            streams.forEach(s => {
                sequences.forEach(seq => { data.add(makeKey(s.id, seq.id)); });
            });
        } else {
            sequences.forEach(seq => { data.add(makeKey(null, seq.id)); });
        }
    } else if (action === 'clear') {
        data.clear();
    } else if (action === 'copy-to-all-terms') {
        const source = new Set(data);
        terms.forEach(t => {
            if (t.id !== currentTermId) {
                enrollmentMap[t.id] = new Set(source);
                dirtyTerms.add(String(t.id));
            }
        });
    }

    markDirty();
    renderTerm();
    updateTermCounts();
}

// ── Stats ──
function updateStats() {
    const data = enrollmentMap[currentTermId] || new Set();
    const totalAssignments = data.size;

    // Count unique sequences enrolled
    const enrolledSeqs = new Set();
    data.forEach(key => {
        const { seqId } = parseKey(key);
        enrolledSeqs.add(seqId);
    });

    document.getElementById('stat-enrolled').textContent = enrolledSeqs.size;
    document.getElementById('stat-total').textContent = totalAssignments;
}

// ── Term Counts ──
function updateTermCounts() {
    document.querySelectorAll('.term-tab').forEach(tab => {
        const tid = parseInt(tab.dataset.term);
        const data = enrollmentMap[tid] || new Set();
        tab.querySelector('.count-badge').textContent = data.size;
    });
}

// ── Dirty State ──
function markDirty() {
    dirtyTerms.add(String(currentTermId));
    updateDirtyUI();
    updateTermCounts();
}

function updateDirtyUI() {
    const hasDirty = dirtyTerms.size > 0;

    document.getElementById('btn-save').disabled = !hasDirty;
    document.getElementById('btn-save-bottom').disabled = !hasDirty;
    document.getElementById('unsaved-badge').classList.toggle('hidden', !hasDirty);

    document.querySelectorAll('.term-tab').forEach(tab => {
        const tid = tab.dataset.term;
        const dot = tab.querySelector('.dirty-dot');
        if (dot) dot.classList.toggle('hidden', !dirtyTerms.has(tid));
    });
}

// ── Save ──
async function saveAll() {
    if (saving || dirtyTerms.size === 0) return;
    saving = true;

    const btnSave = document.getElementById('btn-save');
    const btnSaveBottom = document.getElementById('btn-save-bottom');
    const btnText = document.getElementById('btn-save-text');
    btnText.textContent = '{{ __("Saving...") }}';
    btnSave.disabled = true;
    btnSaveBottom.disabled = true;

    let allSuccess = true;
    let totalSaved = 0;

    for (const tid of dirtyTerms) {
        const termId = parseInt(tid);
        const data = enrollmentMap[termId] || new Set();

        const assignments = [];
        data.forEach(key => {
            const { streamId, seqId } = parseKey(key);
            assignments.push({ stream_id: streamId, sequence_id: seqId });
        });

        try {
            const resp = await fetch(`{{ url('admin/sequence-enrollments') }}/${formId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    term_id: termId,
                    assignments: assignments
                })
            });

            const result = await resp.json();
            if (result.success) {
                totalSaved += result.count;
            } else {
                allSuccess = false;
            }
        } catch (e) {
            allSuccess = false;
        }
    }

    saving = false;
    btnText.textContent = '{{ __("Save") }}';

    if (allSuccess) {
        dirtyTerms.clear();
        updateDirtyUI();
        updateTermCounts();
        showToast('success', `{{ __("Saved successfully!") }} ${totalSaved} {{ __("assignment(s) saved.") }}`);
    } else {
        showToast('error', '{{ __("Some changes could not be saved. Please try again.") }}');
        updateDirtyUI();
    }
}

// ── Toast ──
function showToast(type, message) {
    const toast = document.getElementById('toast');
    const inner = document.getElementById('toast-inner');
    const msgEl = document.getElementById('toast-message');
    const iconSuccess = document.getElementById('toast-icon-success');
    const iconError = document.getElementById('toast-icon-error');

    msgEl.textContent = message;
    iconSuccess.classList.toggle('hidden', type !== 'success');
    iconError.classList.toggle('hidden', type !== 'error');

    if (type === 'success') {
        inner.className = 'flex items-center gap-3 px-5 py-3 rounded-lg shadow-lg border bg-emerald-50 border-emerald-200 text-emerald-800';
    } else {
        inner.className = 'flex items-center gap-3 px-5 py-3 rounded-lg shadow-lg border bg-red-50 border-red-200 text-red-800';
    }

    toast.classList.remove('hidden');
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    });

    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => toast.classList.add('hidden'), 300);
    }, 3000);
}

// ── Prevent Accidental Navigation ──
window.addEventListener('beforeunload', function(e) {
    if (dirtyTerms.size > 0) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// ── Init ──
document.addEventListener('DOMContentLoaded', init);
</script>
@endsection
