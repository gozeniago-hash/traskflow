<?php

namespace Tests\Feature\Livewire;

use App\Livewire\TaskPanel;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Project;
use App\Models\ProjectField;
use App\Models\Section;
use App\Models\Task;
use App\Models\TaskFieldValue;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The task panel: details, subtasks, comments, files, time, custom fields, sharing and approvals. */
class TaskPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_share_a_task_with_the_client(): void
    {
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('client_visible', true)
            ->assertForbidden();

        $this->assertFalse($task->fresh()->client_visible);
    }

    public function test_manager_on_the_project_can_share_a_task_with_the_client(): void
    {
        [$manager, $task] = $this->portalTask('manager');
        $this->actingAs($manager);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('client_visible', true)
            ->assertOk();

        $this->assertTrue($task->fresh()->client_visible);
    }

    public function test_member_cannot_ask_for_or_cancel_client_approval(): void
    {
        [$member, $task] = $this->portalTask('member');
        $task->update(['client_visible' => true]);
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)->call('requestApproval')->assertForbidden();
        $task->update(['approval_status' => 'pending']);
        Livewire::test(TaskPanel::class)->call('open', $task->id)->call('cancelApproval')->assertForbidden();

        $this->assertSame('pending', $task->fresh()->approval_status->value);
    }

    public function test_member_cannot_delete_a_subtask_someone_else_created(): void
    {
        [$member, $task] = $this->portalTask('member');
        $subtask = Task::factory()->subtaskOf($task)->createdBy(User::factory()->inWorkspace($task->project->workspace)->create())->create();
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->call('deleteSubtask', $subtask->id)
            ->assertForbidden();

        $this->assertModelExists($subtask);
    }

    public function test_member_can_delete_their_own_subtask_with_undo(): void
    {
        [$member, $task] = $this->portalTask('member');
        $subtask = Task::factory()->subtaskOf($task)->createdBy($member)->create();
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->call('deleteSubtask', $subtask->id)
            ->assertDispatched('toast', fn (string $name, array $params) => $params['message'] === 'Subtask deleted.' && str_contains((string) $params['undo'], '/undo/'));

        $this->assertModelMissing($subtask);
    }

    public function test_due_date_that_is_not_a_calendar_date_is_put_back_and_not_saved(): void
    {
        [$member, $task] = $this->portalTask('member');
        $task->update(['due_date' => '2026-11-05']);
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('due_date', 'next tuesday')
            ->assertSet('due_date', '2026-11-05');

        $this->assertSame('2026-11-05', $task->fresh()->due_date->toDateString());
    }

    public function test_completing_a_task_someone_else_completed_meanwhile_leaves_it_completed(): void
    {
        [$member, $task] = $this->portalTask('member');
        $other = User::factory()->inWorkspace($task->project->workspace)->create();
        $this->actingAs($member);
        $panel = Livewire::test(TaskPanel::class)->call('open', $task->id);
        $task->forceFill(['completed_at' => now(), 'completed_by' => $other->id])->save();

        $panel->call('setComplete', true, null)->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed_by' => $other->id]);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_close_empties_the_panel(): void
    {
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->assertSet('title', $task->title)
            ->call('close')
            ->assertSet('taskId', null);
    }

    public function test_a_task_from_another_workspace_cannot_be_opened(): void
    {
        [$member] = $this->portalTask('member');
        $elsewhere = Task::factory()->create();
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $elsewhere->id)->assertForbidden();
    }

    public function test_a_blank_title_is_put_back_and_not_saved(): void
    {
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('title', '   ')
            ->assertSet('title', $task->title);

        $this->assertSame($task->title, $task->fresh()->title);
    }

    public function test_a_description_longer_than_20000_characters_is_rejected(): void
    {
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('description', str_repeat('a', 20001))
            ->assertHasErrors(['description' => 'max']);

        $this->assertNull($task->fresh()->description);
    }

    public function test_an_unknown_priority_is_put_back(): void
    {
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('priority', 'urgent')
            ->assertSet('priority', 'none');

        $this->assertSame('none', $task->fresh()->priority);
    }

    public function test_moving_to_another_section_puts_the_task_at_its_end_and_records_it(): void
    {
        [$member, $task] = $this->portalTask('member');
        $done = Section::factory()->for($task->project)->create(['name' => 'Done']);
        Task::factory()->inSection($done)->create();
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)->set('section_id', (string) $done->id);

        $task->refresh();
        $this->assertSame($done->id, $task->section_id);
        $this->assertSame(2, $task->position);
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'action' => 'moved']);
    }

    public function test_subtasks_can_be_added_and_completed(): void
    {
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);
        $panel = Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('newSubtask', 'Write copy')
            ->call('addSubtask')
            ->assertSet('newSubtask', '');
        $subtask = $task->subtasks()->sole();

        $panel->call('setComplete', true, $subtask->id);

        $this->assertSame('Write copy', $subtask->title);
        $this->assertSame($member->id, $subtask->creator_id);
        $this->assertNotNull($subtask->fresh()->completed_at);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_an_empty_comment_is_refused(): void
    {
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);

        $panel = Livewire::test(TaskPanel::class)->call('open', $task->id)->set('comment', '  ')->call('addComment');

        $this->assertSame(['Write a comment or attach a file.'], $panel->errors()->get('comment'));
        $this->assertSame(0, Comment::count());
    }

    public function test_a_comment_is_internal_unless_sent_to_the_client_on_a_shared_task(): void
    {
        [$member, $task] = $this->portalTask('member');
        $unshared = Task::factory()->for($task->project)->create();
        $task->update(['client_visible' => true]);
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)->set('comment', 'Internal note')->call('addComment');
        Livewire::test(TaskPanel::class)->call('open', $task->id)->set('comment', 'For the client')->set('commentForClient', true)->call('addComment');
        Livewire::test(TaskPanel::class)->call('open', $unshared->id)->set('comment', 'Not shared')->set('commentForClient', true)->call('addComment');

        $this->assertSame(
            ['Internal note' => true, 'For the client' => false, 'Not shared' => true],
            Comment::orderBy('id')->get()->mapWithKeys(fn (Comment $c) => [$c->body => $c->is_internal])->all(),
        );
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function commentDeleters(): array
    {
        return [
            'the author' => ['author', true],
            'an admin' => ['admin', true],
            'another member' => ['member', false],
        ];
    }

    #[DataProvider('commentDeleters')]
    public function test_only_the_author_or_an_admin_can_delete_a_comment(string $who, bool $allowed): void
    {
        [$author, $task] = $this->portalTask('member');
        $comment = Comment::factory()->for($task)->create(['user_id' => $author->id]);
        $this->actingAs($who === 'author' ? $author : User::factory()->inWorkspace($task->project->workspace, $who)->create());

        $panel = Livewire::test(TaskPanel::class)->call('open', $task->id)->call('deleteComment', $comment->id);

        $allowed ? $panel->assertOk() : $panel->assertForbidden();
        $this->assertSame(! $allowed, Comment::whereKey($comment->id)->exists());
    }

    public function test_uploaded_files_are_attached_to_the_task(): void
    {
        Storage::fake('local');
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('uploads', [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')])
            ->assertHasNoErrors()
            ->assertSet('uploads', []);

        $this->assertSame(['brief.pdf'], $task->ownAttachments()->pluck('original_name')->all());
    }

    public function test_a_member_deletes_their_own_file_with_undo_but_not_someone_elses(): void
    {
        [$member, $task] = $this->portalTask('member');
        $mine = Attachment::factory()->onTask($task)->uploadedBy($member)->create();
        $theirs = Attachment::factory()->onTask($task)->uploadedBy(User::factory()->inWorkspace($task->project->workspace)->create())->create();
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->call('deleteAttachment', $mine->id)
            ->assertDispatched('toast', fn (string $name, array $params) => $params['message'] === 'File deleted.' && str_contains((string) $params['undo'], '/undo/'));
        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->call('deleteAttachment', $theirs->id)
            ->assertForbidden();

        $this->assertModelMissing($mine);
        $this->assertModelExists($theirs);
    }

    public function test_stopping_the_timer_tells_how_much_time_was_logged(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);
        $panel = Livewire::test(TaskPanel::class)->call('open', $task->id)->call('startTimer')->assertDispatched('timer-changed');
        $this->travelTo('2026-10-20 10:30:00');

        $panel->call('stopTimer')->assertDispatched('toast', message: 'Logged 1h 30m.', type: 'success', undo: null);

        $this->assertSame(5400, TimeEntry::sole()->duration_seconds);
    }

    public function test_time_added_by_hand_starts_at_nine_on_the_chosen_day(): void
    {
        $this->travelTo('2026-10-20 15:00:00');
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('manualDuration', '1h 30m')
            ->set('manualDate', '2026-10-19')
            ->set('manualNote', 'Workshop')
            ->call('addTime')
            ->assertHasNoErrors()
            ->assertDispatched('toast', message: 'Added 1h 30m.', type: 'success', undo: null);

        $entry = TimeEntry::sole();
        $this->assertSame(5400, $entry->duration_seconds);
        $this->assertSame('2026-10-19 09:00:00', $entry->started_at->toDateTimeString());
        $this->assertTrue($entry->is_manual);
        $this->assertSame('Workshop', $entry->note);
    }

    public function test_time_added_by_hand_needs_a_readable_duration(): void
    {
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);

        $panel = Livewire::test(TaskPanel::class)->call('open', $task->id)->set('manualDuration', 'a while')->call('addTime');

        $this->assertSame(['Use a format like 1h 30m, 45m or 1.5h (max 24h).'], $panel->errors()->get('manualDuration'));
        $this->assertSame(0, TimeEntry::count());
    }

    public function test_time_cannot_be_added_for_a_future_day(): void
    {
        $this->travelTo('2026-10-20 15:00:00');
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('manualDuration', '1h')
            ->set('manualDate', '2026-10-21')
            ->call('addTime')
            ->assertHasErrors(['manualDate' => 'before_or_equal']);

        $this->assertSame(0, TimeEntry::count());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function timeEntryDeleters(): array
    {
        return [
            'the person who logged it' => ['logger', true],
            'an admin' => ['admin', true],
            'another member' => ['member', false],
        ];
    }

    #[DataProvider('timeEntryDeleters')]
    public function test_only_the_person_who_logged_time_or_an_admin_can_delete_it(string $who, bool $allowed): void
    {
        [$logger, $task] = $this->portalTask('member');
        $entry = TimeEntry::factory()->onTask($task)->by($logger)->create();
        $this->actingAs($who === 'logger' ? $logger : User::factory()->inWorkspace($task->project->workspace, $who)->create());

        $panel = Livewire::test(TaskPanel::class)->call('open', $task->id)->call('deleteTimeEntry', $entry->id);

        $allowed ? $panel->assertOk() : $panel->assertForbidden();
        $this->assertSame(! $allowed, TimeEntry::whereKey($entry->id)->exists());
    }

    public function test_a_custom_field_value_is_saved_and_recorded_in_the_history(): void
    {
        [$member, $task] = $this->portalTask('member');
        $field = ProjectField::factory()->for($task->project)->create(['name' => 'Budget', 'type' => 'number']);
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('fieldValues.'.$field->id, '1200')
            ->assertHasNoErrors();

        $this->assertSame('1200', $task->fieldValues()->where('field_id', $field->id)->value('value'));
        $this->assertDatabaseHas('activities', ['task_id' => $task->id, 'action' => 'field_changed']);
    }

    public function test_a_required_custom_field_cannot_be_cleared(): void
    {
        [$member, $task] = $this->portalTask('member');
        $field = ProjectField::factory()->for($task->project)->required()->create(['name' => 'Channel']);
        TaskFieldValue::factory()->create(['task_id' => $task->id, 'field_id' => $field->id, 'value' => 'Email']);
        $this->actingAs($member);

        Livewire::test(TaskPanel::class)->call('open', $task->id)
            ->set('fieldValues.'.$field->id, '')
            ->assertHasErrors(['fieldValues.'.$field->id])
            ->assertSet('fieldValues.'.$field->id, 'Email');

        $this->assertSame('Email', $task->fieldValues()->where('field_id', $field->id)->value('value'));
    }

    public function test_the_panel_empties_when_access_to_the_project_is_lost_while_it_is_open(): void
    {
        [$member, $task] = $this->portalTask('member');
        $this->actingAs($member);
        $panel = Livewire::test(TaskPanel::class)->call('open', $task->id);
        $task->project->members()->detach($member->id);
        $task->project->update(['privacy' => 'private']);

        $panel->call('$refresh');

        $panel->assertSet('taskId', null)->assertDontSee($task->title);
    }

    /**
     * A task in a workspace-wide portal project, and a person of the given role on that project.
     *
     * @return array{User, Task}
     */
    private function portalTask(string $role): array
    {
        $workspace = Workspace::factory()->create();
        $project = Project::factory()->withPortal(Client::factory()->for($workspace)->create())->create();
        $user = User::factory()->inWorkspace($workspace, $role)->hasAttached($project)->create();

        return [$user, Task::factory()->for($project)->create()];
    }
}
