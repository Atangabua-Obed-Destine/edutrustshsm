<?php

namespace App\Support;

use App\Exports\ListExport;
use App\Models\SchoolSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * One list screen's sorting, rows per page and exports, from one set of
 * column definitions, so every list behaves the same way.
 *
 *   $list = new Listing($request, 'students', __('Students'), [
 *       'student_id' => ['label' => __('Student ID'), 'sort' => 'student_id', 'value' => fn ($s) => $s->student_id],
 *       'name'       => ['label' => __('Name'), 'sort' => ['last_name', 'first_name'], 'value' => fn ($s) => $s->full_name],
 *       'class'      => ['label' => __('Class'), 'value' => fn ($s) => $s->currentEnrollment?->classSection?->name],
 *   ], default: 'name');
 *
 *   if ($download = $list->export($query)) return $download;
 *   $students = $list->paginate($query);
 *
 * A column's 'sort' is a column name, several, or fn ($query, $dir); without
 * one the column is not sortable. 'value' gives what an export writes;
 * 'money' => true formats it as an amount. Only defined keys can be sorted
 * on, so the address bar cannot reach any other column.
 *
 * Exports carry every row the filters select, not just the page on screen.
 */
class Listing
{
    public const PER_PAGE = [10, 25, 50, 100, 250];

    public const FORMATS = ['xlsx', 'csv', 'pdf', 'print'];

    /** Above this, PDF and print stop; Excel and CSV still take everything. */
    public const PRINT_LIMIT = 2000;

    public const SHEET_LIMIT = 50000;

    public string $sort;

    public string $dir;

    public int $perPage;

    /** @var array<string, string|null> what the export's heading says was filtered */
    protected array $filters = [];

    /**
     * @param  array<string, array{label: string, sort?: string|array<int, string>|Closure, value?: Closure, money?: bool, export?: bool}>  $columns
     */
    public function __construct(
        protected Request $request,
        public string $name,
        public string $title,
        public array $columns,
        ?string $default = null,
        string $defaultDir = 'asc',
        int $defaultPerPage = 25,
    ) {
        $sortable = array_keys(array_filter($columns, fn ($c) => isset($c['sort'])));
        $asked = (string) $request->query('sort', '');

        $this->sort = in_array($asked, $sortable, true) ? $asked : ($default ?? ($sortable[0] ?? ''));
        $this->dir = $request->query('dir') === 'desc' ? 'desc'
            : ($request->query('dir') === 'asc' ? 'asc' : ($asked === '' ? $defaultDir : 'asc'));
        $this->perPage = $this->resolvePerPage($defaultPerPage);
    }

    /**
     * Rows per page are remembered for the person, across every list, the way
     * a desktop program keeps the size you last chose.
     */
    protected function resolvePerPage(int $default): int
    {
        $asked = (int) $this->request->query('per_page', 0);

        if (in_array($asked, self::PER_PAGE, true)) {
            if ($this->request->hasSession()) {
                $this->request->session()->put('listing.per_page', $asked);
            }

            return $asked;
        }

        $saved = $this->request->hasSession() ? (int) $this->request->session()->get('listing.per_page', 0) : 0;

        return in_array($saved, self::PER_PAGE, true) ? $saved : $default;
    }

    /** What the filters on screen are, for the export's heading. */
    public function filters(array $filters): static
    {
        $this->filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        return $this;
    }

    public function isSortable(string $key): bool
    {
        return isset($this->columns[$key]['sort']);
    }

    /** @param  EloquentBuilder|QueryBuilder  $query */
    public function applySort($query)
    {
        if ($this->sort === '' || ! $this->isSortable($this->sort)) {
            return $query;
        }

        $sort = $this->columns[$this->sort]['sort'];
        $query->reorder();

        if ($sort instanceof Closure) {
            $sort($query, $this->dir);
        } else {
            foreach ((array) $sort as $column) {
                $query->orderBy($column, $this->dir);
            }
        }

        // The same rows in the same order on every page, even when the sorted
        // values tie.
        $key = $query instanceof EloquentBuilder ? $query->getModel()->getQualifiedKeyName() : 'id';

        return $query->orderBy($key, $this->dir);
    }

