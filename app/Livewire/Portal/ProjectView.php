<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * What a client sees for one project: shared updates, shared tasks, shared files,
 * approvals, comments, and a form to submit new requests.
 */
#[Layout('layouts.portal')]
class ProjectView extends Component
{
    use InteractsWithWorkspace, WithFileUploads;

    public Project $project;

    #[Url(except: 'overview')]
    public string $tab = 'overview'; // overview | tasks | files

    #[Url(as: 'task', except: null)]
    public ?int $openTaskId = null;

    #[Url(as: 'doc', except: null)]
    public ?int $docId = null;

    public string $comment = '';
    public string $feedback = '';
    public array $uploads = [];
    public array $commentFiles = [];
    public array $newCommentFiles = [];

    public bool $showRequest = false;
    public string $requestTitle = '';
    public string $requestDetails = '';
    public string $requestDue = '';
    public array $requestFiles = [];

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        if (! in_array($this->tab, ['overview', 'tasks', 'docs', 'files'], true)) {
            $this->tab = 'overview';
        }
    }

    /** A task the client is allowed to see, scoped to this project. */
    protected function sharedTask(?int $id): Task
    {
        $task = $this->project->tasks()->where('client_visible', true)->findOrFail($id);
        $this->authorize('view', $task);

        return $task;
    }

    public function openTask(int $id): void
    {
        $this->sharedTask($id);
        $this->openTaskId = $id;
        $this->reset(['comment', 'feedback', 'uploads', 'commentFiles', 'newCommentFiles']);
        $this->resetValidation();
    }

    public function openDoc(int $id): void
    {
        $this->project->docs()->where('client_visible', true)->findOrFail($id);
        $this->docId = $id;
        $this->tab = 'docs';
    }

    public function closeTask(): void
    {
        $this->openTaskId = null;
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
        $task = $this->sharedTask($this->openTaskId);
        $this->authorize('comment', $task);
        $this->validate([
            'comment' => ['nullable', 'string', 'max:5000'],
            'commentFiles.*' => ['file', 'max:'.config('taskflow.max_upload_kb')],
        ], [], ['commentFiles.*' => 'file']);

        if (trim($this->comment) === '' && ! $this->commentFiles) {
            $this->addError('comment', 'Write a message or attach a file.');

            return;
        }

        $comment = $task->comments()->create(['user_id' => $this->me()->id, 'body' => trim($this->comment), 'is_internal' => false]);
        foreach ($this->commentFiles as $file) {
            Attachment::storeUpload($file, $this->project, $this->me(), $task, false, $comment);
        }

        $this->reset(['comment', 'commentFiles', 'newCommentFiles']);
        $this->toast('Message sent to the team.');
    }

    public function approve(): void
    {
        $task = $this->sharedTask($this->openTaskId);
        $this->authorize('approve', $task);
        $task->update(['approval_status' => 'approved', 'approval_by' => $this->me()->id, 'approval_at' => now()]);
        Activity::record($task, $this->me(), 'approved');
        if (trim($this->feedback) !== '') {
            $task->comments()->create(['user_id' => $this->me()->id, 'body' => trim($this->feedback), 'is_internal' => false]);
        }
        $this->feedback = '';
        $this->toast('Approved — thank you!');
    }

    public function requestChanges(): void
    {
        $task = $this->sharedTask($this->openTaskId);
        $this->authorize('approve', $task);
        $this->validate(['feedback' => ['required', 'string', 'max:5000']], ['feedback.required' => 'Tell the team what you would like changed.']);
        $task->update(['approval_status' => 'changes_requested', 'approval_by' => $this->me()->id, 'approval_at' => now()]);
        $task->comments()->create(['user_id' => $this->me()->id, 'body' => trim($this->feedback), 'is_internal' => false]);
        Activity::record($task, $this->me(), 'changes_requested');
        $this->feedback = '';
        $this->toast('Your feedback was sent to the team.');
    }

    public function updatedUploads(): void
    {
        $task = $this->sharedTask($this->openTaskId);
        $this->validate(['uploads.*' => ['file', 'max:'.config('taskflow.max_upload_kb')]]);
        foreach ($this->uploads as $file) {
            Attachment::storeUpload($file, $this->project, $this->me(), $task);
        }
        $this->uploads = [];
        $this->toast('File uploaded.');
    }

    public function submitRequest(): void
    {
        $this->authorize('view', $this->project);
        $this->validate([
            'requestTitle' => ['required', 'string', 'max:190'],
            'requestDetails' => ['nullable', 'string', 'max:10000'],
            'requestDue' => ['nullable', 'date', 'after_or_equal:today'],
            'requestFiles.*' => ['file', 'max:'.config('taskflow.max_upload_kb')],
        ]);

        $section = $this->project->sections()->firstOrCreate(
            ['name' => 'Client requests'],
            ['position' => 0]
        );

        $task = $this->project->tasks()->create([
            'section_id' => $section->id,
            'title' => $this->requestTitle,
            'description' => $this->requestDetails ?: null,
            'due_date' => $this->requestDue ?: null,
            'creator_id' => $this->me()->id,
            'client_visible' => true,
            'requested_by_client' => true,
            'position' => $this->project->nextTaskPosition($section->id),
        ]);
        Activity::record($task, $this->me(), 'client_request');

        foreach ($this->requestFiles as $file) {
            Attachment::storeUpload($file, $this->project, $this->me(), $task);
        }

        $this->reset(['requestTitle', 'requestDetails', 'requestDue', 'requestFiles', 'showRequest']);
        $this->tab = 'tasks';
        $this->toast('Request submitted. The team has been added to the loop.');
    }

    public function render()
    {
        $project = $this->project->fresh(['owner', 'client']);

        $sections = $project->sections()->with(['tasks' => fn ($q) => $q->where('client_visible', true)
            ->with('assignee')->withCount(['comments' => fn ($c) => $c->where('is_internal', false)])])->get()
            ->filter(fn ($s) => $s->tasks->isNotEmpty());

        $openTask = null;
        if ($this->openTaskId) {
            $openTask = $project->tasks()->where('client_visible', true)->with([
                'assignee', 'section',
                'comments' => fn ($q) => $q->where('is_internal', false)->with(['user', 'attachments.user']),
                'ownAttachments.user',
                'subtasks' => fn ($q) => $q->where('client_visible', true),
            ])->find($this->openTaskId);
        }

        $taskFiles = Attachment::query()->where('project_id', $project->id)->whereNotNull('task_id')
            ->whereHas('task', fn ($q) => $q->where('client_visible', true))
            ->where(fn ($q) => $q->whereNull('comment_id')->orWhereHas('comment', fn ($c) => $c->where('is_internal', false)))
            ->with(['task', 'user'])->latest()->get();
        $projectFiles = $project->attachments()->whereNull('task_id')->whereNull('doc_id')->where('client_visible', true)->with('user')->latest()->get();

        $docs = $project->docs()->where('client_visible', true)->with('editor')->get();
        $doc = $this->tab === 'docs' ? ($docs->firstWhere('id', $this->docId) ?? $docs->first()) : null;

        return view('livewire.portal.project-view', [
            'docs' => $docs,
            'doc' => $doc,
            'p' => $project,
            'progress' => $project->progress(clientOnly: true),
            'updates' => $project->statusUpdates()->where('client_visible', true)->with('user')->limit(20)->get(),
            'sections' => $sections,
            'approvals' => $project->tasks()->where('client_visible', true)->where('approval_status', 'pending')->orderBy('due_date')->get(),
            'upcoming' => $project->tasks()->whereNull('parent_id')->where('client_visible', true)->open()->whereNotNull('due_date')->orderBy('due_date')->limit(5)->get(),
            'openTask' => $openTask,
            'files' => $projectFiles->concat($taskFiles)->sortByDesc('created_at')->values(),
            'team' => $project->members()->orderBy('name')->get(),
        ])->title($project->name);
    }
}
