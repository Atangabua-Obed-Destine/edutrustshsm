@extends('layouts.admin')
@section('title', __('Staff Detail'))
@section('breadcrumb', __('Human Resources > Staff > :n', ['n' => $staff->staff_id]))
@section('content')
<div class="max-w-3xl space-y-4">
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-3"><ul class="text-sm text-red-700 list-disc list-inside">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">{{ $staff->full_name }}</h3>
                <p class="text-sm text-gray-500">{{ $staff->staff_id }} · {{ $staff->designation?->title }} · {{ $staff->department?->name }}</p>
            </div>
            <div class="flex items-center gap-2">
                @can('id-card.print')
                <form method="POST" action="{{ route('admin.staff.id-cards.print') }}" target="_blank">
                    @csrf
                    <input type="hidden" name="staff_ids[]" value="{{ $staff->id }}">
                    <button type="submit" class="border border-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded-lg text-sm">{{ __('Print ID card') }}</button>
                </form>
                @endcan
                <a href="{{ route('admin.staff.edit', $staff) }}" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm">{{ __('Edit') }}</a>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4 text-sm">
            <div><span class="text-gray-400">{{ __('Email') }}:</span> {{ $staff->email }}</div>
            <div><span class="text-gray-400">{{ __('Phone') }}:</span> {{ $staff->phone ?? '—' }}</div>
            <div><span class="text-gray-400">{{ __('Salary Type') }}:</span> {{ $staff->salary_type == 1 ? __('Fixed') : __('Hourly') }}</div>
            <div><span class="text-gray-400">{{ __('Basic Salary') }}:</span> {{ number_format($staff->basic_salary, 2) }}</div>
            <div><span class="text-gray-400">{{ __('Joining') }}:</span> {{ optional($staff->joining_date)->format('d/m/Y') ?? '—' }}</div>
            <div><span class="text-gray-400">{{ __('ID Validity') }}:</span> {{ $staff->id_card_validity }}</div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h4 class="font-semibold text-gray-700 mb-3">{{ __('Recent Payrolls') }}</h4>
        @forelse($staff->payrolls->sortByDesc('salary_month')->take(6) as $p)
        <div class="flex justify-between text-sm border-b border-gray-50 py-1.5"><span>{{ $p->salary_month }}</span><span>{{ number_format($p->net_salary, 2) }}</span><span class="{{ $p->status ? 'text-green-600' : 'text-gray-400' }}">{{ $p->status ? __('Paid') : __('Unpaid') }}</span></div>
        @empty<p class="text-sm text-gray-500">{{ __('No payrolls yet.') }}</p>@endforelse
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h4 class="font-semibold text-gray-700 mb-3">{{ __('Notes') }}</h4>
        @can('staff.edit')
        <form method="POST" action="{{ route('admin.staff.notes.store', $staff) }}" enctype="multipart/form-data" class="space-y-2 mb-4">
            @csrf
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="191" placeholder="{{ __('Title') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <textarea name="note" required rows="3" placeholder="{{ __('Note') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('note') }}</textarea>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <input type="file" name="attachment" class="text-xs">
                <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-white px-3 py-1.5 rounded-lg text-sm">{{ __('Add note') }}</button>
            </div>
        </form>
        @endcan
        @forelse($staff->staffNotes as $note)
        <div class="border-t border-gray-100 py-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-gray-800">{{ $note->title }}</p>
                    <p class="text-xs text-gray-400">{{ $note->created_at->format('d/m/Y H:i') }} · {{ $note->createdBy?->full_name ?? '—' }}</p>
                </div>
                @can('staff.delete')
                <form method="POST" action="{{ route('admin.staff.notes.destroy', $note) }}" onsubmit="return confirm('{{ __('Delete this note?') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Delete') }}</button>
                </form>
                @endcan
            </div>
            <p class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $note->note }}</p>
            @if($note->attachment)
            <a href="{{ private_file_url('staff-note', $note, 'attachment') }}" target="_blank" class="text-xs text-blue-600 hover:underline">{{ __('View attachment') }}</a>
            @endif
        </div>
        @empty
        <p class="text-sm text-gray-500">{{ __('No notes yet.') }}</p>
        @endforelse
    </div>
</div>
@endsection
