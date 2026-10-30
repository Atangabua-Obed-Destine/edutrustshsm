<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\PtaMeeting;
use App\Support\ParentContext;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The guardian side of the private file store.
 *
 * Guardians had no way to read an attachment at all: the admin route behind
 * private_file_url() checks a staff permission, and the PTA minutes link in
 * the portal still pointed at public/storage, which stopped resolving the day
 * those files were moved off the public disk.
 *
 * Rather than widen the admin route, this one serves the short list of things
 * a guardian may read, and asks the same question the listing page asked
 * before handing anything over.
 */
class ParentFileController extends Controller
{
    public function notice(Announcement $notice): Response
    {
        // Addressed to this guardian's children, published, and for parents —
        // the same test the notice list applies.
        abort_unless(
            ParentNoticeController::noticesFor(ParentContext::children())
                ->where('announcements.id', $notice->id)
                ->exists(),
            404
        );

        return $this->serve($notice->attachment);
    }

    public function ptaMinutes(PtaMeeting $meeting): Response
    {
        // PTA minutes are for the whole parent body, so being a signed-in
        // guardian is the whole test.
        return $this->serve($meeting->minutes_path);
    }

    private function serve(?string $path): Response
    {
        abort_if(blank($path), 404);

        // Uploads from before the files were secured may still sit on the
        // public disk until `files:secure` runs; the private disk wins.
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path);
            }
        }

        abort(404);
    }
}
