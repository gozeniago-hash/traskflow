<?php

namespace App\Actions\Files;

use App\Models\Attachment;

/** Shows a project file to the client in the portal, or hides it again. Asking for its current state changes nothing. */
class ShareFileWithClient
{
    public function handle(Attachment $attachment, bool $shared): void
    {
        if ($attachment->client_visible !== $shared) {
            $attachment->update(['client_visible' => $shared]);
        }
    }
}
