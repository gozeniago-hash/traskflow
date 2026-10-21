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

    /** Searching an exact ticket number from the header goes straight to the task. */
    public function mount(): void
    {
        if (preg_match('/^\s*([A-Za-z][A-Za-z0-9]{0,9})-(\d+)\s*$/', $this->q, $m)) {
            $task = Task::query()->inVisibleProjects($this->me())->where('number', (int) $m[2])
                ->whereHas('project', fn ($q) => $q->where('key', strtoupper($m[1])))->with('project')->first();
            if ($task) {
                $this->redirect($task->ticketUrl()); // nosemgrep: symfony-non-literal-redirect -- our own route for a task the user can see
            }
        }
    }

    public function render()
    {
        $me = $this->me();
        $term = trim($this->q);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        // "BWR-12" jumps straight to that ticket.
        $ticket = null;
        if (preg_match('/^([A-Za-z][A-Za-z0-9]{0,9})-(\d+)$/', $term, $m)) {
            $ticket = Task::query()->inVisibleProjects($me)->where('number', (int) $m[2])
                ->whereHas('project', fn ($q) => $q->where('key', strtoupper($m[1])))
                ->with(['project', 'assignee'])->first();
        }

        return view('livewire.search', [
            'ticket' => $ticket,
            'projects' => $term === '' ? collect() : Project::query()->visibleTo($me)->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('key', strtoupper($term)))->orderBy('name')->limit(10)->get(),
            'docs' => $term === '' ? collect() : ProjectDoc::query()
                ->whereIn('project_id', Project::query()->visibleTo($me)->select('projects.id'))
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('content', 'like', $like))
                ->with('project')->latest('updated_at')->limit(20)->get(),
            'tasks' => $term === '' ? collect() : Task::query()->inVisibleProjects($me)
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like)
                    ->when(ctype_digit($term), fn ($w) => $w->orWhere('number', (int) $term)))
                ->with(['project', 'assignee'])->orderByRaw('completed_at is not null')->latest('updated_at')->limit(50)->get(),
        ]);
    }
}
