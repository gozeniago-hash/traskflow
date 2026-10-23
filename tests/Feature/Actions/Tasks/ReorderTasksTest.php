<?php

namespace Tests\Feature\Actions\Tasks;

use App\Actions\Tasks\ReorderTasks;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Drag and drop in the List and Board views, including drops made on an out-of-date page. */
class ReorderTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_dragging_within_a_section_saves_the_new_order(): void
    {
        $section = Section::factory()->create();
        [$a, $b, $c] = Task::factory()->count(3)->inSection($section)->create()->all();

        $done = app(ReorderTasks::class)->handle($section, User::factory()->create(), [$c->id, $a->id, $b->id], $c->id, $section->id);

        $this->assertTrue($done);
        $this->assertSame([$c->id, $a->id, $b->id], Task::where('section_id', $section->id)->orderBy('position')->pluck('id')->all());
        $this->assertDatabaseMissing('activities', ['action' => 'moved']);
    }

    public function test_dragging_to_another_section_moves_the_task_and_records_it(): void
    {
        $project = Project::factory()->create();
        $from = Section::factory()->for($project)->create();
        $to = Section::factory()->for($project)->create(['name' => 'Review']);
        $task = Task::factory()->inSection($from)->create();
        $there = Task::factory()->inSection($to)->create();
        $user = User::factory()->create();

        app(ReorderTasks::class)->handle($to, $user, [$there->id, $task->id], $task->id, $from->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'section_id' => $to->id, 'position' => 2]);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'user_id' => $user->id, 'action' => 'moved']);
        $this->assertSame(['to' => 'Review'], Activity::where('task_id', $task->id)->where('action', 'moved')->first()->meta);
    }

    public function test_drop_is_refused_when_someone_else_moved_the_dragged_task_meanwhile(): void
    {
        $project = Project::factory()->create();
        $a = Section::factory()->for($project)->create();
        $b = Section::factory()->for($project)->create();
        $c = Section::factory()->for($project)->create();
        $task = Task::factory()->inSection($a)->create();
        $task->update(['section_id' => $c->id, 'position' => 7]);

        $done = app(ReorderTasks::class)->handle($b, User::factory()->create(), [$task->id], $task->id, $a->id);

        $this->assertFalse($done);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'section_id' => $c->id, 'position' => 7]);
        $this->assertDatabaseMissing('activities', ['action' => 'moved']);
    }

    public function test_task_someone_moved_away_since_the_page_loaded_stays_where_they_put_it(): void
    {
        $project = Project::factory()->create();
        $section = Section::factory()->for($project)->create();
        $elsewhere = Section::factory()->for($project)->create();
        $dragged = Task::factory()->inSection($section)->create();
        $movedAway = Task::factory()->inSection($section)->create();
        $movedAway->update(['section_id' => $elsewhere->id, 'position' => 3]);

        app(ReorderTasks::class)->handle($section, User::factory()->create(), [$movedAway->id, $dragged->id], $dragged->id, $section->id);

        $this->assertDatabaseHas('tasks', ['id' => $movedAway->id, 'section_id' => $elsewhere->id, 'position' => 3]);
        $this->assertDatabaseHas('tasks', ['id' => $dragged->id, 'section_id' => $section->id, 'position' => 2]);
    }
}
