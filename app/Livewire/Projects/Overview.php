<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Overview extends Component
{
    use InteractsWithWorkspace, LiveUpdates;

    public Project $project;

    public string $description = '';
    public bool $editingDescription = false;

    public string $updateStatus = 'on_track';
    public string $updateTitle = '';
    public string $updateBody = '';
    public bool $updateShare = true;

    public string $addMember = '';

    protected function getListeners(): array
    {
        return [$this->projectChannel($this->project->id) => 'onRemoteChange'];
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

    public function saveDescription(): void
    {
        $this->authorize('update', $this->project);
        $this->validate(['description' => ['nullable', 'string', 'max:5000']]);
        $this->project->update(['description' => $this->description ?: null]);
        $this->editingDescription = false;
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
            'pendingApprovals' => $project->tasks()->where('approval_status', 'pending')->count(),
            'timeTotal' => (int) TimeEntry::where('project_id', $project->id)->whereNotNull('ended_at')->sum('duration_seconds'),
            'timeByPerson' => TimeEntry::where('project_id', $project->id)->whereNotNull('ended_at')
                ->selectRaw('user_id, sum(duration_seconds) as seconds')->groupBy('user_id')->orderByDesc('seconds')->with('user')->get(),
            'byAssignee' => Task::query()->where('project_id', $project->id)->whereNull('parent_id')->open()
                ->selectRaw('assignee_id, count(*) as total')->groupBy('assignee_id')->with('assignee')->get(),
        ]);
    }
}
