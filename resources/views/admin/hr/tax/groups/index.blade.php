@extends('layouts.admin')

@section('title', __('Tax Groups'))
@section('breadcrumb', __('Human Resources > Settings > Tax Groups'))

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Tax Group List') }}</h3>
        <div class="flex gap-2">
            <a href="{{ route('admin.tax-settings.index') }}" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Tax Settings / Brackets') }}</a>
            <a href="{{ route('admin.tax-groups.create') }}" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Create Tax Group') }}</a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Order') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Title') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Code') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Brackets') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($groups as $g)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $g->display_order }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $g->title }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $g->code }}</td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $g->is_progressive ? 'bg-indigo-100 text-indigo-700' : 'bg-amber-100 text-amber-700' }}">{{ $g->is_progressive ? __('Progressive') : __('Flat Rate') }}</span></td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ $g->brackets_count }}</td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $g->status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $g->status ? __('Active') : __('Inactive') }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <a href="{{ route('admin.tax-groups.edit', $g) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.tax-groups.destroy', $g) }}" class="inline" onsubmit="return confirm('{{ __('Delete this group and its brackets?') }}')">@csrf @method('DELETE')<button class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Delete') }}</button></form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No data found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
