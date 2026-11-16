{{--
    The bar that appears once rows are ticked: how many, the actions that
    apply to all of them, and Clear. Put it inside the same data-bulk-scope
    as the table. Each action is a form with data-bulk-form; the shell sends
    it with ids[] of the ticked rows (resources/js/shell.js).

    <div data-bulk-scope>
        <x-list.bulk>
            <form method="GET" action="..." target="_blank" data-bulk-form data-no-swap>
                <button type="submit">Print receipts</button>
            </form>
        </x-list.bulk>
        <table> ... <th><x-list.check-all /></th> ... <td><x-list.check :value="$row->id" /></td>
--}}
<div data-bulk-bar hidden {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 border-b border-sky-200 bg-sky-50 px-3 py-1.5 text-xs text-sky-900']) }}>
    <span class="font-semibold tabular-nums" data-bulk-count data-template="{{ __(':count ticked') }}"></span>
    <span class="flex flex-wrap items-center gap-2 [&_button]:rounded-md [&_button]:border [&_button]:border-sky-300 [&_button]:bg-white [&_button]:px-2.5 [&_button]:py-0.5 [&_button]:font-medium [&_button:hover]:bg-sky-100">
        {{ $slot }}
    </span>
    <button type="button" data-bulk-clear class="ml-auto underline hover:no-underline">{{ __('Clear') }}</button>
</div>
