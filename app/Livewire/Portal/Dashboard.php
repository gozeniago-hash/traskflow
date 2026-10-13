<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Your projects')]
class Dashboard extends Component
{
    use InteractsWithWorkspace, LiveUpdates;

    protected function getListeners(): array
    {
        return Project::query()->visibleTo($this->me())->active()->pluck('projects.id')
            ->mapWithKeys(fn ($id) => [$this->projectChannel((int) $id) => 'onRemoteChange'])->all();
    }

    public function render()
    {
        $me = $this->me();
        $projects = Project::query()->visibleTo($me)->active()
            ->with(['statusUpdates' => fn ($q) => $q->where('client_visible', true)->limit(1)])
            ->withCount([
                'topLevelTasks as shared_total' => fn ($q) => $q->where('client_visible', true),
                'topLevelTasks as shared_done' => fn ($q) => $q->where('client_visible', true)->whereNotNull('completed_at'),
            ])
            ->orderBy('name')->get();

        return view('livewire.portal.dashboard', [
            'workspace' => $this->workspace(),
            'client' => Client::find($me->clientId()),
            'projects' => $projects,
            'approvals' => Task::query()->whereIn('project_id', $projects->pluck('id'))
                ->where('client_visible', true)->where('approval_status', 'pending')
                ->with('project')->orderBy('due_date')->get(),
        ]);
    }
}
