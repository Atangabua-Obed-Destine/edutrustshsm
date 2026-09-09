<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PrivateFiles;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only way to read a private upload.
 *
 * These files used to be served straight off public/storage, which meant a
 * birth certificate or a medical certificate needed nothing but a guessed
 * filename. Now every read passes an authenticated session and the permission
 * that governs the record the file hangs off.
 */
class PrivateFileController extends Controller
{
    public function show(string $source, int $id, string $field): Response
    {
        $registered = PrivateFiles::source($source);

        // An unknown source or a column that is not on its whitelist is a 404,
        // not a hint about what does exist.
        abort_if($registered === null || ! PrivateFiles::allows($source, $field), 404);

        [$model, $permission] = $registered;

        abort_unless(auth()->check() && auth()->user()->can($permission), 403);

        $record = $model::find($id);

        abort_if($record === null, 404);

        $path = $record->{$field};

        abort_if(blank($path), 404);

        // Files uploaded before this route existed still sit on the public disk
        // until `files:secure` runs, so both are checked and the private disk
        // wins. New uploads only ever land on the private one.
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                // Inline: these open in a tab the way they always have.
                return Storage::disk($disk)->response($path);
            }
        }

        abort(404);
    }
}
