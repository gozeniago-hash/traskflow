<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The profile page's details form. */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_mixed_case_email_is_saved_in_lowercase(): void
    {
        config(['taskflow.verify_email' => false]);
        $user = User::factory()->inWorkspace(Workspace::factory()->create())->create();
        $this->actingAs($user);

        Livewire::test(Profile::class)->set('email', 'Sam@Example.COM')->call('saveProfile')->assertHasNoErrors();

        $this->assertSame('sam@example.com', $user->fresh()->email);
    }

    public function test_an_email_another_person_uses_in_another_case_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->inWorkspace(Workspace::factory()->create())->create(['email' => 'mine@example.com']);
        $this->actingAs($user);

        Livewire::test(Profile::class)->set('email', 'TAKEN@example.com')->call('saveProfile')->assertHasErrors(['email' => 'unique']);

        $this->assertSame('mine@example.com', $user->fresh()->email);
    }
}
