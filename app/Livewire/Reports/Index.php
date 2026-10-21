<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Workspace reports. Everything is limited to projects the viewer can see,
 * so members get reports on their own work and admins see the whole workspace.
 */
#[Layout('layouts.app')]
#[Title('Reports')]
class Index extends Component
{
    use InteractsWithWorkspace, LiveUpdates;

    public const REPORTS = [
        'incomplete' => 'Incomplete tasks by resource',
        'upcoming' => 'Upcoming tasks by resource',
        'projects' => 'Projects by status',
        'completed' => 'Completed tasks',
        'timesheet' => 'Timesheet by resource',
    ];

    #[Url(as: 'r')]
    public string $report = 'incomplete';

    // Shared filters
    #[Url(except: '')]
    public string $project = '';

    #[Url(except: '')]
    public string $team = '';

    #[Url(except: '')]
    public string $person = '';

    #[Url(except: false)]
    public bool $includeSubtasks = false;

    // Upcoming
    #[Url(except: 14)]
    public int $days = 14;

    // Projects
    #[Url(except: false)]
    public bool $includeArchived = false;

    // Completed
    #[Url(except: 'this_month')]
    public string $range = 'this_month';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    /** Which resource rows are expanded to show their tasks. */
    public array $open = [];

    protected function getListeners(): array
    {
        return [$this->workspaceChannel() => 'onRemoteChange'];
    }

    public function mount(): void
    {
        if (! array_key_exists($this->report, self::REPORTS)) {
            $this->report = 'incomplete';
        }
        if (! in_array($this->days, [7, 14, 30, 60, 90], true)) {
            $this->days = 14;
        }
        $this->applyRange();
    }

    #[On('task-saved')]
    public function onTaskSaved(): void
    {
    }

    public function updatedReport(): void
    {
        $this->open = [];
    }

    public function updatedRange(): void
    {
        $this->applyRange();
    }

    public function toggle(string $key): void
    {
        $this->open[$key] = ! ($this->open[$key] ?? false);
    }

    public function expandAll(bool $expand): void
    {
        $this->open = $expand ? ['__all' => true] : [];
    }

    public function resetFilters(): void
    {
        $this->reset(['project', 'team', 'person', 'includeSubtasks', 'includeArchived', 'open']);
    }

    protected function applyRange(): void
    {
        $today = today(\App\Support\Timesheet::tz());
        [$from, $to] = match ($this->range) {
            'today' => [$today, $today],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'last_week' => [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()],
            'last_7' => [$today->copy()->subDays(6), $today],
            'last_30' => [$today->copy()->subDays(29), $today],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->copy()->firstOfQuarter(), $today->copy()->lastOfQuarter()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'custom' => [null, null],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
        };

        if ($from) {
            $this->from = $from->toDateString();
            $this->to = $to->toDateString();
        }
    }

