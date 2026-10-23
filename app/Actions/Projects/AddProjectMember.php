<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use App\Support\Audit;
use App\Support\ProjectLog;

/** Adds someone from the workspace to a project. Adding someone already in it changes and logs nothing. */
class AddProjectMember
{
    /** Returns false when they were already a member. */
    public function handle(Project $project, User $user): bool
    {
        if (! $project->members()->syncWithoutDetaching([$user->id])['attached']) {
            return false;
        }

        ProjectLog::member($project, 'added', $user->name);
        Audit::log('project.member_added', 'Added '.$user->name.' to project “'.$project->name.'”', $project, ['user_id' => $user->id]);

        return true;
    }
}
