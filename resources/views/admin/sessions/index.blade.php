@extends('layouts.admin')

@section('title', __('Academic Sessions'))
@section('breadcrumb', __('Academic > Sessions'))

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('All Academic Sessions') }}</h3>
        <a href="{{ route('admin.sessions.create') }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            + {{ __('New Session') }}
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Start Date') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('End Date') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($sessions as $session)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $session->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $session->start_date->format('M d, Y') }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $session->end_date->format('M d, Y') }}</td>
                    <td class="px-6 py-4">
                        @if($session->is_current)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ __('Active') }}</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 capitalize">{{ $session->status }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        @if(!$session->is_current)
                            <form method="POST" action="{{ route('admin.sessions.activate', $session) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-green-600 hover:text-green-800 text-sm font-medium" onclick="return confirm('{{ __('Set this as the active session?') }}')">{{ __('Activate') }}</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.sessions.edit', $session) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">{{ __('Edit') }}</a>
                        @if(!$session->is_current)
                            <form method="POST" action="{{ route('admin.sessions.destroy', $session) }}" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium" onclick="return confirm('{{ __('Delete this session?') }}')">{{ __('Delete') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">{{ __('No academic sessions found. Create your first session to get started.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
