<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ChartOfAccount;
use App\Models\ClassSection;
use App\Models\DefaultAccountMapping;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\FiscalYear;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\StudentEnrollment;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/**
 * Answers the most useful question an incomplete system can be asked: what have
 * you not configured yet, and where do I go to fix it.
 *
 * Every check reports one of three verdicts. An ERROR means something is
 * actively broken — marks will not save, report cards will come out empty,
 * payments will not reach the ledger. A WARNING means the school can work but
 * something downstream will misbehave. Everything else is reported as healthy,
 * so the screen is also a statement of what IS set up.
 */
class ConfigurationHealthService
{
    private array $errors = [];
    private array $warnings = [];
    private array $healthy = [];

    /**
     * @return array{
     *     errors: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>,
     *     healthy: array<int, array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    public function report(): array
    {
        $this->errors = $this->warnings = $this->healthy = [];

        $this->checkSession();
        $this->checkTerms();
        $this->checkSequences();
        $this->checkCurriculum();
        $this->checkGrading();
        $this->checkEnrollments();
        $this->checkFees();
        $this->checkLedger();
        $this->checkInfrastructure();

        return [
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'healthy' => $this->healthy,
            'summary' => [
                'errors' => count($this->errors),
                'warnings' => count($this->warnings),
                'healthy' => count($this->healthy),
            ],
        ];
    }

    // ---------------------------------------------------------------- checks

    private function checkSession(): void
    {
        $current = AcademicSession::where('is_current', true)->get();

        if ($current->isEmpty()) {
            $this->error('Academic Year', __('No current academic year'), __('Nothing can be enrolled, marked or billed until one academic year is marked as current.'), 'admin.sessions.index', __('Manage Academic Years'));

            return;
        }

        if ($current->count() > 1) {
            $this->error('Academic Year', __('More than one current academic year'), __(':count academic years are marked as current, so which one a new record belongs to is undefined.', ['count' => $current->count()]), 'admin.sessions.index', __('Fix Academic Years'));

            return;
        }

        $this->ok('Academic Year', __('Current academic year set'), __(':name is the current academic year.', ['name' => $current->first()->name]));
    }

    private function checkTerms(): void
    {
        $terms = Term::count();

        if ($terms === 0) {
            $this->error('Terms', __('No terms configured'), __('Students are enrolled per term, so with no terms nobody can be enrolled at all.'), 'admin.terms.index', __('Set Up Terms'));

            return;
        }

        $this->ok('Terms', __('Terms configured'), __(':count term(s) configured.', ['count' => $terms]));

        if (Term::where('is_current', true)->count() !== 1) {
            $this->warn('Terms', __('No single current term'), __('Marks entry, bulk upload and enrollment all default to the current term. Exactly one term should be marked current.'), 'admin.terms.index', __('Fix Terms'));
        }
    }

    private function checkSequences(): void
    {
        $sequences = Sequence::count();

        if ($sequences === 0) {
            $this->error('Sequences', __('No sequences configured'), __('Marks are entered against a sequence. Without any, no marks can be recorded.'), 'admin.sequences.index', __('Set Up Sequences'));

            return;
        }

        // form_sequence is what actually decides which sequences count toward a
        // form's term average. Sequences that exist but are mapped to no form
        // are invisible to the calculation.
        $mapped = DB::table('form_sequence')->count();

        if ($mapped === 0) {
            $this->error('Sequences', __('No sequences assigned to any class'), __('Sequences exist but none is mapped to a form and term, so every term average is computed over nothing.'), 'admin.sequence-enrollments.index', __('Assign Sequences'));
        } else {
            $this->ok('Sequences', __('Sequences assigned'), __(':count sequence assignment(s) across forms and terms.', ['count' => $mapped]));
        }

        // Every sequence sharing one weight makes the "weighted" term average an
        // unweighted mean — usually a sign the weight field was never filled in.
        $weights = Sequence::distinct()->pluck('weight');

        if ($sequences > 1 && $weights->count() === 1) {
            $this->warn('Sequences', __('All sequences carry the same weight'), __('Every sequence is weighted :weight, so the term average is an unweighted mean. Set the weights if some sequences should count for more.', ['weight' => $weights->first()]), 'admin.sequences.index', __('Set Weights'));
        }
    }

    private function checkCurriculum(): void
    {
        $forms = Form::where('is_active', true)->get();

        if ($forms->isEmpty()) {
            $this->error('Curriculum', __('No active classes'), __('No form or class level is active, so students have nowhere to be placed.'), 'admin.forms.index', __('Manage Classes'));

            return;
        }

        $withSubjects = DB::table('form_subject')->distinct()->pluck('form_id')->all();
        $missing = $forms->whereNotIn('id', $withSubjects);

        if ($missing->isNotEmpty()) {
            // The subject list drives registration, marks entry and the report
            // card's subject universe all at once.
            $this->error('Curriculum', __(':count class(es) have no subjects', ['count' => $missing->count()]), __('No subjects are assigned to :names. Students there cannot be given marks and their report cards will be empty.', ['names' => $missing->pluck('name')->join(', ')]), 'admin.subjects.index', __('Assign Subjects'));
        } else {
            $this->ok('Curriculum', __('Every class has subjects'), __('All :count active class(es) have a subject list.', ['count' => $forms->count()]));
        }

        $withSections = ClassSection::where('is_active', true)->distinct()->pluck('form_id')->all();
        $noSections = $forms->whereNotIn('id', $withSections);

        if ($noSections->isNotEmpty()) {
            $this->warn('Curriculum', __(':count class(es) have no sections', ['count' => $noSections->count()]), __(':names have no active section, so no student can be enrolled into them.', ['names' => $noSections->pluck('name')->join(', ')]), 'admin.class-sections.index', __('Manage Sections'));
        }
    }

    private function checkGrading(): void
    {
        if (! SchoolSetting::current()) {
            $this->error('Grading', __('No school settings'), __('Pass mark, promotion threshold and maximum mark all come from school settings. Nothing that grades a student can work without them.'), 'admin.settings.index', __('Open Settings'));

            return;
        }

        $scale = GradeScale::orderBy('min_mark')->get();

        if ($scale->isEmpty()) {
            $this->error('Grading', __('No grade scale'), __('Marks cannot be converted into grades, so report cards will show scores with no letter grade.'), 'admin.settings.index', __('Set Up Grade Scale'));

            return;
        }

        $max = (float) (SchoolSetting::current()->max_mark ?? 20);
        $covered = $scale->contains(fn ($band) => (float) $band->min_mark <= 0)
            && $scale->contains(fn ($band) => (float) $band->max_mark >= $max);

        if (! $covered) {
            $this->warn('Grading', __('Grade scale does not cover every mark'), __('The scale does not span 0 to :max, so some scores will fall outside every band and get no grade.', ['max' => $max]), 'admin.settings.index', __('Fix Grade Scale'));
        } else {
            $this->ok('Grading', __('Grade scale configured'), __(':count grade band(s) covering 0 to :max.', ['count' => $scale->count(), 'max' => $max]));
        }
    }

    private function checkEnrollments(): void
    {
        $active = StudentEnrollment::where('status', 'active');

        if ((clone $active)->count() === 0) {
            return;
        }

        // Both of these make a student invisible to the screens that are
        // supposed to serve them, while everything still looks fine on the
        // student list.
        $noTerm = (clone $active)->whereNull('term_id')->count();

        if ($noTerm > 0) {
            $this->error('Students', __(':count enrollment(s) have no term', ['count' => $noTerm]), __('These students will not appear in marks entry or on any report card, because both look up students by term.'), 'admin.students.index', __('Review Students'));
        }

        $noSubjects = (clone $active)->whereDoesntHave('studentSubjects')->count();

        if ($noSubjects > 0) {
            $this->error('Students', __(':count student(s) are registered for no subjects', ['count' => $noSubjects]), __('Report cards take the registered subject list as the subject universe, so these students will receive an empty report card.'), 'admin.subject-enrollments.index', __('Register Subjects'));
        }

        if ($noTerm === 0 && $noSubjects === 0) {
            $this->ok('Students', __('Enrollments are complete'), __('Every active enrollment has a term and a registered subject list.'));
        }
    }

    private function checkFees(): void
    {
        if (FeeCategory::count() === 0) {
            $this->warn('Fees', __('No fee categories'), __('Nothing can be billed until at least one fee category exists.'), 'admin.fee-categories.index', __('Add Fee Categories'));

            return;
        }

        $session = AcademicSession::current();

        if ($session && FeeStructure::where('academic_session_id', $session->id)->count() === 0) {
            $this->warn('Fees', __('No fee structure for the current year'), __('Fee categories exist but none is priced for :name, so no student can be billed.', ['name' => $session->name]), 'admin.fee-structures.index', __('Set Fee Amounts'));
        } else {
            $this->ok('Fees', __('Fee structure configured'), __('Fee categories are priced for the current academic year.'));
        }

        if (PaymentAccount::where('is_active', true)->count() === 0) {
            $this->warn('Fees', __('No payment accounts'), __('Payments cannot be tied to a cash or bank account, so treasury balances will not move when money is received.'), 'admin.payment-account.index', __('Add Payment Accounts'));
        }
    }

    private function checkLedger(): void
    {
        if (ChartOfAccount::count() === 0) {
            $this->warn('Accounting', __('No chart of accounts'), __('Nothing can be posted to the general ledger until the chart of accounts is set up.'), 'admin.chart-of-accounts.index', __('Set Up Chart of Accounts'));

            return;
        }

        if (FiscalYear::where('is_closed', false)->count() === 0) {
            $this->warn('Accounting', __('No open fiscal year'), __('Every posting needs an open fiscal year to land in.'), 'admin.fiscal-years.index', __('Manage Fiscal Years'));
        }

        // Mappings are what turn a payment into a journal entry. Without them the
        // auto-posting service quietly gives up and the ledger stays empty while
        // the cashier keeps taking money.
        if (DefaultAccountMapping::count() === 0) {
            $verdict = Payment::count() > 0 ? 'error' : 'warn';
            $this->{$verdict}('Accounting', __('No account mappings'), __('Payments and expenses cannot be posted to the ledger without a mapping, so they are recorded but never reach the accounts.'), 'admin.account-mappings.index', __('Configure Mappings'));
        } else {
            $this->ok('Accounting', __('Account mappings configured'), __(':count mapping(s) defined.', ['count' => DefaultAccountMapping::count()]));
        }
    }

    private function checkInfrastructure(): void
    {
        if (Room::where('is_active', true)->count() === 0) {
            $this->warn('Infrastructure', __('No rooms'), __('Timetables and exam schedules can still be built, but nothing can be assigned a room.'), 'admin.rooms.index', __('Add Rooms'));
        }
    }

    // ----------------------------------------------------------- collectors

    private function error(string $category, string $title, string $message, ?string $route = null, ?string $action = null): void
    {
        $this->errors[] = $this->item($category, $title, $message, $route, $action);
    }

    private function warn(string $category, string $title, string $message, ?string $route = null, ?string $action = null): void
    {
        $this->warnings[] = $this->item($category, $title, $message, $route, $action);
    }

    private function ok(string $category, string $title, string $message): void
    {
        $this->healthy[] = $this->item($category, $title, $message);
    }

    /** @return array<string, mixed> */
    private function item(string $category, string $title, string $message, ?string $route = null, ?string $action = null): array
    {
        return [
            'category' => __($category),
            'title' => $title,
            'message' => $message,
            // Routes are resolved here so a check naming one that has been
            // renamed shows a plain card instead of crashing the whole screen.
            'link' => $route && \Illuminate\Support\Facades\Route::has($route) ? route($route) : null,
            'action' => $action,
        ];
    }
}
