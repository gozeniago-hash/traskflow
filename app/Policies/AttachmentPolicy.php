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

    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->isClient()) {
            return $attachment->user_id === $user->id && $this->view($user, $attachment);
        }

        return $attachment->project->isVisibleTo($user);
    }
}
