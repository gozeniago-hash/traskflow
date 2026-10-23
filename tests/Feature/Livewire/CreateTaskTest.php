<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CreateTask;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** The "New task" form: double clicks, files over the storage allowance, and sharing with the client. */
class CreateTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_clicking_create_twice_adds_the_task_only_once(): void
    {
        [$member, $project] = $this->portalProject('member');
        $this->actingAs($member);

        Livewire::test(CreateTask::class)->call('open', $project->id)
            ->set('title', 'Order banners')
            ->call('save')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Task::where('title', 'Order banners')->count());
    }

    public function test_files_over_the_storage_allowance_cancel_the_whole_task(): void
    {
        Storage::fake('local');
        config(['taskflow.workspace_storage_mb' => 1]);
        [$member, $project] = $this->portalProject('member');
        $this->actingAs($member);

        Livewire::test(CreateTask::class)->call('open', $project->id)
            ->set('title', 'Too many files')
            ->set('files', [
                UploadedFile::fake()->create('a.pdf', 400, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 400, 'application/pdf'),
                UploadedFile::fake()->create('c.pdf', 400, 'application/pdf'),
            ])
            ->call('save')
            ->assertHasErrors('upload');

        $this->assertDatabaseMissing('tasks', ['title' => 'Too many files']);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('attachments'));
    }

    public function test_member_cannot_share_a_new_task_with_the_client(): void
    {
        [$member, $project] = $this->portalProject('member');
        $this->actingAs($member);

        Livewire::test(CreateTask::class)->call('open', $project->id)
            ->set('title', 'Internal draft')
            ->set('client_visible', true)
            ->call('save');

        $this->assertDatabaseHas('tasks', ['title' => 'Internal draft', 'client_visible' => false]);
    }

    public function test_manager_can_share_a_new_task_with_the_client(): void
    {
        [$manager, $project] = $this->portalProject('manager');
        $this->actingAs($manager);

        Livewire::test(CreateTask::class)->call('open', $project->id)
            ->set('title', 'For the client')
            ->set('client_visible', true)
            ->call('save');

        $this->assertDatabaseHas('tasks', ['title' => 'For the client', 'client_visible' => true]);
    }

    /**
     * A workspace-wide portal project, and a person of the given role on it.
     *
     * @return array{User, Project}
     */
    private function portalProject(string $role): array
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->withPortal(Client::factory()->for($workspace)->create())->create();

        return [User::factory()->inWorkspace($workspace, $role)->hasAttached($project)->create(), $project];
    }
}
