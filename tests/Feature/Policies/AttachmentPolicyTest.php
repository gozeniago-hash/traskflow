<?php

namespace Tests\Feature\Policies;

use App\Models\Attachment;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Who can open, edit the details of, and delete a file on a task that is shared with the client. */
class AttachmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, bool, bool, bool}>
     */
    public static function people(): array
    {
        return [
            'admin' => ['admin', true, true, true],
            'manager on the project' => ['manager on project', true, true, true],
            'member who uploaded the file' => ['uploader', true, true, true],
            'member who owns the project' => ['project owner', true, true, true],
            'other member' => ['member', true, false, false],
            'contact of the project client' => ['client of project', true, false, false],
            'contact of another client' => ['client of another', false, false, false],
            'person removed from the workspace' => ['removed', false, false, false],
            'person from another workspace' => ['outsider', false, false, false],
        ];
    }

    #[DataProvider('people')]
    public function test_each_person_can_open_edit_and_delete_exactly_the_files_their_role_allows(string $who, bool $canView, bool $canDelete, bool $canUpdate): void
    {
        [$user, $attachment] = $this->scene($who);

        $this->assertSame(['view' => $canView, 'delete' => $canDelete, 'update' => $canUpdate], [
            'view' => $user->can('view', $attachment),
            'delete' => $user->can('delete', $attachment),
            'update' => $user->can('update', $attachment),
        ]);
    }

    public function test_client_can_delete_a_file_they_uploaded_themselves(): void
    {
        [$client, $attachment] = $this->scene('client of project');
        $attachment->update(['user_id' => $client->id]);

        $this->assertTrue($client->can('delete', $attachment));
    }

    public function test_client_cannot_edit_the_details_of_a_file_they_uploaded_themselves(): void
    {
        [$client, $attachment] = $this->scene('client of project');
        $attachment->update(['user_id' => $client->id]);

        $this->assertFalse($client->can('update', $attachment));
    }

    public function test_client_cannot_open_files_on_tasks_or_docs_that_are_not_shared(): void
    {
        [$client, $onTask] = $this->scene('client of project');
        $onTask->task->update(['client_visible' => false]);
        $onDoc = Attachment::factory()->for($onTask->project)->create(['doc_id' => ProjectDoc::factory()->for($onTask->project)->create()->id]);
        $inInternalComment = Attachment::factory()->onTask(Task::factory()->for($onTask->project)->sharedWithClient()->create())->create();
        $inInternalComment->update(['comment_id' => Comment::factory()->for($inInternalComment->task)->create()->id]);

        $this->assertFalse($client->can('view', $onTask->fresh()));
        $this->assertFalse($client->can('view', $onDoc));
        $this->assertFalse($client->can('view', $inInternalComment->fresh()));
    }

    /**
     * Builds a portal project, a file on a shared task, and the person the case is about.
     *
     * @return array{User, Attachment}
     */
    private function scene(string $who): array
    {
        $workspace = Workspace::factory()->create();
        $client = Client::factory()->for($workspace)->create();
        $project = Project::factory()->withPortal($client)->create();
        $uploader = User::factory()->inWorkspace($workspace)->create();
        $task = Task::factory()->for($project)->sharedWithClient()->create();
        $attachment = Attachment::factory()->onTask($task)->uploadedBy($uploader)->create();

        $user = match ($who) {
            'admin' => User::factory()->inWorkspace($workspace, 'admin')->create(),
            'manager on project' => User::factory()->inWorkspace($workspace, 'manager')->hasAttached($project)->create(),
            'uploader' => $uploader,
            'project owner', 'member' => User::factory()->inWorkspace($workspace)->create(),
            'client of project' => User::factory()->inWorkspace($workspace, 'client', $client)->create(),
            'client of another' => User::factory()->inWorkspace($workspace, 'client', Client::factory()->for($workspace)->create())->create(),
            'removed' => User::factory()->create(['current_workspace_id' => $workspace->id]),
            'outsider' => User::factory()->inWorkspace(Workspace::factory()->create(), 'admin')->create(),
            default => throw new InvalidArgumentException('Unknown person: '.$who),
        };

        if ($who === 'project owner') {
            $project->update(['owner_id' => $user->id]);
        }

        return [$user, $attachment->fresh()];
    }
}
