@extends('layouts.admin')
@section('title', __('Nursery Competences'))
@section('breadcrumb', __('Settings > Nursery Competences'))

@section('content')
<div class="space-y-5" x-data="{ editing: null }">

    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ __('Nursery Competences') }}</h2>
            <p class="text-sm text-gray-500 mt-0.5 max-w-2xl">
                {{ __('What a nursery child is expected to be able to do, grouped by learning area. This is the list every nursery sheet and report is built from.') }}
            </p>
        </div>
        <a href="{{ route('admin.nursery.index') }}" class="text-sm text-gray-600 hover:text-gray-800 whitespace-nowrap border border-gray-300 rounded-lg px-3 py-2">{{ __('Go to assessment') }}</a>
    </div>

    @if($areas->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <h3 class="font-semibold text-gray-800">{{ __('The list is empty.') }}</h3>
            <p class="text-sm text-gray-500 mt-1 max-w-xl mx-auto">
                {{ __('Start from the standard list — the learning areas of the Cameroon preschool curriculum with the competences most nursery schools report on — then change it to match what this school teaches. Or build your own from scratch below.') }}
            </p>
            @can('school-settings.edit')
                <form method="POST" action="{{ route('admin.nursery.competences.seed') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-medium hover:bg-teal-700">{{ __('Load the standard list') }}</button>
                </form>
            @endcan
        </div>
    @endif

    @foreach($areas as $area)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden {{ $area->is_active ? '' : 'opacity-60' }}">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/60 flex items-center justify-between gap-3">
                <template x-if="editing !== 'area-{{ $area->id }}'">
                    <h3 class="font-semibold text-gray-800">
                        {{ $area->name }}
                        @unless($area->is_active) <span class="ml-2 text-xs font-normal text-gray-400">{{ __('hidden') }}</span> @endunless
                        <span class="ml-2 text-xs font-normal text-gray-400">{{ trans_choice('{0} no competences|{1} :count competence|[2,*] :count competences', $area->competences->count(), ['count' => $area->competences->count()]) }}</span>
                    </h3>
                </template>
                @can('school-settings.edit')
                    <form x-show="editing === 'area-{{ $area->id }}'" x-cloak method="POST" action="{{ route('admin.nursery.competences.areas.update', $area) }}" class="flex-1 flex items-center gap-2">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $area->name }}" required maxlength="120" class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-1.5">
                        <label class="text-xs text-gray-600 inline-flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($area->is_active)> {{ __('Shown') }}</label>
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-teal-600 text-white text-xs font-medium">{{ __('Save') }}</button>
                        <button type="button" @click="editing = null" class="text-xs text-gray-500">{{ __('Cancel') }}</button>
                    </form>
                    <div class="flex items-center gap-3 shrink-0" x-show="editing !== 'area-{{ $area->id }}'">
                        <button type="button" @click="editing = 'area-{{ $area->id }}'" class="text-xs text-gray-500 hover:text-gray-800">{{ __('Rename') }}</button>
                        <form method="POST" action="{{ route('admin.nursery.competences.areas.destroy', $area) }}" onsubmit="return confirm('{{ __('Remove this learning area and everything under it?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700">{{ __('Remove') }}</button>
                        </form>
                    </div>
                @endcan
            </div>

            <ul class="divide-y divide-gray-100">
                @forelse($area->competences as $competence)
                    <li class="px-5 py-2.5 flex items-center gap-3 {{ $competence->is_active ? '' : 'opacity-50' }}">
                        <template x-if="editing !== 'c-{{ $competence->id }}'">
                            <div class="flex-1 min-w-0 text-sm text-gray-800">
                                {{ $competence->description }}
                                @if($competence->form)
                                    <span class="ml-1 text-xs text-teal-700 bg-teal-50 rounded px-1.5 py-0.5">{{ $competence->form->name }}</span>
                                @endif
                                @unless($competence->is_active) <span class="ml-1 text-xs text-gray-400">{{ __('hidden') }}</span> @endunless
                                @if($competence->assessments_count > 0)
                                    <span class="ml-1 text-xs text-gray-400">{{ trans_choice('{1} :count rating|[2,*] :count ratings', $competence->assessments_count, ['count' => $competence->assessments_count]) }}</span>
                                @endif
                            </div>
                        </template>
                        @can('school-settings.edit')
                            <form x-show="editing === 'c-{{ $competence->id }}'" x-cloak method="POST" action="{{ route('admin.nursery.competences.update', $competence) }}" class="flex-1 flex flex-wrap items-center gap-2">
                                @csrf @method('PUT')
                                <input type="text" name="description" value="{{ $competence->description }}" required maxlength="255" class="flex-1 min-w-[12rem] text-sm border border-gray-300 rounded-lg px-3 py-1.5">
                                <select name="form_id" class="text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white" style="appearance:auto;">
                                    <option value="">{{ __('Every nursery class') }}</option>
                                    @foreach($forms as $f)
                                        <option value="{{ $f->id }}" @selected($competence->form_id === $f->id)>{{ $f->name }}</option>
                                    @endforeach
                                </select>
                                <label class="text-xs text-gray-600 inline-flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($competence->is_active)> {{ __('Shown') }}</label>
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-teal-600 text-white text-xs font-medium">{{ __('Save') }}</button>
                                <button type="button" @click="editing = null" class="text-xs text-gray-500">{{ __('Cancel') }}</button>
                            </form>
                            <div class="flex items-center gap-3 shrink-0" x-show="editing !== 'c-{{ $competence->id }}'">
                                <button type="button" @click="editing = 'c-{{ $competence->id }}'" class="text-xs text-gray-500 hover:text-gray-800">{{ __('Edit') }}</button>
                                <form method="POST" action="{{ route('admin.nursery.competences.destroy', $competence) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">{{ __('Remove') }}</button>
                                </form>
                            </div>
                        @endcan
                    </li>
                @empty
                    <li class="px-5 py-3 text-sm text-gray-400">{{ __('Nothing under this area yet.') }}</li>
                @endforelse

                @can('school-settings.edit')
                    <li class="px-5 py-3 bg-gray-50/40">
                        <form method="POST" action="{{ route('admin.nursery.competences.store', $area) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="text" name="description" required maxlength="255" placeholder="{{ __('Add a competence, e.g. “Counts objects up to twenty”') }}"
                                   class="flex-1 min-w-[14rem] text-sm border border-gray-300 rounded-lg px-3 py-1.5">
                            <select name="form_id" class="text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white" style="appearance:auto;">
                                <option value="">{{ __('Every nursery class') }}</option>
                                @foreach($forms as $f)
                                    <option value="{{ $f->id }}">{{ $f->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-medium text-gray-700 hover:bg-white">{{ __('Add') }}</button>
                        </form>
                    </li>
                @endcan
            </ul>
        </div>
    @endforeach

    @can('school-settings.edit')
        <form method="POST" action="{{ route('admin.nursery.competences.areas.store') }}" class="bg-white rounded-xl shadow-sm border border-dashed border-gray-300 p-4 flex flex-wrap items-center gap-2">
            @csrf
            <input type="text" name="name" required maxlength="120" placeholder="{{ __('New learning area, e.g. “Health and hygiene”') }}"
                   class="flex-1 min-w-[14rem] text-sm border border-gray-300 rounded-lg px-3 py-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-medium hover:bg-gray-900">{{ __('Add learning area') }}</button>
        </form>
    @endcan
</div>
@endsection
