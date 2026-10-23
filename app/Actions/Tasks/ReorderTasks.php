<?php

namespace App\Actions\Tasks;

use App\Models\Activity;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies a drag and drop in the List or Board view: the dragged task goes to the section
 * it was dropped in, and the tasks around it take the order the person saw.
 *
 * The page may be out of date. If someone else moved the dragged task in the meantime, the
 * drop is refused; other tasks that someone moved away since stay where they were put.
 */
class ReorderTasks
{
    /**
     * @param array<int, int> $ids the task ids of the section in their new order, as shown on the page
     * @return bool false when the page was out of date and nothing was changed
     */
    public function handle(Section $section, User $by, array $ids, int $movedId, ?int $fromSectionId): bool
    {
        return DB::transaction(function () use ($section, $by, $ids, $movedId, $fromSectionId): bool {
            $tasks = Task::where('project_id', $section->project_id)->whereNull('parent_id')
                ->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $moved = $tasks->get($movedId);
            if (! $moved || $moved->section_id !== $fromSectionId) {
                return false;
            }

            foreach ($ids as $index => $id) {
                $task = $tasks->get($id);
                if (! $task || ($task->id !== $moved->id && $task->section_id !== $section->id)) {
                    continue;
                }
                $task->forceFill(['section_id' => $section->id, 'position' => $index + 1])->save();
            }

            if ($fromSectionId !== $section->id) {
                Activity::record($moved, $by, 'moved', ['to' => $section->name]);
            }

            return true;
        });
    }
}
