@extends('layouts.admin')

@section('title', __('Audit Log'))
@section('breadcrumb', __('System > Audit Log'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Audit Log') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Who changed what, and when.') }}</p>
        </div>
        @can('audit-log.export')
        <a href="{{ route('admin.audit-log.export', request()->query()) }}"
           class="inline-flex items-center px-4 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium text-sm transition">
            {{ __('Export CSV') }}
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <select name="user_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('All users') }}</option>
                @foreach($users as $u)
                <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->first_name }} {{ $u->last_name }}</option>
                @endforeach
            </select>

            <select name="action" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('All actions') }}</option>
                @foreach($actions as $a)
                <option value="{{ $a }}" @selected(request('action') === $a)>{{ __(ucfirst(str_replace('_', ' ', $a))) }}</option>
                @endforeach
            </select>

            <select name="model" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('All records') }}</option>
                @foreach($models as $key => $label)
                <option value="{{ $key }}" @selected(request('model') === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <input type="date" name="from" value="{{ request('from') }}"
                   class="rounded-lg border-gray-300 text-sm" aria-label="{{ __('From') }}">
            <input type="date" name="to" value="{{ request('to') }}"
                   class="rounded-lg border-gray-300 text-sm" aria-label="{{ __('To') }}">

            <div class="flex gap-2">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search values / IP') }}"
                       class="rounded-lg border-gray-300 text-sm flex-1">
                <button type="submit" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm font-medium hover:bg-slate-600">
                    {{ __('Filter') }}
                </button>
            </div>
        </div>
        @if(request()->hasAny(['user_id', 'action', 'model', 'from', 'to', 'search']))
        <div class="mt-3">
            <a href="{{ route('admin.audit-log.index') }}" class="text-sm text-blue-600 hover:underline">{{ __('Clear filters') }}</a>
        </div>
        @endif
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('When') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('User') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Action') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Record') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('IP') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($logs as $log)
                @php
                    $tone = match($log->action) {
                        'created', 'posted', 'paid', 'generated' => 'bg-emerald-100 text-emerald-700',
                        'deleted', 'unposted', 'unpaid' => 'bg-red-100 text-red-700',
                        default => 'bg-slate-100 text-slate-700',
                    };
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 whitespace-nowrap text-gray-600">
                        {{ optional($log->created_at)->format('d/m/Y H:i') ?? '—' }}
                    </td>
                    <td class="px-6 py-3">{{ $log->user?->full_name ?? __('System') }}</td>
                    <td class="px-6 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $tone }}">
                            {{ __(ucfirst(str_replace('_', ' ', $log->action))) }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-gray-700">
                        {{ class_basename($log->model_type ?? '') ?: '—' }}
                        @if($log->model_id)<span class="text-gray-400">#{{ $log->model_id }}</span>@endif
                    </td>
                    <td class="px-6 py-3 text-gray-500">{{ $log->ip_address ?? '—' }}</td>
                    <td class="px-6 py-3 text-right">
                        <a href="{{ route('admin.audit-log.show', $log) }}" class="text-blue-600 hover:underline">{{ __('Details') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">{{ __('No audit entries match these filters.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
@endsection
