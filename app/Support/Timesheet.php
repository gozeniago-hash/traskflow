<?php

namespace App\Support;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Export\PdfTable;
use App\Support\Export\Xlsx;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Recorded time for a date range, limited to projects the viewer can see.
 * Used by the project Timesheet tab and the Timesheet report, including their
 * Excel and PDF exports. Days are calendar days in config('taskflow.timezone').
 */
class Timesheet
{
    public CarbonImmutable $from;
    public CarbonImmutable $to;

    /**
     * @param  array<int>|null  $projectIds  limit to these projects (still intersected with what the viewer can see)
     */
    public function __construct(
        public User $viewer,
        string $from,
        string $to,
        public ?array $projectIds = null,
        public ?int $userId = null,
        public ?int $teamId = null,
    ) {
        $tz = static::tz();
        try {
            $a = CarbonImmutable::parse($from, $tz)->startOfDay();
            $b = CarbonImmutable::parse($to, $tz)->endOfDay();
        } catch (\Throwable) {
            $a = CarbonImmutable::now($tz)->startOfMonth();
            $b = CarbonImmutable::now($tz)->endOfDay();
        }
        [$this->from, $this->to] = $a->lte($b) ? [$a, $b] : [$b->startOfDay(), $a->endOfDay()];
    }

    public static function tz(): string
    {
        return (string) config('taskflow.timezone', 'UTC');
    }

    /** Finished entries in range, newest first. */
    public function entries(): Collection
    {
        $projects = Project::query()->visibleTo($this->viewer)
            ->when($this->projectIds !== null, fn ($q) => $q->whereIn('projects.id', $this->projectIds ?: [0]))
            ->when($this->teamId, fn ($q) => $q->where('team_id', $this->teamId))
            ->select('projects.id');

        return TimeEntry::query()
            ->whereNotNull('ended_at')
            ->whereIn('project_id', $projects)
            ->whereBetween('started_at', [$this->from->utc(), $this->to->utc()])
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->with(['user:id,name,email', 'project:id,key,name,color', 'task:id,project_id,number,title,parent_id'])
            ->orderByDesc('started_at')->orderByDesc('id')
            ->get()
            ->each(fn (TimeEntry $e) => $e->setAttribute('local_start', $e->started_at->copy()->setTimezone(static::tz())));
    }

    /** Totals per person, sorted by name: [user, seconds, count, projects, entries]. */
    public function byPerson(?Collection $entries = null): Collection
    {
        return ($entries ?? $this->entries())
            ->groupBy('user_id')
            ->map(fn (Collection $group) => [
                'user' => $group->first()->user,
                'seconds' => (int) $group->sum('duration_seconds'),
                'count' => $group->count(),
                'projects' => $group->pluck('project_id')->unique()->count(),
                'entries' => $group,
            ])
            ->sortBy(fn ($row) => strtolower($row['user']?->name ?? 'zzz'))
            ->values();
    }

    /** Entries grouped by local calendar day, newest day first. */
    public function byDay(?Collection $entries = null): Collection
    {
        return ($entries ?? $this->entries())
            ->groupBy(fn (TimeEntry $e) => $e->local_start->toDateString())
            ->map(fn (Collection $group, string $day) => [
                'day' => CarbonImmutable::parse($day, static::tz()),
                'seconds' => (int) $group->sum('duration_seconds'),
                'entries' => $group,
            ])
            ->values();
    }

    public function rangeLabel(): string
    {
        return $this->from->isSameDay($this->to)
            ? $this->from->format('M j, Y')
            : $this->from->format($this->from->year === $this->to->year ? 'M j' : 'M j, Y').' – '.$this->to->format('M j, Y');
    }

    public static function hours(int $seconds): string
    {
        return number_format($seconds / 3600, 2);
    }

    // ---------------------------------------------------------------- exports

