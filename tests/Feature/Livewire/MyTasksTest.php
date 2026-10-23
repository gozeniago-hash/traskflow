<?php

namespace Tests\Feature\Livewire;

use App\Livewire\MyTasks;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** My tasks: grouping by due date, adding tasks. */
class MyTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_date_that_is_not_a_calendar_date_is_rejected(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->inWorkspace($workspace)->create();
        $project = Project::factory()->for($workspace)->create();
        $this->actingAs($member);

        Livewire::test(MyTasks::class)
            ->set('newTitle', 'Book venue')
            ->set('newProject', (string) $project->id)
            ->set('newDue', 'garbage')
            ->call('addTask')
            ->assertHasErrors(['newDue' => 'Pick a due date from the calendar.']);

        $this->assertDatabaseMissing('tasks', ['title' => 'Book venue']);
    }

    public function test_new_task_is_assigned_to_me_and_makes_me_a_project_member(): void
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->inWorkspace($workspace)->create();
        $project = Project::factory()->for($workspace)->create();
        $this->actingAs($member);

        Livewire::test(MyTasks::class)
            ->set('newTitle', 'Book venue')
            ->set('newProject', (string) $project->id)
            ->set('newDue', '2026-11-20')
            ->call('addTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', ['title' => 'Book venue', 'project_id' => $project->id, 'assignee_id' => $member->id, 'creator_id' => $member->id]);
        $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $member->id]);
    }

    public function test_open_tasks_are_grouped_by_when_they_are_due(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $workspace = Workspace::factory()->create();
        $me = User::factory()->inWorkspace($workspace)->create();
        $project = Project::factory()->for($workspace)->create();
        foreach (['Late' => '2026-10-19', 'Now' => '2026-10-20', 'Soon' => '2026-10-27', 'Far' => '2026-10-28', 'Whenever' => null] as $title => $due) {
            Task::factory()->for($project)->assignedTo($me)->create(['title' => $title, 'due_date' => $due]);
        }
        Task::factory()->for($project)->assignedTo($me)->completed()->create(['title' => 'Finished']);
        $this->actingAs($me);

        $groups = Livewire::test(MyTasks::class)->viewData('groups');

        $this->assertSame(
            ['Overdue' => ['Late'], 'Today' => ['Now'], 'Next 7 days' => ['Soon'], 'Later' => ['Far'], 'No due date' => ['Whenever']],
            array_map(fn (array $tasks) => array_map(fn (Task $task) => $task->title, $tasks), $groups),
        );
    }

    public function test_the_completed_tab_shows_only_completed_tasks(): void
    {
        $workspace = Workspace::factory()->create();
        $me = User::factory()->inWorkspace($workspace)->create();
        $project = Project::factory()->for($workspace)->create();
        Task::factory()->for($project)->assignedTo($me)->create(['title' => 'Still open']);
        Task::factory()->for($project)->assignedTo($me)->completed()->create(['title' => 'Finished']);
        $this->actingAs($me);

        $groups = Livewire::test(MyTasks::class)->set('show', 'completed')->viewData('groups');

        $this->assertSame(['Completed' => ['Finished']], array_map(fn (array $tasks) => array_map(fn (Task $task) => $task->title, $tasks), $groups));
    }

    public function test_a_task_cannot_be_added_to_a_project_i_cannot_open(): void
    {
        $workspace = Workspace::factory()->create();
        $me = User::factory()->inWorkspace($workspace)->create();
        $hidden = Project::factory()->for($workspace)->private()->create();
        $this->actingAs($me);

        $page = Livewire::test(MyTasks::class)
            ->set('newTitle', 'Sneaky')
            ->set('newProject', (string) $hidden->id)
            ->call('addTask');

        $this->assertSame(['Pick a project for this task.'], $page->errors()->get('newProject'));
        $this->assertSame(0, Task::count());
    }
}