    /** [from, to] as Carbon, always valid and in order. */
    protected function dateRange(): array
    {
        try {
            $from = $this->from ? Carbon::parse($this->from)->startOfDay() : today()->startOfMonth();
            $to = $this->to ? Carbon::parse($this->to)->endOfDay() : today()->endOfDay();
        } catch (\Throwable) {
            $from = today()->startOfMonth();
            $to = today()->endOfDay();
        }

        return $from->lte($to) ? [$from, $to] : [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
    }

    // ------------------------------------------------------------- queries

    /**
     * Report scope by role:
     * - admin: every project in the workspace, everyone's data
     * - manager: the projects they belong to, everyone's data
     * - member: projects shared with them, only their own tasks and time
     */
    protected function onlyMine(): bool
    {
        return ! $this->me()->isAdmin() && ! $this->me()->isManager();
    }

    /** The person filter actually applied ('' = everyone). Members are always filtered to themselves. */
    protected function personFilter(): string
    {
        return $this->onlyMine() ? (string) $this->me()->id : $this->person;
    }

    protected function scopedProjects(): Builder
    {
        $me = $this->me();

        return $me->isManager() ? Project::query()->managedBy($me) : Project::query()->visibleTo($me);
    }

    protected function visibleProjects(): Builder
    {
        return $this->scopedProjects()
            ->when($this->project !== '', fn ($q) => $q->where('projects.id', (int) $this->project))
            ->when($this->team !== '', fn ($q) => $q->where('team_id', (int) $this->team));
    }

    protected function taskQuery(): Builder
    {
        return Task::query()
            ->whereIn('project_id', $this->visibleProjects()->active()->select('projects.id'))
            ->when(! $this->includeSubtasks, fn ($q) => $q->whereNull('parent_id'))
            ->when($this->personFilter() === 'unassigned', fn ($q) => $q->whereNull('assignee_id'))
            ->when($this->personFilter() !== '' && $this->personFilter() !== 'unassigned', fn ($q) => $q->where('assignee_id', (int) $this->personFilter()))
            ->with(['project:id,key,name,color', 'assignee', 'parent:id,title', 'fieldValues']);
    }

    /** Group tasks by assignee, sorted by name with "Unassigned" last. */
    protected function byResource(Collection $tasks): Collection
    {
        return $tasks->groupBy(fn (Task $t) => $t->assignee_id ? 'u'.$t->assignee_id : 'none')
            ->map(fn (Collection $group, string $key) => [
                'key' => $key,
                'user' => $group->first()->assignee,
                'tasks' => $group,
            ])
            ->sortBy(fn ($row) => $row['user'] ? '0'.strtolower($row['user']->name) : '1')
            ->values();
    }

    protected function incompleteData(): array
    {
        $today = today();
        $tasks = $this->taskQuery()->whereNull('completed_at')
            ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')->get();

        $rows = $this->byResource($tasks)->map(function ($row) use ($today) {
            $t = $row['tasks'];
            $row['overdue'] = $t->filter(fn ($x) => $x->due_date && $x->due_date->lt($today))->count();
            $row['this_week'] = $t->filter(fn ($x) => $x->due_date && $x->due_date->between($today, $today->copy()->addDays(6)))->count();
            $row['later'] = $t->filter(fn ($x) => $x->due_date && $x->due_date->gt($today->copy()->addDays(6)))->count();
            $row['no_date'] = $t->whereNull('due_date')->count();
            $row['total'] = $t->count();

            return $row;
        });

        return [
            'rows' => $rows,
            'totals' => [
                'total' => $tasks->count(),
                'overdue' => $rows->sum('overdue'),
                'this_week' => $rows->sum('this_week'),
                'later' => $rows->sum('later'),
                'no_date' => $rows->sum('no_date'),
            ],
            'max' => max(1, (int) $rows->max('total')),
        ];
    }

    protected function upcomingData(): array
    {
        $start = today();
        $end = today()->addDays($this->days - 1);
        $tasks = $this->taskQuery()->whereNull('completed_at')
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('due_date')->orderBy('id')->get();

        $rows = $this->byResource($tasks)->map(function ($row) {
            $row['total'] = $row['tasks']->count();
            $row['next'] = $row['tasks']->first()?->due_date;

            return $row;
        });

        // Per-day counts for the strip chart.
        $perDay = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $perDay[$d->toDateString()] = 0;
        }
        foreach ($tasks as $t) {
            $perDay[$t->due_date->toDateString()]++;
        }

        return [
            'rows' => $rows,
            'total' => $tasks->count(),
            'start' => $start,
            'end' => $end,
            'perDay' => $perDay,
            'maxDay' => max(1, max($perDay ?: [0])),
            'max' => max(1, (int) $rows->max('total')),
        ];
    }

    protected function projectsData(): array
    {
        $projects = $this->visibleProjects()
            ->when(! $this->includeArchived, fn ($q) => $q->active())
            ->with(['owner', 'team', 'client'])
            ->withCount([
                'topLevelTasks as tasks_total',
                'topLevelTasks as tasks_done' => fn ($q) => $q->whereNotNull('completed_at'),
                'topLevelTasks as tasks_overdue' => fn ($q) => $q->whereNull('completed_at')->whereDate('due_date', '<', today()),
            ])
            ->withSum(['timeEntries as tracked_seconds' => fn ($q) => $q->whereNotNull('ended_at')], 'duration_seconds')
            ->orderBy('name')->get();

        $groups = collect(Project::STATUSES)->map(fn ($label, $status) => [
            'status' => $status,
            'label' => $label,
            'projects' => $projects->where('status', $status)->values(),
        ])->values();

        return ['groups' => $groups, 'total' => $projects->count()];
    }

