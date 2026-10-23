<?php

namespace Tests\Feature\Actions\Undo;

use App\Actions\Sections\DeleteSection;
use App\Actions\Tasks\DeleteTask;
use App\Actions\Undo\RestoreDeleted;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Undo" on a delete toast, including two clicks and changes made by others in between. */
class RestoreDeletedTest extends TestCase
{
    use RefreshDatabase;

    public function test_restores_a_deleted_task_once_and_refuses_a_second_click(): void
    {
        [$user, $project] = $this->member();
        $task = Task::factory()->for($project)->createdBy($user)->create();
        $token = app(DeleteTask::class)->handle($task, $user);
        $restore = app(RestoreDeleted::class);

        $first = $restore->handle($token, $user);
        $second = $restore->handle($token, $user);

        $this->assertSame(['ok' => true, 'status' => 200, 'message' => 'Restored.', 'project_id' => $project->id], $first);
        $this->assertSame(['ok' => false, 'status' => 410, 'message' => 'This can no longer be undone.', 'project_id' => null], $second);
        $this->assertModelExists($task);
        $this->assertSame(1, Task::count());
    }

    public function test_someone_who_lost_access_to_the_project_cannot_undo(): void
    {
        [$user, $project] = $this->member();
        $task = Task::factory()->for($project)->createdBy($user)->create();
        $token = app(DeleteTask::class)->handle($task, $user);
        $project->update(['privacy' => 'private']);

        $result = app(RestoreDeleted::class)->handle($token, $user);

        $this->assertSame(403, $result['status']);
        $this->assertFalse($result['ok']);
        $this->assertModelMissing($task);
        $this->assertDatabaseHas('undo_snapshots', ['id' => $token]);
    }

    public function test_only_the_person_who_deleted_can_undo(): void
    {
        [$user, $project] = $this->member();
        $task = Task::factory()->for($project)->createdBy($user)->create();
        $token = app(DeleteTask::class)->handle($task, $user);
        $colleague = User::factory()->inWorkspace($project->workspace)->create();

        $result = app(RestoreDeleted::class)->handle($token, $colleague);

        $this->assertSame(410, $result['status']);
        $this->assertModelMissing($task);
    }

    public function test_tasks_someone_moved_after_a_section_was_deleted_stay_where_they_were_put(): void
    {
        [$user, $project] = $this->member();
        $doing = Section::factory()->for($project)->create(['name' => 'Doing']);
        $done = Section::factory()->for($project)->create(['name' => 'Done']);
        $review = Section::factory()->for($project)->create(['name' => 'Review']);
        $stays = Task::factory()->inSection($doing)->create();
        $movedByColleague = Task::factory()->inSection($doing)->create();
        $token = app(DeleteSection::class)->handle($doing, $done, $user)['undo'];
        $movedByColleague->update(['section_id' => $review->id]);

        app(RestoreDeleted::class)->handle($token, $user);

        $this->assertSame($doing->id, $stays->fresh()->section_id);
        $this->assertSame($review->id, $movedByColleague->fresh()->section_id);
    }

    public function test_a_timer_running_on_a_deleted_task_comes_back_stopped(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        [$user, $project] = $this->member();
        $task = Task::factory()->for($project)->createdBy($user)->create();
        $entry = TimeEntry::factory()->onTask($task)->by($user)->create(['started_at' => '2026-10-20 09:30:00', 'ended_at' => null, 'duration_seconds' => 0]);
        $token = app(DeleteTask::class)->handle($task, $user);
        TimeEntry::factory()->onTask(Task::factory()->for($project)->create())->by($user)->running()->create();
        $this->travelTo('2026-10-20 10:05:00');

        app(RestoreDeleted::class)->handle($token, $user);

        $restored = $entry->fresh();
        $this->assertSame('2026-10-20 10:00:00', $restored->ended_at->toDateTimeString());
        $this->assertSame(1800, $restored->duration_seconds);
        $this->assertSame(1, TimeEntry::where('user_id', $user->id)->whereNull('ended_at')->count());
    }

    public function test_an_expired_snapshot_cannot_be_restored(): void
    {
        [$user, $project] = $this->member();
        $task = Task::factory()->for($project)->createdBy($user)->create();
        $token = app(DeleteTask::class)->handle($task, $user);
        $this->travel(11)->minutes();

        $result = app(RestoreDeleted::class)->handle($token, $user);

        $this->assertSame(410, $result['status']);
        $this->assertModelMissing($task);
    }

    /**
     * @return array{User, Project}
     */
    private function member(): array
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace)->create();
        $this->actingAs($user);

        return [$user, Project::factory()->for($workspace)->create()];
    }
}
