@extends('layouts.admin')
@section('title', __('Notice Board'))
@section('breadcrumb', __('Communication > Notice Board'))

@section('content')
<div class="space-y-4">

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Notice Board') }}</h2>
            <p class="text-sm text-gray-500">{{ __('What the school has told parents and staff.') }}</p>
        </div>
        @can('announcement.create')
            <a href="{{ route('admin.announcements.create') }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                {{ __('Write a notice') }}
            </a>
        @endcan
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Category') }}</label>
            <select name="category" class="text-sm border border-gray-300 rounded-lg px-3 py-2" style="appearance:auto;">
                <option value="">{{ __('All categories') }}</option>
                @foreach($categories as $category)
                    <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Audience') }}</label>
            <select name="audience" class="text-sm border border-gray-300 rounded-lg px-3 py-2" style="appearance:auto;">
                <option value="">{{ __('Anyone') }}</option>
                <option value="guardians" @selected(($filters['audience'] ?? '') === 'guardians')>{{ __('Parents') }}</option>
                <option value="staff" @selected(($filters['audience'] ?? '') === 'staff')>{{ __('Staff') }}</option>
                <option value="both" @selected(($filters['audience'] ?? '') === 'both')>{{ __('Parents and staff') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Status') }}</label>
            <select name="status" class="text-sm border border-gray-300 rounded-lg px-3 py-2" style="appearance:auto;">
                <option value="">{{ __('Any status') }}</option>
                <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('Draft') }}</option>
                <option value="published" @selected(($filters['status'] ?? '') === 'published')>{{ __('Published') }}</option>
                <option value="expired" @selected(($filters['status'] ?? '') === 'expired')>{{ __('Expired') }}</option>
            </select>
        </div>
        <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
        <a href="{{ route('admin.announcements.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-2 py-2">{{ __('Clear') }}</a>
    </form>

    @forelse($announcements as $announcement)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($announcement->category)
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $announcement->category->badgeClasses() }}">{{ $announcement->category->name }}</span>
                        @endif
                        <h3 class="font-semibold text-gray-800">{{ $announcement->title }}</h3>
                        <span class="px-2 py-0.5 rounded-full text-xs
                            {{ $announcement->isPublished() && ! $announcement->hasExpired() ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $announcement->status() }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">{{ \Illuminate\Support\Str::limit($announcement->body, 180) }}</p>
                    <p class="text-xs text-gray-400 mt-2">
                        {{ $announcement->reference }}
                        · {{ $announcement->reach() }}
                        · {{ __(match($announcement->audience) {
                            'staff' => 'Staff',
                            'both' => 'Parents and staff',
                            default => 'Parents',
                        }) }}
                        @if($announcement->expires_at) · {{ __('Until :date', ['date' => $announcement->expires_at->format('d M Y')]) }} @endif
                        @if($announcement->attachment) · {{ __('Attachment') }} @endif
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1 shrink-0">
                    @can('announcement.publish')
                        <form method="POST" action="{{ route('admin.announcements.toggle', $announcement) }}">@csrf
                            <button class="text-xs font-medium text-teal-600 hover:text-teal-700">
                                {{ $announcement->published_at ? __('Unpublish') : __('Publish') }}
                            </button>
                        </form>
                    @endcan
                    @can('announcement.edit')
                        <a href="{{ route('admin.announcements.edit', $announcement) }}" class="text-xs text-gray-500 hover:text-gray-700">{{ __('Edit') }}</a>
                    @endcan
                    @can('announcement.delete')
                        <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('{{ __('Delete this notice?') }}')">@csrf @method('DELETE')
                            <button class="text-xs text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <p class="text-sm text-gray-500">{{ __('No notices yet. Anything posted here reaches parents in their portal.') }}</p>
        </div>
    @endforelse

    {{ $announcements->links() }}
</div>
@endsection
