<?php

namespace App\Actions\Tasks;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\CustomFields;
use Illuminate\Support\Facades\DB;

/**
 * Creates a top-level task at the end of a section (the project's first one by default),
 * with its custom field values. An assignee becomes a project member.
 */
class CreateTask
{
    /**
     * @param array{title: string, section_id?: int|null, description?: string|null, assignee_id?: int|null, due_date?: string|null, priority?: string, client_visible?: bool, requested_by_client?: bool} $attributes
     * @param array<int, mixed> $fieldInputs custom field inputs, field_id => value
     */
    public function handle(Project $project, User $by, array $attributes, array $fieldInputs = []): Task
    {
        return DB::transaction(function () use ($project, $by, $attributes, $fieldInputs): Task {
            // Locked so two tasks added to an empty project share the one section made for them.
            Project::whereKey($project->id)->lockForUpdate()->first();
            $sectionId = ($attributes['section_id'] ?? null) ?: $project->firstSection()->id;

            $task = $project->tasks()->create([
                ...$attributes,
                'section_id' => $sectionId,
                'title' => mb_substr(trim($attributes['title']), 0, 255),
                'client_visible' => ($attributes['client_visible'] ?? false) && $project->portal_enabled,
                'creator_id' => $by->id,
                'position' => $project->nextTaskPosition($sectionId),
            ]);
            Activity::record($task, $by, 'created');

            if ($fieldInputs !== []) {
                CustomFields::saveAll($task, $project->fields, $fieldInputs);
            }
            if ($task->assignee_id) {
                $project->members()->syncWithoutDetaching([$task->assignee_id]);
            }

            return $task;
        });
    }
}
