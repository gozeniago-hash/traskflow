<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\ProjectResource;
use App\Models\StatusUpdate;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Recent changes across the projects a person can open.
 *
 * Team members see everything in projects they have access to (the same rule as the
 * Projects list). Client contacts only see changes to things shared with them in their
 * portal: shared tasks, public comments, shared docs, files and updates.
 */
class NotificationFeed
{
    /** Kinds shown in the filter, in order. */
    public const KINDS = [
        'task' => 'Tasks',
        'comment' => 'Comments',
        'file' => 'Files',
        'doc' => 'Docs',
        'update' => 'Status updates',
        'section' => 'Sections',
        'resource' => 'Key resources',
        'field' => 'Custom fields',
        'project' => 'Project settings',
        'member' => 'People',
    ];

    /** Task activity a client may see (on tasks shared with them). */
    protected const CLIENT_TASK_ACTIONS = ['created', 'completed', 'reopened', 'due_changed', 'renamed', 'approval_requested', 'approved', 'changes_requested', 'client_request', 'shared', 'deleted'];

    /** How far back the feed and the unread count look. */
    public const DAYS = 30;

    public function __construct(protected User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    protected function projectIds(): Collection
    {
        return Project::query()->visibleTo($this->user)->pluck('projects.id');
    }

    /** Activity in the person's projects, newest first. */
    public function query(?int $projectId = null, ?string $kind = null, bool $includeOwn = false): Builder
    {
        $ids = $this->projectIds();
        if ($projectId) {
            $ids = $ids->intersect([$projectId]);
        }

        return Activity::query()
            ->whereIn('project_id', $ids)
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->when($this->user->isClient(), fn ($q) => $q->whereIn('kind', ['task', 'comment', 'file', 'doc', 'update', 'resource', 'restore']))
            ->when($kind && array_key_exists($kind, self::KINDS), fn ($q) => $q->where('kind', $kind))
            ->when(! $includeOwn, fn ($q) => $q->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $this->user->id)))
            ->latest('created_at')->latest('id');
    }

    /**
     * The newest $limit entries this person may see.
     *
     * @return Collection<int, Activity>
     */
    public function latest(int $limit = 20, ?int $projectId = null, ?string $kind = null, bool $includeOwn = false): Collection
    {
        if (! $this->user->isClient()) {
            return $this->query($projectId, $kind, $includeOwn)->with(['user', 'project', 'task'])->limit($limit)->get();
        }

        // Clients: check each entry against what's shared right now; read ahead to fill the page.
        $out = collect();
        $this->query($projectId, $kind, $includeOwn)->with(['user', 'project', 'task'])
            ->chunk(200, function ($rows) use (&$out, $limit) {
                $out = $out->concat($rows->filter(fn (Activity $a) => $this->clientCanSee($a)));

                return $out->count() < $limit;
            });

        return $out->take($limit)->values();
    }

    public function readAt(): CarbonInterface
    {
        // First time: treat the last two days as new, so the bell isn't empty or flooded.
        return $this->user->notifications_read_at ?? now()->subDays(2);
    }

    public function unreadCount(int $cap = 99): int
    {
        $query = $this->query()->where('created_at', '>', $this->readAt());

        if (! $this->user->isClient()) {
            return min($cap, $query->limit($cap)->count());
        }

        return $query->with('task')->limit(500)->get()->filter(fn (Activity $a) => $this->clientCanSee($a))->take($cap)->count();
    }

    public function isUnread(Activity $a): bool
    {
        return $a->created_at->greaterThan($this->readAt());
    }

    public function markAllRead(): void
    {
        $this->user->forceFill(['notifications_read_at' => now()])->save();
    }

    // ---------------------------------------------------------------- client rule

    public function clientCanSee(Activity $a): bool
    {
        $m = $a->meta ?? [];
        $sharedTask = fn (?int $taskId) => $taskId && Task::whereKey($taskId)->where('client_visible', true)->exists();

        return match ($a->kind) {
            'task' => $a->action === 'deleted'
                ? (bool) ($m['client_visible'] ?? false) && empty($m['subtask'])
                : in_array($a->action, self::CLIENT_TASK_ACTIONS, true) && $a->task && $a->task->client_visible,
            'comment' => empty($m['internal']) && $sharedTask($a->task_id)
                && Comment::whereKey($a->subject_id)->where('is_internal', false)->exists(),
            'file' => $a->action === 'deleted'
                ? (bool) ($m['client_visible'] ?? false)
                : (bool) Attachment::find($a->subject_id)?->isVisibleToClient(),
            'doc' => $a->action === 'deleted'
                ? (bool) ($m['client_visible'] ?? false)
                : ProjectDoc::whereKey($a->subject_id)->where('client_visible', true)->exists() && $a->action !== 'unshared',
            'update' => $a->action === 'deleted'
                ? (bool) ($m['client_visible'] ?? false)
                : StatusUpdate::whereKey($a->subject_id)->where('client_visible', true)->exists(),
            'resource' => $a->action === 'deleted'
                ? (bool) ($m['client_visible'] ?? false)
                : ProjectResource::whereKey($a->subject_id)->where('client_visible', true)->exists(),
            default => false,
        };
    }

    // ------------------------------------------------------------- presentation

    /** "created BWR-12 Homepage hero" — the part after the person's name. */
    public function headline(Activity $a): string
    {
        $m = $a->meta ?? [];
        $title = $m['title'] ?? null;
        $q = fn (?string $s) => $s !== null && $s !== '' ? '“'.$s.'”' : '';
        $task = $a->task ? trim(($a->project?->key ? $a->project->key.'-'.$a->task->number.' ' : '').$a->task->title) : null;

        return trim(match ($a->kind) {
            'task' => match ($a->action) {
                'created' => 'created '.$task,
                'completed' => 'completed '.$task,
                'reopened' => 'reopened '.$task,
                'assigned' => isset($m['to']) ? 'assigned '.$task.' to '.$m['to'] : 'unassigned '.$task,
                'due_changed' => isset($m['to']) ? 'set the due date of '.$task.' to '.$m['to'] : 'removed the due date of '.$task,
                'start_changed' => isset($m['to']) ? 'set the start date of '.$task.' to '.$m['to'] : 'removed the start date of '.$task,
                'moved' => 'moved '.$task.' to '.($m['to'] ?? 'another section'),
                'renamed' => 'renamed '.$q($m['from'] ?? '').' to '.$task,
                'description_changed' => 'edited the description of '.$task,
                'priority_changed' => 'set the priority of '.$task.' to '.($m['to'] ?? '—'),
                'shared' => 'shared '.$task.' with the client',
                'unshared' => 'hid '.$task.' from the client',
                'approval_requested' => 'asked the client to approve '.$task,
                'approved' => 'approved '.$task,
                'changes_requested' => 'requested changes on '.$task,
                'client_request' => 'submitted a request: '.$task,
                'field_changed' => isset($m['to']) && $m['to'] !== '' ? 'set '.($m['field'] ?? 'a field').' on '.$task.' to '.$m['to'] : 'cleared '.($m['field'] ?? 'a field').' on '.$task,
                'deleted' => 'deleted '.(! empty($m['subtask']) ? 'subtask ' : '').trim(($m['ref'] ?? '').' '.$title),
                default => str_replace('_', ' ', $a->action).' '.$task,
            },
            'comment' => 'commented on '.$task.(! empty($m['excerpt']) ? ': “'.$m['excerpt'].'”' : ''),
            'file' => match ($a->action) {
                'created' => 'uploaded '.$q($title).($task ? ' to '.$task : ''),
                'deleted' => 'deleted the file '.$q($title),
                'shared' => 'shared the file '.$q($title).' with the client',
                'unshared' => 'hid the file '.$q($title).' from the client',
                default => $a->action.' the file '.$q($title),
            },
            'doc' => match ($a->action) {
                'created' => 'created the doc '.$q($title),
                'updated' => 'edited the doc '.$q($title),
                'deleted' => 'deleted the doc '.$q($title),
                'shared' => 'shared the doc '.$q($title).' with the client',
                'unshared' => 'hid the doc '.$q($title).' from the client',
                default => $a->action.' the doc '.$q($title),
            },
            'section' => match ($a->action) {
                'created' => 'added the section '.$q($title),
                'renamed' => 'renamed the section '.$q($m['from'] ?? '').' to '.$q($title),
                'deleted' => 'deleted the section '.$q($title),
                default => $a->action.' the section '.$q($title),
            },
            'resource' => match ($a->action) {
                'created' => 'added the key resource '.$q($title),
                'renamed' => 'renamed the key resource '.$q($m['from'] ?? '').' to '.$q($title),
                'deleted' => 'removed the key resource '.$q($title),
                default => $a->action.' the key resource '.$q($title),
            },
            'update' => $a->action === 'deleted' ? 'deleted the status update '.$q($title) : 'posted a status update: '.$q($title),
            'field' => ($a->action === 'deleted' ? 'deleted' : 'added').' the custom field '.$q($title),
            'member' => ($a->action === 'removed' ? 'removed '.($title ?? 'someone').' from' : 'added '.($title ?? 'someone').' to').' the project',
            'restore' => 'restored the deleted '.($title ?? 'item'),
            'project' => match ($a->action) {
                'created' => 'created the project',
                'archived' => 'archived the project',
                'unarchived' => 'restored the project from the archive',
                'status_changed' => 'set the project status to '.($m['to'] ?? '—'),
                default => 'changed the project '.implode(', ', $m['fields'] ?? ['settings']),
            },
            default => str_replace('_', ' ', $a->action),
        });
    }

    /** Small icon name per kind (see components/icon). */
    public function icon(Activity $a): string
    {
        if ($a->action === 'deleted' || $a->action === 'removed') {
            return 'trash';
        }

        return match ($a->kind) {
            'task' => in_array($a->action, ['completed', 'approved'], true) ? 'check-circle' : 'list',
            'comment' => 'chat',
            'file' => 'paperclip',
            'doc' => 'doc',
            'update' => 'flag',
            'section' => 'list',
            'resource' => 'link',
            'field' => 'cog',
            'member' => 'user',
            'restore' => 'undo',
            default => 'folder',
        };
    }

    /** Where clicking the notification goes, or null if the thing is gone. */
    public function link(Activity $a): ?string
    {
        if (! $a->project) {
            return null;
        }
        $p = $a->project;

        if ($this->user->isClient()) {
            $base = route('portal.projects.show', $p);

            return match (true) {
                $a->task_id !== null && $a->task !== null && $a->kind !== 'file' => $base.'?'.http_build_query(['tab' => 'tasks', 'task' => $a->task_id]),
                $a->kind === 'doc' && $a->action !== 'deleted' => $base.'?'.http_build_query(['tab' => 'docs', 'doc' => $a->subject_id]),
                $a->kind === 'file' && $a->action !== 'deleted' => $base.'?'.http_build_query($a->task_id ? ['tab' => 'tasks', 'task' => $a->task_id] : ['tab' => 'files']),
                default => $base,
            };
        }

        return match (true) {
            $a->task_id !== null && $a->task !== null => route('projects.show', [$p, 'list']).'?task='.$a->task_id,
            $a->kind === 'doc' && $a->action !== 'deleted' => route('projects.show', [$p, 'docs']).'?doc='.$a->subject_id,
            $a->kind === 'file' => route('projects.show', [$p, 'files']),
            $a->kind === 'section' => route('projects.show', [$p, 'list']),
            default => route('projects.show', [$p, 'overview']),
        };
    }
}
