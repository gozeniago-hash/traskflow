<?php

namespace App\Livewire\Portal;

use App\Actions\Comments\AddComment;
use App\Actions\Files\StoreUploads;
use App\Actions\Tasks\AnswerApproval;
use App\Actions\Tasks\SubmitClientRequest;
use App\Enums\ApprovalStatus;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
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
    use InteractsWithWorkspace, LiveUpdates, WithFileUploads;

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

    protected function getListeners(): array
    {
        return [$this->projectChannel($this->project->id) => 'onRemoteChange'];
    }

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
        $task = $this->sharedTask($this->openTaskId);
        $this->authorize('comment', $task);
        $this->validate([
            'comment' => ['nullable', 'string', 'max:5000'],
            'commentFiles.*' => Attachment::rules(),
        ], [], ['commentFiles.*' => 'file']);

        if (trim($this->comment) === '' && ! $this->commentFiles) {
            $this->addError('comment', 'Write a message or attach a file.');

            return;
        }

        $addComment->handle($task, $this->me(), $this->comment, false, $this->commentFiles);

        $this->reset(['comment', 'commentFiles', 'newCommentFiles']);
        $this->toast('Message sent to the team.');
    }

    public function approve(AnswerApproval $answerApproval): void
    {
        $task = $this->sharedTask($this->openTaskId);
        $this->authorize('approve', $task);
        $this->validate(['feedback' => ['nullable', 'string', 'max:5000']]);
        if ($answerApproval->handle($task, $this->me(), ApprovalStatus::Approved, $this->feedback)) {
            $this->toast('Approved — thank you!');
        }
        $this->feedback = '';
    }

    public function requestChanges(AnswerApproval $answerApproval): void
    {
        $task = $this->sharedTask($this->openTaskId);
        $this->authorize('approve', $task);
        $this->validate(['feedback' => ['required', 'string', 'max:5000']], ['feedback.required' => 'Tell the team what you would like changed.']);
        if ($answerApproval->handle($task, $this->me(), ApprovalStatus::ChangesRequested, $this->feedback)) {
            $this->toast('Your feedback was sent to the team.');
        }
        $this->feedback = '';
    }

    public function updatedUploads(StoreUploads $storeUploads): void
    {
        $task = $this->sharedTask($this->openTaskId);
        $this->validate(['uploads.*' => Attachment::rules()]);
        $storeUploads->handle($this->uploads, $this->project, $this->me(), $task);
        $this->uploads = [];
        $this->toast('File uploaded.');
    }

    public function submitRequest(SubmitClientRequest $submitClientRequest): void
    {
        $this->authorize('view', $this->project);
        $this->validate([
            'requestTitle' => ['required', 'string', 'max:190'],
            'requestDetails' => ['nullable', 'string', 'max:10000'],
            'requestDue' => ['nullable', 'date', 'after_or_equal:today'],
            'requestFiles.*' => Attachment::rules(),
        ]);

        $submitClientRequest->handle($this->project, $this->me(), [
            'title' => $this->requestTitle,
            'description' => $this->requestDetails,
            'due_date' => $this->requestDue,
        ], $this->requestFiles);

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
                'fieldValues',
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
            'clientFields' => $project->fields()->where('client_visible', true)->get(),
            'doc' => $doc,
            'p' => $project,
            'progress' => $project->progress(clientOnly: true),
            'updates' => $project->statusUpdates()->where('client_visible', true)->with('user')->limit(20)->get(),
            'sections' => $sections,
            'approvals' => $project->tasks()->where('client_visible', true)->where('approval_status', ApprovalStatus::Pending)->orderBy('due_date')->get(),
            'upcoming' => $project->tasks()->whereNull('parent_id')->where('client_visible', true)->open()->whereNotNull('due_date')->orderBy('due_date')->limit(5)->get(),
            'openTask' => $openTask,
            'files' => $projectFiles->concat($taskFiles)->sortByDesc('created_at')->values(),
            'team' => $project->members()->orderBy('name')->get(),
            'resources' => $project->resources()->where('client_visible', true)->with('attachment')->get(),
        ])->title($project->name);
    }
}
