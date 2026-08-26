@extends('layouts.admin')

@section('title', __('Streams'))
@section('breadcrumb', __('Academic > Streams'))

@section('content')
<div class="max-w-4xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ __('Streams') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Manage academic streams (e.g., Science, Arts, Commercial)') }}</p>
        </div>
        <a href="{{ route('admin.streams.create') }}"
           class="px-4 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium">
            + {{ __('Add Stream') }}
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Code') }}</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">{{ __('Forms') }}</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($streams as $stream)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900">{{ $stream->name }}</div>
                        @if($stream->description)
                            <div class="text-xs text-gray-400 mt-0.5">{{ Str::limit($stream->description, 60) }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="font-mono text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ $stream->code }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        @if($stream->is_general)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ __('General') }}</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">{{ __('Elective') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center text-gray-600">{{ $stream->forms_count }}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $stream->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $stream->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.streams.edit', $stream) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">{{ __('Edit') }}</a>
                            @if($stream->forms_count === 0)
                            <form method="POST" action="{{ route('admin.streams.destroy', $stream) }}" class="inline" onsubmit="return confirm('{{ __('Delete this stream?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Delete') }}</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-400">{{ __('No streams found. Add your first stream.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
