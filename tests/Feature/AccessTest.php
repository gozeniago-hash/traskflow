<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\ProjectDoc;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_reports_render_for_members_and_are_blocked_for_clients(): void
    {
        $owner = User::where('email', 'admin@taskflow.test')->first();
        foreach (['incomplete', 'upcoming', 'projects', 'completed'] as $report) {
            $this->actingAs($owner)->get(route('reports', ['r' => $report]))->assertOk();
        }

        $client = User::where('email', 'client@brightside.test')->first();
        $this->actingAs($client)->get(route('reports'))->assertRedirect(route('portal.dashboard'));
    }

    public function test_clients_cannot_open_files_from_internal_comments(): void
    {
        Storage::fake('local');
        $owner = User::where('email', 'admin@taskflow.test')->first();
        $client = User::where('email', 'client@brightside.test')->first();
        $task = Task::where('title', 'Visual design: homepage and pricing')->first(); // shared with client

        $internal = $task->comments()->create(['user_id' => $owner->id, 'body' => 'internal', 'is_internal' => true]);
        $public = $task->comments()->create(['user_id' => $owner->id, 'body' => 'public', 'is_internal' => false]);
        $hidden = Attachment::storeUpload(UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), $task->project, $owner, $task, false, $internal);
        $shared = Attachment::storeUpload(UploadedFile::fake()->create('mockup.pdf', 10, 'application/pdf'), $task->project, $owner, $task, false, $public);

        $this->actingAs($client)->get(route('attachments.download', $hidden))->assertForbidden();
        $this->actingAs($client)->get(route('attachments.download', $shared))->assertOk();
        $this->actingAs($owner)->get(route('attachments.download', [$hidden, 'inline' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_docs_are_sanitized_and_only_shared_docs_reach_the_portal(): void
    {
        $owner = User::where('email', 'admin@taskflow.test')->first();
        $client = User::where('email', 'client@brightside.test')->first();
        $project = Project::where('name', 'Brightside Website Redesign')->first();

        $shared = $project->docs()->create(['title' => 'Shared spec', 'client_visible' => true, 'content' => '<div>Hello<script>alert(1)</script><img src="x" onerror="alert(1)"></div>']);
        $project->docs()->create(['title' => 'Secret internal notes', 'client_visible' => false, 'content' => '<div>internal</div>']);

        $this->assertStringNotContainsString('<script', $shared->fresh()->content);
        $this->assertStringNotContainsString('onerror', $shared->fresh()->content);

        $this->actingAs($owner)->get(route('projects.show', [$project, 'docs']))->assertOk()->assertSee('Shared spec');

        $this->actingAs($client)->get(route('portal.projects.show', ['project' => $project, 'tab' => 'docs', 'doc' => $shared->id]))
            ->assertOk()->assertSee('Shared spec')->assertDontSee('Secret internal notes');
    }

    public function test_projects_get_ids_and_tasks_get_ticket_numbers(): void
    {
        $owner = User::where('email', 'admin@taskflow.test')->first();
        $web = Project::where('name', 'Brightside Website Redesign')->first();

        $this->assertSame('BWR', $web->key);
        $this->assertSame(range(1, $web->tasks()->count()), $web->tasks()->orderBy('number')->pluck('number')->all());

        $next = $web->tasks()->create(['title' => 'New one', 'section_id' => $web->sections()->first()->id]);
        $this->assertSame($web->tasks()->count(), $next->number);
        $this->assertSame('BWR-'.$next->number, $next->fresh('project')->ticket());

        // A second project with a similar name gets its own key.
        $other = $web->workspace->projects()->create(['name' => 'Big Web Rebuild']);
        $this->assertSame('BWR2', $other->key);
        $this->assertSame(1, $other->tasks()->create(['title' => 'First'])->number);

        // Ticket links resolve to the task.
        $this->actingAs($owner)->get('/t/bwr-'.$next->number)->assertRedirectContains('task='.$next->id);
        $this->actingAs($owner)->get('/t/NOPE-1')->assertNotFound();
    }

    public function test_custom_fields_validate_and_store_values(): void
    {
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $type = $web->fields()->where('name', 'Type')->first();
        $points = $web->fields()->where('name', 'Story points')->first();

        $this->assertNotNull($type);
        $bug = collect($type->optionList())->firstWhere('label', 'Bug');

        $this->assertSame($bug['id'], $type->normalize($bug['id']));
        $this->assertSame('Bug', $type->display($bug['id']));
        $this->assertSame('8', $points->normalize('8'));
        $this->assertNull($points->normalize(''));
        $this->assertContains('numeric', $points->rules());

        $multi = $web->fields()->create(['name' => 'Platforms', 'type' => 'multiselect', 'options' => ProjectField::makeOptions(['Web', 'iOS'])]);
        [$a, $b] = array_column($multi->optionList(), 'id');
        $this->assertSame(json_encode([$a]), $multi->normalize([$a, 'not-an-option']));
        $this->assertSame('Web, iOS', $multi->display(json_encode([$a, $b])));

        $owner = User::where('email', 'admin@taskflow.test')->first();
        $this->actingAs($owner)->get(route('projects.show', [$web, 'board']))->assertOk()->assertSee('Story points');
    }
}
