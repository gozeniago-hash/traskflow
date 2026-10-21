<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DemoSeeder::class);
    }

    protected function openTask(): Task
    {
        return Project::where('name', 'Brightside Website Redesign')->first()
            ->tasks()->whereNull('parent_id')->whereNull('completed_at')->first();
    }

    public function test_completed_task_leaves_the_incomplete_list_at_once_with_undo(): void
    {
        $admin = User::where('email', 'admin@taskflow.test')->first();
        $this->actingAs($admin);
        $task = $this->openTask();
        $task->update(['title' => 'Unique checkbox task', 'assignee_id' => $admin->id]);

        $list = Livewire::test(\App\Livewire\Projects\ListView::class, ['project' => $task->project])
            ->assertSet('filter', 'incomplete')->assertSee('Unique checkbox task');

        $list->call('toggleComplete', $task->id)
            ->assertDontSee('Unique checkbox task')
            ->assertDispatched('toast', fn ($name, $p) => $p['message'] === 'Marked '.$task->ticket().' complete.'
                && $p['undo'] === route('tasks.reopen', $task));

        $list->set('filter', 'completed')->assertSee('Unique checkbox task');

        // My Tasks and Home drop it too.
        Livewire::test(\App\Livewire\MyTasks::class)->assertDontSee('Unique checkbox task');

        // Undo on the toast reopens it (and is safe to press twice).
        $this->postJson(route('tasks.reopen', $task))->assertOk()->assertJson(['message' => $task->ticket().' is incomplete again.']);
        $this->postJson(route('tasks.reopen', $task))->assertOk();
        $this->assertNull($task->fresh()->completed_at);
        Livewire::test(\App\Livewire\Projects\ListView::class, ['project' => $task->project])->assertSee('Unique checkbox task');
    }

    public function test_reopening_follows_project_access(): void
    {
        $task = $this->openTask();
        $task->forceFill(['completed_at' => now()])->save();

        foreach (['client@brightside.test', 'jo@taskflow.test'] as $email) {
            $this->actingAs(User::where('email', $email)->first())
                ->postJson(route('tasks.reopen', $task))->assertForbidden();
        }
        $this->assertNotNull($task->fresh()->completed_at);

        auth()->logout();
        $this->postJson(route('tasks.reopen', $task))->assertUnauthorized();
    }
}
