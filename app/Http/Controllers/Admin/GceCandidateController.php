<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\ClassSection;
use App\Models\GceCandidate;
use App\Models\GceRegistrationSession;
use App\Models\GceSubject;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\GceRegistrationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Candidate entries for a GCE series: who is sitting, in what subjects, and
 * what the school owes the Board for them.
 */
class GceCandidateController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'gce-registration';

    public function __construct(private GceRegistrationService $registration) {}

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('gce-registration.edit', ['setStatus', 'recordPayment']),
            static::can('gce-registration.export', ['exportCsv', 'entrySlip', 'entryList']),
        ];
    }

    public function index(Request $request, GceRegistrationSession $session)
    {
        $candidates = $session->candidates()
            ->with(['student:id,student_id,first_name,last_name', 'subjects:id,code,name'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->get()
            ->sortBy('candidate_number')
            ->values();

        return view('admin.gce.candidates.index', [
            'session' => $session->load('forms'),
            'candidates' => $candidates,
            'totals' => [
                'candidates' => $candidates->count(),
                'entries' => $candidates->sum(fn ($c) => $c->subjects->count()),
                'fees' => $candidates->sum(fn ($c) => (float) $c->fee_amount),
                'paid' => $candidates->sum(fn ($c) => (float) $c->amount_paid),
            ],
            // Who is still missing is the question that matters while a series
            // is open, so it sits on the same screen as who is entered.
            'outstanding' => $this->registration->eligible($session)->count(),
        ]);
    }

    public function create(Request $request, GceRegistrationSession $session)
    {
        $session->load('forms');

        return view('admin.gce.candidates.create', [
            'session' => $session,
            'eligible' => $this->registration->eligible($session, $request->input('class_section_id')),
            'gceSubjects' => GceSubject::active()->forLevel($session->level)->orderBy('code')->get(),
            'classSections' => ClassSection::whereIn('form_id', $session->forms->pluck('id'))
                ->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, GceRegistrationSession $session)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'student_enrollment_id' => ['nullable', 'exists:student_enrollments,id'],
            'gce_subject_ids' => ['required', 'array', 'min:1'],
            'gce_subject_ids.*' => ['required', 'exists:gce_subjects,id'],
        ]);

        try {
            $candidate = $this->registration->register(
                $session,
                Student::findOrFail($validated['student_id']),
                $validated['gce_subject_ids'],
                isset($validated['student_enrollment_id'])
                    ? StudentEnrollment::find($validated['student_enrollment_id'])
                    : null
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.gce.candidates.index', $session)
            ->with('success', __('Entered :name as candidate :number.', [
                'name' => $candidate->student->full_name,
                'number' => $candidate->candidate_number,
            ]));
    }

    public function edit(GceCandidate $candidate)
    {
        $candidate->load(['student', 'subjects', 'registrationSession']);

        return view('admin.gce.candidates.edit', [
            'candidate' => $candidate,
            'session' => $candidate->registrationSession,
            'gceSubjects' => GceSubject::active()->forLevel($candidate->registrationSession->level)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, GceCandidate $candidate)
    {
        $validated = $request->validate([
            'gce_subject_ids' => ['required', 'array', 'min:1'],
            'gce_subject_ids.*' => ['required', 'exists:gce_subjects,id'],
        ]);

        try {
            $this->registration->updateSubjects($candidate, $validated['gce_subject_ids']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.gce.candidates.index', $candidate->gce_registration_session_id)
            ->with('success', __('Entry updated.'));
    }

    public function setStatus(Request $request, GceCandidate $candidate)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,submitted,confirmed,withdrawn'],
        ]);

        try {
            $this->registration->setStatus($candidate, $validated['status']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Entry is now :status.', ['status' => __(ucfirst($validated['status']))]));
    }

    public function recordPayment(Request $request, GceCandidate $candidate)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        // Set rather than incremented, so correcting a mistyped figure does not
        // require a compensating negative entry.
        $candidate->update(['amount_paid' => $validated['amount']]);

        return back()->with('success', __('Entry fee updated.'));
    }

    public function destroy(GceCandidate $candidate)
    {
        $sessionId = $candidate->gce_registration_session_id;

        if (in_array($candidate->status, ['confirmed', 'submitted'], true)) {
            return back()->with('error', __('This entry has gone to the Board. Withdraw it rather than deleting it.'));
        }

        $candidate->delete();

        return redirect()->route('admin.gce.candidates.index', $sessionId)
            ->with('success', __('Entry removed.'));
    }

    // ----------------------------------------------------------------- output

    /** The file the school actually sends to the Board. */
    public function exportCsv(GceRegistrationSession $session): StreamedResponse
    {
        $candidates = $session->candidates()
            ->with(['student', 'subjects', 'enrollment.classSection'])
            ->get()
            ->sortBy('candidate_number');

        $filename = 'gce-'.$session->exam_year.'-'.$session->level.'-'.__('entries').'.csv';

        return response()->streamDownload(function () use ($session, $candidates) {
            $out = fopen('php://output', 'w');

            // Excel reads UTF-8 as Latin-1 without this, mangling accented names.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                __('Centre Number'), __('Candidate Number'), __('Student ID'),
                __('Surname'), __('Other Names'), __('Date of Birth'), __('Gender'),
                __('Class'), __('Subject Count'), __('Subject Codes'), __('Status'),
            ]);

            foreach ($candidates as $candidate) {
                fputcsv($out, [
                    $session->centre_number,
                    $candidate->candidate_number,
                    $candidate->student?->student_id,
                    $candidate->student?->last_name,
                    trim(($candidate->student?->first_name ?? '').' '.($candidate->student?->other_names ?? '')),
                    $candidate->student?->date_of_birth?->format('Y-m-d'),
                    $candidate->student?->gender,
                    $candidate->enrollment?->classSection?->name,
                    $candidate->subjects->count(),
                    // One cell of codes: the Board's template is one row per
                    // candidate, not one row per subject.
                    $candidate->subjects->pluck('code')->join(' '),
                    $candidate->status,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The slip a candidate signs and keeps. */
    public function entrySlip(GceCandidate $candidate)
    {
        $candidate->load(['student', 'subjects', 'registrationSession', 'enrollment.classSection']);

        return Pdf::loadView('admin.gce.entry-slip', [
            'candidate' => $candidate,
            'session' => $candidate->registrationSession,
            'school' => SchoolSetting::current(),
        ])->setPaper('a4', 'portrait')->stream('gce-entry-'.$candidate->candidate_number.'.pdf');
    }

    /** The full entry list, for the file copy and the noticeboard. */
    public function entryList(GceRegistrationSession $session)
    {
        $candidates = $session->candidates()
            ->with(['student', 'subjects', 'enrollment.classSection'])
            ->get()
            ->sortBy('candidate_number');

        return Pdf::loadView('admin.gce.entry-list', [
            'session' => $session,
            'candidates' => $candidates,
            'school' => SchoolSetting::current(),
        ])->setPaper('a4', 'landscape')->stream('gce-entry-list-'.$session->exam_year.'.pdf');
    }
}
