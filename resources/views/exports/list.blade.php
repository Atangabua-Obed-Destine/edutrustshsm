{{-- Any list screen, printed or as a PDF (App\Support\Listing). --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — {{ $school }}</title>
    <style>
        @page { margin: 14mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 0; }
        .head { border-bottom: 2px solid #1f3a5f; padding-bottom: 6px; margin-bottom: 8px; }
        .school { font-size: 15px; font-weight: bold; }
        .title { font-size: 13px; font-weight: bold; color: #1f3a5f; margin-top: 2px; }
        .sub { color: #555; font-style: italic; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1f3a5f; color: #fff; text-align: left; padding: 4px 5px; font-size: 9px; text-transform: uppercase; }
        td { padding: 3px 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .num { text-align: right; white-space: nowrap; }
        .cut { margin-top: 8px; padding: 6px; border: 1px solid #f59e0b; background: #fffbeb; color: #92400e; }
        .count { margin-top: 6px; color: #555; }
        @media print { .no-print { display: none; } thead { display: table-header-group; } }
        @media screen { body { margin: 20px; } }
    </style>
</head>
<body>
    @if($print)
        <p class="no-print"><button type="button" onclick="window.print()">{{ __('Print') }}</button></p>
    @endif
    <div class="head">
        <div class="school">{{ $school }}</div>
        <div class="title">{{ $title }}</div>
        <div class="sub">{{ $subtitle }}</div>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 22px">#</th>
                @foreach($headings as $i => $h)
                    <th @class(['num' => $money[$i] ?? false])>{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $n => $row)
                <tr>
                    <td>{{ $n + 1 }}</td>
                    @foreach($row as $i => $value)
                        <td @class(['num' => $money[$i] ?? false])>{{ ($money[$i] ?? false) && $value !== null ? number_format((float) $value, 0, ',', ' ') : $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) + 1 }}">{{ __('Nothing to show.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="count">{{ trans_choice('{0} No rows|{1} :count row|[2,*] :count rows', count($rows), ['count' => number_format(count($rows), 0, ',', ' ')]) }}</p>
    @if($cut)
        <p class="cut">{{ __('Only the first :shown of :total rows are here. Use Excel or CSV for all of them, or narrow the filters.', ['shown' => number_format(count($rows), 0, ',', ' '), 'total' => number_format($cut, 0, ',', ' ')]) }}</p>
    @endif
    @if($print)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>
</html>
