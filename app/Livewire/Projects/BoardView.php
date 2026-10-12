<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\ManagesProjectTasks;
use App\Models\Project;
use Livewire\Component;

class BoardView extends Component
{
    use ManagesProjectTasks;

    public Project $project;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->filter = 'all';
    }

    public function render()
    {
        return view('livewire.projects.board-view', ['sections' => $this->sectionsWithTasks()]);
    }
}
