@extends('layouts.admin')
@section('title', __('Budget Lines'))
@section('breadcrumb', __('Budgets > Budget Lines'))

@section('content')
@php
    $sectionLabels = \App\Models\BudgetLine::sectionLabels();
    $typeLabels = ['fee_payment' => __('Fee category'), 'income' => __('Income category'), 'expense' => __('Expense category')];
    $allLines = collect($sections)->flatten(1);
    $postable = fn (string $section) => $allLines->where('section', $section)->where('is_header', false)->where('status', true)->values();
    $canEdit = auth()->user()->can('budget-line.edit');
@endphp
<div class="space-y-5" x-data="{ editing: null, linking: null, help: false }">

    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Budget Lines') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5 max-w-3xl">
                {{ __('The rows of the Income & Expenditure sheet, and what feeds them. Money reaches a line through a category; a line no category points at prints as a permanent zero, however much is really spent.') }}
                <button type="button" @click="help = !help" class="text-teal-700 hover:text-teal-900 font-medium ml-1">{{ __('How the sheet is built') }}</button>
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @can('budget-sheet.view')
                <a href="{{ route('admin.budget-sheet.index') }}" class="text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg px-3 py-2">{{ __('Income & Expenditure sheet') }}</a>
            @endcan
        </div>
    </div>

    <div x-show="help" x-cloak class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 text-sm text-gray-600 grid grid-cols-1 md:grid-cols-3 gap-5">
        <div>
            <h3 class="font-semibold text-gray-800 mb-1">{{ __('Headings and lines') }}</h3>
            <p>{{ __('A heading is a title only — ADMINISTRATION, PEDAGOGY. It holds no money of its own; it gathers the lines filed beneath it and the sheet totals them into a subtotal. The lines underneath are where money actually lands. The sheet is two levels deep and no more.') }}</p>
        </div>
        <div>
            <h3 class="font-semibold text-gray-800 mb-1">{{ __('Before a line will show any money') }}</h3>
            <p>{{ __('Income lines can be fed by a fee category or an income category. Expenditure lines can be fed by an expense category. Salaries have no category: tick "fed by payroll" on the line that reports them. Tuition is split by school level when a line names one.') }}</p>
        </div>
        <div>
            <h3 class="font-semibold text-gray-800 mb-1">{{ __('Retiring rather than deleting') }}</h3>
            <p>{{ __('A line that budgeted figures, transactions or categories already point at cannot be deleted — doing so would orphan money. Retire it instead: it leaves the sheet from now on and its history stays intact. Give an income line and an expenditure line the same profit centre to see what that activity earned, cost and made.') }}</p>
        </div>
    </div>

    @if($empty)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <h3 class="font-semibold text-gray-800">{{ __('The sheet has no lines yet.') }}</h3>
            <p class="text-sm text-gray-500 mt-1 max-w-xl mx-auto">{{ __('Start from the standard Income & Expenditure sheet for a school — income in the 600s, expenditure in the 400s and 500s, capital in the 540s — then rename, add and retire lines to match this school. Or build your own below.') }}</p>
            @if($canEdit)
                <form method="POST" action="{{ route('admin.budget-line.template') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">{{ __('Load the standard sheet') }}</button>
                </form>
            @endif
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-5">
        <div class="xl:col-span-3 space-y-5">
            @foreach($sections as $section => $rows)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" data-section="{{ $section }}">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/60 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-800">{{ $sectionLabels[$section] }} <span class="text-xs font-normal text-gray-400 ml-1">{{ trans_choice('{0} no lines|{1} :count line|[2,*] :count lines', $rows->count(), ['count' => $rows->count()]) }}</span></h3>
                        @if($canEdit && $rows->isNotEmpty())
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-gray-400 hidden sm:inline" data-order-status>{{ __('Drag a row by its grip to reorder; drop it under a heading to file it there.') }}</span>
                                <form method="POST" action="{{ route('admin.budget-line.auto-sort') }}" onsubmit="return confirm('{{ __('Renumber this whole section from the codes? Your headings stay as they are.') }}')">
                                    @csrf
                                    <input type="hidden" name="section" value="{{ $section }}">
                                    <button type="submit" class="text-gray-500 hover:text-gray-800">{{ __('Renumber from codes') }}</button>
                                </form>
                            </div>
                        @endif
                    </div>

                    <ul class="divide-y divide-gray-100" data-lines>
                        @forelse($rows as $line)
                            @php
                                $feeds = $byLine->get($line->id, collect());
                                $refs = $references[$line->id] ?? [];
                            @endphp
                            <li class="group {{ $line->is_header ? 'bg-gray-50' : '' }} {{ $line->status ? '' : 'opacity-50' }}"
                                data-line="{{ $line->id }}" data-header="{{ $line->is_header ? 1 : 0 }}" data-parent="{{ $line->parent_id ?? '' }}" draggable="{{ $canEdit ? 'true' : 'false' }}">
                                <div class="px-4 py-2.5 flex items-start gap-3 {{ $line->is_header ? '' : 'pl-8' }}" x-show="editing !== {{ $line->id }}">
                                    @if($canEdit)
                                        <span class="cursor-grab text-gray-300 group-hover:text-gray-500 select-none mt-0.5" title="{{ __('Drag') }}">⋮⋮</span>
                                    @endif
                                    <span class="font-mono text-xs text-gray-500 w-10 shrink-0 mt-0.5">{{ $line->code }}</span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="text-sm {{ $line->is_header ? 'font-semibold uppercase tracking-wide text-gray-700' : 'text-gray-800' }}">{{ $line->displayName() }}</span>
                                            @if($line->name_fr && app()->getLocale() !== 'fr') <span class="text-xs text-gray-400">{{ $line->name_fr }}</span> @endif
                                            @unless($line->status) <span class="text-[11px] px-1.5 py-0.5 rounded bg-gray-200 text-gray-600">{{ __('retired') }}</span> @endunless
                                            @if($line->school_level) <span class="text-[11px] px-1.5 py-0.5 rounded bg-sky-50 text-sky-700">{{ $levels[$line->school_level] ?? $line->school_level }}</span> @endif
                                            @if($line->profit_centre) <span class="text-[11px] px-1.5 py-0.5 rounded bg-violet-50 text-violet-700">{{ $line->profit_centre }}</span> @endif
                                            @if($line->is_payroll) <span class="text-[11px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-700">{{ __('fed by payroll') }}</span> @endif
                                            @unless($line->is_standard) <span class="text-[11px] text-gray-400">{{ __('local') }}</span> @endunless
                                        </div>
                                        @unless($line->is_header)
                                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                                @forelse($feeds as $c)
                                                    <span class="px-1.5 py-0.5 rounded border border-gray-200 text-gray-600" title="{{ $typeLabels[$c['type']] }}">{{ $c['title'] }}</span>
                                                @empty
                                                    @unless($line->is_payroll)
                                                        <span class="text-amber-700">{{ __('no category points here yet') }}</span>
                                                    @endunless
                                                @endforelse
                                                @if($canEdit && $line->status)
                                                    <button type="button" @click="linking = linking === {{ $line->id }} ? null : {{ $line->id }}" class="text-teal-700 hover:text-teal-900 font-medium">{{ __('Give this line a category') }}</button>
                                                @endif
                                            </div>
                                        @endunless
                                    </div>
                                    @if($canEdit)
                                        <div class="flex items-center gap-2 text-xs shrink-0 opacity-0 group-hover:opacity-100 focus-within:opacity-100">
                                            <button type="button" @click="editing = {{ $line->id }}" class="text-gray-500 hover:text-gray-800">{{ __('Edit') }}</button>
                                            <form method="POST" action="{{ route('admin.budget-line.toggle', $line) }}">
                                                @csrf
                                                <button type="submit" class="text-gray-500 hover:text-gray-800">{{ $line->status ? __('Retire') : __('Restore') }}</button>
                                            </form>
                                            @if($refs === [])
                                                <form method="POST" action="{{ route('admin.budget-line.destroy', $line) }}" onsubmit="return confirm('{{ __('Delete :code from the sheet?', ['code' => $line->code]) }}')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="text-red-500 hover:text-red-700">{{ __('Delete') }}</button>
                                                </form>
                                            @else
                                                <span class="text-gray-300" title="{{ implode(', ', $refs) }}">{{ __('in use') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                @if($canEdit)
                                    {{-- Give this line a category --}}
                                    <div x-show="linking === {{ $line->id }}" x-cloak class="px-4 pb-3 pl-8 lg:pl-24">
                                        <div class="bg-teal-50/60 border border-teal-100 rounded-lg p-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                            <form method="POST" action="{{ route('admin.budget-line.category', $line) }}" class="space-y-1.5">
                                                @csrf
                                                <p class="font-medium text-gray-700">{{ __('Create a new category') }}</p>
                                                <div class="flex gap-2">
                                                    <input type="text" name="title" value="{{ $line->name }}" required maxlength="191" class="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-xs">
                                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-teal-600 text-white font-medium">{{ __('Create and link') }}</button>
                                                </div>
                                                <p class="text-gray-500">{{ $line->section === 'income' ? __('Creates an income category. Fee categories are billable to pupils and are created on the Fees screen — link one of those instead.') : __('Creates an expense category.') }}</p>
                                            </form>
                                            <form method="POST" action="{{ route('admin.budget-line.link', $line) }}" class="space-y-1.5" x-data="{ pick: '' }">
                                                @csrf
                                                <p class="font-medium text-gray-700">{{ __('Link an existing one') }}</p>
                                                <div class="flex gap-2">
                                                    <select x-model="pick" required style="appearance:auto;" class="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-xs bg-white">
                                                        <option value="">{{ __('Choose a category') }}</option>
                                                        @foreach(\App\Http\Controllers\Admin\BudgetLineController::categoryTypesFor($line->section) as $type)
                                                            @foreach(($unlinked->get($type, collect()))->concat($byLine->flatten(1)->where('type', $type)) as $c)
                                                                <option value="{{ $type }}:{{ $c['id'] }}">{{ $typeLabels[$type] }} — {{ $c['title'] }}{{ $c['line_id'] && $c['line_id'] !== $line->id ? ' ('.__('now on another line').')' : '' }}</option>
                                                            @endforeach
                                                        @endforeach
                                                    </select>
                                                    <input type="hidden" name="type" :value="pick.split(':')[0]">
                                                    <input type="hidden" name="category_id" :value="pick.split(':')[1] || ''">
                                                    <button type="submit" class="px-3 py-1.5 rounded-lg border border-gray-300 bg-white font-medium text-gray-700">{{ __('Point it at this line') }}</button>
                                                </div>
                                                <p class="text-gray-500">{{ __('Pointing a category here takes its figures off any line it was on, which will then read zero.') }}</p>
                                            </form>
                                        </div>
                                    </div>

                                    {{-- Edit --}}
                                    <form x-show="editing === {{ $line->id }}" x-cloak method="POST" action="{{ route('admin.budget-line.update', $line) }}" class="px-4 py-3 bg-gray-50 border-t border-gray-100">
                                        @csrf @method('PUT')
                                        @include('admin.budget-line._fields', ['line' => $line, 'section' => $line->section])
                                        <div class="flex items-center gap-2 mt-3">
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-gray-800 text-white text-xs font-medium">{{ __('Save') }}</button>
                                            <button type="button" @click="editing = null" class="text-xs text-gray-500">{{ __('Cancel') }}</button>
                                        </div>
                                    </form>
                                @endif
                            </li>
                        @empty
                            <li class="px-5 py-4 text-sm text-gray-400">{{ __('No lines in this section yet.') }}</li>
                        @endforelse
                    </ul>

                    @if($canEdit)
                        <details class="border-t border-gray-100">
                            <summary class="px-5 py-2.5 text-sm text-teal-700 cursor-pointer hover:bg-gray-50">{{ __('Add a line to :section', ['section' => $sectionLabels[$section]]) }}</summary>
                            <form method="POST" action="{{ route('admin.budget-line.store') }}" class="px-5 pb-4">
                                @csrf
                                @include('admin.budget-line._fields', ['line' => null, 'section' => $section])
                                <button type="submit" class="mt-3 px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-medium">{{ __('Add line') }}</button>
                            </form>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Categories not on the sheet --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/60">
                    <h3 class="font-semibold text-gray-800 text-sm">{{ __('Not on the sheet yet') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('Money in these categories shows on no line until each is pointed at one.') }}</p>
                </div>
                @if($unlinked->isEmpty())
                    <p class="px-4 py-4 text-sm text-emerald-700">{{ __('Every category is on the sheet.') }}</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach($unlinked as $type => $cats)
                            @foreach($cats as $c)
                                <li class="px-4 py-2.5 text-sm">
                                    <div class="text-gray-800">{{ $c['title'] }} <span class="text-xs text-gray-400">· {{ $typeLabels[$type] }}</span></div>
                                    @if($canEdit && ! $empty)
                                        <form method="POST" x-data="{ line: '' }" :action="line ? '{{ url('admin/budget-line') }}/' + line + '/link' : '#'" class="mt-1.5 flex gap-1.5">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $type }}">
                                            <input type="hidden" name="category_id" value="{{ $c['id'] }}">
                                            <select x-model="line" required style="appearance:auto;" class="flex-1 min-w-0 text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white">
                                                <option value="">{{ __('Point it at…') }}</option>
                                                @foreach(($type === 'expense' ? ['expenditure', 'capital'] : ['income']) as $sec)
                                                    <optgroup label="{{ $sectionLabels[$sec] }}">
                                                        @foreach($postable($sec) as $l)
                                                            <option value="{{ $l->id }}">{{ $l->code }} {{ $l->displayName() }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
                                            <button type="submit" :disabled="!line" class="px-2.5 py-1.5 rounded-lg bg-teal-600 text-white text-xs font-medium disabled:opacity-40">{{ __('Link') }}</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

@if($canEdit)
<script>
// Drag a row; drop it where it should sit. The section's rows are then posted
// in their new order, each with the heading it now sits under.
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    document.querySelectorAll('[data-section]').forEach(function (card) {
        const list = card.querySelector('[data-lines]');
        const status = card.querySelector('[data-order-status]');
        if (!list) return;
        let dragging = null;

        list.addEventListener('dragstart', function (e) {
            dragging = e.target.closest('[data-line]');
            if (!dragging) return;
            e.dataTransfer.effectAllowed = 'move';
            dragging.classList.add('opacity-40');
        });
        list.addEventListener('dragend', function () {
            if (dragging) dragging.classList.remove('opacity-40');
            dragging = null;
        });
        list.addEventListener('dragover', function (e) {
            if (!dragging) return;
            e.preventDefault();
            const over = e.target.closest('[data-line]');
            if (!over || over === dragging) return;
            const rect = over.getBoundingClientRect();
            const after = (e.clientY - rect.top) > rect.height / 2;
            list.insertBefore(dragging, after ? over.nextSibling : over);
        });
        list.addEventListener('drop', function (e) {
            if (!dragging) return;
            e.preventDefault();
            // A heading gathers every row after it until the next heading.
            let parent = null;
            const lines = [];
            list.querySelectorAll('[data-line]').forEach(function (row) {
                const isHeader = row.dataset.header === '1';
                if (isHeader) { parent = row.dataset.line; lines.push({ id: row.dataset.line, parent_id: null }); return; }
                lines.push({ id: row.dataset.line, parent_id: parent });
                row.dataset.parent = parent || '';
                row.querySelector('div').classList.toggle('pl-8', true);
            });
            if (status) status.textContent = @json(__('Saving order…'));
            fetch(@json(route('admin.budget-line.reorder')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ section: card.dataset.section, lines: lines }),
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
              .then(function (res) {
                  if (status) status.textContent = res.ok ? @json(__('Order saved')) : (res.j.message || @json(__('Order not saved — reload the page')));
              }).catch(function () { if (status) status.textContent = @json(__('Order not saved — reload the page')); });
        });
    });
});
</script>
@endif
@endsection
