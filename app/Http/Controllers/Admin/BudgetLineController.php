<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\BudgetLine;
use App\Models\DefaultAccountMapping;
use App\Models\ExpenseCategory;
use App\Models\FeeCategory;
use App\Models\IncomeCategory;
use App\Services\BudgetActualsService;
use App\Services\BudgetLineTemplate;
use App\Support\LevelContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The rows of the Income & Expenditure sheet, and what feeds them.
 *
 * A line only ever shows money once a category points at it, so this screen
 * is as much about the links as the lines: for each line, which categories
 * feed it; and which categories are not on the sheet at all.
 */
class BudgetLineController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'budget-line';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('budget-line.view', ['index']),
            static::can('budget-line.edit', ['loadTemplate', 'store', 'update', 'toggle', 'destroy', 'reorder', 'autoSort', 'storeCategory', 'linkCategory']),
        ];
    }

    /** Which kinds of category may feed a line, by the half of the sheet it sits in. */
    public static function categoryTypesFor(string $section): array
    {
        return $section === BudgetLine::SECTION_INCOME ? ['fee_payment', 'income'] : ['expense'];
    }

    public function index()
    {
        $lines = BudgetLine::query()->orderBy('sort_order')->orderBy('code')->get();
        $ordered = BudgetLine::inTreeOrder($lines);

        $categories = $this->categories();
        $byLine = $categories->filter(fn ($c) => $c['line_id'])->groupBy('line_id');
        $unlinked = $categories->filter(fn ($c) => ! $c['line_id'])->groupBy('type');

        $references = $this->references($lines->pluck('id')->all());

        $sections = [];
        foreach (BudgetLine::SECTIONS as $section) {
            $sections[$section] = $ordered->where('section', $section)->values();
        }

        return view('admin.budget-line.index', [
            'sections' => $sections,
            'headers' => $lines->where('is_header', true)->groupBy('section'),
            'byLine' => $byLine,
            'unlinked' => $unlinked,
            'references' => $references,
            'levels' => $this->levelOptions(),
            'empty' => $lines->isEmpty(),
        ]);
    }

    public function loadTemplate(BudgetLineTemplate $template)
    {
        $result = $template->load();

        return back()->with('success', __('Standard sheet loaded: :created lines added, :present already present.', $result));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = (int) (BudgetLine::where('section', $data['section'])->max('sort_order') ?? 0) + 10;
        $data['created_by'] = $request->user()?->id;

        $line = BudgetLine::create($data);

        return back()->with('success', __(':code added to the sheet.', ['code' => $line->code]));
    }

    public function update(Request $request, BudgetLine $line)
    {
        $data = $this->validated($request, $line);

        if (! $data['is_header'] && $line->is_header && $line->children()->exists()) {
            return back()->with('error', __('This heading still has lines filed under it. Move them first.'));
        }
        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $line->id) {
            return back()->with('error', __('A line cannot be filed under itself.'));
        }

        $line->update($data);

        return back()->with('success', __(':code updated.', ['code' => $line->code]));
    }

    /**
     * Take a line off the sheet, or put it back, without touching history.
     * A heading is refused while lines still sit under it. A line carrying
     * money is allowed, but said out loud, because that money then shows
     * nowhere until the line returns.
     */
    public function toggle(BudgetLine $line)
    {
        if ($line->status && $line->is_header && $line->children()->where('status', true)->exists()) {
            return back()->with('error', __('Retire or move the lines under this heading first.'));
        }

        $line->update(['status' => ! $line->status]);

        if (! $line->status) {
            $carried = $this->moneyOn($line);
            $message = $carried > 0
                ? __(':code retired. It carried :amount which now appears nowhere on the sheet until the line is restored or its categories are re-pointed.', ['code' => $line->code, 'amount' => number_format($carried)])
                : __(':code retired.', ['code' => $line->code]);

            return back()->with('success', $message);
        }

        return back()->with('success', __(':code is back on the sheet.', ['code' => $line->code]));
    }

    public function destroy(BudgetLine $line)
    {
        $refs = $this->references([$line->id])[$line->id] ?? [];

        if ($refs !== []) {
            return back()->with('error', __(':code cannot be deleted: :reasons. Retire it instead.', ['code' => $line->code, 'reasons' => implode(', ', $refs)]));
        }
        if ($line->children()->exists()) {
            return back()->with('error', __('Move the lines filed under :code before deleting it.', ['code' => $line->code]));
        }

        $line->delete();

        return back()->with('success', __(':code deleted.', ['code' => $line->code]));
    }

    /**
     * Persist a new running order, as dragged. The browser posts one section's
     * lines in the order they now appear, each with the heading it now sits
     * under: where a line is on the sheet is what it belongs to.
     */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'section' => ['required', Rule::in(BudgetLine::SECTIONS)],
            'lines' => ['required', 'array'],
            'lines.*.id' => ['required', 'integer', 'exists:budget_lines,id'],
            'lines.*.parent_id' => ['nullable', 'integer'],
        ]);

        $ids = collect($data['lines'])->pluck('id')->map(fn ($v) => (int) $v)->all();
        $lines = BudgetLine::whereIn('id', $ids)->where('section', $data['section'])->get()->keyBy('id');

        if ($lines->count() !== count($ids)) {
            return response()->json(['message' => __('Some lines could not be moved there — reload to see where they are')], 422);
        }

        DB::transaction(function () use ($data, $lines) {
            $order = 0;
            foreach ($data['lines'] as $row) {
                $line = $lines[(int) $row['id']];
                $parentId = ! empty($row['parent_id']) ? (int) $row['parent_id'] : null;
                $parent = $parentId ? $lines->get($parentId) : null;

                // A heading never sits under another heading, and a line only
                // ever under a heading of its own section.
                if ($line->is_header || ($parent && ! $parent->is_header)) {
                    $parentId = null;
                }
                $order += 10;
                $line->update(['sort_order' => $order, 'parent_id' => $parentId]);
            }
        });

        return response()->json(['message' => __('Order saved')]);
    }

    /** Renumber a section by code, keeping the heading structure. */
    public function autoSort(Request $request)
    {
        $data = $request->validate(['section' => ['required', Rule::in(BudgetLine::SECTIONS)]]);

        $lines = BudgetLine::where('section', $data['section'])->orderBy('code')->get();
        $order = 0;
        foreach ($lines as $line) {
            $order += 10;
            $line->update(['sort_order' => $order]);
        }

        return back()->with('success', __(':section renumbered from its codes.', ['section' => BudgetLine::sectionLabels()[$data['section']]]));
    }

    /** Create a category named after this line, and point it at the line. */
    public function storeCategory(Request $request, BudgetLine $line)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:191']]);

        if ($line->is_header) {
            return back()->with('error', __('A heading holds no money; give the category to a line under it.'));
        }

        $type = $line->section === BudgetLine::SECTION_INCOME ? 'income' : 'expense';
        $model = $type === 'income' ? IncomeCategory::class : ExpenseCategory::class;

        $category = $model::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(4)),
            'status' => true,
        ]);

        $this->point($type, $category->id, $line);

        return back()->with('success', __('Category ":title" created and pointed at :code.', ['title' => $category->title, 'code' => $line->code]));
    }

    /** Point a category that already exists at this line. */
    public function linkCategory(Request $request, BudgetLine $line)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['fee_payment', 'income', 'expense'])],
            'category_id' => ['required', 'integer'],
        ]);

        if ($line->is_header) {
            return back()->with('error', __('A heading holds no money; give the category to a line under it.'));
        }
        if (! in_array($data['type'], self::categoryTypesFor($line->section), true)) {
            return back()->with('error', __('That kind of category cannot feed a :section line.', ['section' => strtolower(BudgetLine::sectionLabels()[$line->section])]));
        }

        $exists = match ($data['type']) {
            'fee_payment' => FeeCategory::whereKey($data['category_id'])->exists(),
            'income' => IncomeCategory::whereKey($data['category_id'])->exists(),
            default => ExpenseCategory::whereKey($data['category_id'])->exists(),
        };
        if (! $exists) {
            return back()->with('error', __('That category no longer exists.'));
        }

        $this->point($data['type'], (int) $data['category_id'], $line);

        return back()->with('success', __('Category pointed at :code.', ['code' => $line->code]));
    }

    // ---------------------------------------------------------------- helpers

    private function point(string $type, int $categoryId, BudgetLine $line): void
    {
        $mapping = DefaultAccountMapping::firstOrNew(['mapping_type' => $type, 'category_id' => $categoryId]);
        $mapping->budget_line_id = $line->id;
        $mapping->status = $mapping->status ?: 'active';
        $mapping->save();
    }

    private function validated(Request $request, ?BudgetLine $line = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('budget_lines', 'code')->ignore($line?->id)],
            'name' => ['required', 'string', 'max:191'],
            'name_fr' => ['nullable', 'string', 'max:191'],
            'section' => ['required', Rule::in(BudgetLine::SECTIONS)],
            'is_header' => ['nullable', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:budget_lines,id'],
            'school_level' => ['nullable', 'string', 'max:30'],
            'profit_centre' => ['nullable', 'string', 'max:191'],
            'is_payroll' => ['nullable', 'boolean'],
        ]);

        $data['is_header'] = (bool) ($data['is_header'] ?? false);
        $data['is_payroll'] = ! $data['is_header'] && (bool) ($data['is_payroll'] ?? false);
        $data['parent_id'] = $data['is_header'] ? null : ($data['parent_id'] ?? null);
        $data['school_level'] = $data['school_level'] ?: null;
        $data['profit_centre'] = trim((string) ($data['profit_centre'] ?? '')) ?: null;

        if ($data['parent_id']) {
            $parent = BudgetLine::find($data['parent_id']);
            if (! $parent || ! $parent->is_header || $parent->section !== $data['section']) {
                $data['parent_id'] = null;
            }
        }

        return $data;
    }

    /** @return \Illuminate\Support\Collection<int, array{type: string, id: int, title: string, line_id: ?int}> */
    private function categories()
    {
        $mapped = DefaultAccountMapping::whereNotNull('category_id')
            ->get(['mapping_type', 'category_id', 'budget_line_id'])
            ->keyBy(fn ($m) => $m->mapping_type.':'.$m->category_id);

        $rows = collect();
        foreach (FeeCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']) as $c) {
            $rows->push(['type' => 'fee_payment', 'id' => $c->id, 'title' => $c->name, 'line_id' => $mapped['fee_payment:'.$c->id]->budget_line_id ?? null]);
        }
        foreach (IncomeCategory::where('status', true)->orderBy('title')->get(['id', 'title']) as $c) {
            $rows->push(['type' => 'income', 'id' => $c->id, 'title' => $c->title, 'line_id' => $mapped['income:'.$c->id]->budget_line_id ?? null]);
        }
        foreach (ExpenseCategory::where('status', true)->orderBy('title')->get(['id', 'title']) as $c) {
            $rows->push(['type' => 'expense', 'id' => $c->id, 'title' => $c->title, 'line_id' => $mapped['expense:'.$c->id]->budget_line_id ?? null]);
        }

        return $rows;
    }

    /**
     * What references each line, so the screen can say why one is undeletable.
     *
     * @return array<int, array<int, string>>
     */
    private function references(array $ids): array
    {
        $refs = [];
        $count = function (string $table, string $column) use ($ids) {
            return DB::table($table)->whereIn($column, $ids)->selectRaw("$column as id, count(*) as n")->groupBy($column)->pluck('n', 'id');
        };

        foreach ([
            ['default_account_mappings', 'budget_line_id', __('categories point at it')],
            ['expenses', 'budget_line_id', __('expenses name it')],
            ['incomes', 'budget_line_id', __('incomes name it')],
            ['budget_allocations', 'budget_line_id', __('budgeted figures')],
        ] as [$table, $column, $label]) {
            foreach ($count($table, $column) as $id => $n) {
                $refs[(int) $id][] = $n.' '.$label;
            }
        }

        return $refs;
    }

    private function moneyOn(BudgetLine $line): float
    {
        $actuals = app(BudgetActualsService::class)->forPeriod();

        return (float) ($actuals['lines'][$line->id] ?? 0);
    }

    /** @return array<string, string> level key => label */
    private function levelOptions(): array
    {
        $labels = [
            'nursery' => __('Nursery'),
            'primary' => __('Primary'),
            'first_cycle' => __('Secondary (First Cycle)'),
            'second_cycle' => __('High School (Second Cycle)'),
        ];

        $runs = [];
        foreach (LevelContext::options() as $schoolLevel) {
            foreach (\App\Models\Form::levelsFor($schoolLevel) as $level) {
                $runs[$level] = $labels[$level] ?? $level;
            }
        }

        return $runs;
    }
}
