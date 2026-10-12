<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\InteractsWithWorkspace;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    use InteractsWithWorkspace;

    public string $name = '';

    public function mount(): void
    {
        $this->requireAdmin();
        $this->name = $this->workspace()->name;
    }

    public function save(): void
    {
        $this->requireAdmin();
        $this->validate(['name' => ['required', 'string', 'max:120']]);
        $this->workspace()->update(['name' => $this->name]);
        $this->toast('Workspace renamed.');
    }

    public function render()
    {
        $ws = $this->workspace();

        return view('livewire.admin.settings', [
            'stats' => [
                'Members' => $ws->members()->count(),
                'Client contacts' => $ws->clientUsers()->count(),
                'Teams' => $ws->teams()->count(),
                'Active projects' => $ws->projects()->active()->count(),
                'Archived projects' => $ws->projects()->whereNotNull('archived_at')->count(),
            ],
            'archived' => $ws->projects()->whereNotNull('archived_at')->orderBy('name')->get(),
        ]);
    }

    public function unarchive(int $projectId): void
    {
        $this->requireAdmin();
        $this->workspace()->projects()->findOrFail($projectId)->update(['archived_at' => null]);
        $this->toast('Project restored.');
    }
}
