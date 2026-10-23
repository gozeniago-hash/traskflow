<?php

namespace App\Actions\GitHub;

use App\Models\GithubRepository;
use App\Models\PullRequest;
use App\Models\Task;
use App\Support\GitHub;
use App\Support\Realtime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores one pull request, from a webhook delivery or "Sync now", and links it to the
 * tickets it mentions. GitHub may deliver the same event twice or out of order: a copy that
 * is older than what is stored is ignored, and the repository row stays locked while the pull
 * request is written, so a webhook and a sync arriving together store it once.
 */
class StorePullRequest
{
    /**
     * @param array<string, mixed> $pr the pull request as GitHub sends it
     */
    public function handle(GithubRepository $repository, array $pr): PullRequest
    {
        $touched = [];
        $model = DB::transaction(function () use ($repository, $pr, &$touched): PullRequest {
            GithubRepository::whereKey($repository->id)->lockForUpdate()->first();

            $model = PullRequest::where('github_repository_id', $repository->id)->where('number', (int) $pr['number'])->first()
                ?? new PullRequest;
            $updatedAt = isset($pr['updated_at']) ? Carbon::parse($pr['updated_at']) : now();
            if ($model->exists && $model->gh_updated_at && $model->gh_updated_at->gt($updatedAt)) {
                return $model; // an older copy of what we already have
            }

            $model->github_repository_id = $repository->id;
            $model->number = (int) $pr['number'];
            $model->fill([
                'workspace_id' => $repository->workspace_id,
                'title' => Str::limit((string) ($pr['title'] ?? ''), 495),
                'url' => (string) ($pr['html_url'] ?? $repository->htmlUrl().'/pull/'.$pr['number']),
                'state' => ($pr['state'] ?? 'open') === 'closed' ? 'closed' : 'open',
                'draft' => (bool) ($pr['draft'] ?? false),
                'merged_at' => isset($pr['merged_at']) ? Carbon::parse($pr['merged_at']) : null,
                'head_ref' => Str::limit((string) ($pr['head']['ref'] ?? ''), 250, ''),
                'author_login' => $pr['user']['login'] ?? null,
                'author_avatar' => $pr['user']['avatar_url'] ?? null,
                'gh_updated_at' => $updatedAt,
            ]);
            $model->save();

            $before = $model->tasks()->pluck('tasks.id')->all();
            $after = GitHub::for($repository->workspace)->matchTasks([$pr['title'] ?? '', $pr['head']['ref'] ?? '', $pr['body'] ?? '']);
            $model->tasks()->sync($after);
            $touched = array_unique(array_merge($before, $after));

            return $model;
        });

        // Tell anyone looking at these tasks to refresh.
        foreach (Task::query()->whereKey($touched)->get(['id', 'project_id']) as $task) {
            Realtime::touch((int) $task->project_id, (int) $task->id);
        }

        return $model;
    }
}
