<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Project;
use App\Models\StatusUpdate;
use App\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Home')]
class Home extends Component
{
    use InteractsWithWorkspace, LiveUpdates;

    #[On('task-saved')]
    protected function getListeners(): array
    {
        return [$this->workspaceChannel() => 'onRemoteChange'];
    }

    public function onTaskSaved(): void
    {
    }

    public function toggleComplete(int $taskId): void
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);
        $task->toggleComplete($this->me());
        $this->completedToast($task);
    }

    public function render()
    {
        $me = $this->me();
        $mine = Task::query()->inVisibleProjects($me)->where('assignee_id', $me->id);

        $projects = Project::query()->visibleTo($me)->active()
            ->withCount([
                'topLevelTasks as tasks_total',
                'topLevelTasks as tasks_done' => fn ($q) => $q->whereNotNull('completed_at'),
            ])
            ->orderByDesc('updated_at')->limit(6)->get();

        $hour = now()->hour;

        return view('livewire.home', [
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'),
            'stats' => [
                'open' => (clone $mine)->open()->count(),
                'overdue' => (clone $mine)->open()->whereDate('due_date', '<', today())->count(),
                'doneThisWeek' => (clone $mine)->where('completed_at', '>=', now()->startOfWeek())->count(),
            ],
            'upcoming' => (clone $mine)->whereNull('completed_at')
                ->with('project')->orderByRaw('due_date is null')->orderBy('due_date')->limit(8)->get(),
            'projects' => $projects,
            'updates' => StatusUpdate::query()->whereIn('project_id', Project::query()->visibleTo($me)->select('projects.id'))
                ->with(['project', 'user'])->latest()->limit(4)->get(),
        ]);
    }
}
