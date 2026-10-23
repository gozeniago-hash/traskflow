<?php

namespace Tests\Feature\Livewire\Portal;

use App\Enums\ApprovalStatus;
use App\Livewire\Portal\ProjectView;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** A client's page for one project in the portal. */
class ProjectViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_approves_a_task_with_feedback(): void
    {
        [$client, $project] = $this->portal();
        $task = Task::factory()->for($project)->awaitingApproval()->create();
        $this->actingAs($client);

        Livewire::test(ProjectView::class, ['project' => $project])
            ->call('openTask', $task->id)
            ->set('feedback', 'Lovely')
            ->call('approve')
            ->assertHasNoErrors()
            ->assertSet('feedback', '');

        $this->assertSame(ApprovalStatus::Approved, $task->fresh()->approval_status);
    }

    public function test_approval_feedback_is_limited_to_5000_characters(): void
    {
        [$client, $project] = $this->portal();
        $task = Task::factory()->for($project)->awaitingApproval()->create();
        $this->actingAs($client);

        Livewire::test(ProjectView::class, ['project' => $project])
            ->call('openTask', $task->id)
            ->set('feedback', str_repeat('a', 5001))
            ->call('approve')
            ->assertHasErrors(['feedback' => 'max']);

        $this->assertSame(ApprovalStatus::Pending, $task->fresh()->approval_status);
    }

    public function test_asking_for_changes_needs_feedback(): void
    {
        [$client, $project] = $this->portal();
        $task = Task::factory()->for($project)->awaitingApproval()->create();
        $this->actingAs($client);

        $page = Livewire::test(ProjectView::class, ['project' => $project])
            ->call('openTask', $task->id)
            ->call('requestChanges');

        $page->assertHasErrors(['feedback']);
        $this->assertSame(['Tell the team what you would like changed.'], $page->errors()->get('feedback'));
        $this->assertSame(ApprovalStatus::Pending, $task->fresh()->approval_status);
    }

    public function test_a_client_cannot_answer_a_task_the_team_did_not_send_for_approval(): void
    {
        [$client, $project] = $this->portal();
        $task = Task::factory()->for($project)->sharedWithClient()->create();
        $this->actingAs($client);

        Livewire::test(ProjectView::class, ['project' => $project])
            ->call('openTask', $task->id)
            ->call('approve')
            ->assertForbidden();

        $this->assertNull($task->fresh()->approval_status);
    }

    public function test_a_client_cannot_open_a_task_that_is_not_shared(): void
    {
        [$client, $project] = $this->portal();
        $internal = Task::factory()->for($project)->create();
        $this->actingAs($client);

        Livewire::test(ProjectView::class, ['project' => $project])
            ->call('openTask', $internal->id)
            ->assertNotFound();
    }

    public function test_a_client_request_becomes_a_shared_task(): void
    {
        [$client, $project] = $this->portal();
        $this->actingAs($client);

        Livewire::test(ProjectView::class, ['project' => $project])
            ->set('requestTitle', 'New banner')
            ->call('submitRequest')
            ->assertHasNoErrors()
            ->assertSet('tab', 'tasks');

        $this->assertTrue(Task::where('title', 'New banner')->sole()->requested_by_client);
    }

    public function test_a_shared_file_shows_its_title_and_description_but_not_the_teams_group_or_extra_details(): void
    {
        [$client, $project] = $this->portal();
        Attachment::factory()->for($project)->sharedWithClient()->create([
            'title' => 'Signed contract',
            'description' => 'Final version',
            'group' => 'Internal paperwork',
            'meta' => [['label' => 'Approved by', 'value' => 'Ana']],
        ]);
        $this->actingAs($client);

        Livewire::test(ProjectView::class, ['project' => $project])
            ->set('tab', 'files')
            ->assertSee('Signed contract')
            ->assertSee('Final version')
            ->assertDontSee('Internal paperwork')
            ->assertDontSee('Approved by');
    }

    /**
     * @return array{User, Project}
     */
    private function portal(): array
    {
        $workspace = Workspace::factory()->create();
        $customer = Client::factory()->for($workspace)->create();

        return [User::factory()->inWorkspace($workspace, 'client', $customer)->create(), Project::factory()->withPortal($customer)->create()];
    }
}
