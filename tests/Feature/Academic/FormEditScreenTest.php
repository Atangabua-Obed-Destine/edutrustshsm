<?php

namespace Tests\Feature\Academic;

use App\Models\Branch;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The form edit screen used to fail with "Undefined variable $selectedStreams".
 *
 * It opened a @php(...) expression near the top and an inline @php ... @endphp
 * further down. Blade pairs the first @php with the first @endphp, so the two
 * were read as one raw block: everything between them was emitted verbatim and
 * the assignment never ran.
 */
class FormEditScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_edit_screen_renders_with_the_forms_streams_ticked(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        SchoolSetting::create([
            'branch_id' => $branch->id, 'school_name' => 'Test School',
            'school_code' => 'TS', 'school_level_mode' => 'both',
        ]);

        $admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $admin->branches()->attach($branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $form = Form::create([
            'branch_id' => $branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'education_system' => 'english', 'has_streams' => true,
            'display_order' => 1, 'is_active' => true,
        ]);

        $science = Stream::create(['branch_id' => $branch->id, 'name' => 'Science', 'code' => 'SCI', 'is_active' => true]);
        $arts = Stream::create(['branch_id' => $branch->id, 'name' => 'Arts', 'code' => 'ART', 'is_active' => true]);
        $form->streams()->attach($science->id);

        $response = $this->get(route('admin.forms.edit', $form))->assertSuccessful();

        // The markup between the two blocks has to survive as markup, not as
        // the text of a raw PHP block.
        $response->assertDontSee('@foreach', false)->assertDontSee('@php', false);

        $response->assertSee('value="'.$science->id.'"'."\n".'                                   checked', false);
        $response->assertSee('value="'.$arts->id.'"', false);
    }
}
