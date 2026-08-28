@extends('layouts.admin')

@section('title', __('Audit Entry'))
@section('breadcrumb', __('System > Audit Log > Entry'))

@section('content')
@php
    $old = $log->old_values ?? [];
    $new = $log->new_values ?? [];
    $keys = collect(array_keys($old + $new))->sort()->values();
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Audit Entry') }} <span class="text-gray-400">#{{ $log->id }}</span></h1>
        <a href="{{ route('admin.audit-log.index') }}" class="text-sm text-blue-600 hover:underline">{{ __('Back to Audit Log') }}</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">{{ __('When') }}</dt>
                <dd class="font-medium text-gray-900">{{ optional($log->created_at)->format('d/m/Y H:i:s') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('User') }}</dt>
                <dd class="font-medium text-gray-900">{{ $log->user?->full_name ?? __('System') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('Action') }}</dt>
                <dd class="font-medium text-gray-900">{{ __(ucfirst(str_replace('_', ' ', $log->action))) }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('Record') }}</dt>
                <dd class="font-medium text-gray-900">
                    {{ class_basename($log->model_type ?? '') ?: '—' }}@if($log->model_id) #{{ $log->model_id }}@endif
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">{{ __('IP Address') }}</dt>
                <dd class="font-medium text-gray-900">{{ $log->ip_address ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-gray-500">{{ __('User Agent') }}</dt>
                <dd class="text-gray-700 break-words">{{ $log->user_agent ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('Changes') }}</h3>
        </div>
        @if($keys->isEmpty())
        <p class="px-6 py-8 text-center text-gray-500 text-sm">{{ __('No field-level detail was recorded for this entry.') }}</p>
        @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Field') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Before') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('After') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($keys as $key)
                @php
                    $before = $old[$key] ?? null;
                    $after = $new[$key] ?? null;
                    $fmtBefore = is_null($before) ? '—' : (is_scalar($before) ? (string) $before : json_encode($before, JSON_UNESCAPED_UNICODE));
                    $fmtAfter = is_null($after) ? '—' : (is_scalar($after) ? (string) $after : json_encode($after, JSON_UNESCAPED_UNICODE));
                    $differs = $fmtBefore !== $fmtAfter;
                @endphp
                <tr class="{{ $differs ? 'bg-amber-50' : '' }}">
                    <td class="px-6 py-3 font-medium text-gray-700">{{ $key }}</td>
                    <td class="px-6 py-3 text-gray-500 break-words">{{ $fmtBefore }}</td>
                    <td class="px-6 py-3 break-words {{ $differs ? 'text-gray-900 font-medium' : 'text-gray-500' }}">{{ $fmtAfter }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
