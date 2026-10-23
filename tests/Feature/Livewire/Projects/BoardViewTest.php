<?php

namespace Tests\Feature\Livewire\Projects;

use App\Livewire\Projects\BoardView;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** A project's Board view. */
class BoardViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_board_shows_completed_tasks_too(): void
    {
        [$member, $project, $section] = $this->project();
        Task::factory()->inSection($section)->create(['title' => 'Open card']);
        Task::factory()->inSection($section)->completed()->create(['title' => 'Done card']);
        $this->actingAs($member);

        Livewire::test(BoardView::class, ['project' => $project])
            ->assertSet('filter', 'all')
            ->assertSee('Open card')
            ->assertSee('Done card');
    }

    public function test_completing_a_card_offers_undo(): void
    {
        [$member, $project, $section] = $this->project();
        $task = Task::factory()->inSection($section)->create();
        $this->actingAs($member);

        Livewire::test(BoardView::class, ['project' => $project])
            ->call('setComplete', $task->id, true)
            ->assertDispatched('toast', message: 'Marked '.$task->ticket().' complete.', type: 'success', undo: route('tasks.reopen', $task));

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_dragging_a_card_to_another_column_moves_it(): void
    {
        [$member, $project, $todo] = $this->project();
        $done = Section::factory()->for($project)->create(['name' => 'Done']);
        $card = Task::factory()->inSection($todo)->create();
        $this->actingAs($member);

        Livewire::test(BoardView::class, ['project' => $project])
            ->call('reorder', $done->id, [$card->id], $card->id, $todo->id);

        $this->assertSame($done->id, $card->fresh()->section_id);
        $this->assertDatabaseHas('activities', ['task_id' => $card->id, 'action' => 'moved']);
    }

    public function test_a_member_cannot_add_a_column_to_a_project_they_do_not_own(): void
    {
        [$member, $project] = $this->project();
        $this->actingAs($member);

        Livewire::test(BoardView::class, ['project' => $project])
            ->set('newSection', 'Blocked')
            ->call('addSection')
            ->assertForbidden();

        $this->assertSame(1, $project->sections()->count());
    }

    /**
     * @return array{User, Project, Section}
     */
    private function project(): array
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->for($workspace)->create();

        return [User::factory()->inWorkspace($workspace)->create(), $project, Section::factory()->for($project)->create(['name' => 'To do'])];
    }
}
