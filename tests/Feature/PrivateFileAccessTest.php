<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Birth certificates, medical certificates and transfer certificates were
 * written to the PUBLIC disk, and public/storage is a real directory — so
 * anyone who guessed a filename could download a minor's medical record with no
 * session at all. Financial attachments and parent receipts sat beside them.
 */
class PrivateFileAccessTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $this->student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'MAIN0001',
            'first_name' => 'Ann', 'last_name' => 'Test',
            'date_of_birth' => '2012-01-01', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-01',
            'medical_certificate' => 'students/documents/secret.pdf',
        ]);

        Storage::disk('local')->put('students/documents/secret.pdf', '%PDF-1.4 medical');
    }

    private function admin(): User
    {
        $user = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $user->branches()->attach($this->branch->id, ['is_default' => true]);

        return $user;
    }

    private function url(string $field = 'medical_certificate'): string
    {
        return route('admin.files.show', [
            'source' => 'student', 'id' => $this->student->id, 'field' => $field,
        ]);
    }

    public function test_a_stranger_cannot_read_a_medical_certificate(): void
    {
        // The whole point: no session, no file.
        $this->get($this->url())->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $teacher = User::create([
            'first_name' => 'Tom', 'last_name' => 'Teacher',
            'email' => 'tom@example.test', 'password' => 'password',
            'role' => 'teacher', 'is_active' => true,
        ]);
        $teacher->branches()->attach($this->branch->id, ['is_default' => true]);

        // No student.view permission attached to this user at all.
        $this->actingAs($teacher)->get($this->url())->assertForbidden();
    }

    public function test_an_authorised_user_gets_the_file(): void
    {
        $response = $this->actingAs($this->admin())->get($this->url())->assertSuccessful();

        $this->assertSame('%PDF-1.4 medical', $response->streamedContent());
    }

    public function test_a_column_outside_the_whitelist_is_not_served(): void
    {
        // Otherwise the route would happily stream any attribute named in a URL.
        $this->actingAs($this->admin())->get($this->url('first_name'))->assertNotFound();
    }

    public function test_an_unknown_source_is_not_served(): void
    {
        $this->actingAs($this->admin())->get(route('admin.files.show', [
            'source' => 'users', 'id' => 1, 'field' => 'password',
        ]))->assertNotFound();
    }

    public function test_a_missing_file_is_a_404_not_an_error(): void
    {
        Storage::disk('local')->delete('students/documents/secret.pdf');

        $this->actingAs($this->admin())->get($this->url())->assertNotFound();
    }

    public function test_files_left_on_the_public_disk_are_still_readable(): void
    {
        // Between deploying this and running files:secure, older uploads are
        // still on the public disk and must not 404 for staff.
        Storage::disk('local')->delete('students/documents/secret.pdf');
        Storage::disk('public')->put('students/documents/secret.pdf', 'legacy');

        $this->actingAs($this->admin())->get($this->url())->assertSuccessful();

        Storage::disk('public')->delete('students/documents/secret.pdf');
    }

    public function test_the_securing_command_moves_files_off_the_public_disk(): void
    {
        Storage::disk('public')->put('students/documents/old.pdf', 'legacy');
        Storage::disk('public')->put('parent-receipts/receipt.jpg', 'receipt');

        $this->artisan('files:secure')->assertSuccessful();

        $this->assertFalse(Storage::disk('public')->exists('students/documents/old.pdf'));
        $this->assertFalse(Storage::disk('public')->exists('parent-receipts/receipt.jpg'));
        $this->assertSame('legacy', Storage::disk('local')->get('students/documents/old.pdf'));
        $this->assertSame('receipt', Storage::disk('local')->get('parent-receipts/receipt.jpg'));
    }

    public function test_the_securing_command_leaves_public_assets_alone(): void
    {
        // Logos and photos are meant to be public and are rendered by <img> and
        // by dompdf from a filesystem path.
        Storage::disk('public')->put('logos/crest.png', 'logo');
        Storage::disk('public')->put('students/photos/ann.jpg', 'photo');

        $this->artisan('files:secure')->assertSuccessful();

        $this->assertTrue(Storage::disk('public')->exists('logos/crest.png'));
        $this->assertTrue(Storage::disk('public')->exists('students/photos/ann.jpg'));

        Storage::disk('public')->delete(['logos/crest.png', 'students/photos/ann.jpg']);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('students');
        Storage::disk('local')->deleteDirectory('parent-receipts');

        parent::tearDown();
    }
}
