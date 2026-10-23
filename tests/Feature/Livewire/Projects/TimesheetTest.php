<?php

namespace Tests\Feature\Livewire\Projects;

use App\Livewire\Projects\Timesheet;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The project timesheet: who sees whose time. */
class TimesheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_sees_only_their_own_time_even_when_asking_for_someone_else(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        [$member, $colleague, $project] = $this->team();
        $this->actingAs($member);

        Livewire::withQueryParams(['person' => (string) $colleague->id])->test(Timesheet::class, ['project' => $project])
            ->assertViewHas('total', 3600)
            ->assertViewHas('canSeeTeam', false);
    }

    public function test_a_manager_on_the_project_sees_everyones_time(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        [, , $project] = $this->team();
        $this->actingAs(User::factory()->inWorkspace($project->workspace, 'manager')->hasAttached($project)->create());

        Livewire::test(Timesheet::class, ['project' => $project])->assertViewHas('total', 3600 + 7200);
    }

    /**
     * @return array{User, User, Project}
     */
    private function team(): array
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();
        $task = Task::factory()->for($project)->create();
        $member = User::factory()->inWorkspace($workspace)->create();
        $colleague = User::factory()->inWorkspace($workspace)->create();
        TimeEntry::factory()->onTask($task)->by($member)->create(['duration_seconds' => 3600]);
        TimeEntry::factory()->onTask($task)->by($colleague)->create(['duration_seconds' => 7200]);

        return [$member, $colleague, $project];
    }
}
