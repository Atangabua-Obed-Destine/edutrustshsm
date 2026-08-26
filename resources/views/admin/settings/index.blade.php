@extends('layouts.admin')
@section('title', __('School Settings'))

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">{{ __('School Settings') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Configure your school\'s information, academic rules, and grading scale') }}</p>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg">{{ session('success') }}</div>
    @endif

    {{-- School Information --}}
    <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border border-gray-200">
        @csrf
        @method('PUT')

        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-xl">
            <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                {{ __('School Information') }}
            </h2>
        </div>

        <div class="p-6 space-y-6">
            {{-- Logo Upload --}}
            <div class="flex items-center gap-6">
                @if($settings->logo)
                    <img src="{{ asset('storage/' . $settings->logo) }}" alt="Logo" class="w-20 h-20 object-contain rounded-lg border">
                @else
                    <div class="w-20 h-20 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                @endif
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('School Logo') }}</label>
                    <input type="file" name="logo" accept="image/*" class="mt-1 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-gray-400 mt-1">{{ __('PNG, JPG up to 2MB') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">{{ __('School Name') }} *</label>
                    <input type="text" name="school_name" value="{{ old('school_name', $settings->school_name) }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('school_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Short Name') }}</label>
                    <input type="text" name="school_short_name" value="{{ old('school_short_name', $settings->school_short_name) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('School Code') }} *</label>
                    <input type="text" name="school_code" value="{{ old('school_code', $settings->school_code) }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Motto') }}</label>
                    <input type="text" name="motto" value="{{ old('motto', $settings->motto) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('Currency') }}</label>
                    <input type="text" name="currency" value="{{ old('currency', $settings->currency ?? 'XAF') }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            {{-- Contact --}}
            <div class="border-t pt-4">
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-3">{{ __('Contact & Location') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">{{ __('Address') }}</label>
                        <input type="text" name="address" value="{{ old('address', $settings->address) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('City') }}</label>
                        <input type="text" name="city" value="{{ old('city', $settings->city) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Region') }}</label>
                        <input type="text" name="region" value="{{ old('region', $settings->region) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('P.O. Box') }}</label>
                        <input type="text" name="po_box" value="{{ old('po_box', $settings->po_box) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Phone') }}</label>
                        <input type="text" name="phone" value="{{ old('phone', $settings->phone) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                        <input type="email" name="email" value="{{ old('email', $settings->email) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Website') }}</label>
                        <input type="url" name="website" value="{{ old('website', $settings->website) }}" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>

            {{-- Academic Rules --}}
            <div class="border-t pt-4">
                <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-3">{{ __('Academic Configuration') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Student ID Prefix') }}</label>
                        <input type="text" name="student_id_prefix" value="{{ old('student_id_prefix', $settings->student_id_prefix ?? 'SCH') }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Receipt Prefix') }}</label>
                        <input type="text" name="receipt_prefix" value="{{ old('receipt_prefix', $settings->receipt_prefix ?? 'RCP') }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Max Terms/Session') }}</label>
                        <input type="number" name="max_terms_per_session" value="{{ old('max_terms_per_session', $settings->max_terms_per_session ?? 3) }}" min="1" max="4" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Max Sequences/Term') }}</label>
                        <input type="number" name="max_sequences_per_term" value="{{ old('max_sequences_per_term', $settings->max_sequences_per_term ?? 2) }}" min="1" max="3" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Max Mark') }}</label>
                        <input type="number" step="0.1" name="max_mark" value="{{ old('max_mark', $settings->max_mark ?? 20) }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Pass Mark') }}</label>
                        <input type="number" step="0.1" name="pass_mark" value="{{ old('pass_mark', $settings->pass_mark ?? 10) }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Promotion Threshold') }}</label>
                        <input type="number" step="0.1" name="promotion_threshold" value="{{ old('promotion_threshold', $settings->promotion_threshold ?? 10) }}" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Min Attendance %') }}</label>
                        <input type="number" name="min_attendance_percent" value="{{ old('min_attendance_percent', $settings->min_attendance_percent ?? 85) }}" min="0" max="100" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t rounded-b-xl flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] font-medium text-sm">
                {{ __('Save Settings') }}
            </button>
        </div>
    </form>

    {{-- School Level Mode --}}
    <form action="{{ route('admin.settings.level-mode') }}" method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200">
        @csrf
        <div class="p-6">
            <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-1">{{ __('School Level Mode') }}</h3>
            <p class="text-xs text-gray-500 mb-4">{{ __('Which levels this school runs. Changing this never deletes data — narrowing simply hides the disabled level.') }}</p>
            @php($mode = $settings->school_level_mode)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @php($modes = [
                    'nursery_primary' => [__('Nursery / Primary only'), __('Nursery 1–3, Class 1–6')],
                    'secondary' => [__('Secondary / High School only'), __('Form 1–5, Lower/Upper Sixth')],
                    'both' => [__('Both'), __('Combined school')],
                ])
                @foreach($modes as $value => [$title, $desc])
                <label class="flex items-start gap-3 border rounded-xl p-4 cursor-pointer transition {{ $mode === $value ? 'border-teal-500 bg-teal-50/50' : 'border-gray-200 hover:border-teal-400' }}">
                    <input type="radio" name="school_level_mode" value="{{ $value }}" class="mt-1" {{ $mode === $value ? 'checked' : '' }} required>
                    <span>
                        <span class="block text-sm font-semibold text-gray-800">{{ $title }}</span>
                        <span class="block text-xs text-gray-500">{{ $desc }}</span>
                    </span>
                </label>
                @endforeach
            </div>
            <div class="flex justify-end mt-4">
                <button type="submit" class="bg-[#1e293b] hover:bg-[#334155] text-white px-5 py-2 rounded-lg text-sm font-medium">{{ __('Save Level Mode') }}</button>
            </div>
        </div>
    </form>

    {{-- Grade Scale --}}
    <form action="{{ route('admin.settings.grade-scale') }}" method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200">
        @csrf
        @method('PUT')

        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-xl flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-700 flex items-center">
                <svg class="w-5 h-5 mr-2 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                {{ __('Grading Scale (0-20)') }}
            </h2>
            <button type="button" onclick="addGradeRow()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">{{ __('+ Add Grade') }}</button>
        </div>

        <div class="p-6">
            <table class="w-full text-sm" id="grade-table">
                <thead>
                    <tr class="text-left text-xs text-gray-500 uppercase tracking-wider">
                        <th class="pb-3 w-20">{{ __('Grade') }}</th>
                        <th class="pb-3 w-28">{{ __('Min Mark') }}</th>
                        <th class="pb-3 w-28">{{ __('Max Mark') }}</th>
                        <th class="pb-3">{{ __('Description') }}</th>
                        <th class="pb-3 w-16"></th>
                    </tr>
                </thead>
                <tbody id="grade-rows">
                    @forelse($gradeScales as $i => $grade)
                    <tr class="grade-row border-t border-gray-100">
                        <td class="py-2 pr-2">
                            <input type="text" name="grades[{{ $i }}][grade]" value="{{ $grade->grade }}" required class="w-full rounded border-gray-300 text-sm">
                        </td>
                        <td class="py-2 pr-2">
                            <input type="number" step="0.1" name="grades[{{ $i }}][min_mark]" value="{{ $grade->min_mark }}" required class="w-full rounded border-gray-300 text-sm">
                        </td>
                        <td class="py-2 pr-2">
                            <input type="number" step="0.1" name="grades[{{ $i }}][max_mark]" value="{{ $grade->max_mark }}" required class="w-full rounded border-gray-300 text-sm">
                        </td>
                        <td class="py-2 pr-2">
                            <input type="text" name="grades[{{ $i }}][description]" value="{{ $grade->description }}" required class="w-full rounded border-gray-300 text-sm">
                        </td>
                        <td class="py-2 text-center">
                            <button type="button" onclick="this.closest('tr').remove()" class="text-red-400 hover:text-red-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr class="grade-row border-t border-gray-100">
                        <td class="py-2 pr-2"><input type="text" name="grades[0][grade]" value="A" required class="w-full rounded border-gray-300 text-sm"></td>
                        <td class="py-2 pr-2"><input type="number" step="0.1" name="grades[0][min_mark]" value="16" required class="w-full rounded border-gray-300 text-sm"></td>
                        <td class="py-2 pr-2"><input type="number" step="0.1" name="grades[0][max_mark]" value="20" required class="w-full rounded border-gray-300 text-sm"></td>
                        <td class="py-2 pr-2"><input type="text" name="grades[0][description]" value="Excellent" required class="w-full rounded border-gray-300 text-sm"></td>
                        <td class="py-2 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-400 hover:text-red-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t rounded-b-xl flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-[#1e293b] text-white rounded-lg hover:bg-[#334155] font-medium text-sm">
                {{ __('Save Grade Scale') }}
            </button>
        </div>
    </form>
</div>

<script>
let gradeIndex = {{ count($gradeScales) ?: 1 }};
function addGradeRow() {
    const tbody = document.getElementById('grade-rows');
    const tr = document.createElement('tr');
    tr.className = 'grade-row border-t border-gray-100';
    tr.innerHTML = `
        <td class="py-2 pr-2"><input type="text" name="grades[${gradeIndex}][grade]" required class="w-full rounded border-gray-300 text-sm"></td>
        <td class="py-2 pr-2"><input type="number" step="0.1" name="grades[${gradeIndex}][min_mark]" required class="w-full rounded border-gray-300 text-sm"></td>
        <td class="py-2 pr-2"><input type="number" step="0.1" name="grades[${gradeIndex}][max_mark]" required class="w-full rounded border-gray-300 text-sm"></td>
        <td class="py-2 pr-2"><input type="text" name="grades[${gradeIndex}][description]" required class="w-full rounded border-gray-300 text-sm"></td>
        <td class="py-2 text-center"><button type="button" onclick="this.closest('tr').remove()" class="text-red-400 hover:text-red-600"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></td>
    `;
    tbody.appendChild(tr);
    gradeIndex++;
}
</script>
@endsection
