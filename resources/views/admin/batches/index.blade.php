@extends('layouts.admin')

@section('title', __('Batches'))
@section('breadcrumb', __('Academic > Batches'))

@section('content')
<div class="max-w-4xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ __('Batches') }}</h3>
            <p class="text-sm text-gray-500">{{ __('Manage student admission batches / intake cohorts') }}</p>
        </div>
        <a href="{{ route('admin.batches.create') }}"
           class="px-4 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium">
            {{ __('+ Add Batch') }}
        </a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">{{ __('Shortcode') }}</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($batches as $batch)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900">{{ $batch->name }}</div>
                        @if($batch->description)
                            <div class="text-xs text-gray-400 mt-0.5">{{ Str::limit($batch->description, 80) }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded bg-gray-100 text-xs font-mono font-medium text-gray-700">{{ $batch->shortcode }}</span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $batch->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $batch->is_active ? __('Active') : __('Inactive') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.batches.edit', $batch) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('admin.batches.destroy', $batch) }}" class="inline" onsubmit="return confirm('{{ __('Delete this batch?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-8 text-center text-gray-400">{{ __('No batches found. Add your first batch.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
