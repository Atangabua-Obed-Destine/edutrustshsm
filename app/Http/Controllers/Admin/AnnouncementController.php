<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Models\ClassSection;
use App\Models\Form;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

/**
 * The school's notice board.
 *
 * Until now the PTA was the only part of the school that could address
 * parents. This is the same table — a notice simply carries a category, and
 * PTA is one of them.
 *
 * Publishing is a separate permission from writing: drafting a notice and
 * putting it in front of every parent in the school are different acts.
 */
class AnnouncementController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'announcement';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('announcement.publish', ['togglePublish']),
        ];
    }

    public function index(Request $request)
    {
        $query = Announcement::with(['category', 'targetForm', 'targetClass', 'createdBy']);

        if ($slug = $request->query('category')) {
            $query->inCategory($slug);
        }

        if ($audience = $request->query('audience')) {
            $query->where('audience', $audience);
        }

        match ($request->query('status')) {
            'draft' => $query->whereNull('published_at'),
            'published' => $query->published(),
            'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<', now()),
            default => null,
        };

        return view('admin.announcements.index', [
            'announcements' => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'categories' => AnnouncementCategory::active()->orderBy('name')->get(),
            'filters' => $request->only(['category', 'audience', 'status']),
        ]);
    }

    public function create()
    {
        return view('admin.announcements.form', [
            'announcement' => new Announcement([
                'scope' => Announcement::SCOPE_ALL,
                'audience' => Announcement::AUDIENCE_GUARDIANS,
            ]),
        ] + $this->formData());
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $announcement = Announcement::create($validated + [
            'reference' => Announcement::nextReference(BranchContext::current()),
            'attachment' => $this->storeAttachment($request),
            'published_at' => $this->publishedAt($request),
            'created_by' => auth()->id(),
        ] + Announcement::targetsFor($validated));

        return redirect()->route('admin.announcements.index')
            ->with('success', __('Notice :reference saved.', ['reference' => $announcement->reference]));
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.form', compact('announcement') + $this->formData());
    }

    public function update(Request $request, Announcement $announcement)
    {
        $validated = $this->validated($request);

        $attachment = $this->storeAttachment($request);
        if ($attachment && $announcement->attachment) {
            Storage::disk('local')->delete($announcement->attachment);
        }

        $announcement->update($validated + array_filter([
            'attachment' => $attachment,
        ]) + Announcement::targetsFor($validated));

        return redirect()->route('admin.announcements.index')
            ->with('success', __('Notice updated.'));
    }

    public function togglePublish(Announcement $announcement)
    {
        $announcement->update(['published_at' => $announcement->published_at ? null : now()]);

        return back()->with('success', $announcement->published_at
            ? __('Notice published.')
            : __('Notice unpublished — it is off the board but not deleted.'));
    }

    public function destroy(Announcement $announcement)
    {
        if ($announcement->attachment) {
            Storage::disk('local')->delete($announcement->attachment);
        }

        $announcement->delete();

        return back()->with('success', __('Notice deleted.'));
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

    /**
     * The uploaded file is validated but dropped from the result: it shares a
     * key with the stored path, and in `$validated + $overrides` the left-hand
     * side wins, so leaving it in would write the upload object to the column.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:announcement_categories,id'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'scope' => ['required', 'in:'.implode(',', Announcement::scopes())],
            'audience' => ['required', 'in:'.implode(',', Announcement::audiences())],
            'target_form_id' => ['nullable', 'required_if:scope,form_specific', 'exists:forms,id'],
            'target_class_section_id' => ['nullable', 'required_if:scope,class_specific', 'exists:class_sections,id'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'target_form_id.required_if' => __('Choose the form this notice is for.'),
            'target_class_section_id.required_if' => __('Choose the class this notice is for.'),
        ]);

        unset($validated['attachment']);

        return $validated;
    }

    /**
     * Notice attachments go to the private disk like every other upload here —
     * a circular about a child protection matter is not a public file.
     */
    private function storeAttachment(Request $request): ?string
    {
        return $request->hasFile('attachment')
            ? $request->file('attachment')->store('announcements', 'local')
            : null;
    }

    /** Saving as a draft and publishing are the same form, one checkbox apart. */
    private function publishedAt(Request $request): ?string
    {
        return $request->boolean('publish') ? now() : null;
    }
}
