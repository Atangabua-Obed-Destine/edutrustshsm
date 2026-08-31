@extends('layouts.admin')
@section('title', __('Configuration Health'))

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-gray-800">{{ __('Configuration Health') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ __('What is set up, what is missing, and where to fix it') }}</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('Needs Attention') }}</p>
            <p class="text-3xl font-bold {{ $summary['errors'] > 0 ? 'text-red-600' : 'text-gray-300' }} mt-1">{{ $summary['errors'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('Warnings') }}</p>
            <p class="text-3xl font-bold {{ $summary['warnings'] > 0 ? 'text-amber-600' : 'text-gray-300' }} mt-1">{{ $summary['warnings'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 p-5">
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('Healthy') }}</p>
            <p class="text-3xl font-bold {{ $summary['healthy'] > 0 ? 'text-emerald-600' : 'text-gray-300' }} mt-1">{{ $summary['healthy'] }}</p>
        </div>
    </div>

    @if($summary['errors'] === 0 && $summary['warnings'] === 0)
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm text-emerald-700 font-medium">{{ __('Everything checked is configured. Nothing needs your attention.') }}</p>
        </div>
    </div>
    @endif

    @foreach([
        ['items' => $errors,   'title' => __('Needs Attention'), 'note' => __('These will stop something from working.'),      'ring' => 'border-red-200',   'chip' => 'bg-red-50 text-red-700',     'dot' => 'bg-red-500'],
        ['items' => $warnings, 'title' => __('Warnings'),        'note' => __('The school can work, but something downstream will misbehave.'), 'ring' => 'border-amber-200', 'chip' => 'bg-amber-50 text-amber-700', 'dot' => 'bg-amber-500'],
    ] as $group)
        @if(count($group['items']) > 0)
        <div class="space-y-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-800">{{ $group['title'] }}</h2>
                <p class="text-xs text-gray-500">{{ $group['note'] }}</p>
            </div>

            @foreach($group['items'] as $item)
            <div class="bg-white rounded-xl border {{ $group['ring'] }} p-5">
                <div class="flex items-start gap-4">
                    <span class="w-2 h-2 rounded-full {{ $group['dot'] }} mt-2 shrink-0"></span>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $group['chip'] }}">{{ $item['category'] }}</span>
                            <h3 class="font-semibold text-gray-800">{{ $item['title'] }}</h3>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">{{ $item['message'] }}</p>
                    </div>
                    @if($item['link'])
                    <a href="{{ $item['link'] }}" class="shrink-0 text-sm font-medium text-indigo-600 hover:text-indigo-700 whitespace-nowrap">
                        {{ $item['action'] }} &rarr;
                    </a>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    @endforeach

    @if(count($healthy) > 0)
    <div class="space-y-3">
        <h2 class="text-lg font-semibold text-gray-800">{{ __('Healthy') }}</h2>

        <div class="bg-white rounded-xl border border-gray-100 divide-y divide-gray-50">
            @foreach($healthy as $item)
            <div class="flex items-start gap-3 p-4">
                <svg class="w-4 h-4 text-emerald-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-700">{{ $item['title'] }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $item['message'] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
