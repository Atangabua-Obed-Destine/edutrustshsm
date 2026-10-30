@extends('layouts.admin')
@section('title', __('School Calendar'))
@section('breadcrumb', __('Communication > School Calendar'))

@section('content')
<div class="space-y-4">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('School Calendar') }}</h2>
            <p class="text-sm text-gray-500">{{ __('Dated entries parents and staff can see in advance.') }}</p>
        </div>
        @can('event.create')
            <a href="{{ route('admin.events.create') }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                {{ __('Add an event') }}
            </a>
        @endcan
    </div>

    <div class="flex gap-2">
        <a href="{{ route('admin.events.index', ['showing' => 'upcoming']) }}"
           class="px-3 py-1.5 rounded-lg text-sm {{ $showing !== 'past' ? 'bg-[#1e293b] text-white' : 'bg-gray-100 text-gray-600' }}">{{ __('Coming up') }}</a>
        <a href="{{ route('admin.events.index', ['showing' => 'past']) }}"
           class="px-3 py-1.5 rounded-lg text-sm {{ $showing === 'past' ? 'bg-[#1e293b] text-white' : 'bg-gray-100 text-gray-600' }}">{{ __('Earlier') }}</a>
    </div>

    @forelse($events as $event)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
            <div class="text-center bg-gray-50 rounded-lg px-3 py-2 shrink-0">
                <div class="text-lg font-bold text-gray-700 leading-none">{{ $event->starts_on->format('j') }}</div>
                <div class="text-[0.6rem] uppercase text-gray-500">{{ $event->starts_on->format('M') }}</div>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    @if($event->category)
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $event->category->badgeClasses() }}">{{ $event->category->name }}</span>
                    @endif
                    <h3 class="font-semibold text-gray-800">{{ $event->title }}</h3>
                    @if(! $event->isPublished())
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs">{{ __('Draft') }}</span>
                    @elseif($event->isRunning())
                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs">{{ __('On now') }}</span>
                    @endif
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $event->whenLabel() }}
                    @if(! $event->isAllDay()) · {{ \Illuminate\Support\Str::substr($event->starts_at, 0, 5) }} @else · {{ __('All day') }} @endif
                    @if($event->venue) · {{ $event->venue }} @endif
                    · {{ $event->reach() }}
                </p>
                @if($event->description)
                    <p class="text-sm text-gray-600 mt-2">{{ \Illuminate\Support\Str::limit($event->description, 160) }}</p>
                @endif
            </div>

            <div class="flex flex-col items-end gap-1 shrink-0">
                @can('event.edit')
                    <form method="POST" action="{{ route('admin.events.toggle', $event) }}">@csrf
                        <button class="text-xs font-medium text-teal-600 hover:text-teal-700">{{ $event->published_at ? __('Unpublish') : __('Publish') }}</button>
                    </form>
                    <a href="{{ route('admin.events.edit', $event) }}" class="text-xs text-gray-500 hover:text-gray-700">{{ __('Edit') }}</a>
                @endcan
                @can('event.delete')
                    <form method="POST" action="{{ route('admin.events.destroy', $event) }}" onsubmit="return confirm('{{ __('Delete this event?') }}')">@csrf @method('DELETE')
                        <button class="text-xs text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                    </form>
                @endcan
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <p class="text-sm text-gray-500">{{ $showing === 'past' ? __('Nothing has happened yet.') : __('Nothing is scheduled.') }}</p>
        </div>
    @endforelse

    {{ $events->links() }}
</div>
@endsection
