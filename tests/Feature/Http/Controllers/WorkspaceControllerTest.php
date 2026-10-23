<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Creating and switching workspaces. */
class WorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_workspace_opens_it(): void
    {
        $user = User::factory()->inWorkspace(Workspace::factory()->create())->create();

        $response = $this->actingAs($user)->post(route('workspaces.store'), ['name' => 'Side project']);

        $response->assertRedirect(route('home'));
        $created = Workspace::where('name', 'Side project')->sole();
        $this->assertSame($created->id, $user->fresh()->current_workspace_id);
    }

    public function test_a_second_post_with_the_same_name_is_refused(): void
    {
        $user = User::factory()->inWorkspace(Workspace::factory()->create())->create();
        $this->actingAs($user)->post(route('workspaces.store'), ['name' => 'Side project']);

        $response = $this->post(route('workspaces.store'), ['name' => 'Side project']);

        $response->assertSessionHasErrors(['name' => 'You already have a workspace called “Side project”.']);
        $this->assertSame(1, Workspace::where('name', 'Side project')->count());
    }

    public function test_switching_to_a_workspace_you_are_not_in_is_forbidden(): void
    {
        $user = User::factory()->inWorkspace(Workspace::factory()->create())->create();

        $response = $this->actingAs($user)->post(route('workspaces.switch', Workspace::factory()->create()));

        $response->assertForbidden();
    }

    public function test_switching_to_your_other_workspace_opens_it(): void
    {
        $other = Workspace::factory()->create();
        $user = User::factory()->inWorkspace(Workspace::factory()->create())->create();
        $other->users()->attach($user->id, ['role' => 'member']);

        $response = $this->actingAs($user)->post(route('workspaces.switch', $other));

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($other->id, $user->fresh()->current_workspace_id);
    }
}
