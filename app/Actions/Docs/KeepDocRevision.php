<?php

namespace App\Actions\Docs;

use App\Models\ProjectDoc;

/** Keeps the doc's current version in its history before it is replaced (the 50 newest are kept). */
class KeepDocRevision
{
    public function handle(ProjectDoc $doc): void
    {
        if (trim((string) $doc->content) === '' && $doc->revisions()->doesntExist()) {
            return; // brand-new empty doc, nothing worth keeping
        }

        $revision = $doc->revisions()->create([
            'user_id' => $doc->updated_by,
            'title' => $doc->title,
            'content' => $doc->content,
        ]);
        // Date the revision when that version was actually written.
        $revision->forceFill(['created_at' => $doc->updated_at, 'updated_at' => $doc->updated_at])->save();

        $keep = $doc->revisions()->limit(50)->pluck('id');
        $doc->revisions()->whereNotIn('id', $keep)->delete();
    }
}
