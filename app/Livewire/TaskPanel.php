<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\TimeTracker;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The slide-over task detail panel used everywhere in the internal app.
 * Open it from any view with: $dispatch('open-task', { id: 123 })
 */
class TaskPanel extends Component
{
    use InteractsWithWorkspace, WithFileUploads;

    public ?int $taskId = null;

    // Editable fields (saved as soon as they change)
    public string $title = '';
    public string $description = '';
    public string $assignee_id = '';
    public string $start_date = '';
    public string $due_date = '';
    public string $priority = 'none';
    public string $section_id = '';
    public bool $client_visible = false;

    public string $newSubtask = '';
    public string $comment = '';
    public bool $commentForClient = false;
    public array $uploads = [];

    // Files attached to the comment being written
    public array $commentFiles = [];
    public array $newCommentFiles = [];

    // Manual time entry
    public string $manualDuration = '';
    public string $manualDate = '';
    public string $manualNote = '';

    #[On('open-task')]
    public function open(int $id): void
    {
        $task = Task::with('project')->findOrFail($id);
        $this->authorize('view', $task);

        $this->taskId = $task->id;
        $this->fillFrom($task);
        $this->reset(['newSubtask', 'comment', 'uploads', 'manualDuration', 'manualNote', 'commentFiles', 'newCommentFiles']);
        $this->manualDate = today()->toDateString();
        $this->commentForClient = false;
        $this->resetValidation();
    }

    #[On('timer-changed')]
    public function onTimerChanged(): void
    {
        // Re-render so totals and the running state stay current.
    }

    public function close(): void
    {
        $this->taskId = null;
    }

    protected function fillFrom(Task $task): void
    {
        $this->title = $task->title;
        $this->description = (string) $task->description;
        $this->assignee_id = (string) $task->assignee_id;
        $this->start_date = $task->start_date?->toDateString() ?? '';
        $this->due_date = $task->due_date?->toDateString() ?? '';
        $this->priority = $task->priority;
        $this->section_id = (string) $task->section_id;
        $this->client_visible = $task->client_visible;
    }

    protected function task(): Task
    {
        $task = Task::with('project')->findOrFail($this->taskId);
        $this->authorize('update', $task);

        return $task;
    }

    protected function saved(): void
    {
        $this->dispatch('task-saved');
    }

    public function updated(string $property): void
    {
        if (! $this->taskId || ! in_array($property, ['title', 'description', 'assignee_id', 'start_date', 'due_date', 'priority', 'section_id', 'client_visible'], true)) {
            return;
        }

        $task = $this->task();
        $me = $this->me();

        switch ($property) {
            case 'title':
                $title = trim($this->title);
                if ($title === '') {
                    $this->title = $task->title;

                    return;
                }
                $task->update(['title' => mb_substr($title, 0, 255)]);
                break;

            case 'description':
                $this->validateOnly('description', ['description' => ['nullable', 'string', 'max:20000']]);
                $task->update(['description' => $this->description ?: null]);
                break;

            case 'assignee_id':
                $userId = $this->assignee_id === '' ? null : (int) $this->assignee_id;
                if ($userId && ! $this->workspace()->members()->whereKey($userId)->exists()) {
                    $this->assignee_id = (string) $task->assignee_id;

                    return;
                }
                $task->update(['assignee_id' => $userId]);
                if ($userId) {
                    $task->project->members()->syncWithoutDetaching([$userId]);
                }
                Activity::record($task, $me, 'assigned', ['to' => $task->fresh('assignee')->assignee?->name]);
                break;

            case 'start_date':
            case 'due_date':
                $value = $this->{$property};
                if ($value !== '' && ! strtotime($value)) {
                    $this->{$property} = $task->{$property}?->toDateString() ?? '';

                    return;
                }
                $task->update([$property => $value ?: null]);
                if ($property === 'due_date') {
                    Activity::record($task, $me, 'due_changed', ['to' => $value ? Carbon::parse($value)->format('M j') : null]);
                }
                break;

            case 'priority':
                if (! array_key_exists($this->priority, Task::PRIORITIES)) {
                    $this->priority = $task->priority;

                    return;
                }
                $task->update(['priority' => $this->priority]);
                break;

            case 'section_id':
                $section = $task->project->sections()->find((int) $this->section_id);
                if (! $section) {
                    $this->section_id = (string) $task->section_id;

                    return;
                }
                $task->update(['section_id' => $section->id, 'position' => $task->project->nextTaskPosition($section->id)]);
                Activity::record($task, $me, 'moved', ['to' => $section->name]);
                break;

            case 'client_visible':
                if ($this->client_visible && ! $task->project->portal_enabled) {
                    $this->client_visible = false;
                    $this->toast('Turn on the client portal for this project first (project settings).', 'error');

                    return;
                }
                $task->update(['client_visible' => $this->client_visible]);
                Activity::record($task, $me, $this->client_visible ? 'shared' : 'unshared');
                break;
        }

        $this->saved();
    }

