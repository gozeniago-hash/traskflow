<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Makes deletes undoable for a few minutes.
 *
 * Before deleting, a caller captures the rows (and stored files) that will disappear.
 * The delete then happens for real, so nothing half-deleted ever shows up in lists or
 * reports. Undo puts the same rows back with the same ids; after the window passes, the
 * kept files are removed from storage.
 */
class Undo
{
    public const MINUTES = 10;

    /** Insert order matters: parents before the rows that point at them. */
    public const ORDER = ['sections', 'tasks', 'comments', 'attachments', 'project_resources', 'activities', 'time_entries', 'task_field_values', 'pull_request_task'];

    /**
     * @param array<string, array<int, array>> $rows table => rows
     * @param array<int, array{disk:string,path:string}> $files stored files to keep until the window ends
     * @param array<int, array{0:int,1:int,2:int}> $moves [taskId, sectionId, position] to put back after restoring
     */
    public static function capture(User $user, ?int $projectId, string $label, array $rows, array $files = [], array $moves = []): string
    {
        static::purgeExpired();

        $id = (string) Str::uuid();
        DB::table('undo_snapshots')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'project_id' => $projectId,
            'label' => Str::limit($label, 250),
            'payload' => json_encode(['rows' => $rows, 'moves' => $moves]),
            'files' => $files ? json_encode($files) : null,
            'expires_at' => now()->addMinutes(self::MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** Rows for a set of tasks, their subtasks and everything hanging off them. */
    public static function taskRows(array $taskIds): array
    {
        $ids = collect($taskIds);
        $children = DB::table('tasks')->whereIn('parent_id', $ids)->pluck('id');
        $all = $ids->concat($children)->unique()->values();

        $rows = [
            // parents first, so subtasks can point at them
            'tasks' => DB::table('tasks')->whereIn('id', $all)->orderByRaw('parent_id is not null')->orderBy('id')->get(),
            'comments' => DB::table('comments')->whereIn('task_id', $all)->orderBy('id')->get(),
            'attachments' => DB::table('attachments')->whereIn('task_id', $all)->orderBy('id')->get(),
            'activities' => DB::table('activities')->whereIn('task_id', $all)->orderBy('id')->get(),
            'time_entries' => DB::table('time_entries')->whereIn('task_id', $all)->orderBy('id')->get(),
            'task_field_values' => DB::table('task_field_values')->whereIn('task_id', $all)->orderBy('id')->get(),
            'pull_request_task' => DB::table('pull_request_task')->whereIn('task_id', $all)->get(),
        ];

        return array_map(fn ($c) => $c->map(fn ($r) => (array) $r)->all(), $rows);
    }

    /** Rows for attachments, plus key resources that point at them. */
    public static function attachmentRows(array $attachmentIds): array
    {
        return [
            'attachments' => DB::table('attachments')->whereIn('id', $attachmentIds)->get()->map(fn ($r) => (array) $r)->all(),
            'project_resources' => DB::table('project_resources')->whereIn('attachment_id', $attachmentIds)->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    public static function filesOf(array $attachmentRows): array
    {
        return collect($attachmentRows)->map(fn ($a) => ['disk' => $a['disk'], 'path' => $a['path']])->values()->all();
    }

    /** Delete inside $fn without removing stored files (they're kept for the undo window). */
    public static function keepingFiles(callable $fn): mixed
    {
        Attachment::$keepFiles = true;
        try {
            return $fn();
        } finally {
            Attachment::$keepFiles = false;
        }
    }

    /** Point restored rows at things that may have gone in the meantime; skip rows that can't be restored. */
    public static function repair(string $table, array $row, ?int $projectId, string $deletedAt): ?array
    {
        $userColumns = ['user_id', 'creator_id', 'assignee_id', 'completed_by', 'approval_by'];
        foreach ($userColumns as $col) {
            if (! empty($row[$col]) && ! DB::table('users')->where('id', $row[$col])->exists()) {
                $row[$col] = null;
            }
        }

        if ($table === 'tasks' && ! empty($row['section_id']) && ! DB::table('sections')->where('id', $row['section_id'])->exists()) {
            $row['section_id'] = DB::table('sections')->where('project_id', $row['project_id'])->orderBy('position')->value('id');
        }
        // A timer that was running when its task was deleted comes back stopped at that moment,
        // so the restore never leaves someone with two running timers.
        if ($table === 'time_entries' && empty($row['ended_at'])) {
            $row['ended_at'] = $deletedAt;
            $row['duration_seconds'] = max(0, strtotime($deletedAt) - strtotime((string) $row['started_at']));
        }
        if ($table === 'pull_request_task' && ! DB::table('pull_requests')->where('id', $row['pull_request_id'])->exists()) {
            return null;
        }
        if ($table === 'task_field_values' && ! DB::table('project_fields')->where('id', $row['field_id'])->exists()) {
            return null;
        }
        if ($table === 'attachments' && ! empty($row['doc_id']) && ! DB::table('project_docs')->where('id', $row['doc_id'])->exists()) {
            return null;
        }

        return $row;
    }

    /** Forget snapshots whose window has passed and remove the files they were keeping. */
    public static function purgeExpired(): void
    {
        $expired = DB::table('undo_snapshots')->where('expires_at', '<', now())->limit(100)->pluck('id');
        foreach ($expired as $id) {
            // Lock the snapshot first: if an undo of it is running, wait for it, and skip it once it's restored.
            DB::transaction(function () use ($id): void {
                $snap = DB::table('undo_snapshots')->where('id', $id)->where('expires_at', '<', now())->lockForUpdate()->first();
                if (! $snap) {
                    return;
                }
                foreach (json_decode($snap->files ?? '[]', true) ?: [] as $file) {
                    // Only remove the file if nothing (e.g. a later restore of another snapshot) uses it.
                    if (! DB::table('attachments')->where('path', $file['path'])->exists()) {
                        Storage::disk($file['disk'])->delete($file['path']);
                    }
                }
                DB::table('undo_snapshots')->where('id', $snap->id)->delete();
            });
        }
    }
}
