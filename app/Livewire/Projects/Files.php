<?php

namespace App\Livewire\Projects;

use App\Actions\Files\ShareFileWithClient;
use App\Actions\Files\StoreUploads;
use App\Actions\Files\UpdateFileDetails;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Attachment;
use App\Models\Project;
use App\Support\Undo;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Files extends Component
{
    use InteractsWithWorkspace, LiveUpdates, WithFileUploads;

    public Project $project;

    public array $uploads = [];

    public bool $shareWithClient = false;

    /** Shows only the files in this group; empty shows all. */
    public string $group = '';

    #[Locked] // set only by edit(), after the permission check
    public ?int $editingId = null;

    /** @var array{title: string, description: string, group: string, meta: array<int, array{label: string, value: string}>} */
    public array $form = ['title' => '', 'description' => '', 'group' => '', 'meta' => []];

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
    public function onTaskSaved(): void {}

    public function updatedUploads(StoreUploads $storeUploads): void
    {
        $this->authorize('contribute', $this->project);
        $this->validate(['uploads.*' => Attachment::rules()]);

        $share = $this->shareWithClient && $this->project->portal_enabled && $this->me()->can('shareWithClient', $this->project);
        $storeUploads->handle($this->uploads, $this->project, $this->me(), null, $share);
        $count = count($this->uploads);
        $this->uploads = [];
        $this->toast($count.' '.str('file')->plural($count).' uploaded.');
    }

    public function share(int $id, bool $shared, ShareFileWithClient $shareFileWithClient): void
    {
        $this->authorize('shareWithClient', $this->project);
        $attachment = $this->project->attachments()->whereNull('task_id')->whereNull('doc_id')->findOrFail($id);
        $shareFileWithClient->handle($attachment, $shared);
    }

    public function delete(int $id): void
    {
        $attachment = $this->project->attachments()->findOrFail($id);
        $this->authorize('delete', $attachment);
        $rows = Undo::attachmentRows([$attachment->id]);
        $undo = Undo::capture($this->me(), $this->project->id, 'file '.$attachment->original_name, $rows, Undo::filesOf($rows['attachments']));
        Undo::keepingFiles(fn () => $attachment->delete());
        $this->toast('File deleted.', undo: $undo);
    }

    public function edit(int $id): void
    {
        $attachment = $this->project->attachments()->findOrFail($id);
        $this->authorize('update', $attachment);
        $this->resetValidation();
        $this->editingId = $attachment->id;
        $this->form = [
            'title' => (string) $attachment->title,
            'description' => (string) $attachment->description,
            'group' => (string) $attachment->group,
            'meta' => array_map(fn (array $row): array => ['label' => (string) ($row['label'] ?? ''), 'value' => (string) ($row['value'] ?? '')], $attachment->meta ?? []),
        ];
    }

    public function addMetaRow(): void
    {
        if (count($this->form['meta']) < 20) {
            $this->form['meta'][] = ['label' => '', 'value' => ''];
        }
    }

    public function removeMetaRow(int $index): void
    {
        unset($this->form['meta'][$index]);
        $this->form['meta'] = array_values($this->form['meta']);
    }

    public function saveDetails(UpdateFileDetails $updateFileDetails): void
    {
        $attachment = $this->project->attachments()->findOrFail($this->editingId);
        $this->authorize('update', $attachment);
        $data = $this->validate(Attachment::detailRules('form.'), [], [
            'form.title' => 'title', 'form.description' => 'description', 'form.group' => 'group',
            'form.meta' => 'extra details', 'form.meta.*.label' => 'label', 'form.meta.*.value' => 'value',
        ])['form'];

        $updateFileDetails->handle($attachment, $data['title'], $data['description'], $data['group'], $data['meta'] ?? []);
        $this->cancelEdit();
        $this->toast('File details saved.');
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'form']);
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.projects.files', [
            'files' => $this->project->attachments()->with(['user', 'task', 'comment', 'doc'])
                ->when($this->group !== '', fn ($query) => $query->where('group', $this->group))
                ->latest()->get(),
            'groups' => $this->project->attachments()->whereNotNull('group')->distinct()->orderBy('group')->pluck('group'),
            'canManage' => $this->me()->can('shareWithClient', $this->project),
        ]);
    }
}
