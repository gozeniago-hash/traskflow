<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\ManagesProjectTasks;
use App\Models\Project;
use Livewire\Component;

class ListView extends Component
{
    use ManagesProjectTasks;

    public Project $project;
    public array $collapsed = [];

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
    }

    public function toggleSection(int $id): void
    {
        $this->collapsed[$id] = ! ($this->collapsed[$id] ?? false);
    }

    public function render()
    {
        return view('livewire.projects.list-view', ['sections' => $this->sectionsWithTasks(), 'running' => $this->runningEntry(), ...$this->cardFields()]);
    }
}