    protected function completedData(): array
    {
        [$from, $to] = $this->dateRange();
        $tasks = $this->taskQuery()->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->orderByDesc('completed_at')->get();

        $rows = $this->byResource($tasks)->map(function ($row) {
            $t = $row['tasks'];
            $row['total'] = $t->count();
            $row['on_time'] = $t->filter(fn ($x) => $x->due_date && $x->completed_at->copy()->startOfDay()->lte($x->due_date))->count();
            $row['late'] = $t->filter(fn ($x) => $x->due_date && $x->completed_at->copy()->startOfDay()->gt($x->due_date))->count();

            return $row;
        })->sortByDesc('total')->values();

        $timeLogged = (int) TimeEntry::query()
            ->whereIn('project_id', $this->visibleProjects()->select('projects.id'))
            ->whereNotNull('ended_at')->whereBetween('started_at', [$from, $to])
            ->when($this->personFilter() !== '' && $this->personFilter() !== 'unassigned', fn ($q) => $q->where('user_id', (int) $this->personFilter()))
            ->sum('duration_seconds');

        $withDue = $rows->sum('on_time') + $rows->sum('late');

        return [
            'rows' => $rows,
            'total' => $tasks->count(),
            'from' => $from,
            'to' => $to,
            'onTimeRate' => $withDue ? (int) round($rows->sum('on_time') / $withDue * 100) : null,
            'late' => $rows->sum('late'),
            'timeLogged' => $timeLogged,
            'max' => max(1, (int) $rows->max('total')),
        ];
    }

    // ----------------------------------------------------------- timesheet

    protected function timesheet(): \App\Support\Timesheet
    {
        [$from, $to] = $this->dateRange();

        return new \App\Support\Timesheet(
            $this->me(), $from->toDateString(), $to->toDateString(),
            $this->project !== '' ? [(int) $this->project] : ($this->me()->isManager() ? $this->scopedProjects()->pluck('projects.id')->all() : null),
            ctype_digit($this->personFilter()) ? (int) $this->personFilter() : null,
            $this->team !== '' ? (int) $this->team : null,
        );
    }

    protected function timesheetData(): array
    {
        $sheet = $this->timesheet();
        $entries = $sheet->entries();
        $rows = $sheet->byPerson($entries)->map(function (array $row) {
            $row['key'] = 'u'.($row['user']?->id ?? 0);
            $row['byProject'] = $row['entries']->groupBy('project_id')
                ->map(fn ($g) => ['project' => $g->first()->project, 'seconds' => (int) $g->sum('duration_seconds')])
                ->sortByDesc('seconds')->values();

            return $row;
        });

        return [
            'sheet' => $sheet,
            'rows' => $rows,
            'total' => (int) $entries->sum('duration_seconds'),
            'count' => $entries->count(),
            'projectCount' => $entries->pluck('project_id')->unique()->count(),
            'max' => max(1, (int) $rows->max('seconds')),
        ];
    }

    public function exportTimesheet(string $format)
    {
        $notes = [];
        if ($this->project !== '' && ($p = Project::query()->visibleTo($this->me())->find((int) $this->project))) {
            $notes[] = $p->name;
        }
        if ($this->team !== '' && ($t = $this->workspace()->teams()->find((int) $this->team))) {
            $notes[] = $t->name;
        }
        if (ctype_digit($this->personFilter()) && ($u = $this->workspace()->members()->find((int) $this->personFilter()))) {
            $notes[] = $u->name;
        }
        [$contents, $filename, $mime] = $this->timesheet()->export(
            $format === 'pdf' ? 'pdf' : 'xlsx', 'people', 'Timesheet by resource', $notes ?: ['All projects'],
        );

        return response()->streamDownload(fn () => print($contents), $filename, ['Content-Type' => $mime]);
    }

    // -------------------------------------------------------------- export