    public function toggleComplete(?int $id = null): void
    {
        $task = $this->task();
        $target = $id ? $task->subtasks()->findOrFail($id) : $task;
        $target->toggleComplete($this->me());
        $this->saved();
    }

    public function addSubtask(): void
    {
        $task = $this->task();
        $title = trim($this->newSubtask);
        if ($title === '') {
            return;
        }
        $sub = $task->subtasks()->create([
            'project_id' => $task->project_id,
            'title' => mb_substr($title, 0, 255),
            'creator_id' => $this->me()->id,
            'position' => (int) $task->subtasks()->max('position') + 1,
        ]);
        Activity::record($sub, $this->me(), 'created');
        $this->newSubtask = '';
        $this->saved();
    }

    public function deleteSubtask(int $id): void
    {
        $this->task()->subtasks()->findOrFail($id)->delete();
        $this->saved();
    }

    public function updatedNewCommentFiles(): void
    {
        $this->validate(['newCommentFiles.*' => ['file', 'max:'.config('taskflow.max_upload_kb')]], [], ['newCommentFiles.*' => 'file']);
        $this->commentFiles = array_merge($this->commentFiles, $this->newCommentFiles);
        $this->newCommentFiles = [];
    }

    public function removeCommentFile(int $index): void
    {
        unset($this->commentFiles[$index]);
        $this->commentFiles = array_values($this->commentFiles);
    }

    public function addComment(): void
    {
        $task = Task::with('project')->findOrFail($this->taskId);
        $this->authorize('comment', $task);
        $this->validate([
            'comment' => ['nullable', 'string', 'max:10000'],
            'commentFiles.*' => ['file', 'max:'.config('taskflow.max_upload_kb')],
        ], [], ['commentFiles.*' => 'file']);

        if (trim($this->comment) === '' && ! $this->commentFiles) {
            $this->addError('comment', 'Write a comment or attach a file.');

            return;
        }

        $comment = $task->comments()->create([
            'user_id' => $this->me()->id,
            'body' => trim($this->comment),
            'is_internal' => ! ($this->commentForClient && $task->client_visible),
        ]);

        foreach ($this->commentFiles as $file) {
            Attachment::storeUpload($file, $task->project, $this->me(), $task, false, $comment);
        }

        $this->reset(['comment', 'commentFiles', 'newCommentFiles']);
        $this->commentForClient = false;
        $this->saved();
    }

    public function deleteComment(int $id): void
    {
        $task = $this->task();
        $comment = $task->comments()->findOrFail($id);
        abort_unless($comment->user_id === $this->me()->id || $this->me()->isAdmin(), 403);
        $comment->deleteWithFiles();
        $this->saved();
    }

    public function updatedUploads(): void
    {
        $task = $this->task();
        $this->validate(['uploads.*' => ['file', 'max:'.config('taskflow.max_upload_kb')]]);
        foreach ($this->uploads as $file) {
            Attachment::storeUpload($file, $task->project, $this->me(), $task);
        }
        $this->uploads = [];
        $this->saved();
    }

