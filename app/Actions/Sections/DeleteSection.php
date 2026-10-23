<?php

namespace App\Actions\Sections;

use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use App\Support\Undo;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a section and moves its tasks to another one, keeping both restorable for a
 * few minutes. The project row stays locked until the delete is done, and creating a task
 * takes the same lock, so no task can be added to the section while it disappears.
 */
class DeleteSection
{
    /**
     * The fallback may be null only when the section is the project's last one and is empty.
     *
     * @return array{undo: string, moved: int}
     */
    public function handle(Section $section, ?Section $fallback, User $by): array
    {
        return DB::transaction(function () use ($section, $fallback, $by): array {
            Project::whereKey($section->project_id)->lockForUpdate()->first();

            $tasks = Task::where('section_id', $section->id)->whereNull('parent_id')->orderBy('position')->get();
            $undo = Undo::capture($by, $section->project_id, 'section '.$section->name,
                ['sections' => [$section->getAttributes()]], [],
                $tasks->map(fn (Task $t) => [$t->id, $section->id, (int) $t->position, $fallback?->id])->all());

            if ($fallback) {
                $offset = $section->project->nextTaskPosition($fallback->id);
                $tasks->each(fn (Task $t, int $i) => $t->update(['section_id' => $fallback->id, 'position' => $offset + $i]));
            }
            $section->delete();

            return ['undo' => $undo, 'moved' => $tasks->count()];
        });
    }
}
