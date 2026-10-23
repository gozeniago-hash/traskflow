<?php

namespace App\Livewire;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\SetTaskCompletion;
use App\Actions\Time\StartTimer;
use App\Actions\Time\StopTimer;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\TimeTracker;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My tasks')]
class MyTasks extends Component
{
    use InteractsWithWorkspace, LiveUpdates;

    #[Url(except: 'incomplete')]
    public string $show = 'incomplete'; // incomplete | completed

    public string $newTitle = '';

    public string $newProject = '';

    public string $newDue = '';

    protected function getListeners(): array
    {
        return [$this->workspaceChannel() => 'onRemoteChange'];
    }

    #[On('task-saved')]
    #[On('timer-changed')]
    public function onTaskSaved(): void {}

    public function startTimer(int $taskId, StartTimer $startTimer): void
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);
        $startTimer->handle($task, $this->me());
        $this->dispatch('timer-changed');
    }

    public function stopTimer(StopTimer $stopTimer): void
    {
        $entry = $stopTimer->handle($this->me());
        $this->dispatch('timer-changed');
        if ($entry) {
            $this->toast('Logged '.TimeEntry::formatShort($entry->duration_seconds).'.');
        }
    }

    public function setComplete(int $taskId, bool $complete, SetTaskCompletion $setTaskCompletion): void
    {
        $task = Task::findOrFail($taskId);
        $this->authorize('update', $task);
        if ($setTaskCompletion->handle($task, $this->me(), $complete)) {
            $this->completedToast($task);
        }
    }

    public function addTask(CreateTask $createTask): void
    {
        $title = trim($this->newTitle);
        if ($title === '') {
            return;
        }
        $this->validate(['newDue' => ['nullable', 'date_format:Y-m-d']], ['newDue.date_format' => 'Pick a due date from the calendar.']);
        $project = Project::query()->visibleTo($this->me())->active()->find((int) $this->newProject);
        if (! $project) {
            $this->addError('newProject', 'Pick a project for this task.');

            return;
        }
        $this->authorize('contribute', $project);

        $createTask->handle($project, $this->me(), [
            'title' => $title,
            'assignee_id' => $this->me()->id,
            'due_date' => $this->newDue ?: null,
        ]);

        $this->reset(['newTitle', 'newDue']);
        $this->resetValidation();
    }

    protected function groups(Collection $tasks): array
    {
        $today = today();
        $groups = ['Overdue' => [], 'Today' => [], 'Next 7 days' => [], 'Later' => [], 'No due date' => []];

        foreach ($tasks as $task) {
            $due = $task->due_date;
            $key = match (true) {
                $due === null => 'No due date',
                $due->lt($today) && ! $task->isCompleted() => 'Overdue',
                $due->isSameDay($today) => 'Today',
                $due->lte($today->copy()->addDays(7)) && $due->gte($today) => 'Next 7 days',
                $due->lt($today) => 'Today',
                default => 'Later',
            };
            $groups[$key][] = $task;
        }

        return array_filter($groups);
    }

    public function render()
    {
        $me = $this->me();
        $query = Task::query()->inVisibleProjects($me)->where('assignee_id', $me->id)
            ->with(['project', 'parent'])->withCount('subtasks')
            ->withSum(['timeEntries as tracked_seconds' => fn ($t) => $t->whereNotNull('ended_at')], 'duration_seconds');

        if ($this->show === 'completed') {
            $tasks = $query->whereNotNull('completed_at')->latest('completed_at')->limit(100)->get();
            $groups = ['Completed' => $tasks->all()];
        } else {
            $tasks = $query->whereNull('completed_at')
                ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')->get();
            $groups = $this->groups($tasks);
        }

        $projects = Project::query()->visibleTo($me)->active()->orderBy('name')->get(['projects.id', 'name', 'color']);
        if ($this->newProject === '' && $projects->isNotEmpty()) {
            $this->newProject = (string) $projects->first()->id;
        }

        return view('livewire.my-tasks', [
            'groups' => $groups,
            'projects' => $projects,
            'total' => $tasks->count(),
            'running' => TimeTracker::running($me),
        ]);
    }
}
