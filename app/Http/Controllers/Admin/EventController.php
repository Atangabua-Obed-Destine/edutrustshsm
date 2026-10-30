<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AnnouncementCategory;
use App\Models\ClassSection;
use App\Models\Event;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * The school calendar.
 *
 * Events carry the same reach as a notice rather than being global as in the
 * reference system: a nursery sports day is not news to a secondary parent,
 * and a staff briefing is not news to any parent.
 */
class EventController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'event';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('event.edit', ['togglePublish']),
        ];
    }

    public function index(Request $request)
    {
        $showing = $request->query('showing', 'upcoming');

        $query = Event::with(['category', 'targetForm', 'targetClass']);

        $events = $showing === 'past'
            ? $query->past()->orderByDesc('starts_on')->paginate(20)->withQueryString()
            : $query->upcoming()->orderBy('starts_on')->paginate(20)->withQueryString();

        return view('admin.events.index', compact('events', 'showing'));
    }

    public function create()
    {
        return view('admin.events.form', [
            'event' => new Event([
                'scope' => Event::SCOPE_ALL,
                'audience' => Event::AUDIENCE_GUARDIANS,
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->toDateString(),
            ]),
        ] + $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        Event::create($validated + [
            'published_at' => $request->boolean('publish') ? now() : null,
            'created_by' => auth()->id(),
        ] + Event::targetsFor($validated));

        return redirect()->route('admin.events.index')->with('success', __('Event saved.'));
    }

    public function edit(Event $event)
    {
        return view('admin.events.form', compact('event') + $this->formData());
    }

    public function update(Request $request, Event $event)
    {
        $validated = $this->validated($request);

        $event->update($validated + Event::targetsFor($validated));

        return redirect()->route('admin.events.index')->with('success', __('Event updated.'));
    }

    public function togglePublish(Event $event)
    {
        $event->update(['published_at' => $event->published_at ? null : now()]);

        return back()->with('success', $event->published_at
            ? __('Event published.')
            : __('Event unpublished.'));
    }

    public function destroy(Event $event)
    {
        $event->delete();

        return back()->with('success', __('Event deleted.'));
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'categories' => AnnouncementCategory::active()->orderBy('name')->get(),
            'forms' => Form::active()->forCurrentLevel()->ordered()->get(),
            'sections' => ClassSection::where('is_active', true)
                ->whereHas('form', fn ($q) => $q->forCurrentLevel())
                ->with('form')->orderBy('name')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'exists:announcement_categories,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            // Blank times mean all day, which is most of what a school runs.
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'venue' => ['nullable', 'string', 'max:150'],
            'scope' => ['required', 'in:'.implode(',', Event::scopes())],
            'audience' => ['required', 'in:'.implode(',', Event::audiences())],
            'target_form_id' => ['nullable', 'required_if:scope,form_specific', 'exists:forms,id'],
            'target_class_section_id' => ['nullable', 'required_if:scope,class_specific', 'exists:class_sections,id'],
        ], [
            'target_form_id.required_if' => __('Choose the form this event is for.'),
            'target_class_section_id.required_if' => __('Choose the class this event is for.'),
            'ends_on.after_or_equal' => __('An event cannot end before it starts.'),
        ]);
    }
}
