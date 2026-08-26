@extends('layouts.admin')

@section('title', __('Exam Sequences'))
@section('breadcrumb', __('Academic > Exam Sequences'))

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Exam Sequences') }}</h3>
        <button onclick="openAddModal()" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            + {{ __('New Sequence') }}
        </button>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Name') }}</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($sequences as $sequence)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $sequence->sequence_number }}</td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $sequence->name }}</td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <button onclick="openEditModal({{ $sequence->id }}, {{ Js::from($sequence->only(['name'])) }})" class="text-blue-600 hover:text-blue-800 text-sm font-medium">{{ __('Edit') }}</button>
                        <form method="POST" action="{{ route('admin.sequences.destroy', $sequence) }}" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium" onclick="return confirm('{{ __('Delete this sequence? This cannot be undone.') }}')">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="px-6 py-8 text-center text-gray-500">{{ __('No exam sequences found.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ================================================================ --}}
{{-- ADD MODAL                                                         --}}
{{-- ================================================================ --}}
<div id="addModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeAddModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md z-10 transform transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">{{ __('New Exam Sequence') }}</h3>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.sequences.store') }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Sequence Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="add_name" value="{{ old('name') }}" placeholder="{{ __('e.g., 1st Sequence') }}" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                    @error('name')
                        @if(old('_method') === null)
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @endif
                    @enderror
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeAddModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-5 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium">{{ __('Save Sequence') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ================================================================ --}}
{{-- EDIT MODAL                                                        --}}
{{-- ================================================================ --}}
<div id="editModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeEditModal()"></div>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md z-10 transform transition-all">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800">{{ __('Edit Sequence') }}</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="editForm" method="POST" class="p-6 space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Sequence Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="edit_name" placeholder="{{ __('e.g., 1st Sequence') }}" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-5 py-2 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] transition text-sm font-medium">{{ __('Update Sequence') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const seqBaseUrl = @json(route('admin.sequences.index'));

    function openAddModal() {
        document.getElementById('add_name').value = '';
        document.getElementById('addModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        document.getElementById('add_name').focus();
    }
    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    function openEditModal(id, data) {
        document.getElementById('editForm').action = seqBaseUrl + '/' + id;
        document.getElementById('edit_name').value = data.name || '';
        document.getElementById('editModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        document.getElementById('edit_name').focus();
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeAddModal(); closeEditModal(); }
    });

    @if($errors->any() && old('_method') === null)
        openAddModal();
    @endif
</script>
@endpush
@endsection
