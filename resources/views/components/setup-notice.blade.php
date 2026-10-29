@php
    // The configuration health page knows what is broken, but a page nobody
    // visits warns nobody. Only the admins who can fix things see this, and
    // it is quiet when everything is set.
    $show = auth()->user()?->can('school-settings.view');
    $report = $show ? app(\App\Services\ConfigurationHealthService::class)->report() : null;
    $items = $report ? array_merge($report['errors'], $report['warnings']) : [];
    $broken = $report['summary']['errors'] ?? 0;
@endphp
@if($show && count($items) > 0)
<div class="{{ $broken > 0 ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200' }} border rounded-xl px-4 py-3" role="status">
    <div class="flex flex-col sm:flex-row sm:items-start gap-3">
        <svg class="w-5 h-5 shrink-0 mt-0.5 {{ $broken > 0 ? 'text-red-500' : 'text-amber-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold {{ $broken > 0 ? 'text-red-900' : 'text-amber-900' }}">
                @if($broken > 0)
                    {{ trans_choice('{1} :count setting is missing, so part of the school cannot work yet.|[2,*] :count settings are missing, so parts of the school cannot work yet.', $broken, ['count' => $broken]) }}
                @else
                    {{ trans_choice('{1} :count setting needs attention.|[2,*] :count settings need attention.', count($items), ['count' => count($items)]) }}
                @endif
            </p>
            <ul class="mt-1.5 space-y-1 text-sm {{ $broken > 0 ? 'text-red-800' : 'text-amber-800' }}">
                @foreach(array_slice($items, 0, 3) as $item)
                    <li class="flex flex-wrap items-baseline gap-x-2">
                        <span class="font-medium">{{ $item['title'] }}</span>
                        <span class="opacity-80">{{ $item['message'] }}</span>
                        @if($item['link'])
                            <a href="{{ $item['link'] }}" class="underline underline-offset-2 font-medium whitespace-nowrap">{{ $item['action'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        <a href="{{ route('admin.configuration-health.index') }}" class="shrink-0 text-sm font-medium underline underline-offset-2 {{ $broken > 0 ? 'text-red-900' : 'text-amber-900' }}">
            @if(count($items) > 3)
                {{ __('See all :count', ['count' => count($items)]) }}
            @else
                {{ __('Configuration Health') }}
            @endif
        </a>
    </div>
</div>
@endif
