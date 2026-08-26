<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\PtaAnnouncement;
use App\Models\PtaLevy;
use App\Models\PtaLevyPayment;
use App\Models\PtaMeeting;
use App\Support\ParentContext;

class ParentPtaController extends Controller
{
    /** PTA hub: next meeting, latest announcements, per-child levy status. */
    public function index()
    {
        $guardian = ParentContext::guardian();
        $children = ParentContext::children();

        $announcements = $this->announcementsFor($children)->take(5);
        $nextMeeting = PtaMeeting::upcoming()->orderBy('meeting_date')->first();
        $levyStatus = $this->levyStatusFor($children);

        return view('parent.pta.index', compact('announcements', 'nextMeeting', 'levyStatus', 'children'));
    }

    public function announcements()
    {
        $children = ParentContext::children();
        $announcements = $this->announcementsFor($children);

        return view('parent.pta.announcements', compact('announcements'));
    }

    public function meetings()
    {
        $upcoming = PtaMeeting::where('status', 'scheduled')
            ->where('meeting_date', '>=', now())
            ->orderBy('meeting_date')
            ->get();

        $past = PtaMeeting::where(fn ($q) => $q->where('status', 'completed')->orWhere('meeting_date', '<', now()))
            ->orderByDesc('meeting_date')
            ->get();

        return view('parent.pta.meetings', compact('upcoming', 'past'));
    }

    public function levies()
    {
        $children = ParentContext::children();
        $levyStatus = $this->levyStatusFor($children);

        return view('parent.pta.levies', compact('levyStatus', 'children'));
    }

    /**
     * Published announcements relevant to this guardian's children:
     * audience=all, or matching one of the children's current form/class.
     */
    protected function announcementsFor($children)
    {
        $formIds = $children->map(fn ($c) => optional($c->currentEnrollment?->classSection)->form_id)->filter()->unique()->all();
        $classIds = $children->map(fn ($c) => $c->currentEnrollment?->class_section_id)->filter()->unique()->all();

        return PtaAnnouncement::published()
            ->where(function ($q) use ($formIds, $classIds) {
                $q->where('audience', 'all')
                    ->orWhere(fn ($sub) => $sub->where('audience', 'form_specific')->whereIn('target_form_id', $formIds ?: [0]))
                    ->orWhere(fn ($sub) => $sub->where('audience', 'class_specific')->whereIn('target_class_section_id', $classIds ?: [0]));
            })
            ->orderByDesc('published_at')
            ->get();
    }

    /**
     * For each child: the applicable PTA levy for their current session/form and
     * how much has been verified-paid.
     *
     * @return array<int, array>
     */
    protected function levyStatusFor($children): array
    {
        $status = [];

        foreach ($children as $child) {
            $enrollment = $child->currentEnrollment;
            if (! $enrollment) {
                continue;
            }

            $formId = optional($enrollment->classSection)->form_id;

            // Form-specific levy takes precedence over the all-forms levy.
            $levy = PtaLevy::where('academic_session_id', $enrollment->academic_session_id)
                ->where(fn ($q) => $q->where('form_id', $formId)->orWhereNull('form_id'))
                ->orderByRaw('form_id IS NULL ASC')
                ->first();

            if (! $levy) {
                continue;
            }

            $paid = PtaLevyPayment::where('pta_levy_id', $levy->id)
                ->where('student_id', $child->id)
                ->where('status', 'verified')
                ->sum('amount_paid');

            $status[$child->id] = [
                'student' => $child,
                'levy'    => $levy,
                'paid'    => (float) $paid,
                'balance' => max(0, (float) $levy->amount - (float) $paid),
            ];
        }

        return $status;
    }
}
