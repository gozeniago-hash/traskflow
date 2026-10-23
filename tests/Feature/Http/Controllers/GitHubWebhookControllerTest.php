<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\GithubRepository;
use App\Models\Project;
use App\Models\PullRequest;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** Pull request events that GitHub sends to a repository's webhook. */
class GitHubWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_delivery_twice_stores_one_pull_request_with_its_ticket_links(): void
    {
        $repository = GithubRepository::factory()->create();
        $task = Task::factory()->for(Project::factory()->for($repository->workspace)->create(['key' => 'WEB']))->create();
        $payload = $this->pullRequest(7, 'open', '2026-10-20T10:00:00Z', 'WEB-'.$task->number.' New header');

        $this->deliver($repository, $payload)->assertOk();
        $this->deliver($repository, $payload)->assertOk();

        $this->assertSame(1, PullRequest::count());
        $this->assertSame([$task->id], PullRequest::sole()->tasks()->pluck('tasks.id')->all());
    }

    public function test_a_late_older_delivery_does_not_undo_a_newer_one(): void
    {
        $repository = GithubRepository::factory()->create();
        $this->deliver($repository, $this->pullRequest(7, 'closed', '2026-10-20T11:00:00Z'))->assertOk();

        $this->deliver($repository, $this->pullRequest(7, 'open', '2026-10-20T10:00:00Z'))->assertOk();

        $this->assertSame('closed', PullRequest::sole()->state);
    }

    public function test_a_delivery_with_a_wrong_signature_is_refused_with_401(): void
    {
        $repository = GithubRepository::factory()->create();
        $body = json_encode($this->pullRequest(7, 'open', '2026-10-20T10:00:00Z'));

        $response = $this->call('POST', route('github.webhook', $repository), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'wrong-secret'),
        ], $body);

        $response->assertUnauthorized();
        $this->assertSame(0, PullRequest::count());
    }

    /**
     * @return array{action: string, pull_request: array<string, mixed>}
     */
    private function pullRequest(int $number, string $state, string $updatedAt, string $title = 'Tidy up'): array
    {
        return ['action' => 'edited', 'pull_request' => [
            'number' => $number,
            'title' => $title,
            'state' => $state,
            'html_url' => 'https://github.com/acme/site/pull/'.$number,
            'updated_at' => $updatedAt,
            'head' => ['ref' => 'feature/x'],
            'user' => ['login' => 'octocat'],
        ]];
    }

    /**
     * @param array<string, mixed> $payload
     * @return TestResponse<Response>
     */
    private function deliver(GithubRepository $repository, array $payload): TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', route('github.webhook', $repository), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-webhook-secret'),
        ], $body);
    }
}
