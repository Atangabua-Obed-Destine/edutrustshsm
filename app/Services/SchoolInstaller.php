<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The first run of a new school. `php artisan edutrust:install` prepares the
 * server and issues a one-time link; the page behind it names the school and
 * creates its super admin.
 *
 * The link is what keeps the first account safe: without it, anyone on the
 * school's network could reach the page before the technician does.
 */
class SchoolInstaller
{
    private const TOKEN_FILE = '.install-token';

    /** A school is installed once it has an account. */
    public function isInstalled(): bool
    {
        try {
            return DB::table('users')->exists();
        } catch (QueryException) {
            return false;
        }
    }

    public function issueToken(): string
    {
        $token = Str::random(48);
        Storage::disk('local')->put(self::TOKEN_FILE, $token);

        return $token;
    }

    public function tokenIsValid(?string $token): bool
    {
        $disk = Storage::disk('local');

        return $token !== null
            && $disk->exists(self::TOKEN_FILE)
            && hash_equals(trim((string) $disk->get(self::TOKEN_FILE)), $token);
    }

    /**
     * @param  array{school_name: string, school_short_name: ?string, school_code: string, school_level_mode: string}  $school
     * @param  array{first_name: string, last_name: string, email: string, password: string}  $admin
     */
    public function finish(array $school, array $admin): User
    {
        $user = DB::transaction(function () use ($school, $admin) {
            $code = strtoupper($school['school_code']);

            $branch = Branch::query()->orderBy('id')->firstOrFail();
            $branch->update(['name' => $school['school_name'], 'code' => $code]);

            $settings = SchoolSetting::withoutBranchScope()->first() ?? new SchoolSetting;
            $settings->fill([
                'branch_id' => $branch->id,
                'school_name' => $school['school_name'],
                'school_short_name' => $school['school_short_name'] ?? null,
                'school_code' => $code,
                'school_level_mode' => $school['school_level_mode'],
                'student_id_prefix' => $code,
                'receipt_prefix' => 'RCP',
                'currency' => 'XAF',
                'max_terms_per_session' => 3,
                'max_sequences_per_term' => 2,
                'pass_mark' => 10.00,
                'promotion_threshold' => 10.00,
                'min_attendance_percent' => 80,
                'max_mark' => 20.00,
            ])->save();

            $user = User::create([
                'first_name' => $admin['first_name'],
                'last_name' => $admin['last_name'],
                'email' => $admin['email'],
                'password' => Hash::make($admin['password']),
                'role' => 'super_admin',
                'is_active' => true,
            ]);

            // Permission checks resolve through the role row, for this account
            // exactly as for anyone created on the user screens.
            $user->roles()->sync(Role::where('name', 'super_admin')->pluck('id'));
            $user->branches()->syncWithoutDetaching([$branch->id => ['is_default' => true]]);

            return $user;
        });

        Storage::disk('local')->delete(self::TOKEN_FILE);

        return $user;
    }
}
