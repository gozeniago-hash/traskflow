<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Support\Audit;

/** Archives a project, or restores it. Asking for the state it is already in changes nothing. */
class ArchiveProject
{
    /** Returns false when the project was already archived (or already active). */
    public function handle(Project $project, bool $archive): bool
    {
        $project->refresh();
        if (($project->archived_at !== null) === $archive) {
            return false;
        }

        $project->update(['archived_at' => $archive ? now() : null]);
        Audit::log($archive ? 'project.archived' : 'project.restored', ($archive ? 'Archived' : 'Restored').' project “'.$project->name.'”', $project);

        return true;
    }
}
