<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectField;
use App\Models\Task;
use Illuminate\Support\Collection;

/** Shared helpers for reading, validating and saving custom field values. */
class CustomFields
{
    /** Validation rules keyed as "{$prefix}.{fieldId}". */
    public static function rules(Collection $fields, string $prefix = 'fieldValues'): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $rules[$prefix.'.'.$field->id] = $field->rules();
        }

        return $rules;
    }

    /** Friendly attribute names for validation messages. */
    public static function attributes(Collection $fields, string $prefix = 'fieldValues'): array
    {
        return $fields->mapWithKeys(fn ($f) => [$prefix.'.'.$f->id => $f->name])->all();
    }

    /** Empty inputs for a form: field_id => default input value. */
    public static function blankInputs(Collection $fields): array
    {
        return $fields->mapWithKeys(fn (ProjectField $f) => [$f->id => $f->toInput(null)])->all();
    }

    /** Current values of a task as form inputs. */
    public static function inputsFor(Task $task, Collection $fields): array
    {
        $stored = $task->fieldValues()->pluck('value', 'field_id')->all();

        return $fields->mapWithKeys(fn (ProjectField $f) => [$f->id => $f->toInput($stored[$f->id] ?? null)])->all();
    }

    /** Save one field's value for a task (deletes the row when empty). */
    public static function save(Task $task, ProjectField $field, mixed $input, ?Project $project = null): void
    {
        $value = $field->normalize($input);

        if ($field->type === 'person' && $value !== null) {
            $project ??= $task->project;
            if (! $project->workspace->members()->whereKey((int) $value)->exists()) {
                $value = null;
            }
        }

        if ($value === null) {
            $task->fieldValues()->where('field_id', $field->id)->delete();

            return;
        }

        $task->fieldValues()->updateOrCreate(['field_id' => $field->id], ['value' => $value]);
    }

    public static function saveAll(Task $task, Collection $fields, array $inputs): void
    {
        foreach ($fields as $field) {
            if (array_key_exists($field->id, $inputs)) {
                static::save($task, $field, $inputs[$field->id]);
            }
        }
    }

    /** id => name for person fields. */
    public static function peopleMap(Project $project): array
    {
        return $project->workspace->members()->pluck('name', 'users.id')->all();
    }

    /** Choices for a person field: people who can open the project. */
    public static function assignableMap(Project $project): array
    {
        return $project->people()->orderBy('name')->pluck('name', 'users.id')->all();
    }
}