    /**
     * Build an export. $mode is 'project' (one project, rows by day) or 'people' (rows by person).
     *
     * @return array{0: string, 1: string, 2: string} [contents, filename, mime type]
     */
    public function export(string $format, string $mode, string $title, array $filterNotes = []): array
    {
        $entries = $this->entries();
        $total = (int) $entries->sum('duration_seconds');
        $people = $entries->pluck('user_id')->unique()->count();

        $columns = [
            ['title' => 'Date', 'type' => 'date', 'width' => 12, 'pdf' => 0.09],
            ['title' => 'Person', 'type' => 'text', 'width' => 20, 'pdf' => 0.12],
            ['title' => 'Project', 'type' => 'text', 'width' => 26, 'pdf' => 0.14],
            ['title' => 'Ticket', 'type' => 'text', 'width' => 10, 'pdf' => 0.07],
            ['title' => 'Task', 'type' => 'text', 'width' => 40, 'pdf' => 0.22],
            ['title' => 'Note', 'type' => 'text', 'width' => 40, 'pdf' => 0.2],
            ['title' => 'Start', 'type' => 'text', 'width' => 8, 'pdf' => 0.05],
            ['title' => 'End', 'type' => 'text', 'width' => 8, 'pdf' => 0.05],
            ['title' => 'Hours', 'type' => 'hours', 'width' => 9, 'pdf' => 0.06, 'align' => 'right'],
        ];
        if ($mode === 'project') {
            unset($columns[2]); // one project: its name is in the title
            $columns = array_values($columns);
            $columns[3]['pdf'] += 0.07;
            $columns[4]['pdf'] += 0.07;
        }

        $line = function (TimeEntry $e, bool $pdf) use ($mode) {
            $local = $e->local_start;
            $end = $e->ended_at?->copy()->setTimezone(static::tz());
            $cells = [
                $pdf ? $local->format('M j, Y') : $local->toDateString(),
                $e->user?->name ?? 'Former member',
                $e->project?->name,
                $e->task ? ($e->project?->key ?? 'T').'-'.$e->task->number : '',
                $e->task?->title ?? '(task deleted)',
                (string) $e->note.($e->is_manual ? ($e->note ? ' ' : '').'(added manually)' : ''),
                $e->is_manual ? '' : $local->format('H:i'),
                $e->is_manual || ! $end ? '' : $end->format('H:i'),
                $pdf ? static::hours((int) $e->duration_seconds) : round($e->duration_seconds / 3600, 2),
            ];
            if ($mode === 'project') {
                unset($cells[2]);
            }

            return ['cells' => array_values($cells)];
        };

        $build = function (bool $pdf) use ($mode, $entries, $line, $total, $columns) {
            $width = count($columns);
            $group = function (string $label, int $seconds) use ($pdf, $width) {
                $cells = array_fill(0, $width, null);
                $cells[0] = $label;
                $cells[$width - 1] = $pdf ? static::hours($seconds) : round($seconds / 3600, 2);

                return ['cells' => $cells, 'style' => 'group'];
            };
            $rows = [];
            if ($mode === 'people') {
                foreach ($this->byPerson($entries) as $p) {
                    $rows[] = $group(($p['user']?->name ?? 'Former member').' — '.$p['count'].' '.Str::plural('entry', $p['count']), $p['seconds']);
                    foreach ($p['entries']->sortBy('started_at') as $e) {
                        $rows[] = $line($e, $pdf);
                    }
                }
            } else {
                foreach ($this->byDay($entries)->reverse() as $d) {
                    $rows[] = $group($d['day']->format('l, M j, Y'), $d['seconds']);
                    foreach ($d['entries']->sortBy('started_at') as $e) {
                        $rows[] = $line($e, $pdf);
                    }
                }
            }
            $totalCells = array_fill(0, $width, null);
            $totalCells[0] = 'Total';
            $totalCells[$width - 1] = $pdf ? static::hours($total) : round($total / 3600, 2);
            $rows[] = ['cells' => $totalCells, 'style' => 'total'];

            return $rows;
        };

        $subtitle = array_filter([
            $this->rangeLabel().($filterNotes ? ' · '.implode(' · ', $filterNotes) : ''),
            $entries->count().' '.Str::plural('entry', $entries->count()).' · '.$people.' '.Str::plural('person', $people).' · '.static::hours($total).' hours',
        ]);
        $exported = 'Exported from TaskFlow on '.now(static::tz())->format('M j, Y g:i A').' ('.static::tz().')';
        $base = Str::slug($title).'-'.$this->from->toDateString().'-to-'.$this->to->toDateString();

        if ($format === 'pdf') {
            $pdfColumns = array_map(fn ($c) => ['title' => $c['title'], 'width' => $c['pdf'], 'align' => $c['align'] ?? 'left'], $columns);

            return [(new PdfTable($title, array_values($subtitle), $pdfColumns, $build(true), $exported))->render(), $base.'.pdf', 'application/pdf'];
        }

        $xlsx = new Xlsx('Timesheet', $columns, $build(false), $title, [...array_values($subtitle), $exported]);

        return [$xlsx->render(), $base.'.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }
}
