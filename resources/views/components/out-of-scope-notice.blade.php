@php
    // Shown wherever a list is filtered by the school's level, so that an
    // empty page is never mistaken for an empty school. Quiet when there is
    // nothing hidden, and for anyone who may not see pupils anyway.
    $hidden = app(\App\Services\OutOfScopeService::class);
    $show = auth()->user()?->can('student.view') && $hidden->hasAny();
    $summary = $show ? $hidden->summary() : null;
    $levelNames = $show
        ? collect($summary['levels'])->map(fn ($l) => \App\Support\LevelContext::labels()[$l] ?? $l)->join(', ')
        : '';
@endphp
@if($show)
<div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3" role="status">
    <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
    <p class="text-sm text-amber-800 flex-1">
        @if($summary['pupils'] > 0)
            {{ trans_choice('{1} :count pupil is enrolled in a :level class.|[2,*] :count pupils are enrolled in :level classes.', $summary['pupils'], ['count' => $summary['pupils'], 'level' => $levelNames]) }}
        @else
            {{ trans_choice('{1} :count :level class is still active.|[2,*] :count :level classes are still active.', $summary['classes'], ['count' => $summary['classes'], 'level' => $levelNames]) }}
        @endif
        {{ __('This school does not run that level, so they are left out of every list and count here.') }}
    </p>
    <div class="flex items-center gap-3 shrink-0 text-sm">
        <a href="{{ route('admin.students.out-of-scope') }}" class="font-medium text-amber-900 underline underline-offset-2 hover:text-amber-950">{{ __('Review them') }}</a>
        @can('school-settings.view')
        <a href="{{ route('admin.settings.index') }}#level-mode" class="text-amber-700 hover:text-amber-900">{{ __('Change school level') }}</a>
        @endcan
    </div>
</div>
@endif
