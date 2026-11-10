<?php

namespace App\Services\EdutrustPay;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who a branch has at the end of a month: its students, form by form, and its
 * staff, by name, department and post. Never pay.
 *
 * HOW A STUDENT IS COUNTED
 *
 * student_enrollments holds one row per student per TERM, and its status is
 * today's state, not the state at month end (a term move closes the old row as
 * "completed"; a promotion marks last year's row "promoted"). Counting rows
 * therefore double-counted after every promotion, and dropped students from
 * past months whenever they moved term. So a student counts once, in the
 * session the month falls in, if they were enrolled by month end and had not
 * left by then. Leaving is read from students.withdrawal_date, the date the
 * exit was recorded; a row marked withdrawn or transferred with no date is
 * taken as gone.
 *
 * Everything is branch-scoped by hand: this runs from the console, where the
 * branch scope is inactive.
 */
class PeopleSummaryService
{
    private string $end;

    public function __construct(private int $branchId, string $period)
    {
        $this->end = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth()->toDateString();
    }

    /**
     * The session the month falls in: the latest one started by month end.
     * The same choice the fees charged use, so the two always name one session.
     */
    public function session(): ?object
    {
        return DB::table('academic_sessions')
            ->where('branch_id', $this->branchId)
            ->whereDate('start_date', '<=', $this->end)
            ->orderByDesc('start_date')
            ->first();
    }

    /** Students on the roll at month end, each counted once. */
    public function studentCount(): int
    {
        return array_sum($this->studentsByForm());
    }

    /**
     * @return array<string, int>  form name => students, using each student's latest class that month
     */
    public function studentsByForm(): array
    {
        $session = $this->session();

        if (! $session) {
            return [];
        }

        $rows = DB::table('student_enrollments as e')
            ->join('students as s', 's.id', '=', 'e.student_id')
            ->leftJoin('class_sections as c', 'c.id', '=', 'e.class_section_id')
            ->leftJoin('forms as f', 'f.id', '=', 'c.form_id')
            ->where('e.branch_id', $this->branchId)
            ->where('e.academic_session_id', $session->id)
            ->whereDate('e.enrollment_date', '<=', $this->end)
            ->where(fn ($q) => $q->whereNull('s.withdrawal_date')->orWhereDate('s.withdrawal_date', '>', $this->end))
            ->where(fn ($q) => $q->whereNotIn('e.status', ['withdrawn', 'transferred'])->orWhereNotNull('s.withdrawal_date'))
            ->orderBy('e.enrollment_date')
            ->orderBy('e.id')
            ->get(['e.student_id', 'f.name as form']);

        // The last row wins: a student who changed class mid-year counts in the new one.
        $latest = $rows->keyBy('student_id');

        return $latest->countBy(fn ($row) => $row->form ?: 'No class')->sortKeys(SORT_NATURAL)->all();
    }

    /**
     * Staff on the books at month end: the people the school's own Staff screen
     * lists, in this branch, who had joined by then and had not left.
     *
     * A leaving date decides when there is one. Without one, an account switched
     * off is taken as gone, since nothing says when; the console never refiles a
     * closed month for this, so history is not rewritten by it.
     *
     * @return list<array{number: ?string, name: string, department: ?string, designation: ?string}>
     */
    public function staff(): array
    {
        return User::query()
            ->staff()
            ->whereHas('branches', fn ($q) => $q->where('branches.id', $this->branchId))
            ->where(fn ($q) => $q->whereNull('joining_date')->orWhereDate('joining_date', '<=', $this->end))
            ->where(fn ($q) => $q->whereDate('ending_date', '>', $this->end)
                ->orWhere(fn ($open) => $open->whereNull('ending_date')->where('is_active', true)))
            ->with([
                'department' => fn ($q) => $q->withoutBranchScope(),
                'designation' => fn ($q) => $q->withoutBranchScope(),
            ])
            ->get()
            ->map(fn (User $user) => [
                'number' => $user->staff_id ?: null,
                'name' => $user->full_name,
                'department' => $user->department?->name,
                'designation' => $user->designation?->title,
            ])
            ->filter(fn (array $person) => trim($person['name']) !== '')
            ->values()
            ->all();
    }
}
