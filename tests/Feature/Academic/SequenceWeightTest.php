<?php

namespace Tests\Feature\Academic;

use App\Models\Branch;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The term average is weighted by Sequence.weight, but no form field ever wrote
 * it — so every live sequence sat at the same default and the "weighted"
 * average was silently a plain mean.
 */
class SequenceWeightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $admin->branches()->attach($branch->id, ['is_default' => true]);
        $this->actingAs($admin);
    }

    public function test_a_weight_can_be_set_when_creating_a_sequence(): void
    {
        $this->post(route('admin.sequences.store'), [
            'name' => 'Sequence 1',
            'weight' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(3.0, (float) Sequence::firstOrFail()->weight);
    }

    public function test_a_weight_can_be_changed(): void
    {
        $this->post(route('admin.sequences.store'), ['name' => 'Sequence 1', 'weight' => 1]);

        $sequence = Sequence::firstOrFail();

        $this->put(route('admin.sequences.update', $sequence), [
            'name' => 'Sequence 1',
            'weight' => 2.5,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(2.5, (float) $sequence->fresh()->weight);
    }

    public function test_a_zero_weight_is_refused(): void
    {
        // A sequence weighted zero would count for nothing while still looking
        // like it was being marked.
        $this->post(route('admin.sequences.store'), [
            'name' => 'Sequence 1',
            'weight' => 0,
        ])->assertSessionHasErrors('weight');

        $this->assertSame(0, Sequence::count());
    }
}
