<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Event;
use App\Support\ParentContext;

/**
 * What the school has told this guardian.
 *
 * A guardian sees a notice only if it is published, addressed to parents, and
 * aimed at the whole school or at a form or class one of their children is
 * currently in. That matching lives on the model so this page, the PTA page
 * and the dashboard cannot drift apart.
 */
class ParentNoticeController extends Controller
{
    public function index()
    {
        $children = ParentContext::children();

        return view('parent.notices.index', [
            'notices' => static::noticesFor($children)->paginate(15),
            'events' => static::eventsFor($children)->upcoming()->orderBy('starts_on')->take(5)->get(),
        ]);
    }

    public function show(Announcement $notice)
    {
        $children = ParentContext::children();

        // Route binding hands over any notice at all, so re-ask the same
        // question the list asked before showing it.
        abort_unless(
            static::noticesFor($children)->where('announcements.id', $notice->id)->exists(),
            404
        );

        return view('parent.notices.show', compact('notice'));
    }

    public function events()
    {
        $children = ParentContext::children();

        return view('parent.notices.events', [
            'upcoming' => static::eventsFor($children)->upcoming()->orderBy('starts_on')->get(),
            'past' => static::eventsFor($children)->past()->orderByDesc('starts_on')->take(20)->get(),
        ]);
    }

    /** @param  \Illuminate\Support\Collection<int, \App\Models\Student>  $children */
    public static function noticesFor($children)
    {
        return Announcement::with('category')
            ->published()
            ->forGuardians()
            ->addressedToChildren($children)
            ->orderByDesc('published_at');
    }

    /** @param  \Illuminate\Support\Collection<int, \App\Models\Student>  $children */
    public static function eventsFor($children)
    {
        return Event::with('category')
            ->published()
            ->forGuardians()
            ->addressedToChildren($children);
    }
}
