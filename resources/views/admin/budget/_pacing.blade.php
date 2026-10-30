@php
    // Said in words, not only in colour: a gap of +18 points means little to
    // a bursar on a Friday afternoon; "spending is running ahead of the
    // year" is something they can take to the head.
    $tones = [
        'good' => ['bg-emerald-50 border-emerald-200', 'text-emerald-900', __('Spending is keeping pace with the year.')],
        'warning' => ['bg-amber-50 border-amber-200', 'text-amber-900', __('Spending is running a little ahead of the year.')],
        'critical' => ['bg-red-50 border-red-200', 'text-red-900', __('Spending is running well ahead of the year. At this rate the budget runs out early.')],
        'none' => ['bg-gray-50 border-gray-200', 'text-gray-700', __('No total has been set, so there is nothing to pace against.')],
    ];
    [$box, $text, $sentence] = $tones[$pacing['status']] ?? $tones['none'];
@endphp
<div class="rounded-xl border p-5 {{ $box }}">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <p class="text-sm font-semibold {{ $text }}">{{ $sentence }}</p>
            <p class="text-xs {{ $text }} opacity-80 mt-1">
                {{ __(':elapsed% of the period has passed and :consumed% of the money has been spent. At this rate the year ends at :projected. :days days left.', [
                    'elapsed' => number_format($pacing['elapsed'], 0),
                    'consumed' => number_format($pacing['consumed'], 0),
                    'projected' => number_format($pacing['projected'], 0),
                    'days' => $pacing['days_left'],
                ]) }}
            </p>
        </div>
        <div class="w-full md:w-72 space-y-1.5 text-xs {{ $text }}">
            <div class="flex items-center gap-2">
                <span class="w-14">{{ __('Year') }}</span>
                <div class="flex-1 h-2 bg-white/70 rounded-full overflow-hidden"><div class="h-2 bg-gray-400" style="width: {{ min(100, round($pacing['elapsed'])) }}%"></div></div>
                <span class="w-10 text-right">{{ number_format($pacing['elapsed'], 0) }}%</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="w-14">{{ __('Money') }}</span>
                <div class="flex-1 h-2 bg-white/70 rounded-full overflow-hidden"><div class="h-2 {{ $pacing['status'] === 'critical' ? 'bg-red-500' : ($pacing['status'] === 'warning' ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min(100, round($pacing['consumed'])) }}%"></div></div>
                <span class="w-10 text-right">{{ number_format($pacing['consumed'], 0) }}%</span>
            </div>
        </div>
    </div>
</div>
