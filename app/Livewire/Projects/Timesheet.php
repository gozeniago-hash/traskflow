<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\FiltersDateRange;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Project;
use App\Support\Timesheet as TimesheetData;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/** All recorded time on one project, by day, with Excel and PDF export. */
class Timesheet extends Component
{
    use FiltersDateRange, InteractsWithWorkspace, LiveUpdates;

    public Project $project;

    #[Url(except: 'this_month')]
    public string $range = 'this_month';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $person = '';

    protected function getListeners(): array
    {
        return $this->projectListeners($this->project->id);
    }

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        if (! array_key_exists($this->range, static::rangeLabels())) {
            $this->range = 'this_month';
        }
        if ($this->range !== 'custom' || ! $this->from || ! $this->to) {
            $this->applyRange();
        }
    }

    #[On('timer-changed')]
    #[On('task-saved')]
    public function refreshTotals(): void
    {
    }

    protected function sheet(): TimesheetData
    {
        return new TimesheetData($this->me(), $this->from, $this->to, [$this->project->id], $this->person !== '' ? (int) $this->person : null);
    }

    public function export(string $format)
    {
        $this->authorize('view', $this->project);
        $notes = [];
        if ($this->person !== '' && ($u = $this->workspace()->members()->find((int) $this->person))) {
            $notes[] = $u->name;
        }
        [$contents, $filename, $mime] = $this->sheet()->export(
            $format === 'pdf' ? 'pdf' : 'xlsx', 'project', $this->project->name.' — Timesheet', $notes,
        );

        return response()->streamDownload(fn () => print($contents), $filename, ['Content-Type' => $mime]);
    }

    public function render()
    {
        $sheet = $this->sheet();
        $entries = $sheet->entries();

        return view('livewire.projects.timesheet', [
            'sheet' => $sheet,
            'days' => $sheet->byDay($entries),
            'people' => $sheet->byPerson($entries),
            'total' => (int) $entries->sum('duration_seconds'),
            'count' => $entries->count(),
            'members' => $this->workspace()->members()->orderBy('name')->get(['users.id', 'name']),
            'running' => \App\Models\TimeEntry::query()->running()->where('project_id', $this->project->id)->with('user:id,name')->get(),
        ]);
    }
}
