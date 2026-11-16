{{--
    A column heading that sorts the list when clicked (App\Support\Listing).
    A column without a 'sort' in the listing is a plain heading.

    <x-list.th :list="$list" key="name" class="px-4 py-3 text-left" />
--}}
@props(['list', 'key'])
@php
    $__sortable = $list->isSortable($key);
    $__active = $__sortable && $list->sort === $key;
    $__label = $slot->isEmpty() ? ($list->columns[$key]['label'] ?? $key) : $slot;
@endphp
<th {{ $attributes }} @if($__sortable) aria-sort="{{ $__active ? ($list->dir === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif>
    @if($__sortable)
        <a href="{{ $list->sortUrl($key) }}" class="group inline-flex items-center gap-1 hover:text-gray-900 {{ $__active ? 'text-gray-900' : '' }}"
           title="{{ $__active && $list->dir === 'asc' ? __('Sorted A to Z. Click for Z to A.') : __('Click to sort') }}">
            <span>{{ $__label }}</span>
            <span aria-hidden="true" class="text-[9px] {{ $__active ? 'text-teal-600' : 'text-gray-300 group-hover:text-gray-500' }}">{{ $__active ? ($list->dir === 'asc' ? '▲' : '▼') : '▲▼' }}</span>
        </a>
    @else
        {{ $__label }}
    @endif
</th>
