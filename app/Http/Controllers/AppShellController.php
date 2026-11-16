<?php

namespace App\Http\Controllers;

use App\Models\SchoolSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * What the desktop shell needs from the server: a keep-alive for the session,
 * and the manifest and icon that let EduTrust open in its own window from a
 * desktop or taskbar icon.
 */
class AppShellController extends Controller
{
    /**
     * Touch the session while someone is working, and hand back the form
     * token: after signing in again on another tab it has changed, and the
     * page's forms need the new one to save.
     */
    public function keepAlive(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'token' => csrf_token(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Pupils for the Ctrl+K palette: the same people the student list would
     * find, at the school level being worked in, a handful at a time.
     */
    public function pupils(\Illuminate\Http\Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('student.view'), 403);

        $search = trim((string) $request->query('q'));
        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $query = \App\Models\Student::with('currentEnrollment.classSection')
            ->whereHas('currentEnrollment.classSection.form', fn ($q) => $q->where('school_level', \App\Support\LevelContext::current()));

        foreach (preg_split('/\s+/', $search) as $word) {
            $query->where(fn ($q) => $q->where('student_id', 'like', "%{$word}%")
                ->orWhere('first_name', 'like', "%{$word}%")
                ->orWhere('last_name', 'like', "%{$word}%")
                ->orWhere('other_names', 'like', "%{$word}%"));
        }

        return response()->json($query->orderBy('last_name')->orderBy('first_name')->limit(8)->get()
            ->map(fn ($s) => [
                'name' => $s->full_name,
                'detail' => trim($s->student_id.' · '.($s->currentEnrollment?->classSection?->name ?? ''), ' ·'),
                'url' => route('admin.students.show', $s),
            ])->values());
    }

    /**
     * Built per request so that a school installed in a sub-folder
     * (http://server/edutrust/public) gets the right start address.
     */
    public function manifest(): JsonResponse
    {
        $school = SchoolSetting::current();
        $name = $school->school_name ?? 'EduTrust';
        $short = $school->school_short_name ?: (mb_strlen($name) > 12 ? 'EduTrust' : $name);

        return response()->json([
            'name' => $name,
            'short_name' => $short,
            'description' => __('School management'),
            'id' => route('admin.dashboard', [], false),
            'start_url' => route('admin.dashboard'),
            'scope' => url('/').'/',
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone'],
            'background_color' => '#f3f4f6',
            'theme_color' => '#1e293b',
            'icons' => [
                ['src' => route('app.icon', 192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('app.icon', 512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The window's icon: the school's logo when it has one, otherwise a plain
     * EduTrust mark.
     */
    public function icon(int $size): Response
    {
        abort_unless(in_array($size, [180, 192, 512], true), 404);
        abort_unless(function_exists('imagecreatetruecolor'), 404);

        $canvas = imagecreatetruecolor($size, $size);
        imagesavealpha($canvas, true);

        $logo = $this->logo();

        if ($logo) {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            $pad = (int) round($size * 0.1);
            $box = $size - 2 * $pad;
            $w = imagesx($logo);
            $h = imagesy($logo);
            $scale = min($box / $w, $box / $h);
            $dw = (int) round($w * $scale);
            $dh = (int) round($h * $scale);
            imagecopyresampled($canvas, $logo, (int) (($size - $dw) / 2), (int) (($size - $dh) / 2), 0, 0, $dw, $dh, $w, $h);
        } else {
            $this->drawMark($canvas, $size);
        }

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** @return \GdImage|null */
    private function logo()
    {
        $path = SchoolSetting::current()?->logo;

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $image = @imagecreatefromstring((string) Storage::disk('public')->get($path));

        return $image ?: null;
    }

    /** A slate square with an open book, in the sidebar's colours. */
    private function drawMark($canvas, int $size): void
    {
        $s = $size / 100;
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 30, 41, 59));
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $teal = imagecolorallocate($canvas, 20, 184, 166);

        $p = fn (array $pts) => array_map(fn ($v) => (int) round($v * $s), $pts);

        // Two pages meeting at the spine.
        imagefilledpolygon($canvas, $p([20, 34, 48, 40, 48, 76, 20, 70]), $white);
        imagefilledpolygon($canvas, $p([80, 34, 52, 40, 52, 76, 80, 70]), $white);
        // A bookmark.
        imagefilledpolygon($canvas, $p([60, 26, 70, 26, 70, 48, 65, 43, 60, 48]), $teal);
    }
}