    public function export(): StreamedResponse
    {
        abort_if($this->onlyMine() && $this->report === 'projects', 403);
        $filename = 'taskflow-'.$this->report.'-'.now()->format('Y-m-d').'.csv';

        [$header, $lines] = match ($this->report) {
            'projects' => $this->projectsCsv(),
            'completed' => $this->tasksCsv($this->completedData()['rows'], true),
            'upcoming' => $this->tasksCsv($this->upcomingData()['rows']),
            default => $this->tasksCsv($this->incompleteData()['rows']),
        };

        return response()->streamDownload(function () use ($header, $lines) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
            fputcsv($out, \App\Support\Export\Csv::row($header));
            foreach ($lines as $line) {
                fputcsv($out, \App\Support\Export\Csv::row($line)); // no spreadsheet formulas from user text
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function tasksCsv(Collection $rows, bool $completed = false): array
    {
        // Custom fields from every project in the export, one column per field name.
        $projectIds = $rows->flatMap(fn ($r) => $r['tasks']->pluck('project_id'))->unique();
        $fields = \App\Models\ProjectField::whereIn('project_id', $projectIds)->orderBy('position')->get();
        $fieldNames = $fields->pluck('name')->unique()->values();
        $fieldsByProject = $fields->groupBy('project_id');
        $people = $fields->contains('type', 'person') ? $this->workspace()->members()->pluck('name', 'users.id')->all() : [];

        $header = ['Resource', 'Ticket', 'Task', 'Parent task', 'Project', 'Priority', 'Due date'];
        $header[] = $completed ? 'Completed on' : 'Status';
        $header = array_merge($header, $fieldNames->all());

        $lines = [];
        foreach ($rows as $row) {
            foreach ($row['tasks'] as $t) {
                $line = [
                    $row['user']?->name ?? 'Unassigned',
                    $t->ticket(),
                    $t->title,
                    $t->parent?->title ?? '',
                    $t->project?->name ?? '',
                    Task::PRIORITIES[$t->priority] ?? $t->priority,
                    $t->due_date?->toDateString() ?? '',
                    $completed ? $t->completed_at->toDateString() : ($t->isOverdue() ? 'Overdue' : 'Open'),
                ];
                $values = $t->fieldValues->pluck('value', 'field_id');
                $byName = ($fieldsByProject[$t->project_id] ?? collect())->keyBy('name');
                foreach ($fieldNames as $name) {
                    $f = $byName[$name] ?? null;
                    $line[] = $f ? (string) $f->display($values[$f->id] ?? null, $people) : '';
                }
                $lines[] = $line;
            }
        }

        return [$header, $lines];
    }

    protected function projectsCsv(): array
    {
        $lines = [];
        foreach ($this->projectsData()['groups'] as $group) {
            foreach ($group['projects'] as $p) {
                $lines[] = [
                    $group['label'], $p->key, $p->name, $p->owner?->name ?? '', $p->team?->name ?? '', $p->client?->name ?? 'Internal',
                    $p->tasks_total, $p->tasks_done, $p->tasks_overdue,
                    $p->tasks_total ? round($p->tasks_done / $p->tasks_total * 100).'%' : '0%',
                    round(((int) $p->tracked_seconds) / 3600, 2),
                    $p->due_date?->toDateString() ?? '', $p->archived_at ? 'Yes' : 'No',
                ];
            }
        }

        return [['Status', 'Project ID', 'Project', 'Owner', 'Team', 'Client', 'Tasks', 'Completed', 'Overdue', 'Progress', 'Hours logged', 'Due date', 'Archived'], $lines];
    }

    // -------------------------------------------------------------- render

    public function render()
    {
        if ($this->onlyMine() && $this->report === 'projects') {
            $this->report = 'incomplete'; // the portfolio report covers everyone's work
        }
        $ws = $this->workspace();

        $data = match ($this->report) {
            'upcoming' => $this->upcomingData(),
            'projects' => $this->projectsData(),
            'completed' => $this->completedData(),
            'timesheet' => $this->timesheetData(),
            default => $this->incompleteData(),
        };

        return view('livewire.reports.index', [
            'data' => $data,
            'projects' => $this->scopedProjects()->active()->orderBy('name')->get(['projects.id', 'name']),
            'teams' => $ws->teams()->orderBy('name')->get(['id', 'name']),
            'people' => $ws->members()->orderBy('name')->get(),
            'scopeNote' => match (true) {
                $this->me()->isAdmin() => 'All projects in the workspace',
                $this->me()->isManager() => 'Projects you belong to',
                default => 'Your own tasks and time',
            },
            'onlyMine' => $this->onlyMine(),
            'reports' => $this->onlyMine() ? array_diff_key(self::REPORTS, ['projects' => true]) : self::REPORTS,
        ]);
    }
}
