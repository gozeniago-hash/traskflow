<?php

namespace App\Livewire;

use App\Actions\Comments\AddComment;
use App\Actions\Files\StoreUploads;
use App\Actions\Tasks\CancelApproval;
use App\Actions\Tasks\DeleteTask;
use App\Actions\Tasks\RequestApproval;
use App\Actions\Tasks\SetTaskCompletion;
use App\Actions\Tasks\ShareTaskWithClient;
use App\Actions\Tasks\UpdateTaskDetail;
use App\Actions\Time\StartTimer;
use App\Actions\Time\StopTimer;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\CustomFields;
use App\Support\TimeTracker;
use App\Support\Undo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The slide-over task detail panel used everywhere in the internal app.
 * Open it from any view with: $dispatch('open-task', { id: 123 })
 */
class TaskPanel extends Component
{
    use InteractsWithWorkspace, LiveUpdates, WithFileUploads;

    /**
     * Locked: the browser can't change which task is open. Tasks are opened only
     * through open() (which checks access) or a ?task= link (checked in mount()).
     */
    #[Locked]
    #[Url(as: 'task', except: null)]
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

    /** Set when someone else changes the open task, so we can offer to reload its fields. */
    public ?string $remoteChangeBy = null;

    /** Custom field inputs: field_id => value */
    public array $fieldValues = [];

    // Manual time entry
    public string $manualDuration = '';

    public string $manualDate = '';

    public string $manualNote = '';

    protected function getListeners(): array
    {
        return [$this->workspaceChannel() => 'onWorkspaceChange'];
    }

    /**
     * Another person changed something in the workspace. Comments, subtasks, files and
     * activity refresh on re-render; edited fields only reload when you choose to,
     * so nothing you're typing gets overwritten.
     */
    public function onWorkspaceChange($event = null): void
    {
        if (! $this->taskId) {
            $this->skipRender();

            return;
        }
        $ids = array_map('intval', (array) ($event['taskIds'] ?? []));
        $task = Task::find($this->taskId);
        if (! $task || ! $this->me()->can('view', $task)) {
            $this->taskId = null;

            return;
        }
        if (! in_array($task->id, $ids, true) && ! in_array((int) $task->parent_id, $ids, true)) {
            $this->skipRender();

            return;
        }

        $fieldsChanged = $task->title !== $this->title
            || (string) $task->description !== $this->description
            || (string) $task->assignee_id !== $this->assignee_id
            || ($task->due_date?->toDateString() ?? '') !== $this->due_date
            || $task->priority !== $this->priority
            || (string) $task->section_id !== $this->section_id;

        if ($fieldsChanged) {
            $this->remoteChangeBy = $event['actor'] ?? 'Someone';
        }
    }

    public function reloadTask(): void
    {
        if ($this->taskId) {
            $this->open($this->taskId);
        }
    }

    /** Opening a page with ?task=ID (e.g. from a ticket link) shows that task straight away. */
    public function mount(): void
    {
        if ($this->taskId) {
            $task = Task::find($this->taskId);
            if ($task && $this->me()->can('view', $task)) {
                $this->open($task->id);
            } else {
                $this->taskId = null;
            }
        }
    }

    #[On('open-task')]
    public function open(int $id): void
    {
        $task = Task::with('project')->findOrFail($id);
        $this->authorize('view', $task);

        $this->taskId = $task->id;
        $this->remoteChangeBy = null;
        $this->fillFrom($task);
        $this->reset(['newSubtask', 'comment', 'uploads', 'manualDuration', 'manualNote', 'commentFiles', 'newCommentFiles']);
        $this->manualDate = today()->toDateString();
        $this->commentForClient = false;
        $this->resetValidation();
        $this->dispatch('task-panel-loaded', id: $task->id);
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
        $this->fieldValues = CustomFields::inputsFor($task, $task->project->fields);
    }

    #[On('fields-updated')]
    public function onFieldsUpdated(): void
    {
        if ($this->taskId && ($task = Task::with('project')->find($this->taskId))) {
            $this->fieldValues = CustomFields::inputsFor($task, $task->project->fields);
        }
    }

