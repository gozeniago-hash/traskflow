<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Search')]
class Search extends Component
{
    use InteractsWithWorkspace;

    #[Url]
    public string $q = '';

    public function render()
    {
        $me = $this->me();
        $term = trim($this->q);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return view('livewire.search', [
            'projects' => $term === '' ? collect() : Project::query()->visibleTo($me)->where('name', 'like', $like)->orderBy('name')->limit(10)->get(),
            'docs' => $term === '' ? collect() : ProjectDoc::query()
                ->whereIn('project_id', Project::query()->visibleTo($me)->select('projects.id'))
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('content', 'like', $like))
                ->with('project')->latest('updated_at')->limit(20)->get(),
            'tasks' => $term === '' ? collect() : Task::query()->inVisibleProjects($me)
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like))
                ->with(['project', 'assignee'])->orderByRaw('completed_at is not null')->latest('updated_at')->limit(50)->get(),
        ]);
    }
}
