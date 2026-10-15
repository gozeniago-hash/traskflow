<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\ProjectField;
use App\Models\ProjectResource;
use App\Models\Section;
use App\Models\StatusUpdate;
use App\Models\Task;
use Illuminate\Support\Str;

/**
 * Records what people add, change and delete in a project, for notifications.
 *
 * Task changes made in the task panel are recorded where they happen (Activity::record);
 * everything else is picked up here from model events. Only changes made by a signed-in
 * person are recorded, so seeders, webhooks and background jobs don't create noise.
 */
class ProjectLog
{
    /** While true nothing is recorded (e.g. the files removed along with a deleted task). */
    public static bool $quiet = false;

    public static function quietly(callable $fn): mixed
    {
        $was = static::$quiet;
        static::$quiet = true;
        try {
            return $fn();
        } finally {
            static::$quiet = $was;
        }
    }

    /** Repeated edits by the same person within this many minutes become one entry. */
    protected const COALESCE_MINUTES = 30;

    public static function register(): void
    {
        // ---- tasks: deletions, and edits the panel doesn't log itself
        Task::updated(function (Task $task) {
            if ($task->wasChanged('title')) {
                static::taskEvent($task, 'renamed', ['from' => $task->getOriginal('title'), 'to' => $task->title]);
            }
            if ($task->wasChanged('description')) {
                static::taskEvent($task, 'description_changed', [], coalesce: true);
            }
            if ($task->wasChanged('priority')) {
                static::taskEvent($task, 'priority_changed', ['to' => Task::PRIORITIES[$task->priority] ?? $task->priority]);
            }
            if ($task->wasChanged('start_date')) {
                static::taskEvent($task, 'start_changed', ['to' => $task->start_date?->format('M j')]);
            }
        });
        Task::deleted(function (Task $task) {
            // task_id stays empty: the task's own activity rows are removed with it.
            Activity::log($task->project_id, 'task', 'deleted', [
                'title' => $task->title,
                'ref' => $task->project?->key ? $task->project->key.'-'.$task->number : null,
                'subtask' => (bool) $task->parent_id,
                'client_visible' => (bool) $task->client_visible,
            ], $task->id);
        });

        // ---- comments
        Comment::created(function (Comment $comment) {
            $task = Task::find($comment->task_id);
            if ($task) {
                Activity::log($task->project_id, 'comment', 'created', [
                    'excerpt' => Str::limit(trim(strip_tags($comment->body)), 140),
                    'internal' => (bool) $comment->is_internal,
                ], $comment->id, $task->id);
            }
        });

        // ---- sections
        Section::created(fn (Section $section) => Activity::log($section->project_id, 'section', 'created', ['title' => $section->name], $section->id));
        Section::updated(function (Section $section) {
            if ($section->wasChanged('name')) {
                Activity::log($section->project_id, 'section', 'renamed', ['from' => $section->getOriginal('name'), 'title' => $section->name], $section->id);
            }
        });
        Section::deleted(fn (Section $section) => Activity::log($section->project_id, 'section', 'deleted', ['title' => $section->name], $section->id));

        // ---- docs
        ProjectDoc::created(fn (ProjectDoc $doc) => Activity::log($doc->project_id, 'doc', 'created', ['title' => $doc->title, 'client_visible' => (bool) $doc->client_visible], $doc->id));
        ProjectDoc::updated(function (ProjectDoc $doc) {
            if ($doc->wasChanged('client_visible')) {
                Activity::log($doc->project_id, 'doc', $doc->client_visible ? 'shared' : 'unshared', ['title' => $doc->title], $doc->id);
            }
            if ($doc->wasChanged('title') || $doc->wasChanged('content')) {
                static::coalesced($doc->project_id, 'doc', 'updated', $doc->id, null, ['title' => $doc->title]);
            }
        });
        ProjectDoc::deleted(fn (ProjectDoc $doc) => Activity::log($doc->project_id, 'doc', 'deleted', ['title' => $doc->title, 'client_visible' => (bool) $doc->client_visible], $doc->id));

        // ---- files (images pasted into docs and files on comments are covered by those events)
        Attachment::created(function (Attachment $file) {
            if (! $file->doc_id && ! $file->comment_id) {
                Activity::log($file->project_id, 'file', 'created', ['title' => $file->original_name], $file->id, $file->task_id);
            }
        });
        Attachment::updated(function (Attachment $file) {
            if ($file->wasChanged('client_visible') && ! $file->task_id && ! $file->doc_id) {
                Activity::log($file->project_id, 'file', $file->client_visible ? 'shared' : 'unshared', ['title' => $file->original_name], $file->id);
            }
        });
        Attachment::deleted(function (Attachment $file) {
            if (! $file->doc_id && ! $file->comment_id && Project::whereKey($file->project_id)->exists()) {
                $taskId = $file->task_id && Task::whereKey($file->task_id)->exists() ? $file->task_id : null;
                Activity::log($file->project_id, 'file', 'deleted', [
                    'title' => $file->original_name,
                    'client_visible' => $file->isVisibleToClient(),
                ], $file->id, $taskId);
            }
        });

        // ---- key resources
        ProjectResource::created(fn (ProjectResource $r) => Activity::log($r->project_id, 'resource', 'created', ['title' => $r->title], $r->id));
        ProjectResource::updated(function (ProjectResource $r) {
            if ($r->wasChanged('title')) {
                Activity::log($r->project_id, 'resource', 'renamed', ['from' => $r->getOriginal('title'), 'title' => $r->title], $r->id);
            }
        });
        ProjectResource::deleted(fn (ProjectResource $r) => Activity::log($r->project_id, 'resource', 'deleted', ['title' => $r->title, 'client_visible' => (bool) $r->client_visible], $r->id));

        // ---- status updates
        StatusUpdate::created(fn (StatusUpdate $u) => Activity::log($u->project_id, 'update', 'created', ['title' => $u->title, 'status' => $u->status], $u->id));
        StatusUpdate::deleted(fn (StatusUpdate $u) => Activity::log($u->project_id, 'update', 'deleted', ['title' => $u->title, 'client_visible' => (bool) $u->client_visible], $u->id));

        // ---- custom fields
        ProjectField::created(fn (ProjectField $f) => Activity::log($f->project_id, 'field', 'created', ['title' => $f->name], $f->id));
        ProjectField::deleted(fn (ProjectField $f) => Activity::log($f->project_id, 'field', 'deleted', ['title' => $f->name], $f->id));

        // ---- the project itself
        Project::created(fn (Project $p) => Activity::log($p->id, 'project', 'created', ['title' => $p->name], $p->id));
        Project::updated(function (Project $p) {
            $labels = ['name' => 'name', 'status' => 'status', 'start_date' => 'start date', 'due_date' => 'due date', 'privacy' => 'who can see it',
                'owner_id' => 'project lead', 'team_id' => 'team', 'client_id' => 'client', 'portal_enabled' => 'client portal', 'description' => 'description'];
            $changed = array_values(array_intersect_key($labels, $p->getChanges()));
            if ($p->wasChanged('archived_at')) {
                Activity::log($p->id, 'project', $p->archived_at ? 'archived' : 'unarchived', ['title' => $p->name], $p->id);
            }
            if ($p->wasChanged('status')) {
                Activity::log($p->id, 'project', 'status_changed', ['title' => $p->name, 'to' => $p->statusLabel()], $p->id);
                $changed = array_values(array_diff($changed, ['status']));
            }
            if ($changed) {
                static::coalesced($p->id, 'project', 'updated', $p->id, null, ['title' => $p->name, 'fields' => $changed], mergeFields: true);
            }
        });
    }

