<?php

namespace Tests\Feature\Http\Controllers;

use App\Actions\Tasks\DeleteTask;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Undo button on delete toasts. */
class UndoControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_undo_restores_and_answers_with_the_project(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace)->create();
        $task = Task::factory()->for(Project::factory()->for($workspace))->createdBy($user)->create();
        $token = app(DeleteTask::class)->handle($task, $user);

        $response = $this->actingAs($user)->postJson(route('undo', $token));

        $response->assertOk()->assertJson(['ok' => true, 'project_id' => $task->project_id]);
        $this->assertModelExists($task);
    }

    public function test_a_second_click_gets_410(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace)->create();
        $task = Task::factory()->for(Project::factory()->for($workspace))->createdBy($user)->create();
        $token = app(DeleteTask::class)->handle($task, $user);
        $this->actingAs($user)->postJson(route('undo', $token));

        $response = $this->postJson(route('undo', $token));

        $response->assertGone()->assertJson(['ok' => false, 'message' => 'This can no longer be undone.']);
    }

    public function test_someone_removed_from_the_workspace_is_signed_out_instead(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace)->create();
        $task = Task::factory()->for(Project::factory()->for($workspace))->createdBy($user)->create();
        $token = app(DeleteTask::class)->handle($task, $user);
        $workspace->users()->detach($user->id);

        $response = $this->actingAs($user)->post(route('undo', $token));

        $response->assertRedirect(route('login'));
        $this->assertModelMissing($task);
        $this->assertGuest();
    }

    public function test_someone_whose_workspace_requires_two_step_verification_must_set_it_up_first(): void
    {
        $workspace = Workspace::factory()->requiringTwoFactor()->create();
        $user = User::factory()->inWorkspace($workspace)->create();
        $task = Task::factory()->for(Project::factory()->for($workspace))->createdBy($user)->create();
        $token = app(DeleteTask::class)->handle($task, $user);

        $response = $this->actingAs($user)->post(route('undo', $token));

        $response->assertRedirect(route('profile'));
        $this->assertModelMissing($task);
    }
}
