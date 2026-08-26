{{-- Reusable "create form + list + edit modal" for simple HR config lists.
     Expects: $title, $storeRoute, $updateRouteName, $destroyRouteName, $items --}}
@extends('layouts.admin')

@section('title', $title)
@section('breadcrumb', __('Human Resources') . ' > ' . $title)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-700 mb-4">{{ __('Create') }} {{ $title }}</h3>
        <form method="POST" action="{{ $storeRoute }}">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500" required>
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            <button type="submit" class="mt-4 bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100"><h3 class="text-base font-semibold text-gray-700">{{ $title }} {{ __('List') }}</h3></div>
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Title') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($items as $i => $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                    <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $item->title }}</td>
                    <td class="px-6 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $item->status ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $item->status ? __('Active') : __('Inactive') }}</span></td>
                    <td class="px-6 py-3 text-right space-x-2 whitespace-nowrap">
                        <form method="POST" action="{{ route($updateRouteName, $item) }}" class="inline">@csrf @method('PUT')
                            <input type="hidden" name="title" value="{{ $item->title }}">
                            <input type="hidden" name="status" value="{{ $item->status ? 0 : 1 }}">
                            <button class="text-amber-600 hover:text-amber-800 text-xs font-medium">{{ $item->status ? __('Disable') : __('Enable') }}</button>
                        </form>
                        <form method="POST" action="{{ route($destroyRouteName, $item) }}" class="inline" onsubmit="return confirm('{{ __('Delete this item?') }}')">@csrf @method('DELETE')<button class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Delete') }}</button></form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">{{ __('No data found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
