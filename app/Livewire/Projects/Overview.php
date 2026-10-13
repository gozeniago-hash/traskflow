<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectResource;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Overview extends Component
{
    use InteractsWithWorkspace, LiveUpdates, WithFileUploads;

    public Project $project;

    public string $description = '';
    public bool $editingDescription = false;

    public string $updateStatus = 'on_track';
    public string $updateTitle = '';
    public string $updateBody = '';
    public bool $updateShare = true;

    public string $addMember = '';

    // Key resources
    public ?string $addingResource = null; // null | link
    public string $resourceUrl = '';
    public string $resourceTitle = '';
    public bool $resourceInternal = false; // resources are shared with the client portal unless marked internal
    public array $resourceFiles = [];

    protected function getListeners(): array
    {
        return $this->projectListeners($this->project->id);
    }

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->description = (string) $project->description;
        $this->updateStatus = $project->status;
    }

    #[On('task-saved')]
    #[On('timer-changed')]
    public function onTaskSaved(): void
    {
    }

    /** The header edited the project: pick up a new description unless one is being typed. */
    public function onProjectUpdated(): void
    {
        $this->project->refresh();
        if (! $this->editingDescription) {
            $this->description = (string) $this->project->description;
        }
    }

    public function saveDescription(): void
    {
        $this->authorize('update', $this->project);
        $this->validate(['description' => ['nullable', 'string', 'max:5000']]);
        $this->project->update(['description' => $this->description ?: null]);
        $this->editingDescription = false;
        $this->dispatch('project-updated');
    }

    public function postUpdate(): void
    {
        $this->authorize('update', $this->project);
        $data = $this->validate([
            'updateStatus' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'updateTitle' => ['required', 'string', 'max:190'],
            'updateBody' => ['nullable', 'string', 'max:10000'],
            'updateShare' => ['boolean'],
        ]);

        $this->project->statusUpdates()->create([
            'user_id' => $this->me()->id,
            'status' => $data['updateStatus'],
            'title' => $data['updateTitle'],
            'body' => $data['updateBody'] ?: null,
            'client_visible' => $data['updateShare'] && $this->project->portal_enabled,
        ]);
        $this->project->update(['status' => $data['updateStatus']]);
        $this->dispatch('project-updated');
        $this->reset(['updateTitle', 'updateBody']);
        $this->toast('Status update posted.');
    }

    public function deleteUpdate(int $id): void
    {
        $this->authorize('update', $this->project);
        $update = $this->project->statusUpdates()->findOrFail($id);
        abort_unless($update->user_id === $this->me()->id || $this->me()->isAdmin(), 403);
        $update->delete();
    }

    public function addProjectMember(): void
    {
        $this->authorize('update', $this->project);
        $userId = (int) $this->addMember;
        if ($userId && $this->workspace()->members()->whereKey($userId)->exists()) {
            $this->project->members()->syncWithoutDetaching([$userId]);
        }
        $this->addMember = '';
    }

    public function removeProjectMember(int $userId): void
    {
        $this->authorize('update', $this->project);
        $this->project->members()->detach($userId);
    }

    // ---- Key resources ----------------------------------------------------

    public function addLink(): void
    {
        $this->authorize('update', $this->project);
        $url = trim($this->resourceUrl);
        if ($url !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url; // "figma.com/file/…" works too
        }
        $this->resourceUrl = $url;
        $this->validate([
            'resourceUrl' => ['required', 'url:http,https', 'max:2000'],
            'resourceTitle' => ['nullable', 'string', 'max:150'],
        ], [], ['resourceUrl' => 'link', 'resourceTitle' => 'title']);

        $resource = new ProjectResource(['url' => $url, 'client_visible' => ! $this->resourceInternal]);
        $resource->title = trim($this->resourceTitle) ?: ($resource->service() ?? $resource->domain());
        $this->saveResource($resource);

        $this->reset(['resourceUrl', 'resourceTitle', 'resourceInternal', 'addingResource']);
    }

    /** Files upload as soon as they're picked. */
    public function updatedResourceFiles(): void
    {
        $this->authorize('update', $this->project);
        $this->validate(['resourceFiles.*' => ['file', 'max:'.config('taskflow.max_upload_kb')]], [], ['resourceFiles.*' => 'file']);

        foreach ($this->resourceFiles as $file) {
            $attachment = Attachment::storeUpload($file, $this->project, $this->me(), null, true);
            $title = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: $file->getClientOriginalName();
            $this->saveResource(new ProjectResource(['title' => \Illuminate\Support\Str::limit($title, 145), 'attachment_id' => $attachment->id, 'client_visible' => true]));
        }
        $this->resourceFiles = [];
    }

    public function toggleResourceShare(int $id): void
    {
        $this->authorize('update', $this->project);
        $resource = $this->resource($id);
        $resource->update(['client_visible' => ! $resource->client_visible]);
        // A shared file must be downloadable from the portal, so the file follows the resource.
        $resource->attachment?->update(['client_visible' => $resource->client_visible]);
    }

    public function renameResource(int $id, string $title): void
    {
        $this->authorize('update', $this->project);
        $title = trim(\Illuminate\Support\Str::limit($title, 150, ''));
        if ($title !== '') {
            $this->resource($id)->update(['title' => $title]);
        }
    }

    public function moveResource(int $id, int $direction): void
    {
        $this->authorize('update', $this->project);
        $items = $this->project->resources()->get()->values();
        $from = $items->search(fn ($r) => $r->id === $id);
        $to = $from + ($direction < 0 ? -1 : 1);
        if ($from === false || ! isset($items[$to])) {
            return;
        }
        $order = $items->pluck('id')->all();
        [$order[$from], $order[$to]] = [$order[$to], $order[$from]];
        foreach ($order as $pos => $rid) {
            ProjectResource::whereKey($rid)->update(['position' => $pos + 1]);
        }
        \App\Support\Realtime::touch($this->project->id);
    }

    public function removeResource(int $id): void
    {
        $this->authorize('update', $this->project);
        $this->resource($id)->delete();
    }

    protected function saveResource(ProjectResource $resource): void
    {
        $resource->project_id = $this->project->id;
        $resource->user_id = $this->me()->id;
        $resource->position = (int) $this->project->resources()->max('position') + 1;
        $resource->save();
    }

    protected function resource(int $id): ProjectResource
    {
        return $this->project->resources()->findOrFail($id);
    }


    public function render()
    {
        $project = $this->project->fresh(['owner', 'team', 'client']);
        $members = $project->members()->orderBy('name')->get();

        return view('livewire.projects.overview', [
            'p' => $project,
            'progress' => $project->progress(),
            'updates' => $project->statusUpdates()->with('user')->limit(20)->get(),
            'members' => $members,
            'available' => $this->workspace()->members()->whereNotIn('users.id', $members->pluck('id'))->orderBy('name')->get(),
            'upcoming' => $project->tasks()->whereNull('parent_id')->open()->whereNotNull('due_date')
                ->orderBy('due_date')->with('assignee')->limit(6)->get(),
            'resources' => $project->resources()->with('attachment')->get(),
            'canEdit' => $this->me()->can('update', $project),
            'hasClient' => (bool) $project->client_id,
            'portalOn' => (bool) $project->portal_enabled && $project->client_id,
            'pendingApprovals' => $project->tasks()->where('approval_status', 'pending')->count(),
            'timeTotal' => (int) TimeEntry::where('project_id', $project->id)->whereNotNull('ended_at')->sum('duration_seconds'),
            'timeByPerson' => TimeEntry::where('project_id', $project->id)->whereNotNull('ended_at')
                ->selectRaw('user_id, sum(duration_seconds) as seconds')->groupBy('user_id')->orderByDesc('seconds')->with('user')->get(),
            'byAssignee' => Task::query()->where('project_id', $project->id)->whereNull('parent_id')->open()
                ->selectRaw('assignee_id, count(*) as total')->groupBy('assignee_id')->with('assignee')->get(),
        ]);
    }
}
