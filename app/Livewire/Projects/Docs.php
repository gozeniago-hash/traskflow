<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Support\DocTemplates;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Project docs: requirements, specs, notes. Rich text (Trix), autosave,
 * version history, optional sharing with the client portal.
 */
class Docs extends Component
{
    use InteractsWithWorkspace, LiveUpdates, WithFileUploads;

    public Project $project;

    #[Url(as: 'doc', except: null)]
    public ?int $docId = null;

    public bool $editing = false;

    public string $title = '';

    /** updated_at of the doc when this edit session started (conflict detection). */
    public ?string $editingSince = null;

    public bool $snapshotTaken = false;

    /** Bumped whenever the editor must be rebuilt with fresh server content. */
    public int $editorVersion = 0;

    public bool $showTemplates = false;

    public bool $showHistory = false;

    #[Locked] // set only by previewRevision(), which checks it belongs to this doc
    public ?int $previewRevisionId = null;

    /** Image/file dropped into the editor (uploaded via $wire.upload). */
    public $docUpload;

    protected function getListeners(): array
    {
        return $this->projectListeners($this->project->id);
    }

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;

        if (! $this->docId || ! $project->docs()->whereKey($this->docId)->exists()) {
            $this->docId = $project->docs()->value('id');
        }
        $this->title = (string) $this->doc()?->title;
    }

    protected function doc(): ?ProjectDoc
    {
        return $this->docId ? $this->project->docs()->find($this->docId) : null;
    }

    protected function editableDoc(): ProjectDoc
    {
        $this->authorize('contribute', $this->project);

        return $this->project->docs()->findOrFail($this->docId);
    }

    // ------------------------------------------------------------ selection

    public function select(int $id): void
    {
        $doc = $this->project->docs()->findOrFail($id);
        $this->docId = $doc->id;
        $this->title = $doc->title;
        $this->editing = false;
        $this->showHistory = false;
        $this->previewRevisionId = null;
    }

    // ------------------------------------------------------------- creating

    public function create(string $template = 'blank'): void
    {
        $this->authorize('contribute', $this->project);
        $template = array_key_exists($template, DocTemplates::LIST) ? $template : 'blank';

        $doc = $this->project->docs()->create([
            'title' => DocTemplates::title($template),
            'content' => DocTemplates::content($template, $this->project->name),
            'position' => (int) $this->project->docs()->max('position') + 1,
            'created_by' => $this->me()->id,
            'updated_by' => $this->me()->id,
        ]);

        $this->showTemplates = false;
        $this->select($doc->id);
        $this->startEdit();
    }

    public function duplicate(): void
    {
        $doc = $this->editableDoc();
        $copy = $this->project->docs()->create([
            'title' => mb_substr($doc->title.' (copy)', 0, 255),
            'content' => $doc->content,
            'position' => (int) $this->project->docs()->max('position') + 1,
            'created_by' => $this->me()->id,
            'updated_by' => $this->me()->id,
        ]);
        $this->select($copy->id);
        $this->toast('Doc duplicated.');
    }

    // -------------------------------------------------------------- editing

    public function startEdit(): void
    {
        $doc = $this->editableDoc();
        $this->editing = true;
        $this->title = $doc->title;
        $this->editingSince = $doc->updated_at?->toIso8601String();
        $this->snapshotTaken = false;
        $this->showHistory = false;
        $this->previewRevisionId = null;
        $this->editorVersion++;
    }

    /**
     * Saves the editor contents. Returns a small status object to the browser.
     * Renderless so the editor isn't re-rendered while someone is typing.
     */
    #[Renderless]
    public function autosave(string $html, string $title, bool $force = false): array
    {
        $doc = $this->editableDoc();

        // Someone else saved since this session started: don't silently overwrite them.
        if (! $force && $this->editingSince && (int) $doc->updated_by !== (int) $this->me()->id
            && $doc->updated_at && $doc->updated_at->gt(Carbon::parse($this->editingSince))) {
            return ['ok' => false, 'conflict' => true, 'by' => $doc->editor?->name ?? 'Someone'];
        }

        $title = trim($title) === '' ? 'Untitled doc' : mb_substr(trim($title), 0, 255);
        $clean = HtmlSanitizer::clean($html);

        if ($clean === (string) $doc->content && $title === $doc->title) {
            return ['ok' => true, 'savedAt' => $doc->updated_at?->format('g:i A')];
        }

        $this->snapshotBeforeChange($doc);

        $doc->forceFill(['title' => $title, 'content' => $clean, 'updated_by' => $this->me()->id])->save();
        $this->editingSince = $doc->updated_at->toIso8601String();
        $this->title = $title;

        return ['ok' => true, 'savedAt' => $doc->updated_at->format('g:i A')];
    }

    /** Leaves edit mode. The browser saves first, so this only switches the view. */
    public function finishEdit(): void
    {
        $this->editableDoc();
        $this->editing = false;
    }

    /** Throw away local changes and load the latest version. */
    public function reloadLatest(): void
    {
        $this->editing = false;
        $this->startEdit();
    }

    /** Keep one revision of the previous state per edit session, so history can restore it. */
    protected function snapshotBeforeChange(ProjectDoc $doc): void
    {
        if ($this->snapshotTaken) {
            return;
        }
        $this->snapshotTaken = true;

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

        // Keep history bounded.
        $keep = $doc->revisions()->limit(50)->pluck('id');
        $doc->revisions()->whereNotIn('id', $keep)->delete();
    }

    // ----------------------------------------------------- uploads in editor

    /** Called from the editor after $wire.upload('docUpload', file). */
    public function storeDocUpload(): array
    {
        $doc = $this->editableDoc();
        $this->validate(['docUpload' => Attachment::rules(required: true)], [], ['docUpload' => 'file']);

        $attachment = Attachment::storeUpload($this->docUpload, $this->project, $this->me(), null, false, null, $doc);
        $this->docUpload = null;

        return [
            'url' => $attachment->isImage() ? $attachment->viewUrl() : $attachment->downloadUrl(),
            'href' => $attachment->isImage() ? $attachment->viewUrl() : $attachment->downloadUrl(),
            'isImage' => $attachment->isImage(),
        ];
    }

    // ------------------------------------------------------------- settings

    public function toggleShare(): void
    {
        $this->authorize('update', $this->project); // what clients see is a project setting
        $doc = $this->editableDoc();
        if (! $doc->client_visible && ! $this->project->portal_enabled) {
            $this->toast('Turn on the client portal for this project first (project details).', 'error');

            return;
        }
        $doc->forceFill(['client_visible' => ! $doc->client_visible])->saveQuietly();
        $this->toast($doc->client_visible ? 'Doc shared with the client.' : 'Doc is now internal only.');
    }

    public function move(int $direction): void
    {
        $doc = $this->editableDoc();
        $docs = $this->project->docs()->get()->values();
        $i = $docs->search(fn ($d) => $d->id === $doc->id);
        $j = $i + ($direction < 0 ? -1 : 1);
        if ($i === false || ! isset($docs[$j])) {
            return;
        }
        $ordered = $docs->all();
        [$ordered[$i], $ordered[$j]] = [$ordered[$j], $ordered[$i]];
        foreach ($ordered as $pos => $d) {
            $d->forceFill(['position' => $pos + 1])->saveQuietly();
        }
    }

    public function delete(): void
    {
        $doc = $this->editableDoc();
        abort_unless((int) $doc->created_by === (int) $this->me()->id || $this->me()->can('update', $this->project) || (int) $this->project->owner_id === (int) $this->me()->id, 403, 'Only the doc author, the project owner or an admin can delete it.');
        $doc->delete();
        $this->docId = $this->project->docs()->value('id');
        $this->title = (string) $this->doc()?->title;
        $this->editing = false;
        $this->toast('Doc deleted.');
    }

    // -------------------------------------------------------------- history

    public function toggleHistory(): void
    {
        $this->showHistory = ! $this->showHistory;
        $this->previewRevisionId = null;
    }

    public function previewRevision(int $id): void
    {
        $this->doc()?->revisions()->findOrFail($id);
        $this->previewRevisionId = $id;
    }

    public function restoreRevision(int $id): void
    {
        $doc = $this->editableDoc();
        $revision = $doc->revisions()->findOrFail($id);

        $this->snapshotTaken = false;
        $this->snapshotBeforeChange($doc); // current version stays restorable
        $doc->forceFill(['title' => $revision->title, 'content' => $revision->content, 'updated_by' => $this->me()->id])->save();

        $this->title = $doc->title;
        $this->previewRevisionId = null;
        $this->showHistory = false;
        $this->editing = false;
        $this->editorVersion++;
        $this->toast('Restored the version from '.$revision->created_at->format('M j, g:i A').'.');
    }

    public function render()
    {
        $doc = $this->doc()?->load(['creator', 'editor']);

        return view('livewire.projects.docs', [
            'docs' => $this->project->docs()->with('editor')->get(['id', 'title', 'client_visible', 'updated_at', 'updated_by', 'position']),
            'doc' => $doc,
            'canEdit' => $this->me()->can('contribute', $this->project),
            'canManage' => $this->me()->can('update', $this->project),
            'canDelete' => $doc && ((int) $doc->created_by === (int) $this->me()->id || $this->me()->can('update', $this->project) || (int) $this->project->owner_id === (int) $this->me()->id),
            'revisions' => $doc && $this->showHistory ? $doc->revisions()->with('user')->get() : collect(),
            'preview' => $doc && $this->previewRevisionId ? $doc->revisions()->with('user')->find($this->previewRevisionId) : null,
            'templates' => DocTemplates::LIST,
        ]);
    }
}
