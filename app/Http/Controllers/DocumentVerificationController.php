<?php

namespace App\Http\Controllers;

use App\Models\IssuedDocument;
use App\Models\SchoolSetting;
use App\Support\CheckCode;
use Illuminate\Http\Request;

/**
 * The public page a certificate's check code opens: whether the school issued
 * that paper, and what it said when it did. A forged or altered copy shows up
 * against the register.
 */
class DocumentVerificationController extends Controller
{
    public function show(Request $request, ?string $code = null)
    {
        if ($code === null && $request->filled('code')) {
            $typed = CheckCode::normalise($request->query('code'));

            return redirect()->route('document.verify', $typed !== '' ? $typed : null);
        }

        $settings = SchoolSetting::current();

        if ($code === null) {
            return response()->view('verify-document', ['settings' => $settings, 'document' => null, 'asked' => false]);
        }

        $normalised = CheckCode::normalise($code);
        $document = strlen($normalised) === 12
            ? IssuedDocument::withoutBranchScope()->where('verify_code', $normalised)->first()
            : null;

        return response()->view('verify-document', [
            'settings' => $settings,
            'document' => $document,
            'asked' => true,
            'code' => $normalised,
        ], $document ? 200 : 404);
    }
}
