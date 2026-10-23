<?php

namespace App\Actions\Fields;

use App\Models\Project;
use App\Models\ProjectField;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds a custom field to a project, or saves changes to one. Field names are unique within a
 * project: the project row stays locked while the name is checked, so two people adding the
 * same field at the same moment get one field and a normal error message.
 */
class SaveField
{
    /**
     * @param array{name: string, type: string, description: ?string, required: bool, show_on_card: bool, client_visible: bool, options: ?array<int, array{id: string, label: string, color: string}>} $attributes
     *
     * @throws ValidationException when the project has another field with that name
     */
    public function handle(Project $project, ?ProjectField $field, array $attributes): ProjectField
    {
        return DB::transaction(function () use ($project, $field, $attributes): ProjectField {
            Project::whereKey($project->id)->lockForUpdate()->first();

            $taken = $project->fields()->whereRaw('lower(name) = ?', [mb_strtolower($attributes['name'])])
                ->when($field, fn ($q) => $q->whereKeyNot($field->id))->exists();
            if ($taken) {
                throw ValidationException::withMessages(['form.name' => 'This project already has a field with that name.']);
            }

            if ($field) {
                $field->update($attributes);

                return $field;
            }

            return $project->fields()->create($attributes + ['position' => (int) $project->fields()->max('position') + 1]);
        });
    }
}
