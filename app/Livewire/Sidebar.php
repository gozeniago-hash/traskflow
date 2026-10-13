<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Project;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Workspace switcher, navigation and project list. A component (not plain layout
 * markup) so it refreshes when the workspace, a project or your profile changes —
 * whether you made the change on this page or a teammate made it elsewhere.
 */
class Sidebar extends Component
{
    use InteractsWithWorkspace, LiveUpdates;

    #[Locked]
    public string $route = '';

    #[Locked]
    public int $currentProjectId = 0;

    protected function getListeners(): array
    {
        return [
            $this->workspaceChannel() => 'onRemoteChange',
            'workspace-updated' => '$refresh',
            'project-updated' => '$refresh',
            'profile-updated' => '$refresh',
        ];
    }

    public function render()
    {
        $me = $this->me();

        return view('livewire.sidebar', [
            'me' => $me,
            'workspace' => $this->workspace(),
            'myWorkspaces' => $me->workspaces()->orderBy('name')->get(),
            'sidebarProjects' => Project::query()->visibleTo($me)->active()
                ->orderByRaw('owner_id = ? desc', [$me->id])->orderBy('name')->limit(12)->get(['id', 'name', 'color']),
        ]);
    }
}
