<?php

namespace App\Actions\Docs;

use App\Models\ProjectDoc;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

/**
 * Saves the editor's contents (autosave). Every save counts up the doc's version. An editor
 * that opened an older version is told someone saved meanwhile, even in the same second or
 * from the same person's other tab, instead of overwriting them. The doc row stays locked
 * while it is compared and saved.
 */
class SaveDoc
{
    /**
     * Pass $keepPrevious on the first save of an edit session, so the version it replaces stays
     * in the history. The result names who saved meanwhile when there is a conflict.
     *
     * @return array{saved: bool, conflict: ?string, version: int}
     */
    public function handle(ProjectDoc $doc, User $by, string $html, string $title, int $openedVersion, bool $force = false, bool $keepPrevious = false): array
    {
        return DB::transaction(function () use ($doc, $by, $html, $title, $openedVersion, $force, $keepPrevious): array {
            $current = ProjectDoc::whereKey($doc->id)->lockForUpdate()->firstOrFail();
            if (! $force && $current->version !== $openedVersion) {
                return ['saved' => false, 'conflict' => $current->editor->name ?? 'Someone', 'version' => $current->version];
            }

            $title = trim($title) === '' ? 'Untitled doc' : mb_substr(trim($title), 0, 255);
            $clean = HtmlSanitizer::clean($html);
            if ($clean === (string) $current->content && $title === $current->title) {
                return ['saved' => false, 'conflict' => null, 'version' => $current->version];
            }

            if ($keepPrevious) {
                app(KeepDocRevision::class)->handle($current);
            }
            $current->forceFill(['title' => $title, 'content' => $clean, 'updated_by' => $by->id, 'version' => $current->version + 1])->save();
            $doc->setRawAttributes($current->getAttributes(), true);

            return ['saved' => true, 'conflict' => null, 'version' => $current->version];
        });
    }
}
