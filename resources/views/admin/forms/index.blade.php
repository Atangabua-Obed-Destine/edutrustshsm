@extends('layouts.admin')

@section('title', __('Forms'))
@section('breadcrumb', __('Academic > Forms'))

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('All Forms / Classes') }}</h3>
        <a href="{{ route('admin.forms.create') }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            {{ __('+ New Form') }}
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Order') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Short Name') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('School Level') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Level') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('System') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Streams') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($forms as $form)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $form->display_order }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $form->name }}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $form->short_name }}</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $form->school_level === 'nursery_primary' ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-700' }}">
                            {{ \App\Support\LevelContext::labels()[$form->school_level] ?? ucfirst(str_replace('_', ' ', $form->school_level)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 capitalize">{{ str_replace('_', ' ', $form->level) }}</td>
                    <td class="px-6 py-4 text-sm">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $form->education_system === 'english' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700' }}">
                            {{ $form->education_system === 'english' ? __('English') : __('French') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        @if($form->has_streams)
                            {{ $form->streams->pluck('name')->join(', ') }}
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($form->is_active)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ __('Active') }}</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <a href="{{ route('admin.forms.edit', $form) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.forms.destroy', $form) }}" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium" onclick="return confirm('{{ __('Delete this form?') }}')">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">{{ __('No forms found.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
