<?php

namespace App\Actions\Resources;

use App\Models\ProjectResource;
use Illuminate\Support\Facades\DB;

/**
 * Shows a key resource to the client in the portal, or hides it again. A shared file must
 * be downloadable from the portal, so the file follows the resource.
 */
class ShareResourceWithClient
{
    public function handle(ProjectResource $resource, bool $shared): void
    {
        DB::transaction(function () use ($resource, $shared): void {
            $resource->update(['client_visible' => $shared]);
            $resource->attachment?->update(['client_visible' => $shared]);
        });
    }
}