    /** People added to or removed from a project (pivot changes don't fire model events). */
    public static function member(Project $project, string $action, ?string $name): void
    {
        Activity::log($project->id, 'member', $action, ['title' => $name]);
    }

    /** Something deleted earlier came back via Undo. */
    public static function restored(int $projectId, string $label): void
    {
        Activity::log($projectId, 'restore', 'restored', ['title' => $label]);
    }

    protected static function taskEvent(Task $task, string $action, array $meta = [], bool $coalesce = false): void
    {
        if (! auth()->check()) {
            return;
        }
        if ($coalesce) {
            static::coalesced($task->project_id, 'task', $action, null, $task->id, $meta);

            return;
        }
        Activity::record($task, auth()->user(), $action, $meta);
    }

    /** One entry for a burst of edits (autosave, typing) by the same person to the same thing. */
    protected static function coalesced(int $projectId, string $kind, string $action, ?int $subjectId, ?int $taskId, array $meta, bool $mergeFields = false): void
    {
        $user = auth()->user();
        if (! $user || static::$quiet) {
            return;
        }

        $recent = Activity::where('project_id', $projectId)->where('kind', $kind)->where('action', $action)
            ->where('user_id', $user->id)
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->when($taskId, fn ($q) => $q->where('task_id', $taskId))
            ->where('created_at', '>=', now()->subMinutes(self::COALESCE_MINUTES))
            ->latest('id')->first();

        if ($recent) {
            if ($mergeFields) {
                $meta['fields'] = array_values(array_unique(array_merge($recent->meta['fields'] ?? [], $meta['fields'] ?? [])));
            }
            $recent->forceFill(['meta' => $meta ?: null, 'created_at' => now()])->save();

            return;
        }

        Activity::create(['project_id' => $projectId, 'task_id' => $taskId, 'user_id' => $user->id, 'kind' => $kind, 'subject_id' => $subjectId, 'action' => $action, 'meta' => $meta ?: null]);
    }
}
