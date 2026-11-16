<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\SchoolSetting;
use Illuminate\Http\Request;

/**
 * The public page a receipt's check code opens.
 *
 * A parent, an auditor or another school can confirm that a paper receipt was
 * recorded by this school and still stands, without signing in. It shows only
 * what the receipt itself shows, and never why a receipt was reversed: that is
 * the school's business, the fact that it no longer counts is not.
 */
class ReceiptVerificationController extends Controller
{
    public function show(Request $request, ?string $code = null)
    {
        // The form on the page sends ?code=; land on the address the receipt
        // prints, so the result can be bookmarked or shared.
        if ($code === null && $request->filled('code')) {
            $typed = Payment::normaliseVerifyCode($request->query('code'));

            return $typed !== ''
                ? redirect()->route('receipt.verify', $typed)
                : redirect()->route('receipt.verify');
        }

        $settings = SchoolSetting::current();

        if ($code === null) {
            return response()->view('verify-receipt', ['settings' => $settings, 'payment' => null, 'asked' => false]);
        }

        $normalised = Payment::normaliseVerifyCode($code);
        $payment = strlen($normalised) === 12
            ? Payment::withoutBranchScope()
                ->where('verify_code', $normalised)
                ->with(['enrollment.student', 'enrollment.classSection', 'enrollment.academicSession'])
                ->first()
            : null;

        return response()->view('verify-receipt', [
            'settings' => $settings,
            'payment' => $payment,
            'asked' => true,
            'code' => $normalised,
        ], $payment ? 200 : 404);
    }
}
