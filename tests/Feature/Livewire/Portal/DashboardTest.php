<?php

namespace Tests\Feature\Livewire\Portal;

use App\Livewire\Portal\Dashboard;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The portal home page a client contact sees. */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_sees_only_their_own_projects_with_the_portal_on(): void
    {
        $workspace = Workspace::factory()->create();
        $client = Client::factory()->for($workspace)->create();
        Project::factory()->withPortal($client)->create(['name' => 'Our shop']);
        Project::factory()->withPortal($client)->create(['name' => 'Their portal off', 'portal_enabled' => false]);
        Project::factory()->withPortal(Client::factory()->for($workspace)->create())->create(['name' => 'Another client']);
        Project::factory()->for($workspace)->create(['name' => 'Internal work']);
        $this->actingAs(User::factory()->inWorkspace($workspace, 'client', $client)->create());

        Livewire::test(Dashboard::class)
            ->assertSee('Our shop')
            ->assertDontSee('Their portal off')
            ->assertDontSee('Another client')
            ->assertDontSee('Internal work');
    }
}
