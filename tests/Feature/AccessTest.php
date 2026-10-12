<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoSeeder::class);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_registration_creates_a_workspace_owned_by_the_user(): void
    {
        $this->post('/register', [
            'name' => 'New Owner',
            'email' => 'owner@example.com',
            'workspace' => 'New Co',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('home'));

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertSame('New Co', $user->currentWorkspace->name);
        $this->assertTrue($user->isOwner());
    }

    public function test_members_see_the_internal_app(): void
    {
        $owner = User::where('email', 'admin@taskflow.test')->first();
        $project = Project::where('name', 'Brightside Website Redesign')->first();

        $this->actingAs($owner)->get('/home')->assertOk()->assertSee('My tasks');
        $this->actingAs($owner)->get(route('projects.show', [$project, 'board']))->assertOk()->assertSee('Homepage wireframes');
        $this->actingAs($owner)->get(route('my-tasks'))->assertOk();
        $this->actingAs($owner)->get(route('projects.index'))->assertOk();
    }

    public function test_clients_are_redirected_to_the_portal(): void
    {
        $client = User::where('email', 'client@brightside.test')->first();

        $this->actingAs($client)->get('/home')->assertRedirect(route('portal.dashboard'));
        $this->actingAs($client)->get('/')->assertRedirect(route('portal.dashboard'));
    }

    public function test_clients_only_see_shared_tasks_of_their_projects(): void
    {
        $client = User::where('email', 'client@brightside.test')->first();
        $shared = Project::where('name', 'Brightside Website Redesign')->first();
        $internal = Project::where('name', 'Mobile App v2 Launch')->first();

        $this->actingAs($client)->get(route('portal.dashboard'))->assertOk()->assertSee('Brightside Website Redesign')->assertDontSee('Mobile App v2 Launch');

        $this->actingAs($client)->get(route('portal.projects.show', ['project' => $shared, 'tab' => 'tasks']))
            ->assertOk()
            ->assertSee('Homepage wireframes')          // shared
            ->assertDontSee('Design system tokens');     // internal-only task

        $this->actingAs($client)->get(route('portal.projects.show', $internal))->assertForbidden();
    }

    public function test_private_projects_are_hidden_from_non_members(): void
    {
        $project = Project::where('name', 'Mobile App v2 Launch')->first();
        $project->update(['privacy' => 'private']);
        $jo = User::where('email', 'jo@taskflow.test')->first(); // member, not on this project

        $this->actingAs($jo)->get(route('projects.show', $project))->assertForbidden();
    }

    public function test_task_policy_blocks_clients_from_editing(): void
    {
        $client = User::where('email', 'client@brightside.test')->first();
        $task = Task::where('title', 'Homepage wireframes')->first();

        $this->assertTrue($client->can('view', $task));
        $this->assertFalse($client->can('update', $task));
    }
}
