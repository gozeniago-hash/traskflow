<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\ProjectField;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ProjectLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates a project owned by its creator, with starter sections for the chosen layout and
 * its custom fields. When two projects get the same ID at the same moment, an ID that was
 * suggested automatically is picked again; one the person typed gives a normal error.
 */
class CreateProject
{
    /** How often an automatic ID is picked again before giving up. */
    protected const KEY_ATTEMPTS = 3;

    /**
     * @param array{name: string, key?: string|null, description?: string|null, color: string, team_id?: int|string|null, client_id?: int|string|null, privacy: string, layout: string, portal_enabled?: bool, due_date?: string|null} $details
     * @param array<int, array{name: string, type: string, options?: string|null, preset?: string|null}> $fields
     *
     * @throws ValidationException when the ID the person typed was taken meanwhile
     */
    public function handle(Workspace $workspace, User $owner, array $details, array $fields = []): Project
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): Project => $this->create($workspace, $owner, $details, $fields));
            } catch (UniqueConstraintViolationException $e) {
                if (filled($details['key'] ?? null)) {
                    throw ValidationException::withMessages(['form.key' => 'Another project in this workspace already uses that ID.']);
                }
                if ($attempt >= self::KEY_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $details
     * @param array<int, array{name: string, type: string, options?: string|null, preset?: string|null}> $fields
     */
    protected function create(Workspace $workspace, User $owner, array $details, array $fields): Project
    {
        $project = $workspace->projects()->create([
            'name' => $details['name'],
            'key' => ($details['key'] ?? null) ?: null,
            'description' => ($details['description'] ?? null) ?: null,
            'color' => $details['color'],
            'team_id' => ($details['team_id'] ?? null) ?: null,
            'client_id' => ($details['client_id'] ?? null) ?: null,
            'owner_id' => $owner->id,
            'privacy' => $details['privacy'],
            'default_view' => $details['layout'] === 'board' ? 'board' : 'list',
            'portal_enabled' => ($details['client_id'] ?? null) ? (bool) ($details['portal_enabled'] ?? false) : false,
            'due_date' => ($details['due_date'] ?? null) ?: null,
        ]);
        $project->members()->attach($owner->id);

        $sections = match ($details['layout']) {
            'board' => ['To do', 'In progress', 'In review', 'Done'],
            'client' => ['Discovery', 'Design', 'Build', 'Client review', 'Launch'],
            default => ['To do'],
        };
        foreach ($sections as $i => $name) {
            ProjectLog::quietly(fn () => $project->sections()->create(['name' => $name, 'position' => $i + 1])); // starter sections aren't news
        }

        foreach (array_values($fields) as $i => $row) {
            $hasOptions = in_array($row['type'], ['select', 'multiselect'], true);
            $presetColors = ProjectField::PRESETS[$row['preset'] ?? '']['options'] ?? [];
            $labels = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) ($row['options'] ?? '')) ?: []))));

            // Keep preset colours where the label matches; other options get automatic colours.
            $options = [];
            foreach ($labels as $label) {
                if (isset($presetColors[$label])) {
                    $options[$label] = $presetColors[$label];
                } else {
                    $options[] = $label;
                }
            }

            $project->fields()->create([
                'name' => trim($row['name']),
                'type' => $hasOptions && ! $labels ? 'text' : $row['type'],
                'options' => $hasOptions && $labels ? ProjectField::makeOptions($options) : null,
                'position' => $i + 1,
            ]);
        }

        return $project;
    }
}
