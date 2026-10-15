<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Attachment;
use App\Models\Project;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Files extends Component
{
    use InteractsWithWorkspace, LiveUpdates, WithFileUploads;

    public Project $project;
    public array $uploads = [];
    public bool $shareWithClient = false;

    protected function getListeners(): array
    {
        return $this->projectListeners($this->project->id);
    }

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
        $this->authorize('contribute', $this->project);
        $this->validate(['uploads.*' => \App\Models\Attachment::rules()]);

        foreach ($this->uploads as $file) {
            $share = $this->shareWithClient && $this->project->portal_enabled && $this->me()->can('update', $this->project);
            Attachment::storeUpload($file, $this->project, $this->me(), null, $share);
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
        $rows = \App\Support\Undo::attachmentRows([$attachment->id]);
        $undo = \App\Support\Undo::capture($this->me(), $this->project->id, 'file '.$attachment->original_name, $rows, \App\Support\Undo::filesOf($rows['attachments']));
        \App\Support\Undo::keepingFiles(fn () => $attachment->delete());
        $this->toast('File deleted.', undo: $undo);
    }

    public function render()
    {
        return view('livewire.projects.files', [
            'files' => $this->project->attachments()->with(['user', 'task', 'comment', 'doc'])->latest()->get(),
            'canManage' => $this->me()->can('update', $this->project),
        ]);
    }
}
