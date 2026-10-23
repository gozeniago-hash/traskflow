<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Saves the details a person changed in the project dialog, and only those, so a dialog
 * opened before someone else's change can't put the old privacy, team or owner back.
 * Changes to who can open the project go in the audit log.
 */
class UpdateProjectDetails
{
    /** Details that decide who can open the project. */
    protected const ACCESS = ['privacy', 'team_id', 'client_id', 'portal_enabled', 'owner_id'];

    /**
     * @param array<string, mixed> $changes only the details that changed in the dialog
     *
     * @throws ValidationException when another project took the ID meanwhile
     */
    public function handle(Project $project, array $changes): void
    {
        $project->refresh();
        if (! (array_key_exists('client_id', $changes) ? $changes['client_id'] : $project->client_id)) {
            $changes['portal_enabled'] = false; // the portal needs a client
        }

        $before = $project->only(self::ACCESS);
        try {
            $project->update($changes);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['form.key' => 'Another project in this workspace already uses that ID.']);
        }

        $changed = array_keys(array_diff_assoc(array_map('strval', $project->only(self::ACCESS)), array_map('strval', $before)));
        if ($changed) {
            Audit::log('project.access_changed', 'Changed who can access “'.$project->name.'” ('.implode(', ', str_replace(['_id', '_'], ['', ' '], $changed)).')', $project, ['from' => $before, 'to' => $project->only(self::ACCESS)]);
        }
    }
}
