@extends('layouts.admin')

@section('title', __('Income Categories'))
@section('breadcrumb', __('Income & Expense > Income Categories'))

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    {{-- Create --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-700 mb-4">{{ __('Create Income Category') }}</h3>
        <form method="POST" action="{{ route('admin.account.income-category.store') }}">
            @csrf
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
            @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            <button type="submit" class="mt-4 bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2 rounded-lg text-sm font-medium">{{ __('Save') }}</button>
        </form>
    </div>

    {{-- List --}}
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <h3 class="text-base font-semibold text-gray-700 p-6 pb-3">{{ __('Income Category List') }}</h3>
        <table class="w-full">
            <thead class="bg-gray-50 border-y border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Title') }}</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($categories as $i => $cat)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                    <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $cat->title }}</td>
                    <td class="px-6 py-3">
                        @if($cat->status)
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ __('Active') }}</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right space-x-2 whitespace-nowrap">
                        <button type="button" onclick="editCat({{ $cat->id }}, @js($cat->title), {{ $cat->status ? 1 : 0 }})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">{{ __('Edit') }}</button>
                        <form method="POST" action="{{ route('admin.account.income-category.destroy', $cat) }}" class="inline" onsubmit="return confirm('{{ __('Delete this category?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">{{ __('No categories yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Edit Modal --}}
<div id="edit-modal" class="fixed inset-0 bg-black/50 z-40 hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-md">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 bg-blue-500 rounded-t-xl">
            <h4 class="text-white font-semibold">{{ __('Edit Income Category') }}</h4>
            <button onclick="closeCat()" class="text-white">&times;</button>
        </div>
        <form id="edit-form" method="POST" class="p-5 space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }} <span class="text-red-500">*</span></label>
                <input type="text" id="edit-title" name="title" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                <select id="edit-status" name="status" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-4 py-2 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="1">{{ __('Active') }}</option>
                    <option value="0">{{ __('Inactive') }}</option>
                </select>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeCat()" class="px-4 py-2 text-sm bg-gray-500 text-white rounded-lg">{{ __('Close') }}</button>
                <button type="submit" class="px-4 py-2 text-sm bg-emerald-500 text-white rounded-lg">{{ __('Update') }}</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const baseUrl = "{{ url('admin/account/income-category') }}";
    function editCat(id, title, status) {
        document.getElementById('edit-form').action = baseUrl + '/' + id;
        document.getElementById('edit-title').value = title;
        document.getElementById('edit-status').value = status;
        const m = document.getElementById('edit-modal');
        m.classList.remove('hidden'); m.classList.add('flex');
    }
    function closeCat() {
        const m = document.getElementById('edit-modal');
        m.classList.add('hidden'); m.classList.remove('flex');
    }
</script>
@endpush
@endsection
