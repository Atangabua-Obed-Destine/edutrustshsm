{{-- Row box for x-list.bulk. Shift-click ticks every row in between. --}}
@props(['value', 'label' => null])
<input type="checkbox" value="{{ $value }}" data-bulk-item aria-label="{{ $label ?? __('Tick this row') }}" {{ $attributes->merge(['class' => 'h-4 w-4 rounded border-gray-300 align-middle']) }}>
