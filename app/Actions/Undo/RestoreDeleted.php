<?php

namespace App\Actions\Undo;

use App\Models\Project;
use App\Models\User;
use App\Support\ProjectLog;
use App\Support\Realtime;
use App\Support\Undo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Undo" on a delete toast: puts the captured rows back with their old ids.
 *
 * The snapshot is locked while it is restored, so a double click or the clean-up of
 * expired snapshots can't use it a second time. Only its author can restore it, and only
 * while they can still work in the project. Tasks that someone moved after a section was
 * deleted stay where they were put.
 */
class RestoreDeleted
{
    /**
     * @return array{ok: bool, status: int, message: string, project_id: ?int}
     */
    public function handle(string $token, User $user): array
    {
        try {
            $result = DB::transaction(function () use ($token, $user): array {
                $snap = DB::table('undo_snapshots')->where('id', $token)->where('user_id', $user->id)->lockForUpdate()->first();
                if (! $snap || now()->greaterThan($snap->expires_at)) {
                    return $this->failed(410, 'This can no longer be undone.');
                }

                $project = $snap->project_id ? Project::find($snap->project_id) : null;
                if ($project && ! $user->can('contribute', $project)) {
                    return $this->failed(403, 'You can no longer change this project, so this can’t be undone.');
                }

                $this->putBack(json_decode($snap->payload, true), $snap);
                DB::table('undo_snapshots')->where('id', $snap->id)->delete();

                return ['ok' => true, 'status' => 200, 'message' => 'Restored.', 'project_id' => $snap->project_id ? (int) $snap->project_id : null, 'label' => $snap->label];
            });
        } catch (Throwable $e) {
            Log::warning('Undo failed: '.$e->getMessage());

            return $this->failed(410, 'Couldn’t undo: something it depended on was changed or removed.');
        }

        if ($result['ok'] && $result['project_id']) {
            Realtime::touch($result['project_id']);
            if (Project::whereKey($result['project_id'])->exists()) {
                ProjectLog::restored($result['project_id'], $result['label']);
            }
        }
        unset($result['label']);

        return $result;
    }

    /**
     * @param array{rows?: array<string, array<int, array<string, mixed>>>, moves?: array<int, array<int, int|null>>} $payload
     */
    protected function putBack(array $payload, object $snap): void
    {
        foreach (Undo::ORDER as $table) {
            foreach ($payload['rows'][$table] ?? [] as $row) {
                $row = Undo::repair($table, $row, $snap->project_id, (string) $snap->created_at);
                if ($row !== null) {
                    DB::table($table)->insert($row);
                }
            }
        }

        // [task, section, position, where the delete moved it]. Older snapshots have no fourth value.
        foreach ($payload['moves'] ?? [] as $move) {
            [$taskId, $sectionId, $position] = $move;
            DB::table('tasks')->where('id', $taskId)->where('project_id', $snap->project_id)
                ->when(isset($move[3]), fn ($q) => $q->where('section_id', $move[3]))
                ->update(['section_id' => $sectionId, 'position' => $position]);
        }
    }

    /**
     * @return array{ok: false, status: int, message: string, project_id: null}
     */
    protected function failed(int $status, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message, 'project_id' => null];
    }
}