    public function deleteAttachment(int $id): void
    {
        $attachment = $this->task()->ownAttachments()->findOrFail($id);
        $this->authorize('delete', $attachment);
        $attachment->delete();
        $this->saved();
    }

    public function startTimer(): void
    {
        TimeTracker::start($this->task(), $this->me());
        $this->dispatch('timer-changed');
    }

    public function stopTimer(): void
    {
        $entry = TimeTracker::stop($this->me());
        $this->dispatch('timer-changed');
        if ($entry) {
            $this->toast('Logged '.TimeEntry::formatShort($entry->duration_seconds).'.');
        }
    }

    public function addTime(): void
    {
        $task = $this->task();
        $this->validate([
            'manualDuration' => ['required', 'string', 'max:20'],
            'manualDate' => ['required', 'date', 'before_or_equal:today'],
            'manualNote' => ['nullable', 'string', 'max:500'],
        ]);

        $seconds = TimeEntry::parseDuration($this->manualDuration);
        if (! $seconds || $seconds > 24 * 3600) {
            $this->addError('manualDuration', 'Use a format like 1h 30m, 45m or 1.5h (max 24h).');

            return;
        }

        TimeTracker::addManual($task, $this->me(), $seconds, \Carbon\Carbon::parse($this->manualDate), $this->manualNote ?: null);
        $this->reset(['manualDuration', 'manualNote']);
        $this->toast('Added '.TimeEntry::formatShort($seconds).'.');
        $this->dispatch('timer-changed');
    }

    public function deleteTimeEntry(int $id): void
    {
        $entry = $this->task()->timeEntries()->findOrFail($id);
        abort_unless($entry->user_id === $this->me()->id || $this->me()->isAdmin(), 403);
        $entry->delete();
        $this->dispatch('timer-changed');
    }

    public function requestApproval(): void
    {
        $task = $this->task();
        if (! $task->client_visible) {
            $this->toast('Share the task with the client before asking for approval.', 'error');

            return;
        }
        $task->update(['approval_status' => 'pending', 'approval_by' => null, 'approval_at' => null]);
        Activity::record($task, $this->me(), 'approval_requested');
        $this->toast('Approval requested. The client will see it in their portal.');
        $this->saved();
    }

    public function cancelApproval(): void
    {
        $task = $this->task();
        $task->update(['approval_status' => null, 'approval_by' => null, 'approval_at' => null]);
        $this->saved();
    }

    public function deleteTask(): void
    {
        $task = $this->task();
        // Delete stored files for the task and its subtasks (DB rows cascade, files don't).
        foreach ($task->attachments->concat($task->subtasks()->with('attachments')->get()->pluck('attachments')->flatten()) as $attachment) {
            $attachment->delete();
        }
        if ($task->parent_id) {
            $parent = $task->parent_id;
            $task->delete();
            $this->open($parent);
        } else {
            $task->delete();
            $this->taskId = null;
        }
        $this->toast('Task deleted.');
        $this->saved();
    }

    public function render()
    {
        if (! $this->taskId || ! ($task = Task::find($this->taskId))) {
            return view('livewire.task-panel', ['task' => null]);
        }

        $task->load([
            'project.sections', 'parent', 'assignee', 'creator',
            'subtasks.assignee', 'ownAttachments.user', 'comments.user', 'comments.attachments.user', 'activities.user',
        ]);

        $timeline = $task->comments->map(fn ($c) => ['type' => 'comment', 'at' => $c->created_at, 'item' => $c])
            ->concat($task->activities->map(fn ($a) => ['type' => 'activity', 'at' => $a->created_at, 'item' => $a]))
            ->sortBy('at')->values();

        return view('livewire.task-panel', [
            'task' => $task,
            'people' => $this->workspace()->members()->orderBy('name')->get(),
            'timeline' => $timeline,
            'canEdit' => $this->me()->can('update', $task),
            'timeEntries' => $task->timeEntries()->with('user')->latest('started_at')->limit(50)->get(),
            'trackedTotal' => TimeTracker::totalFor($task),
            'running' => TimeTracker::running($this->me()),
            'clientUserIds' => $this->workspace()->clientUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }
}