    public function updatedFieldValues($value, $key): void
    {
        $fieldId = (int) explode('.', (string) $key)[0];
        $task = $this->task();
        $field = $task->project->fields()->find($fieldId);
        if (! $field) {
            return;
        }

        $input = $this->fieldValues[$fieldId] ?? null;
        $validator = Validator::make(['v' => $input], ['v' => $field->rules()], [], ['v' => $field->name]);
        if ($validator->fails()) {
            $this->addError('fieldValues.'.$fieldId, $validator->errors()->first('v'));
            if (! $field->required || $field->normalize($input) !== null) {
                return;
            }
            // Required fields can't be cleared: put the saved value back.
            $this->fieldValues[$fieldId] = $field->toInput($task->fieldValues()->where('field_id', $fieldId)->value('value'));

            return;
        }
        $this->resetErrorBag('fieldValues.'.$fieldId);

        CustomFields::save($task, $field, $input);
        $stored = $task->fieldValues()->where('field_id', $fieldId)->value('value');
        $people = $field->type === 'person' ? CustomFields::peopleMap($task->project) : [];
        Activity::record($task, $this->me(), 'field_changed', ['field' => $field->name, 'to' => $field->display($stored, $people)]);
        $this->saved();
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
        $update = app(UpdateTaskDetail::class);

        switch ($property) {
            case 'title':
                if (trim($this->title) === '') {
                    $this->title = $task->title;

                    return;
                }
                $update->handle($task, $this->me(), 'title', $this->title);
                break;

            case 'description':
                $this->validateOnly('description', ['description' => ['nullable', 'string', 'max:20000']]);
                $update->handle($task, $this->me(), 'description', $this->description);
                break;

            case 'assignee_id':
                $userId = $this->assignee_id === '' ? null : (int) $this->assignee_id;
                if ($userId && ! $task->project->canBeAssigned($userId)) {
                    $this->assignee_id = (string) $task->assignee_id;

                    return;
                }
                $update->handle($task, $this->me(), 'assignee_id', $userId);
                break;

            case 'start_date':
            case 'due_date':
                // The date picker sends Y-m-d; anything else is put back to the saved date.
                if (Validator::make(['date' => $this->{$property}], ['date' => ['nullable', 'date_format:Y-m-d']])->fails()) {
                    $this->{$property} = $task->{$property}?->toDateString() ?? '';

                    return;
                }
                $update->handle($task, $this->me(), $property, $this->{$property});
                break;

            case 'priority':
                if (! array_key_exists($this->priority, Task::PRIORITIES)) {
                    $this->priority = $task->priority;

                    return;
                }
                $update->handle($task, $this->me(), 'priority', $this->priority);
                break;

            case 'section_id':
                $section = $task->project->sections()->find((int) $this->section_id);
                if (! $section) {
                    $this->section_id = (string) $task->section_id;

                    return;
                }
                $update->handle($task, $this->me(), 'section_id', $section);
                break;

            case 'client_visible':
                if (! $this->me()->can('shareWithClient', $task->project)) {
                    $this->client_visible = $task->client_visible;

                    abort(403);
                }
                if ($this->client_visible && ! $task->project->portal_enabled) {
                    $this->client_visible = false;
                    $this->toast('Turn on the client portal for this project first (project settings).', 'error');

                    return;
                }
                app(ShareTaskWithClient::class)->handle($task, $this->me(), $this->client_visible);
                break;
        }

        $this->saved();
    }