    /** @param  EloquentBuilder|QueryBuilder  $query */
    public function paginate($query): LengthAwarePaginator
    {
        return $this->applySort($query)->paginate($this->perPage)->withQueryString();
    }

    /**
     * The download, when one was asked for; null to show the screen.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public function export($query): ?Response
    {
        $format = (string) $this->request->query('export', '');

        if (! in_array($format, self::FORMATS, true)) {
            return null;
        }

        $this->applySort($query);
        $counter = clone $query;
        $total = ($counter instanceof EloquentBuilder ? $counter->toBase() : $counter)->getCountForPagination();
        $limit = in_array($format, ['pdf', 'print'], true) ? self::PRINT_LIMIT : self::SHEET_LIMIT;
        $columns = array_filter($this->columns, fn ($c) => ($c['export'] ?? true) && isset($c['value']));

        // In chunks, relations loaded per chunk; past the limit, the first
        // rows only, and the heading says so.
        $records = $total > $limit ? $query->limit($limit)->get() : $query->lazy(500);

        $rows = [];
        foreach ($records as $record) {
            $rows[] = array_map(fn ($c) => $this->cell($c, $record), $columns);
        }

        $headings = array_map(fn ($c) => $c['label'], array_values($columns));
        $money = array_values(array_map(fn ($c) => ! empty($c['money']), $columns));
        $rows = array_map('array_values', $rows);
        $file = Str::slug($this->title).'-'.now()->format('Y-m-d');
        $cut = $total > count($rows) ? $total : null;

        \App\Models\AuditLog::log('exported', 'list:'.$this->name, null, null, [
            'format' => $format, 'rows' => count($rows), 'filters' => $this->filters,
        ]);

        return match ($format) {
            'csv' => $this->csv($file, $headings, $rows),
            'xlsx' => Excel::download(new ListExport($this->heading(), $headings, $rows, $money), $file.'.xlsx'),
            'pdf' => Pdf::loadView('exports.list', $this->printData($headings, $rows, $money, $cut, false))
                ->setPaper('a4', count($headings) > 6 ? 'landscape' : 'portrait')
                ->download($file.'.pdf'),
            'print' => response()->view('exports.list', $this->printData($headings, $rows, $money, $cut, true)),
        };
    }

    /** @return array{0: string, 1: string, 2: string} school, title, filters and date */
    public function heading(): array
    {
        $parts = [];
        foreach ($this->filters as $label => $value) {
            $parts[] = $label.': '.$value;
        }

        return [
            SchoolSetting::current()?->school_name ?? config('app.name'),
            $this->title,
            trim(implode(' · ', $parts).($parts ? ' — ' : '').__('Printed :date', ['date' => now()->format('d/m/Y H:i')])),
        ];
    }

    /** The address of this list with some query values changed. */
    public function url(array $changes): string
    {
        $query = array_merge($this->request->query(), $changes);
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');

        return $this->request->url().($query ? '?'.http_build_query($query) : '');
    }

    public function sortUrl(string $key): string
    {
        $dir = $this->sort === $key && $this->dir === 'asc' ? 'desc' : 'asc';

        return $this->url(['sort' => $key, 'dir' => $dir, 'page' => null]);
    }

    public function exportUrl(string $format): string
    {
        return $this->url(['export' => $format, 'page' => null]);
    }

    protected function cell(array $column, $record): mixed
    {
        $value = $column['value']($record);

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (! empty($column['money'])) {
            return $value === null || $value === '' ? null : round((float) $value, 2);
        }

        return is_bool($value) ? ($value ? __('Yes') : __('No')) : $value;
    }

    protected function csv(string $file, array $headings, array $rows): Response
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            // A byte-order mark, so Excel reads the accents in names correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => $v === null ? '' : (string) $v, $row), ',', '"', '');
            }
            fclose($out);
        }, $file.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function printData(array $headings, array $rows, array $money, ?int $cut, bool $print): array
    {
        [$school, $title, $subtitle] = $this->heading();

        return compact('school', 'title', 'subtitle', 'headings', 'rows', 'money', 'cut', 'print');
    }
}
