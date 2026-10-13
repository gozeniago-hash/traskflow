<?php

namespace App\Support;

use App\Models\GithubRepository;
use App\Models\Project;
use App\Models\PullRequest;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * GitHub pull request sync for a workspace.
 *
 * A PR is linked to every task whose ticket number (e.g. BWR-12) appears in the PR's
 * title, branch name or description. PRs arrive by webhook in real time; "Sync now"
 * and adding a repository pull recent PRs through the REST API.
 */
class GitHub
{
    /** Ticket references: KEY-123, also in lower case inside branch names (bwr-12-fix-nav). */
    public const TICKET_PATTERN = '/(?<![A-Za-z0-9])([A-Za-z][A-Za-z0-9]{1,9})-(\d{1,7})(?!\d)/';

    public function __construct(protected Workspace $workspace)
    {
    }

    public static function for(Workspace $workspace): static
    {
        return new static($workspace);
    }

    public function connected(): bool
    {
        return filled($this->workspace->github_token);
    }

    protected function http(?string $token = null): PendingRequest
    {
        return Http::baseUrl('https://api.github.com')
            ->withToken($token ?? (string) $this->workspace->github_token)
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'TaskFlow'])
            ->acceptJson()
            ->timeout(15)
            ->connectTimeout(5);
    }

    /** Check a token and return the GitHub login it belongs to. */
    public function verifyToken(string $token): string
    {
        $response = $this->http($token)->get('/user');
        if ($response->status() === 401) {
            throw new \RuntimeException('GitHub rejected that token. Check it was copied in full and has not expired.');
        }
        $response->throw();

        return (string) $response->json('login');
    }

    /** Confirm the token can see a repository; returns its canonical owner/name. */
    public function verifyRepository(string $fullName): string
    {
        $response = $this->http()->get('/repos/'.$fullName);
        if (in_array($response->status(), [403, 404], true)) {
            throw new \RuntimeException("GitHub couldn't find {$fullName}, or the token doesn't have access to it.");
        }
        $response->throw();

        return (string) $response->json('full_name');
    }

    /**
     * Try to create the webhook on GitHub (needs the token's "Webhooks: read and write"
     * permission). Returns false when it couldn't, so the admin can add it by hand.
     */
    public function installWebhook(GithubRepository $repo): bool
    {
        try {
            $response = $this->http()->post('/repos/'.$repo->full_name.'/hooks', [
                'name' => 'web',
                'active' => true,
                'events' => ['pull_request'],
                'config' => [
                    'url' => $repo->webhookUrl(),
                    'content_type' => 'json',
                    'secret' => $repo->webhook_secret,
                    'insecure_ssl' => '0',
                ],
            ]);
        } catch (\Throwable) {
            return false;
        }
        if (! $response->successful()) {
            return false;
        }
        $repo->update(['webhook_id' => $response->json('id')]);

        return true;
    }

    public function removeWebhook(GithubRepository $repo): void
    {
        if (! $repo->webhook_id || ! $this->connected()) {
            return;
        }
        try {
            $this->http()->delete('/repos/'.$repo->full_name.'/hooks/'.$repo->webhook_id);
        } catch (\Throwable) {
            // The repository is being removed from TaskFlow either way.
        }
    }

    /** Pull the most recently updated PRs (up to 300) and link them to tickets. Returns how many were read. */
    public function sync(GithubRepository $repo): int
    {
        $count = 0;
        try {
            for ($page = 1; $page <= 3; $page++) {
                $response = $this->http()->get('/repos/'.$repo->full_name.'/pulls', [
                    'state' => 'all', 'sort' => 'updated', 'direction' => 'desc', 'per_page' => 100, 'page' => $page,
                ])->throw();
                $items = $response->json() ?: [];
                foreach ($items as $item) {
                    $this->upsert($repo, $item);
                    $count++;
                }
                if (count($items) < 100) {
                    break;
                }
            }
            $repo->update(['last_synced_at' => now(), 'last_error' => null]);
        } catch (RequestException $e) {
            $repo->update(['last_error' => static::describe($e)]);
            throw new \RuntimeException($repo->last_error, 0, $e);
        }

        return $count;
    }

    /** Store one PR (from the API or a webhook payload) and relink it to tickets. */
    public function upsert(GithubRepository $repo, array $pr): PullRequest
    {
        $model = PullRequest::firstOrNew(['github_repository_id' => $repo->id, 'number' => (int) $pr['number']]);
        $model->fill([
            'workspace_id' => $repo->workspace_id,
            'title' => Str::limit((string) ($pr['title'] ?? ''), 495),
            'url' => (string) ($pr['html_url'] ?? $repo->htmlUrl().'/pull/'.$pr['number']),
            'state' => ($pr['state'] ?? 'open') === 'closed' ? 'closed' : 'open',
            'draft' => (bool) ($pr['draft'] ?? false),
            'merged_at' => isset($pr['merged_at']) ? Carbon::parse($pr['merged_at']) : null,
            'head_ref' => Str::limit((string) ($pr['head']['ref'] ?? ''), 250, ''),
            'author_login' => $pr['user']['login'] ?? null,
            'author_avatar' => $pr['user']['avatar_url'] ?? null,
            'gh_updated_at' => isset($pr['updated_at']) ? Carbon::parse($pr['updated_at']) : now(),
        ]);
        $model->save();

        $before = $model->tasks()->pluck('tasks.id')->all();
        $after = $this->matchTasks([$pr['title'] ?? '', $pr['head']['ref'] ?? '', $pr['body'] ?? '']);
        $model->tasks()->sync($after);

        // Tell anyone looking at these tasks to refresh.
        $touched = Task::query()->whereKey(array_unique(array_merge($before, $after)))->get(['id', 'project_id']);
        foreach ($touched as $task) {
            Realtime::touch((int) $task->project_id, (int) $task->id);
        }

        return $model;
    }

    /** Task ids in this workspace whose tickets are mentioned in any of the given texts. */
    public function matchTasks(array $texts): array
    {
        $refs = static::ticketRefs(implode("\n", array_map('strval', $texts)));
        if (! $refs) {
            return [];
        }
        $projects = Project::query()->where('workspace_id', $this->workspace->id)
            ->whereIn('key', array_keys($refs))->pluck('id', 'key');

        $ids = [];
        foreach ($projects as $key => $projectId) {
            $ids = array_merge($ids, Task::query()->where('project_id', $projectId)
                ->whereIn('number', $refs[$key])->pluck('id')->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** ['BWR' => [5, 12], ...] */
    public static function ticketRefs(string $text): array
    {
        preg_match_all(static::TICKET_PATTERN, $text, $m, PREG_SET_ORDER);
        $refs = [];
        foreach ($m as [, $key, $number]) {
            $refs[strtoupper($key)][] = (int) $number;
        }

        return array_map(fn ($n) => array_values(array_unique($n)), $refs);
    }

    public static function verifySignature(string $payload, ?string $signature, string $secret): bool
    {
        if (! $signature || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $payload, $secret), $signature);
    }

    protected static function describe(RequestException $e): string
    {
        $status = $e->response->status();

        return match (true) {
            $status === 401 => 'GitHub rejected the token. Reconnect GitHub with a new token.',
            $status === 403 && $e->response->header('X-RateLimit-Remaining') === '0' => 'GitHub rate limit reached. Try again in a few minutes.',
            in_array($status, [403, 404], true) => 'The token no longer has access to this repository.',
            default => 'GitHub returned an error ('.$status.'). Try again shortly.',
        };
    }
}
