<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\Form;
use App\Models\PtaAnnouncement;
use App\Models\PtaLevy;
use App\Models\PtaLevyPayment;
use App\Models\PtaMeeting;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PtaController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'pta';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('pta.view', ['index']),
            static::can('pta.create', ['storeLevy', 'storeAnnouncement', 'storeMeeting']),
            static::can('pta.edit', ['togglePublishAnnouncement', 'uploadMinutes', 'updateMeetingStatus']),
            static::can('pta.delete', ['destroyLevy', 'destroyAnnouncement', 'destroyMeeting']),
        ];
    }

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'levies');

        $levies = PtaLevy::with(['academicSession', 'form'])
            ->withCount('payments')
            ->orderByDesc('id')
            ->get();

        $announcements = PtaAnnouncement::with(['targetForm', 'targetClass', 'createdBy'])
            ->orderByDesc('id')
            ->get();

        $meetings = PtaMeeting::with('createdBy')
            ->orderByDesc('meeting_date')
            ->get();

        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $forms = Form::query()->orderBy('name')->get();

        // Levy collection stats.
        $leviesPendingCount = PtaLevyPayment::where('status', 'pending')->count();

        return view('admin.pta.index', compact(
            'tab', 'levies', 'announcements', 'meetings', 'sessions', 'forms', 'leviesPendingCount'
        ));
    }

    // ── Levies ──────────────────────────────

    public function storeLevy(Request $request)
    {
        $validated = $request->validate([
            'academic_session_id' => ['required', 'exists:academic_sessions,id'],
            'form_id'             => ['nullable', 'exists:forms,id'],
            'amount'              => ['required', 'numeric', 'min:0'],
            'due_date'            => ['nullable', 'date'],
            'description'         => ['nullable', 'string', 'max:255'],
        ]);

        PtaLevy::create($validated);

        return back()->with('success', __('PTA levy created.'));
    }

    public function destroyLevy(PtaLevy $levy)
    {
        $levy->delete();

        return back()->with('success', __('PTA levy deleted.'));
    }

    // ── Announcements ───────────────────────

    public function storeAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'title'                   => ['required', 'string', 'max:200'],
            'body'                    => ['required', 'string'],
            'audience'                => ['required', 'in:all,form_specific,class_specific'],
            'target_form_id'          => ['nullable', 'exists:forms,id'],
            'target_class_section_id' => ['nullable', 'exists:class_sections,id'],
            'publish'                 => ['nullable', 'boolean'],
            'expires_at'              => ['nullable', 'date', 'after:today'],
        ]);

        PtaAnnouncement::create([
            'title'                   => $validated['title'],
            'body'                    => $validated['body'],
            'audience'                => $validated['audience'],
            'target_form_id'          => $validated['audience'] === 'form_specific' ? ($validated['target_form_id'] ?? null) : null,
            'target_class_section_id' => $validated['audience'] === 'class_specific' ? ($validated['target_class_section_id'] ?? null) : null,
            'published_at'            => $request->boolean('publish') ? now() : null,
            'expires_at'              => $validated['expires_at'] ?? null,
            'created_by'              => auth()->id(),
        ]);

        return back()->with('success', __('Announcement saved.'));
    }

    public function togglePublishAnnouncement(PtaAnnouncement $announcement)
    {
        $announcement->update([
            'published_at' => $announcement->published_at ? null : now(),
        ]);

        return back()->with('success', $announcement->published_at ? __('Announcement published.') : __('Announcement unpublished.'));
    }

    public function destroyAnnouncement(PtaAnnouncement $announcement)
    {
        $announcement->delete();

        return back()->with('success', __('Announcement deleted.'));
    }

    // ── Meetings ────────────────────────────

    public function storeMeeting(Request $request)
    {
        $validated = $request->validate([
            'title'        => ['required', 'string', 'max:200'],
            'agenda'       => ['nullable', 'string'],
            'venue'        => ['nullable', 'string', 'max:200'],
            'meeting_date' => ['required', 'date'],
        ]);

        PtaMeeting::create([
            ...$validated,
            'status'     => 'scheduled',
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', __('Meeting scheduled.'));
    }

    public function uploadMinutes(Request $request, PtaMeeting $meeting)
    {
        $request->validate([
            'minutes' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ]);

        $path = $request->file('minutes')->store('pta-minutes', 'local');

        $meeting->update([
            'minutes_path' => $path,
            'status'       => 'completed',
        ]);

        return back()->with('success', __('Minutes uploaded; meeting marked completed.'));
    }

    public function updateMeetingStatus(Request $request, PtaMeeting $meeting)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:scheduled,completed,cancelled'],
        ]);

        $meeting->update($validated);

        return back()->with('success', __('Meeting status updated.'));
    }

    public function destroyMeeting(PtaMeeting $meeting)
    {
        $meeting->delete();

        return back()->with('success', __('Meeting deleted.'));
    }
}
