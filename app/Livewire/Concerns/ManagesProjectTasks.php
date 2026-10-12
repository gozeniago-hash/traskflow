<?php

namespace App\Livewire\Concerns;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\TimeTracker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * Shared behaviour for the List and Board views of a project.
 * Expects a public Project $project property on the component.
 */
trait ManagesProjectTasks
{
    use InteractsWithWorkspace;

    public string $filter = 'incomplete';   // incomplete | all | completed
    public string $assigneeFilter = '';     // '' | me | unassigned
    public array $newTask = [];             // [sectionId => title]
    public string $newSection = '';
    public ?int $editingSection = null;
    public string $sectionName = '';

    #[On('task-saved')]
    #[On('timer-changed')]
    public function onTaskSaved(): void
    {
        // Re-render picks up the change.
    }

    protected function ensureCanEdit(): void
    {
        $this->authorize('update', $this->project);
    }

    protected function section(?int $id): Section
    {
        return $this->project->sections()->findOrFail($id);
    }

    protected function projectTask(int $id): Task
    {
        return $this->project->tasks()->findOrFail($id);
    }

    public function addTask(int $sectionId): void
    {
        $this->ensureCanEdit();
        $title = trim($this->newTask[$sectionId] ?? '');
        if ($title === '') {
            return;
        }
        $section = $this->section($sectionId);

        $task = $this->project->tasks()->create([
            'section_id' => $section->id,
            'title' => mb_substr($title, 0, 255),
            'creator_id' => $this->me()->id,
            'position' => $this->project->nextTaskPosition($section->id),
        ]);
        Activity::record($task, $this->me(), 'created');

        $this->newTask[$sectionId] = '';
    }

    public function toggleComplete(int $taskId): void
    {
        $this->ensureCanEdit();
        $this->projectTask($taskId)->toggleComplete($this->me());
        $this->dispatch('task-saved');
    }

    public function startTimer(int $taskId): void
    {
        $this->ensureCanEdit();
        TimeTracker::start($this->projectTask($taskId), $this->me());
        $this->dispatch('timer-changed');
    }

    public function stopTimer(): void
    {
        $entry = TimeTracker::stop($this->me());
        $this->dispatch('timer-changed');
        if ($entry) {
            $this->toast('Logged '.TimeEntry::formatShort($entry->duration_seconds).'.');
        }
    }

    protected function runningEntry(): ?TimeEntry
    {
        return TimeTracker::running($this->me());
    }

    /** Called by the Sortable helper with the full new order of a section. */
    public function reorder(?int $sectionId, array $ids): void
    {
        $this->ensureCanEdit();
        $section = $this->section($sectionId);
        $ids = array_values(array_filter(array_map('intval', $ids)));

        DB::transaction(function () use ($section, $ids) {
            $tasks = $this->project->tasks()->whereNull('parent_id')->whereIn('id', $ids)->get()->keyBy('id');
            foreach ($ids as $index => $id) {
                if (! $task = $tasks->get($id)) {
                    continue;
                }
                $moved = $task->section_id !== $section->id;
                $task->forceFill(['section_id' => $section->id, 'position' => $index + 1])->save();
                if ($moved) {
                    Activity::record($task, $this->me(), 'moved', ['to' => $section->name]);
                }
            }
        });
    }

    public function addSection(): void
    {
        $this->ensureCanEdit();
        $name = trim($this->newSection);
        if ($name === '') {
            return;
        }
        $this->project->sections()->create([
            'name' => mb_substr($name, 0, 120),
            'position' => (int) $this->project->sections()->max('position') + 1,
        ]);
        $this->newSection = '';
    }

    public function editSection(int $id): void
    {
        $this->editingSection = $id;
        $this->sectionName = $this->section($id)->name;
    }

    public function renameSection(): void
    {
        $this->ensureCanEdit();
        if ($this->editingSection && trim($this->sectionName) !== '') {
            $this->section($this->editingSection)->update(['name' => mb_substr(trim($this->sectionName), 0, 120)]);
        }
        $this->editingSection = null;
    }

    public function moveSection(int $id, int $direction): void
    {
        $this->ensureCanEdit();
        $sections = $this->project->sections()->get()->values();
        $index = $sections->search(fn ($s) => $s->id === $id);
        $swap = $index + ($direction < 0 ? -1 : 1);
        if ($index === false || ! isset($sections[$swap])) {
            return;
        }
        $ordered = $sections->all();
        [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
        foreach ($ordered as $i => $section) {
            $section->update(['position' => $i + 1]);
        }
    }

    public function deleteSection(int $id): void
    {
        $this->ensureCanEdit();
        $section = $this->section($id);
        $fallback = $this->project->sections()->where('id', '!=', $section->id)->first();

        if (! $fallback && $section->tasks()->exists()) {
            $this->toast('A project needs at least one section to hold its tasks.', 'error');

            return;
        }

        if ($fallback) {
            $offset = $this->project->nextTaskPosition($fallback->id);
            $section->tasks()->get()->each(fn (Task $t, $i) => $t->update(['section_id' => $fallback->id, 'position' => $offset + $i]));
        }
        $section->delete();
        $this->toast('Section deleted'.($fallback ? '. Its tasks moved to “'.$fallback->name.'”.' : '.'));
    }

    /** Sections with their (filtered) top-level tasks. */
    protected function sectionsWithTasks(): Collection
    {
        $me = $this->me();

        $scope = function ($q) use ($me) {
            $q->with('assignee')->withCount([
                'subtasks',
                'subtasks as completed_subtasks_count' => fn (Builder $s) => $s->whereNotNull('completed_at'),
                'comments',
                'attachments',
            ])->withSum(['timeEntries as tracked_seconds' => fn (Builder $t) => $t->whereNotNull('ended_at')], 'duration_seconds');

            match ($this->filter) {
                // Keep just-completed tasks visible for a few minutes so the click feels right.
                'incomplete' => $q->where(fn ($w) => $w->whereNull('completed_at')->orWhere('completed_at', '>=', now()->subMinutes(5))),
                'completed' => $q->whereNotNull('completed_at'),
                default => null,
            };

            match ($this->assigneeFilter) {
                'me' => $q->where('assignee_id', $me->id),
                'unassigned' => $q->whereNull('assignee_id'),
                default => null,
            };
        };

        if (! $this->project->sections()->exists()) {
            $this->project->firstSection();
        }

        return $this->project->sections()->with(['tasks' => $scope])->get();
    }
}
