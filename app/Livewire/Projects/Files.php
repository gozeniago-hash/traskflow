<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Attachment;
use App\Models\Project;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Files extends Component
{
    use InteractsWithWorkspace, WithFileUploads;

    public Project $project;
    public array $uploads = [];
    public bool $shareWithClient = false;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    #[On('task-saved')]
    public function onTaskSaved(): void
    {
    }

    public function updatedUploads(): void
    {
        $this->authorize('update', $this->project);
        $this->validate(['uploads.*' => ['file', 'max:'.config('taskflow.max_upload_kb')]]);

        foreach ($this->uploads as $file) {
            Attachment::storeUpload($file, $this->project, $this->me(), null, $this->shareWithClient && $this->project->portal_enabled);
        }
        $count = count($this->uploads);
        $this->uploads = [];
        $this->toast($count.' '.str('file')->plural($count).' uploaded.');
    }

    public function toggleShare(int $id): void
    {
        $this->authorize('update', $this->project);
        $attachment = $this->project->attachments()->whereNull('task_id')->whereNull('doc_id')->findOrFail($id);
        $attachment->update(['client_visible' => ! $attachment->client_visible]);
    }

    public function delete(int $id): void
    {
        $attachment = $this->project->attachments()->findOrFail($id);
        $this->authorize('delete', $attachment);
        $attachment->delete();
        $this->toast('File deleted.');
    }

    public function render()
    {
        return view('livewire.projects.files', [
            'files' => $this->project->attachments()->with(['user', 'task', 'comment', 'doc'])->latest()->get(),
        ]);
    }
}
