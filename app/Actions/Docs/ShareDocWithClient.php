<?php

namespace App\Actions\Docs;

use App\Models\ProjectDoc;

/** Shows a doc to the client in the portal, or hides it again. Asking for its current state changes nothing. */
class ShareDocWithClient
{
    public function handle(ProjectDoc $doc, bool $shared): void
    {
        if ($doc->client_visible !== $shared) {
            $doc->update(['client_visible' => $shared]);
        }
    }
}
