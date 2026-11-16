@extends('layouts.admin')

@section('title', __('Certificates issued'))
@section('breadcrumb', __('Students > Certificates issued'))

@section('content')
<div class="space-y-4">
    <div>
        <h2 class="text-xl font-bold text-gray-800">{{ __('Certificates issued') }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ __('Every leaving certificate and cumulative record printed, each with its own serial. Anyone holding one can check it at :url.', ['url' => route('document.verify')]) }}</p>
    </div>

    <div class="space-y-4" data-list-region="issued-documents">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <form method="GET" class="flex flex-wrap gap-3" data-auto-submit>
                @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="dir" value="{{ request('dir') }}">@endif
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Serial, pupil name or matricule…') }}"
                       class="flex-1 min-w-[220px] px-4 py-2 border border-gray-300 rounded-lg text-sm">
                <select name="type" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" style="appearance:auto;-webkit-appearance:menulist;">
                    <option value="">{{ __('All documents') }}</option>
                    @foreach(array_keys(\App\Models\IssuedDocument::PREFIX) as $t)
                        <option value="{{ $t }}" @selected($type === $t)>{{ \App\Models\IssuedDocument::typeLabel($t) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Search') }}</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <x-list.toolbar :list="$list" :rows="$documents" />
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                        <tr>
                            <x-list.th :list="$list" key="serial" class="px-4 py-3 text-left" />
                            <x-list.th :list="$list" key="type" class="px-4 py-3 text-left" />
                            <x-list.th :list="$list" key="pupil" class="px-4 py-3 text-left" />
                            <x-list.th :list="$list" key="issued" class="px-4 py-3 text-left" />
                            <x-list.th :list="$list" key="by" class="px-4 py-3 text-left" />
                            <th class="px-4 py-3 text-left">{{ __('Check') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($documents as $document)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs font-semibold text-gray-800">{{ $document->serial }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ \App\Models\IssuedDocument::typeLabel($document->type) }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $document->student?->first_name }} {{ $document->student?->last_name }}</div>
                                <div class="text-xs text-gray-400">{{ $document->student?->student_id }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $document->issued_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-600 text-xs">{{ $document->issuedBy?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3"><a href="{{ $document->verifyUrl() }}" target="_blank" rel="noopener" data-no-swap class="font-mono text-xs text-blue-600 hover:underline">{{ $document->verifyCodeForPrint() }}</a></td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">{{ __('No certificate has been printed yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($documents->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">{{ $documents->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
