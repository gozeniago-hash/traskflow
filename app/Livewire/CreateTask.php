<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * "New task" form with full details and attachments.
 * Open from anywhere: $dispatch('create-task', { projectId: 1, sectionId: 2 })
 */
class CreateTask extends Component
{
    use InteractsWithWorkspace, WithFileUploads;

    public bool $show = false;
    public ?int $projectId = null;

    public string $title = '';
    public string $description = '';
    public string $section_id = '';
    public string $assignee_id = '';
    public string $due_date = '';
    public string $priority = 'none';
    public bool $client_visible = false;
    public bool $openAfter = false;

    /** Files queued for upload, and the input that feeds them (so picking twice adds, not replaces). */
    public array $files = [];
    public array $newFiles = [];

    #[On('create-task')]
    public function open(int $projectId, ?int $sectionId = null, ?string $dueDate = null): void
    {
        $project = Project::findOrFail($projectId);
        $this->authorize('update', $project);

        $this->reset(['title', 'description', 'assignee_id', 'due_date', 'priority', 'client_visible', 'files', 'newFiles', 'openAfter']);
        $this->resetValidation();
        $this->projectId = $project->id;
        $this->section_id = (string) ($sectionId && $project->sections()->whereKey($sectionId)->exists() ? $sectionId : $project->firstSection()->id);
        $this->due_date = $dueDate && strtotime($dueDate) ? date('Y-m-d', strtotime($dueDate)) : '';
        $this->show = true;
    }

    public function updatedNewFiles(): void
    {
        $this->validate(['newFiles.*' => ['file', 'max:'.config('taskflow.max_upload_kb')]], [], ['newFiles.*' => 'file']);
        $this->files = array_merge($this->files, $this->newFiles);
        $this->newFiles = [];
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    protected function project(): Project
    {
        $project = Project::findOrFail($this->projectId);
        $this->authorize('update', $project);

        return $project;
    }

    public function save(): void
    {
        $project = $this->project();
        $ws = $this->workspace();

        $data = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'section_id' => ['required', Rule::exists('sections', 'id')->where('project_id', $project->id)],
            'assignee_id' => ['nullable', Rule::exists('workspace_user', 'user_id')->where('workspace_id', $ws->id)->whereNot('role', 'client')],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],
            'files.*' => ['file', 'max:'.config('taskflow.max_upload_kb')],
        ], [], ['files.*' => 'file']);

        $me = $this->me();
        $task = DB::transaction(function () use ($project, $data, $me) {
            $task = $project->tasks()->create([
                'section_id' => (int) $data['section_id'],
                'title' => trim($data['title']),
                'description' => $data['description'] ?: null,
                'assignee_id' => $data['assignee_id'] ?: null,
                'due_date' => $data['due_date'] ?: null,
                'priority' => $data['priority'],
                'client_visible' => $this->client_visible && $project->portal_enabled,
                'creator_id' => $me->id,
                'position' => $project->nextTaskPosition((int) $data['section_id']),
            ]);
            Activity::record($task, $me, 'created');
            if ($task->assignee_id) {
                $project->members()->syncWithoutDetaching([$task->assignee_id]);
            }

            return $task;
        });

        foreach ($this->files as $file) {
            Attachment::storeUpload($file, $project, $me, $task);
        }

        $count = count($this->files);
        $this->show = false;
        $this->files = [];
        $this->dispatch('task-saved');
        $this->toast('Task created'.($count ? ' with '.$count.' '.str('file')->plural($count) : '').'.');

        if ($this->openAfter) {
            $this->dispatch('open-task', id: $task->id);
        }
    }

    public function render()
    {
        $project = $this->show && $this->projectId ? Project::with('sections')->find($this->projectId) : null;

        return view('livewire.create-task', [
            'project' => $project,
            'people' => $project ? $this->workspace()->members()->orderBy('name')->get() : collect(),
        ]);
    }
}
