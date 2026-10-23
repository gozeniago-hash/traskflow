<?php

namespace App\Livewire\Projects;

use App\Actions\Tasks\CreateTask;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

class CalendarView extends Component
{
    use InteractsWithWorkspace, LiveUpdates;

    public Project $project;

    #[Url(as: 'month')]
    public string $month = '';

    public ?string $addingOn = null;

    public string $newTitle = '';

    protected function getListeners(): array
    {
        return $this->projectListeners($this->project->id);
    }

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    #[On('task-saved')]
    #[On('timer-changed')]
    public function onTaskSaved(): void {}

    protected function monthStart(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $this->month.'-01')->startOfDay();
        } catch (Throwable) {
            return CarbonImmutable::now()->startOfMonth();
        }
    }

    public function shift(int $months): void
    {
        $this->month = $this->monthStart()->addMonths($months)->format('Y-m');
    }

    public function today(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function startAdding(string $date): void
    {
        $this->addingOn = $date;
        $this->newTitle = '';
    }

    public function createOnDay(CreateTask $createTask): void
    {
        $this->authorize('contribute', $this->project);
        $title = trim($this->newTitle);
        if ($title === '' || ! $this->addingOn) {
            $this->addingOn = null;

            return;
        }
        $this->validate(['addingOn' => ['date_format:Y-m-d']]);

        $createTask->handle($this->project, $this->me(), ['title' => $title, 'due_date' => $this->addingOn]);
        $this->addingOn = null;
        $this->newTitle = '';
    }

    public function render()
    {
        $start = $this->monthStart();
        $gridStart = $start->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $start->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $tasks = $this->project->tasks()->whereNull('parent_id')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->with('assignee')->orderBy('completed_at')->orderBy('position')->get()
            ->groupBy(fn ($t) => $t->due_date->toDateString());

        $days = [];
        for ($d = $gridStart; $d->lte($gridEnd); $d = $d->addDay()) {
            $days[] = $d;
        }

        $unscheduled = $this->project->tasks()->whereNull('parent_id')->whereNull('due_date')->whereNull('completed_at')->count();

        return view('livewire.projects.calendar-view', compact('start', 'days', 'tasks', 'unscheduled'));
    }
}
