<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        if (! $attachment->project->isVisibleTo($user)) {
            return false;
        }

        return $user->isClient() ? $attachment->isVisibleToClient() : true;
    }

    /** Team members edit the details of files they uploaded; whoever manages the project, and its owner, edit any. Clients never do. */
    public function update(User $user, Attachment $attachment): bool
    {
        return ! $user->isClient() && $this->delete($user, $attachment);
    }

    /** People delete the files they uploaded; whoever manages the project, and its owner, delete any. */
    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->isClient()) {
            return $attachment->user_id === $user->id && $this->view($user, $attachment);
        }

        return $attachment->project->isVisibleTo($user)
            && ($attachment->user_id === $user->id || $user->can('manageContent', $attachment->project));
    }
}
