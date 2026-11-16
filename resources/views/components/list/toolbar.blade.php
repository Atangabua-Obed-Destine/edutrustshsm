{{--
    The bar above every list (App\Support\Listing): how many rows, how many
    on a page, and the list as Excel, CSV, PDF or printed. Exports carry every
    row the filters select, not just this page.

    <x-list.toolbar :list="$list" :rows="$students" />

    :exportable="false" hides the export buttons, for someone whose role
    may see the list but not take it away.
--}}
@props(['list', 'rows', 'exportable' => true])
@php
    // The current filters, carried when the page size changes.
    $__keep = [];
    foreach (explode('&', http_build_query(request()->except(['per_page', 'page', 'export']))) as $__pair) {
        if ($__pair === '') continue;
        [$__k, $__v] = array_pad(explode('=', $__pair, 2), 2, '');
        $__keep[] = [urldecode($__k), urldecode($__v)];
    }
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50/70 px-3 py-1.5 text-xs text-gray-600']) }} data-list-toolbar>
    <span class="tabular-nums" data-list-count>
        @if($rows->total() > 0)
            {{ __('Showing :from–:to of :total', ['from' => number_format($rows->firstItem(), 0, ',', ' '), 'to' => number_format($rows->lastItem(), 0, ',', ' '), 'total' => number_format($rows->total(), 0, ',', ' ')]) }}
        @else
            {{ __('No rows') }}
        @endif
    </span>

    <div class="flex flex-wrap items-center gap-2">
        <form method="GET" action="{{ request()->url() }}" class="inline-flex items-center gap-1">
            @foreach($__keep as [$__k, $__v])
                <input type="hidden" name="{{ $__k }}" value="{{ $__v }}">
            @endforeach
            <label for="per-page-{{ $list->name }}">{{ __('Rows') }}</label>
            <select id="per-page-{{ $list->name }}" name="per_page" onchange="this.form.requestSubmit()"
                    class="rounded border border-gray-300 bg-white py-0.5 pl-1.5 pr-6 text-xs" style="appearance:auto;-webkit-appearance:menulist;">
                @foreach(\App\Support\Listing::PER_PAGE as $__n)
                    <option value="{{ $__n }}" @selected($list->perPage === $__n)>{{ $__n }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="underline">{{ __('Go') }}</button></noscript>
        </form>

        @if($exportable)
        <span class="inline-flex overflow-hidden rounded-md border border-gray-300 bg-white" role="group" aria-label="{{ __('Export this list') }}">
            <a href="{{ $list->exportUrl('xlsx') }}" data-no-swap data-no-busy class="px-2 py-0.5 hover:bg-gray-100" title="{{ __('Every row the filters select, as an Excel workbook') }}">Excel</a>
            <a href="{{ $list->exportUrl('csv') }}" data-no-swap data-no-busy class="border-l border-gray-300 px-2 py-0.5 hover:bg-gray-100" title="{{ __('Every row the filters select, as a CSV file') }}">CSV</a>
            <a href="{{ $list->exportUrl('pdf') }}" data-no-swap data-no-busy class="border-l border-gray-300 px-2 py-0.5 hover:bg-gray-100" title="{{ __('Every row the filters select, as a PDF') }}">PDF</a>
            <a href="{{ $list->exportUrl('print') }}" target="_blank" rel="noopener" class="border-l border-gray-300 px-2 py-0.5 hover:bg-gray-100" title="{{ __('Print every row the filters select') }}">{{ __('Print') }}</a>
        </span>
        @endif
    </div>
</div>
