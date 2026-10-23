<?php

namespace App\Livewire\Projects;

use App\Actions\Docs\CreateDoc;
use App\Actions\Docs\RestoreDocRevision;
use App\Actions\Docs\SaveDoc;
use App\Actions\Docs\ShareDocWithClient;
use App\Actions\Files\StoreUploads;
use App\Actions\Ordering\MoveItem;
use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Livewire\Concerns\LiveUpdates;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Support\DocTemplates;
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

    /** The doc's version when this edit session started, or after its own last save (conflict detection). */
    #[Locked]
    public ?int $editingVersion = null;

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

    public function create(CreateDoc $createDoc, string $template = 'blank'): void
    {
        $this->authorize('contribute', $this->project);
        $template = array_key_exists($template, DocTemplates::LIST) ? $template : 'blank';

        $doc = $createDoc->handle($this->project, $this->me(), DocTemplates::title($template), DocTemplates::content($template, $this->project->name));

        $this->showTemplates = false;
        $this->select($doc->id);
        $this->startEdit();
    }

    public function duplicate(CreateDoc $createDoc): void
    {
        $doc = $this->editableDoc();
        $copy = $createDoc->handle($this->project, $this->me(), $doc->title.' (copy)', $doc->content);
        $this->select($copy->id);
        $this->toast('Doc duplicated.');
    }

    // -------------------------------------------------------------- editing

    public function startEdit(): void
    {
        $doc = $this->editableDoc();
        $this->editing = true;
        $this->title = $doc->title;
        $this->editingVersion = $doc->version;
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
    public function autosave(string $html, string $title, SaveDoc $saveDoc, bool $force = false): array
    {
        $doc = $this->editableDoc();

        $result = $saveDoc->handle($doc, $this->me(), $html, $title, (int) $this->editingVersion, $force, keepPrevious: ! $this->snapshotTaken);
        if ($result['conflict'] !== null) {
            // Someone else saved since this session started: don't silently overwrite them.
            return ['ok' => false, 'conflict' => true, 'by' => $result['conflict']];
        }

        if ($result['saved']) {
            $this->snapshotTaken = true;
            $this->editingVersion = $result['version'];
            $this->title = $doc->title;
        }

        return ['ok' => true, 'savedAt' => $doc->updated_at?->format('g:i A')];
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

    // ----------------------------------------------------- uploads in editor

    /** Called from the editor after $wire.upload('docUpload', file). */
    public function storeDocUpload(StoreUploads $storeUploads): array
    {
        $doc = $this->editableDoc();
        $this->validate(['docUpload' => Attachment::rules(required: true)], [], ['docUpload' => 'file']);

        $attachment = $storeUploads->handle([$this->docUpload], $this->project, $this->me(), null, false, null, $doc)->first();
        $this->docUpload = null;

        return [
            'url' => $attachment->isImage() ? $attachment->viewUrl() : $attachment->downloadUrl(),
            'href' => $attachment->isImage() ? $attachment->viewUrl() : $attachment->downloadUrl(),
            'isImage' => $attachment->isImage(),
        ];
    }

    // ------------------------------------------------------------- settings

    public function share(bool $shared, ShareDocWithClient $shareDocWithClient): void
    {
        $this->authorize('shareWithClient', $this->project);
        $doc = $this->editableDoc();
        if ($shared && ! $this->project->portal_enabled) {
            $this->toast('Turn on the client portal for this project first (project details).', 'error');

            return;
        }
        $shareDocWithClient->handle($doc, $shared);
        $this->toast($shared ? 'Doc shared with the client.' : 'Doc is now internal only.');
    }

    public function move(int $direction, MoveItem $moveItem): void
    {
        $doc = $this->editableDoc();
        $moveItem->handle($this->project->docs(), $doc->id, $direction);
    }

    public function delete(): void
    {
        $doc = $this->editableDoc();
        abort_unless($this->canDelete($doc), 403, 'Only the doc author, the project owner or a manager can delete it.');
        $doc->delete();
        $this->docId = $this->project->docs()->value('id');
        $this->title = (string) $this->doc()?->title;
        $this->editing = false;
        $this->toast('Doc deleted.');
    }

    /** The doc's author deletes it, and so does whoever manages the project, and its owner. */
    protected function canDelete(ProjectDoc $doc): bool
    {
        return (int) $doc->created_by === (int) $this->me()->id || $this->me()->can('manageContent', $this->project);
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

    public function restoreRevision(int $id, RestoreDocRevision $restoreDocRevision): void
    {
        $doc = $this->editableDoc();
        $revision = $doc->revisions()->findOrFail($id);

        $restoreDocRevision->handle($doc, $revision, $this->me());

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
            'canManage' => $this->me()->can('shareWithClient', $this->project),
            'canDelete' => $doc && $this->canDelete($doc),
            'revisions' => $doc && $this->showHistory ? $doc->revisions()->with('user')->get() : collect(),
            'preview' => $doc && $this->previewRevisionId ? $doc->revisions()->with('user')->find($this->previewRevisionId) : null,
            'templates' => DocTemplates::LIST,
        ]);
    }
}
