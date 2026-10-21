<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Only people who can open a project can be assigned work in it. */
class ProjectPeopleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoSeeder::class);
    }

    protected function user(string $email): User
    {
        return User::where('email', $email)->first();
    }

    /** Like "Zita Jira": made by a manager, no team picked (privacy defaults to team). */
    protected function projectWithoutTeam(): Project
    {
        $maya = $this->user('maya@taskflow.test');
        $project = Project::create(['workspace_id' => $maya->current_workspace_id, 'name' => 'Zita Jira', 'owner_id' => $maya->id,
            'privacy' => 'team', 'team_id' => null, 'color' => Project::COLORS[0], 'status' => 'on_track']);
        $project->members()->attach($maya->id);
        $project->sections()->create(['name' => 'To do', 'position' => 1]);

        return $project;
    }

    public function test_project_without_a_team_only_offers_its_own_people(): void
    {
        $project = $this->projectWithoutTeam();
        $names = $project->assignableUsers()->pluck('name')->all();

        $this->assertContains('Maya Santos', $names, 'the owner');
        $this->assertContains('Alex Rivera', $names, 'workspace admins can open every project');
        $this->assertNotContains('Leo Tan', $names, 'not on this project');
        $this->assertNotContains('Jo Bautista', $names);
        $this->assertTrue($project->assignableUsers()->every(fn (User $u) => ! $u->isClient()));

        // Adding Leo to the project makes him assignable.
        $project->members()->attach($this->user('leo@taskflow.test')->id);
        $this->assertContains('Leo Tan', $project->assignableUsers()->pluck('name')->all());
    }

    public function test_new_task_form_lists_and_accepts_only_project_people(): void
    {
        $project = $this->projectWithoutTeam();
        $leo = $this->user('leo@taskflow.test');
        $this->actingAs($this->user('maya@taskflow.test'));

        Livewire::test(\App\Livewire\CreateTask::class)
            ->call('open', $project->id)
            ->assertSee('Maya Santos')->assertDontSee('Leo Tan')
            ->set('title', 'Sneaky assignment')->set('assignee_id', (string) $leo->id)
            ->call('save')->assertHasErrors('assignee_id');

        $this->assertFalse($project->tasks()->where('title', 'Sneaky assignment')->exists());
        $this->assertFalse($leo->can('view', $project->fresh()), 'Leo still has no access');
    }

    public function test_task_panel_refuses_assigning_an_outsider(): void
    {
        $project = $this->projectWithoutTeam();
        $leo = $this->user('leo@taskflow.test');
        $maya = $this->user('maya@taskflow.test');
        $this->actingAs($maya);
        $task = $project->tasks()->create(['section_id' => $project->sections()->value('id'), 'title' => 'Spec', 'creator_id' => $maya->id, 'position' => 1]);

        Livewire::test(\App\Livewire\TaskPanel::class)->call('open', $task->id)
            ->assertDontSeeHtml('>Leo Tan<')
            ->set('assignee_id', (string) $leo->id)
            ->assertSet('assignee_id', '');

        $this->assertNull($task->fresh()->assignee_id);
        $this->assertFalse($project->members()->whereKey($leo->id)->exists(), 'assigning must not add people to the project');

        Livewire::test(\App\Livewire\TaskPanel::class)->call('open', $task->id)->set('assignee_id', (string) $maya->id);
        $this->assertSame($maya->id, $task->fresh()->assignee_id);
    }

    public function test_team_and_workspace_projects(): void
    {
        // Brightside is shared with Product & Engineering: Leo and Maya yes, Jo (Marketing) no.
        $web = Project::where('name', 'Brightside Website Redesign')->first();
        $names = $web->assignableUsers()->pluck('name')->all();
        $this->assertContains('Leo Tan', $names);
        $this->assertNotContains('Jo Bautista', $names);

        // Shared with everyone: every internal person, never clients.
        $web->update(['privacy' => 'workspace']);
        $names = $web->fresh()->assignableUsers()->pluck('name')->all();
        $this->assertContains('Jo Bautista', $names);
        $this->assertNotContains($this->user('client@brightside.test')->name, $names);
    }
}
