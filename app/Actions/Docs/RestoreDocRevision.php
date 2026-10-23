<?php

namespace App\Actions\Docs;

use App\Models\DocRevision;
use App\Models\ProjectDoc;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Puts an earlier version of a doc back. The version it replaces stays in the history. */
class RestoreDocRevision
{
    public function __construct(protected KeepDocRevision $keepDocRevision) {}

    public function handle(ProjectDoc $doc, DocRevision $revision, User $by): void
    {
        DB::transaction(function () use ($doc, $revision, $by): void {
            $current = ProjectDoc::whereKey($doc->id)->lockForUpdate()->firstOrFail();
            $this->keepDocRevision->handle($current);
            $current->forceFill(['title' => $revision->title, 'content' => $revision->content, 'updated_by' => $by->id, 'version' => $current->version + 1])->save();
            $doc->setRawAttributes($current->getAttributes(), true);
        });
    }
}
