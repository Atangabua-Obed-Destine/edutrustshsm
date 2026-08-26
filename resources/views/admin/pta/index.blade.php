@extends('layouts.admin')
@section('title', __('PTA Management'))

@section('content')
@php $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA'); @endphp

<div class="max-w-6xl mx-auto" x-data="{ tab: '{{ $tab }}' }">
    @if(session('success'))
        <div class="mb-5 bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-1 mb-6 bg-gray-100 p-1 rounded-xl w-fit">
        <button @click="tab='levies'" :class="tab==='levies' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-4 py-2 rounded-lg text-sm font-medium transition">{{ __('Levies') }}</button>
        <button @click="tab='announcements'" :class="tab==='announcements' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-4 py-2 rounded-lg text-sm font-medium transition">{{ __('Announcements') }}</button>
        <button @click="tab='meetings'" :class="tab==='meetings' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-4 py-2 rounded-lg text-sm font-medium transition">{{ __('Meetings') }}</button>
    </div>

    {{-- ── LEVIES ── --}}
    <div x-show="tab==='levies'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('Create Levy') }}</h3>
                <form method="POST" action="{{ route('admin.pta.levies.store') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Session') }} *</label>
                        <select name="academic_session_id" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                            @foreach($sessions as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Form') }} ({{ __('blank = all') }})</label>
                        <select name="form_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                            <option value="">{{ __('All forms') }}</option>
                            @foreach($forms as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Amount') }} ({{ $currency }}) *</label>
                        <input type="number" name="amount" step="any" min="0" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Due Date') }}</label>
                        <input type="date" name="due_date" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Description') }}</label>
                        <input type="text" name="description" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <button type="submit" class="w-full bg-teal-600 text-white py-2 rounded-lg text-sm font-semibold hover:bg-teal-700">{{ __('Add Levy') }}</button>
                </form>
            </div>
        </div>
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-800">{{ __('PTA Levies') }}</h3></div>
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-gray-500 uppercase border-b border-gray-100">
                        <th class="px-5 py-3">{{ __('Session') }}</th><th class="px-5 py-3">{{ __('Form') }}</th>
                        <th class="px-5 py-3 text-right">{{ __('Amount') }}</th><th class="px-5 py-3 text-center">{{ __('Payments') }}</th><th class="px-5 py-3"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($levies as $levy)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-gray-700">{{ $levy->academicSession->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $levy->form->name ?? __('All forms') }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-800">{{ number_format($levy->amount, 0) }} {{ $currency }}</td>
                                <td class="px-5 py-3 text-center text-gray-600">{{ $levy->payments_count }}</td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.pta.levies.destroy', $levy) }}" onsubmit="return confirm('{{ __('Delete this levy?') }}')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400 text-sm">{{ __('No levies defined yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── ANNOUNCEMENTS ── --}}
    <div x-show="tab==='announcements'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5" x-data="{ audience: 'all' }">
                <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('New Announcement') }}</h3>
                <form method="POST" action="{{ route('admin.pta.announcements.store') }}" class="space-y-3">
                    @csrf
                    <input type="text" name="title" placeholder="{{ __('Title') }}" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <textarea name="body" rows="4" placeholder="{{ __('Message...') }}" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2"></textarea>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Audience') }}</label>
                        <select name="audience" x-model="audience" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                            <option value="all">{{ __('All parents') }}</option>
                            <option value="form_specific">{{ __('Specific form') }}</option>
                        </select>
                    </div>
                    <div x-show="audience==='form_specific'" x-cloak>
                        <select name="target_form_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                            @foreach($forms as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('Expires') }} ({{ __('optional') }})</label>
                        <input type="date" name="expires_at" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="publish" value="1" checked class="accent-teal-600"> {{ __('Publish immediately') }}
                    </label>
                    <button type="submit" class="w-full bg-teal-600 text-white py-2 rounded-lg text-sm font-semibold hover:bg-teal-700">{{ __('Post') }}</button>
                </form>
            </div>
        </div>
        <div class="lg:col-span-2 space-y-3">
            @forelse($announcements as $a)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h4 class="font-semibold text-gray-800">{{ $a->title }}</h4>
                                @if($a->isPublished())
                                    <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs">{{ __('Published') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs">{{ __('Draft') }}</span>
                                @endif
                            </div>
                            <p class="text-sm text-gray-600 mt-1">{{ \Illuminate\Support\Str::limit($a->body, 160) }}</p>
                            <p class="text-xs text-gray-400 mt-2">
                                {{ $a->audience === 'all' ? __('All parents') : ($a->audience === 'form_specific' ? ($a->targetForm->name ?? __('Form')) : ($a->targetClass->name ?? __('Class'))) }}
                                · {{ $a->created_at->format('d M Y') }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1 shrink-0">
                            <form method="POST" action="{{ route('admin.pta.announcements.toggle', $a) }}">@csrf
                                <button class="text-xs text-teal-600 hover:text-teal-700">{{ $a->isPublished() ? __('Unpublish') : __('Publish') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.pta.announcements.destroy', $a) }}" onsubmit="return confirm('{{ __('Delete?') }}')">@csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center text-gray-400 text-sm">{{ __('No announcements yet.') }}</div>
            @endforelse
        </div>
    </div>

    {{-- ── MEETINGS ── --}}
    <div x-show="tab==='meetings'" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('Schedule Meeting') }}</h3>
                <form method="POST" action="{{ route('admin.pta.meetings.store') }}" class="space-y-3">
                    @csrf
                    <input type="text" name="title" placeholder="{{ __('Title') }}" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <input type="datetime-local" name="meeting_date" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <input type="text" name="venue" placeholder="{{ __('Venue') }}" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <textarea name="agenda" rows="3" placeholder="{{ __('Agenda...') }}" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2"></textarea>
                    <button type="submit" class="w-full bg-teal-600 text-white py-2 rounded-lg text-sm font-semibold hover:bg-teal-700">{{ __('Schedule') }}</button>
                </form>
            </div>
        </div>
        <div class="lg:col-span-2 space-y-3">
            @forelse($meetings as $m)
                @php $statusBadge = ['scheduled' => ['bg-blue-100','text-blue-700'], 'completed' => ['bg-green-100','text-green-700'], 'cancelled' => ['bg-gray-100','text-gray-500']][$m->status] ?? ['bg-gray-100','text-gray-500']; @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="font-semibold text-gray-800">{{ $m->title }}</h4>
                                <span class="px-2 py-0.5 rounded-full {{ $statusBadge[0] }} {{ $statusBadge[1] }} text-xs capitalize">{{ $m->status }}</span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">📅 {{ $m->meeting_date->format('D, d M Y · H:i') }} @if($m->venue) · 📍 {{ $m->venue }} @endif</p>
                            @if($m->agenda)<p class="text-sm text-gray-600 mt-2">{{ \Illuminate\Support\Str::limit($m->agenda, 140) }}</p>@endif
                            @if($m->minutes_path)<a href="{{ asset('storage/'.$m->minutes_path) }}" target="_blank" class="text-xs text-teal-600 hover:text-teal-700 mt-2 inline-block">📄 {{ __('View Minutes') }}</a>@endif
                        </div>
                        <div class="flex flex-col gap-2 shrink-0 items-end">
                            <form method="POST" action="{{ route('admin.pta.meetings.minutes', $m) }}" enctype="multipart/form-data" class="flex flex-col gap-1">
                                @csrf
                                <input type="file" name="minutes" accept=".pdf,.doc,.docx" class="text-xs w-36">
                                <button class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded hover:bg-gray-200">{{ __('Upload Minutes') }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.pta.meetings.destroy', $m) }}" onsubmit="return confirm('{{ __('Delete?') }}')">@csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:text-red-700">{{ __('Delete') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center text-gray-400 text-sm">{{ __('No meetings scheduled.') }}</div>
            @endforelse
        </div>
    </div>
</div>
<style>[x-cloak]{display:none!important;}</style>
@endsection
