<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

/**
 * Staff identity cards, and the public check that one is genuine.
 *
 * Students had printable ID cards; staff did not. Follows the reference
 * system's staff ID cards — one or many at a time, with a way to verify a card
 * — using the same card layout as the student cards so the two match.
 */
class StaffIdCardController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'id-card';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('id-card.print', ['print']),
        ];
    }

    /** Printable cards for one or more staff members. */
    public function print(Request $request)
    {
        $validated = $request->validate([
            'staff_ids' => ['required', 'array', 'min:1'],
            'staff_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $staff = User::staff()
            ->whereIn('id', $validated['staff_ids'])
            ->with(['department', 'designation'])
            ->orderBy('staff_id')
            ->get();

        abort_if($staff->isEmpty(), 404);

        // Each card gets a verification token the first time it is printed. It
        // is random rather than the staff ID, so a card can be checked but the
        // staff list cannot be walked.
        $staff->each(function (User $member) {
            if (! $member->id_card_token) {
                $member->forceFill(['id_card_token' => Str::random(32)])->save();
            }
        });

        return view('admin.staff-id-cards.print', [
            'staff' => $staff,
            'settings' => SchoolSetting::current(),
        ]);
    }

    /**
     * The public page a card's verification link opens.
     *
     * Shows only what a card itself shows, plus whether the card is still valid:
     * enough to confirm a person is who the card says, nothing more.
     */
    public function verify(string $token)
    {
        $member = strlen($token) === 32
            ? User::where('id_card_token', $token)->with(['designation', 'department'])->first()
            : null;

        return response()->view('verify-staff', [
            'member' => $member,
            'valid' => $member && $member->is_active && ! $member->ending_date?->isPast(),
            'settings' => SchoolSetting::current(),
        ], $member ? 200 : 404);
    }
}
