@extends('layouts.admin')
@section('title', __('Class Council'))
@section('breadcrumb', __('Academics > Class Councils'))

@php
    $locked = $council->isConcluded();
    $awardStyles = [
        'congratulations' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'honour_roll' => 'bg-teal-50 text-teal-700 border-teal-200',
        'encouragement' => 'bg-sky-50 text-sky-700 border-sky-200',
        'none' => 'bg-white text-gray-600 border-gray-300',
        'work_warning' => 'bg-amber-50 text-amber-800 border-amber-200',
        'conduct_warning' => 'bg-rose-50 text-rose-700 border-rose-200',
    ];
@endphp

@section('content')
<div class="space-y-5" x-data="council()">

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-lg font-semibold text-gray-800">{{ $council->classSection?->name }}</h2>
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $locked ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $council->statusLabel() }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $council->reference }} · {{ $council->term?->name }}
                    @if($council->classSection?->form) · {{ $council->classSection->form->name }} @endif
                </p>
                @if($locked && $council->concluded_at)
                    <p class="text-xs text-gray-400 mt-1">
                        {{ __('Concluded :when by :who', [
                            'when' => $council->concluded_at->format('d M Y, H:i'),
                            'who' => $council->concludedBy ? $council->concludedBy->first_name.' '.$council->concludedBy->last_name : '—',
                        ]) }}
                    </p>
                @endif
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('admin.councils.minutes', $council) }}"
                   class="px-3 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50 transition">
                    {{ __('Minutes (PDF)') }}
                </a>

                @can('class-council.conclude')
                    @if($locked)
                        <form method="POST" action="{{ route('admin.councils.reopen', $council) }}">
                            @csrf
                            <button class="px-3 py-2 rounded-lg border border-amber-300 text-sm text-amber-700 hover:bg-amber-50 transition">
                                {{ __('Reopen') }}
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.councils.conclude', $council) }}"
                              onsubmit="return confirm('{{ __('Conclude this council? Its figures will be fixed to what the meeting saw.') }}')">
                            @csrf
                            <button class="px-4 py-2 rounded-lg bg-[#1e293b] hover:bg-[#334155] text-white text-sm font-medium transition">
                                {{ __('Conclude') }}
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        {{-- Figures --}}
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mt-5 pt-5 border-t border-gray-100">
            @php
                $cards = [
                    [__('Pupils'), $statistics['students'], null],
                    [__('Average'), $statistics['class_average'] !== null ? number_format($statistics['class_average'], 2) : '—', null],
                    [__('Pass rate'), $statistics['pass_rate'] !== null ? number_format($statistics['pass_rate'], 1).'%' : '—', ($statistics['pass_rate'] ?? 0) >= 50 ? 'text-emerald-600' : 'text-rose-600'],
                    [__('Passed'), $statistics['passed'], 'text-emerald-600'],
                    [__('Failed'), $statistics['failed'], 'text-rose-600'],
                    [__('Highest'), $statistics['highest_average'] !== null ? number_format($statistics['highest_average'], 2) : '—', null],
                ];
            @endphp
            @foreach($cards as [$label, $value, $tone])
                <div>
                    <p class="text-[0.65rem] uppercase tracking-wide text-gray-400">{{ $label }}</p>
                    <p class="text-lg font-bold {{ $tone ?? 'text-gray-800' }} mt-0.5">{{ $value ?? '—' }}</p>
                </div>
            @endforeach
        </div>

        @if($locked)
            <p class="text-xs text-gray-400 mt-4">
                {{ __('These are the figures the meeting saw. A mark corrected after the sitting does not change them.') }}
            </p>
        @endif
    </div>

    {{-- ── Meeting details ────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="text-sm font-semibold text-gray-700 mb-4">{{ __('The sitting') }}</h4>

        <form method="POST" action="{{ route('admin.councils.update', $council) }}">
            @csrf @method('PUT')
            <fieldset @disabled($locked) class="{{ $locked ? 'opacity-70' : '' }}">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Date') }}</label>
                        <input type="date" name="meeting_date" value="{{ old('meeting_date', $council->meeting_date?->format('Y-m-d')) }}"
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Venue') }}</label>
                        <input type="text" name="venue" value="{{ old('venue', $council->venue) }}" maxlength="150"
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Chaired by') }}</label>
                        <select name="chaired_by" style="appearance:auto;" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                            <option value="">—</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" @selected(old('chaired_by', $council->chaired_by) == $member->id)>{{ $member->first_name }} {{ $member->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Secretary') }}</label>
                        <select name="secretary_id" style="appearance:auto;" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                            <option value="">—</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" @selected(old('secretary_id', $council->secretary_id) == $member->id)>{{ $member->first_name }} {{ $member->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('What was discussed') }}</label>
                        <textarea name="deliberations" rows="4" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">{{ old('deliberations', $council->deliberations) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('What happens next') }}</label>
                        <textarea name="action_items" rows="4" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">{{ old('action_items', $council->action_items) }}</textarea>
                    </div>
                </div>

                @unless($locked)
                    <div class="flex justify-end mt-4">
                        <button type="submit" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50 transition">
                            {{ __('Save the sitting') }}
                        </button>
                    </div>
                @endunless
            </fieldset>
        </form>
    </div>

    {{-- ── Decisions ──────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('admin.councils.decisions', $council) }}" @change="dirty = true">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                <div>
                    <h4 class="text-sm font-semibold text-gray-700">{{ __('Decisions') }}</h4>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ __('Each award is suggested from the average and meant to be overridden. That is what the meeting is for.') }}
                    </p>
                </div>

                {{-- Live tally, so the shape of the class is visible while deciding --}}
                <div class="flex flex-wrap gap-1.5">
                    @foreach($awards as $value => $label)
                        <span class="px-2 py-1 rounded-full text-xs font-medium border {{ $awardStyles[$value] ?? 'bg-gray-50 text-gray-600 border-gray-200' }}">
                            {{ $label }} <span class="font-bold" x-text="tally['{{ $value }}'] ?? 0"></span>
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
                            <th class="py-2 pr-3 w-12">{{ __('Rank') }}</th>
                            <th class="py-2 px-3">{{ __('Pupil') }}</th>
                            <th class="py-2 px-3 text-right w-20">{{ __('Average') }}</th>
                            <th class="py-2 px-3 w-48">{{ __('Award') }}</th>
                            <th class="py-2 px-3 w-36">{{ __('Conduct') }}</th>
                            <th class="py-2 pl-3">{{ __('Remark') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $entry)
                            @php($failing = $entry->average !== null && (float) $entry->average < $passMark)
                            <tr class="border-b border-gray-50 {{ $failing ? 'bg-rose-50/40' : '' }}">
                                <td class="py-2 pr-3 text-gray-400">{{ $entry->rank ?? '—' }}</td>
                                <td class="py-2 px-3">
                                    <div class="font-medium text-gray-800">{{ $entry->student?->first_name }} {{ $entry->student?->last_name }}</div>
                                    <div class="text-xs text-gray-400">{{ $entry->student?->student_id }}</div>
                                </td>
                                <td class="py-2 px-3 text-right font-semibold {{ $failing ? 'text-rose-600' : 'text-gray-800' }}">
                                    {{ $entry->average !== null ? number_format($entry->average, 2) : '—' }}
                                </td>
                                <td class="py-2 px-3">
                                    <select name="entries[{{ $entry->id }}][award]"
                                            @disabled($locked)
                                            x-model="awards[{{ $entry->id }}]"
                                            style="appearance:auto;"
                                            class="w-full text-xs font-medium border rounded-lg px-2 py-1.5 transition
                                                   {{ $awardStyles[$entry->award] ?? 'bg-white border-gray-300' }}">
                                        @foreach($awards as $value => $label)
                                            <option value="{{ $value }}" @selected($entry->award === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2 px-3">
                                    <select name="entries[{{ $entry->id }}][conduct]" @disabled($locked) style="appearance:auto;"
                                            class="w-full text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white">
                                        <option value="">—</option>
                                        @foreach(['excellent' => __('Excellent'), 'very_good' => __('Very good'), 'good' => __('Good'), 'fair' => __('Fair'), 'poor' => __('Poor')] as $value => $label)
                                            <option value="{{ $value }}" @selected($entry->conduct === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2 pl-3">
                                    <input type="text" name="entries[{{ $entry->id }}][remark]" value="{{ $entry->remark }}"
                                           @disabled($locked) maxlength="500"
                                           placeholder="{{ $locked ? '' : __('The council\'s words for this pupil') }}"
                                           class="w-full text-xs border border-gray-300 rounded-lg px-2 py-1.5">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($entries->isEmpty())
                <p class="text-sm text-gray-500 py-6 text-center">{{ __('No pupil in this class has a published result for the term.') }}</p>
            @endif
        </div>

        {{-- Sticky save bar, so the button is never a scroll away --}}
        @unless($locked)
            @if($entries->isNotEmpty())
                <div class="sticky bottom-4 mt-4 flex justify-end">
                    <div class="bg-white/95 backdrop-blur rounded-xl shadow-lg border border-gray-200 px-4 py-3 flex items-center gap-4">
                        <span class="text-xs text-gray-500" x-show="dirty" x-cloak>{{ __('Unsaved changes') }}</span>
                        <button type="submit" class="px-5 py-2 rounded-lg bg-[#1e293b] hover:bg-[#334155] text-white text-sm font-medium transition">
                            {{ __('Save decisions') }}
                        </button>
                    </div>
                </div>
            @endif
        @endunless
    </form>

    {{-- ── Signatures ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="text-sm font-semibold text-gray-700">{{ __('Who sat') }}</h4>
        <p class="text-xs text-gray-500 mt-0.5 mb-4">{{ __('A decision nobody is named against is not one the school can stand behind later.') }}</p>

        @if($council->signatures->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach($council->signatures as $signature)
                    <span class="inline-flex items-center gap-2 pl-3 pr-2 py-1.5 rounded-full bg-gray-50 border border-gray-200 text-sm">
                        <span class="text-gray-800">{{ $signature->name }}</span>
                        @if($signature->role)<span class="text-xs text-gray-400">{{ $signature->role }}</span>@endif
                        @unless($locked)
                            <form method="POST" action="{{ route('admin.councils.signatures.destroy', [$council, $signature]) }}">
                                @csrf @method('DELETE')
                                <button class="text-gray-300 hover:text-rose-600 transition" title="{{ __('Remove') }}">&times;</button>
                            </form>
                        @endunless
                    </span>
                @endforeach
            </div>
        @endif

        @unless($locked)
            <form method="POST" action="{{ route('admin.councils.signatures.store', $council) }}" class="flex flex-wrap gap-2 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Name') }}</label>
                    <input type="text" name="name" required maxlength="150" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Role') }}</label>
                    <input type="text" name="role" maxlength="100" placeholder="{{ __('Class teacher, parent representative…') }}"
                           class="text-sm border border-gray-300 rounded-lg px-3 py-2 w-64">
                </div>
                <button type="submit" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 hover:bg-gray-50 transition">
                    {{ __('Add') }}
                </button>
            </form>
        @endunless
    </div>

    @unless($locked)
        @can('class-council.delete')
            <form method="POST" action="{{ route('admin.councils.destroy', $council) }}"
                  onsubmit="return confirm('{{ __('Delete this council and its decisions?') }}')" class="flex justify-end">
                @csrf @method('DELETE')
                <button class="text-xs text-rose-600 hover:text-rose-700">{{ __('Delete this council') }}</button>
            </form>
        @endcan
    @endunless
</div>

@push('scripts')
<script>
function council() {
    return {
        dirty: false,
        // Mirrors the selects so the tally and the chip colours follow the
        // decisions as they are made, not after a save.
        awards: @json($entries->pluck('award', 'id')),
        styles: @json($awardStyles),

        get tally() {
            const counts = {};
            Object.values(this.awards).forEach(a => { counts[a] = (counts[a] || 0) + 1; });
            return counts;
        },

        init() {
            this.$watch('awards', () => this.paint(), { deep: true });
            this.paint();
        },

        // Recolour each select to match its value, so praise and warning are
        // distinguishable down a long class list without reading every row.
        paint() {
            Object.entries(this.awards).forEach(([id, award]) => {
                const el = this.$el.querySelector(`[name="entries[${id}][award]"]`);
                if (!el) return;
                el.className = 'w-full text-xs font-medium border rounded-lg px-2 py-1.5 transition ' +
                    (this.styles[award] || 'bg-white border-gray-300');
            });
        },
    };
}
</script>
@endpush
@endsection