    /** Completes or reopens the open task, or one of its subtasks when $id is given. */
    public function setComplete(bool $complete, ?int $id, SetTaskCompletion $setTaskCompletion): void
    {
        $task = $this->task();
        $target = $id ? $task->subtasks()->findOrFail($id) : $task;
        $setTaskCompletion->handle($target, $this->me(), $complete);
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

    public function deleteSubtask(int $id, DeleteTask $deleteTask): void
    {
        $subtask = $this->task()->subtasks()->findOrFail($id);
        $this->authorize('delete', $subtask);
        $undo = $deleteTask->handle($subtask, $this->me());
        $this->toast('Subtask deleted.', undo: $undo);
        $this->saved();
    }

    public function updatedNewCommentFiles(): void
    {
        $this->validate(['newCommentFiles.*' => Attachment::rules()], [], ['newCommentFiles.*' => 'file']);
        $this->commentFiles = array_merge($this->commentFiles, $this->newCommentFiles);
        $this->newCommentFiles = [];
    }

    public function removeCommentFile(int $index): void
    {
        unset($this->commentFiles[$index]);
        $this->commentFiles = array_values($this->commentFiles);
    }

    public function addComment(AddComment $addComment): void
    {
        $task = Task::with('project')->findOrFail($this->taskId);
        $this->authorize('comment', $task);
        $this->validate([
            'comment' => ['nullable', 'string', 'max:10000'],
            'commentFiles.*' => Attachment::rules(),
        ], [], ['commentFiles.*' => 'file']);

        if (trim($this->comment) === '' && ! $this->commentFiles) {
            $this->addError('comment', 'Write a comment or attach a file.');

            return;
        }

        $addComment->handle($task, $this->me(), $this->comment, ! ($this->commentForClient && $task->client_visible), $this->commentFiles);

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

    public function updatedUploads(StoreUploads $storeUploads): void
    {
        $task = $this->task();
        $this->validate(['uploads.*' => Attachment::rules()]);
        $storeUploads->handle($this->uploads, $task->project, $this->me(), $task);
        $this->uploads = [];
        $this->saved();
    }

    public function deleteAttachment(int $id): void
    {
        $attachment = $this->task()->ownAttachments()->findOrFail($id);
        $this->authorize('delete', $attachment);
        $rows = Undo::attachmentRows([$attachment->id]);
        $undo = Undo::capture($this->me(), $attachment->project_id, 'file '.$attachment->original_name, $rows, Undo::filesOf($rows['attachments']));
        Undo::keepingFiles(fn () => $attachment->delete());
        $this->toast('File deleted.', undo: $undo);
        $this->saved();
    }

    public function startTimer(StartTimer $startTimer): void
    {
        $startTimer->handle($this->task(), $this->me());
        $this->dispatch('timer-changed');
    }

    public function stopTimer(StopTimer $stopTimer): void
    {
        $entry = $stopTimer->handle($this->me());
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

        TimeTracker::addManual($task, $this->me(), $seconds, Carbon::parse($this->manualDate), $this->manualNote ?: null);
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

    public function requestApproval(RequestApproval $requestApproval): void
    {
        $task = $this->task();
        $this->authorize('shareWithClient', $task->project);
        if (! $task->client_visible) {
            $this->toast('Share the task with the client before asking for approval.', 'error');

            return;
        }
        $requestApproval->handle($task, $this->me());
        $this->toast('Approval requested. The client will see it in their portal.');
        $this->saved();
    }

    public function cancelApproval(CancelApproval $cancelApproval): void
    {
        $task = $this->task();
        $this->authorize('shareWithClient', $task->project);
        $cancelApproval->handle($task);
        $this->saved();
    }

    public function deleteTask(DeleteTask $deleteTask): void
    {
        $task = $this->task();
        $this->authorize('delete', $task);

        $undo = $deleteTask->handle($task, $this->me());

        if ($task->parent_id) {
            $this->open($task->parent_id);
        } else {
            $this->taskId = null;
        }
        $this->toast(($task->parent_id ? 'Subtask' : 'Task').' deleted.', undo: $undo);
        $this->saved();
    }

    public function render()
    {
        $task = $this->taskId ? Task::find($this->taskId) : null;
        // Re-check access on every render, not just when the panel opened: access can be
        // revoked while it's open, and nothing from a task you can't see may be sent back.
        if (! $task || ! $this->me()->can('view', $task)) {
            $this->taskId = null;

            return view('livewire.task-panel', ['task' => null]);
        }

        $task->load([
            'project.sections', 'project.fields', 'fieldValues', 'parent', 'assignee', 'creator',
            'subtasks.assignee', 'ownAttachments.user', 'comments.user', 'comments.attachments.user', 'activities.user', 'pullRequests.repository',
        ]);

        $timeline = $task->comments->map(fn ($c) => ['type' => 'comment', 'at' => $c->created_at, 'item' => $c])
            ->concat($task->activities->map(fn ($a) => ['type' => 'activity', 'at' => $a->created_at, 'item' => $a]))
            ->sortBy('at')->values();

        return view('livewire.task-panel', [
            'task' => $task,
            // People who can open the project, plus the current assignee if they've since lost access.
            'people' => $task->project->assignableUsers()
                ->when($task->assignee && ! $task->project->canBeAssigned($task->assignee_id), fn ($c) => $c->push($task->assignee)->sortBy('name')->values()),
            'timeline' => $timeline,
            'canEdit' => $this->me()->can('update', $task),
            'canShare' => $this->me()->can('shareWithClient', $task->project),
            'githubEnabled' => $task->pullRequests->isNotEmpty() || $this->workspace()->githubRepositories()->exists(),
            'timeEntries' => $task->timeEntries()->with('user')->latest('started_at')->limit(50)->get(),
            'trackedTotal' => TimeTracker::totalFor($task),
            'running' => TimeTracker::running($this->me()),
            'fields' => $task->project->fields,
            // Person-field choices: people who can open the project, plus anyone already picked.
            'fieldPeople' => $task->project->fields->contains('type', 'person')
                ? CustomFields::assignableMap($task->project) + array_intersect_key(CustomFields::peopleMap($task->project), array_flip(array_map('intval', array_filter($task->fieldValues->pluck('value')->all(), 'is_numeric'))))
                : [],
            'clientUserIds' => $this->workspace()->clientUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }
}
