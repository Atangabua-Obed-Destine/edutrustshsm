<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\StaffNote;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;

/**
 * Notes on a staff member's record — a warning, a commendation, a meeting.
 *
 * Follows the reference system's staff notes. They are read on the staff
 * page, so there is no separate list; attachments go to the private disk and
 * are served through the authorised file route, because these are HR records.
 */
class StaffNoteController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'staff';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('staff.edit', ['store']),
            static::can('staff.delete', ['destroy']),
        ];
    }

    public function store(Request $request, User $staff)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'note' => ['required', 'string', 'max:10000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        $staff->staffNotes()->create([
            'title' => $validated['title'],
            'note' => $validated['note'],
            'attachment' => $request->hasFile('attachment')
                ? $request->file('attachment')->store('staff-notes', 'local')
                : null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('admin.staff.show', $staff)->with('success', __('Note added.'));
    }

    public function destroy(StaffNote $note)
    {
        $staffId = $note->user_id;

        if ($note->attachment) {
            Storage::disk('local')->delete($note->attachment);
        }

        $note->delete();

        return redirect()->route('admin.staff.show', $staffId)->with('success', __('Note deleted.'));
    }
}
