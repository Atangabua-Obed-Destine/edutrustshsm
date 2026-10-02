<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\RecurringJournalEntry;
use App\Services\RecurringEntryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recurring journal entry templates — rent, standing charges, monthly accruals.
 *
 * The generator and its daily schedule existed, but there was no screen at all:
 * a template could only be created by writing rows into the database. Actions
 * follow the reference system's RecurringEntryController — pause, resume, skip
 * the next run, run now, run everything due, duplicate — as server-rendered
 * forms rather than JSON endpoints.
 */
class RecurringEntryController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'recurring-entry';

    public function __construct(private RecurringEntryService $recurring)
    {
    }

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('recurring-entry.edit', ['pause', 'resume', 'skipNext']),
            static::can('recurring-entry.create', ['duplicate']),
            static::can('recurring-entry.process', ['process', 'processAll']),
        ];
    }

    public function index(Request $request)
    {
        $templates = RecurringJournalEntry::with('lines')
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->input('status') === 'active'))
            ->when($request->filled('frequency'), fn ($q) => $q->where('frequency', $request->input('frequency')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->input('search').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->orderByDesc('is_active')
            ->orderBy('next_run_date')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'active' => RecurringJournalEntry::active()->count(),
            'paused' => RecurringJournalEntry::where('is_active', false)->count(),
            'due' => RecurringJournalEntry::due()->count(),
        ];

        return view('admin.accounting.recurring.index', [
            'templates' => $templates,
            'summary' => $summary,
            'upcoming' => $this->recurring->upcoming(30),
            'frequencies' => RecurringJournalEntry::FREQUENCIES,
        ]);
    }

    public function create()
    {
        return view('admin.accounting.recurring.form', [
            'template' => new RecurringJournalEntry(['frequency' => 'monthly', 'start_date' => now()->startOfMonth()->addMonth()]),
            'accounts' => $this->accounts(),
            'frequencies' => RecurringJournalEntry::FREQUENCIES,
            'lines' => [],
        ]);
    }

    public function store(Request $request)
    {
        [$attributes, $lines] = $this->validated($request);

        $template = DB::transaction(function () use ($attributes, $lines) {
            $template = RecurringJournalEntry::create($attributes + [
                'next_run_date' => $attributes['start_date'],
                'runs_generated' => 0,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            $this->writeLines($template, $lines);

            return $template;
        });

        return redirect()->route('admin.recurring-entries.show', $template)
            ->with('success', __('Recurring entry created. It will first run on :date.', [
                'date' => $template->next_run_date->format('d M Y'),
            ]));
    }

    public function show(RecurringJournalEntry $recurringEntry)
    {
        $recurringEntry->load(['lines.account', 'createdBy']);

        $generated = JournalEntry::where('reference_type', 'recurring_entry')
            ->where('reference_id', $recurringEntry->id)
            ->orderByDesc('entry_date')
            ->limit(24)
            ->get();

        return view('admin.accounting.recurring.show', [
            'template' => $recurringEntry,
            'generated' => $generated,
            'schedule' => $this->recurring->upcomingFor($recurringEntry, 6),
        ]);
    }

    public function edit(RecurringJournalEntry $recurringEntry)
    {
        $recurringEntry->load('lines');

        return view('admin.accounting.recurring.form', [
            'template' => $recurringEntry,
            'accounts' => $this->accounts(),
            'frequencies' => RecurringJournalEntry::FREQUENCIES,
            'lines' => $recurringEntry->lines->map(fn ($l) => [
                'account_id' => $l->account_id,
                'description' => $l->description,
                'debit' => (float) $l->debit ?: null,
                'credit' => (float) $l->credit ?: null,
            ])->all(),
        ]);
    }

    public function update(Request $request, RecurringJournalEntry $recurringEntry)
    {
        [$attributes, $lines] = $this->validated($request);

        DB::transaction(function () use ($recurringEntry, $attributes, $lines) {
            // A template that has never run follows its start date. One that has
            // run keeps its place in the schedule, so editing it cannot make it
            // re-generate a month it already produced.
            if ($recurringEntry->runs_generated === 0) {
                $attributes['next_run_date'] = $attributes['start_date'];
            }

            $recurringEntry->update($attributes);
            $recurringEntry->lines()->delete();
            $this->writeLines($recurringEntry, $lines);
        });

        return redirect()->route('admin.recurring-entries.show', $recurringEntry)
            ->with('success', __('Recurring entry updated.'));
    }

    public function destroy(RecurringJournalEntry $recurringEntry)
    {
        // Entries it already generated point back at it; pausing keeps that
        // history readable where deleting would orphan it.
        if ($recurringEntry->runs_generated > 0) {
            return back()->with('error', __('This template has already generated entries, so it cannot be deleted. Pause it instead.'));
        }

        $recurringEntry->delete();

        return redirect()->route('admin.recurring-entries.index')
            ->with('success', __('Recurring entry deleted.'));
    }

    public function pause(RecurringJournalEntry $recurringEntry)
    {
        $this->recurring->pause($recurringEntry);

        return back()->with('success', __('Recurring entry paused. Nothing will be generated until it is resumed.'));
    }

    public function resume(Request $request, RecurringJournalEntry $recurringEntry)
    {
        $validated = $request->validate([
            'next_run_date' => ['nullable', 'date'],
        ]);

        try {
            $template = $this->recurring->resume($recurringEntry, $validated['next_run_date'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Recurring entry resumed. Next run: :date.', [
            'date' => $template->next_run_date->format('d M Y'),
        ]));
    }

    public function skipNext(RecurringJournalEntry $recurringEntry)
    {
        $template = $this->recurring->skipNext($recurringEntry);

        return back()->with('success', $template->is_active
            ? __('Next run skipped. It will now run on :date.', ['date' => $template->next_run_date->format('d M Y')])
            : __('Next run skipped. That was the last one, so the template has finished.'));
    }

    public function process(RecurringJournalEntry $recurringEntry)
    {
        try {
            $entry = $this->recurring->generate($recurringEntry->load('lines'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (! $entry) {
            return back()->with('error', __('This template is not due yet. Its next run is :date.', [
                'date' => $recurringEntry->fresh()->next_run_date->format('d M Y'),
            ]));
        }

        return back()->with('success', __('Generated journal entry :number.', ['number' => $entry->entry_number]));
    }

    public function processAll()
    {
        $result = $this->recurring->runDue();

        // Only the counts: the result also carries the error list, and handing
        // an array to the translator as a replacement throws.
        $message = __(':generated generated, :skipped already done.', [
            'generated' => $result['generated'],
            'skipped' => $result['skipped'],
        ]);

        if ($result['errors'] !== []) {
            return back()->with('error', $message.' '.implode(' ', $result['errors']));
        }

        return back()->with('success', $message);
    }

    public function duplicate(RecurringJournalEntry $recurringEntry)
    {
        $copy = $this->recurring->duplicate($recurringEntry);

        return redirect()->route('admin.recurring-entries.edit', $copy)
            ->with('success', __('Copy created and paused. Check the dates, then resume it.'));
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'frequency' => ['required', 'in:'.implode(',', array_keys(RecurringJournalEntry::FREQUENCIES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'auto_post' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $debit = 0.0;
        $credit = 0.0;

        foreach ($validated['lines'] as $line) {
            $d = (float) ($line['debit'] ?? 0);
            $c = (float) ($line['credit'] ?? 0);

            if (($d > 0) === ($c > 0)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lines' => __('Each line must have either a debit or a credit, not both or neither.'),
                ]);
            }

            $debit += $d;
            $credit += $c;
        }

        // Checked at save time as well as at run time: a template that does not
        // balance would otherwise fail silently every month until someone looked.
        if (abs($debit - $credit) > 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => __('Entry is not balanced: debits (:d) must equal credits (:c).', [
                    'd' => number_format($debit, 2), 'c' => number_format($credit, 2),
                ]),
            ]);
        }

        $attributes = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'frequency' => $validated['frequency'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'auto_post' => $request->boolean('auto_post'),
        ];

        return [$attributes, array_values($validated['lines'])];
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function writeLines(RecurringJournalEntry $template, array $lines): void
    {
        foreach ($lines as $i => $line) {
            $template->lines()->create([
                'account_id' => $line['account_id'],
                'line_number' => $i + 1,
                'debit' => (float) ($line['debit'] ?? 0),
                'credit' => (float) ($line['credit'] ?? 0),
                'description' => $line['description'] ?? null,
            ]);
        }
    }

    private function accounts()
    {
        return ChartOfAccount::where('is_active', true)
            ->where('account_type', 'detail')
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);
    }
}
