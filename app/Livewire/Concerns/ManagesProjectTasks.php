<?php

namespace App\Livewire\Concerns;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\CustomFields;
use App\Support\TimeTracker;
use App\Support\Undo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/**
 * Shared behaviour for the List and Board views of a project.
 * Expects a public Project $project property on the component.
 */
trait ManagesProjectTasks
{
    use InteractsWithWorkspace, LiveUpdates;

    protected function getListeners(): array
    {
        return $this->projectListeners($this->project->id);
    }

    public string $filter = 'incomplete';   // incomplete | all | completed

    public string $assigneeFilter = '';     // '' | me | unassigned

    public array $newTask = [];             // [sectionId => title]

    public string $newSection = '';

    #[Locked] // set only by server actions that check access
    public ?int $editingSection = null;

    public string $sectionName = '';

    #[On('task-saved')]
    #[On('timer-changed')]
    #[On('fields-updated')]
    public function onTaskSaved(): void
    {
        // Re-render picks up the change.
    }

    /** Sections and project structure: managers and admins. */
    protected function ensureCanEdit(): void
    {
        $this->authorize('update', $this->project);
    }

    /** Sections: project managers and the project's owner. */
    protected function ensureCanManageSections(): void
    {
        $this->authorize('manageSections', $this->project);
    }

    /** Working on tasks: anyone on the team who can see the project. */
    protected function ensureCanContribute(): void
    {
        $this->authorize('contribute', $this->project);
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
        $this->ensureCanContribute();
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
        $this->ensureCanContribute();
        $task = $this->projectTask($taskId);
        $task->toggleComplete($this->me());
        $this->completedToast($task);
        $this->dispatch('task-saved');
    }

    public function startTimer(int $taskId): void
    {
        $this->ensureCanContribute();
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

    /** Fields shown on cards/rows, and names for person fields. */
    protected function cardFields(): array
    {
        $fields = $this->project->fields()->where('show_on_card', true)->get();

        return [
            'cardFields' => $fields,
            'fieldPeople' => $fields->contains('type', 'person') ? CustomFields::peopleMap($this->project) : [],
        ];
    }

    protected function runningEntry(): ?TimeEntry
    {
        return TimeTracker::running($this->me());
    }

    /** Called by the Sortable helper with the full new order of a section. */
    public function reorder(?int $sectionId, array $ids): void
    {
        $this->ensureCanContribute();
        $section = $this->section($sectionId);
        $ids = array_values(array_filter(array_map('intval', $ids)));

        DB::transaction(function () use ($section, $ids): void {
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
        $this->ensureCanManageSections();
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
        $this->ensureCanManageSections();
        $this->editingSection = $id;
        $this->sectionName = $this->section($id)->name;
    }

    public function renameSection(): void
    {
        $this->ensureCanManageSections();
        if ($this->editingSection && trim($this->sectionName) !== '') {
            $this->section($this->editingSection)->update(['name' => mb_substr(trim($this->sectionName), 0, 120)]);
        }
        $this->editingSection = null;
    }

    public function moveSection(int $id, int $direction): void
    {
        $this->ensureCanManageSections();
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

    /** Drag and drop: $ids is every section of the project in its new order. */
    public function reorderSections(array $ids): void
    {
        $this->ensureCanManageSections();
        $ids = array_values(array_map('intval', $ids));
        $current = $this->project->sections()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($ids) !== count($current) || array_diff($current, $ids)) {
            return; // stale page or someone else added/removed a section meanwhile; the re-render shows the real order
        }
        DB::transaction(function () use ($ids): void {
            foreach ($ids as $i => $id) {
                Section::whereKey($id)->where('project_id', $this->project->id)->update(['position' => $i + 1]);
            }
        });
    }

    public function cancelSectionEdit(): void
    {
        $this->editingSection = null;
    }

    public function deleteSection(int $id): void
    {
        $this->ensureCanManageSections();
        $section = $this->section($id);
        $fallback = $this->project->sections()->where('id', '!=', $section->id)->first();

        if (! $fallback && $section->tasks()->exists()) {
            $this->toast('A project needs at least one section to hold its tasks.', 'error');

            return;
        }

        // Remember the section and where each task sat, so Undo can put them back.
        $tasks = $section->tasks()->get();
        $undo = Undo::capture($this->me(), $this->project->id, 'section '.$section->name,
            ['sections' => [$section->getAttributes()]], [],
            $tasks->map(fn (Task $t) => [$t->id, $section->id, (int) $t->position])->all());

        if ($fallback) {
            $offset = $this->project->nextTaskPosition($fallback->id);
            $tasks->each(fn (Task $t, $i) => $t->update(['section_id' => $fallback->id, 'position' => $offset + $i]));
        }
        $section->delete();
        $this->toast('Section deleted'.($fallback && $tasks->isNotEmpty() ? '. Its tasks moved to “'.$fallback->name.'”.' : '.'), undo: $undo);
    }

    /** Sections with their (filtered) top-level tasks. */
    protected function sectionsWithTasks(): Collection
    {
        $me = $this->me();

        $scope = function ($q) use ($me): void {
            $q->with(['assignee', 'fieldValues'])->withCount([
                'subtasks',
                'subtasks as completed_subtasks_count' => fn (Builder $s) => $s->whereNotNull('completed_at'),
                'comments',
                'attachments',
                'pullRequests',
                'pullRequests as open_prs_count' => fn (Builder $p) => $p->where('state', 'open'),
            ])->withSum(['timeEntries as tracked_seconds' => fn (Builder $t) => $t->whereNotNull('ended_at')], 'duration_seconds');

            match ($this->filter) {
                // Completed tasks leave at once; the toast's Undo brings one back.
                'incomplete' => $q->whereNull('completed_at'),
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
