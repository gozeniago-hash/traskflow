<?php

namespace Tests\Feature\Actions\Workspaces;

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Starting a new workspace. */
class CreateWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_workspace_and_opens_it_for_its_owner(): void
    {
        $owner = User::factory()->create();

        $workspace = app(CreateWorkspace::class)->handle($owner, ' Acme ');

        $this->assertSame('Acme', $workspace->name);
        $this->assertSame($owner->id, $workspace->owner_id);
        $this->assertSame($workspace->id, $owner->fresh()->current_workspace_id);
        $this->assertSame('owner', $owner->fresh()->role());
        $this->assertSame(['General'], $workspace->teams()->pluck('name')->all());
    }

    public function test_a_second_workspace_with_the_same_name_is_refused(): void
    {
        $owner = User::factory()->create();
        app(CreateWorkspace::class)->handle($owner, 'Acme');

        try {
            app(CreateWorkspace::class)->handle($owner, 'Acme');
            $this->fail('A second workspace with the same name was created.');
        } catch (ValidationException $e) {
            $this->assertSame(['You already have a workspace called “Acme”.'], $e->errors()['name']);
        }

        $this->assertSame(1, Workspace::count());
    }

    public function test_another_person_may_use_the_same_name(): void
    {
        app(CreateWorkspace::class)->handle(User::factory()->create(), 'Acme');

        app(CreateWorkspace::class)->handle(User::factory()->create(), 'Acme');

        $this->assertSame(2, Workspace::where('name', 'Acme')->count());
    }
}
