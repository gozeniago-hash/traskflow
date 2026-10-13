<?php

namespace App\Http\Controllers;

use App\Models\GithubRepository;
use App\Support\GitHub;
use Illuminate\Http\Request;

/** Receives pull_request events from GitHub. Each repository has its own secret. */
class GitHubWebhookController extends Controller
{
    public function __invoke(Request $request, GithubRepository $repository)
    {
        if (! GitHub::verifySignature($request->getContent(), $request->header('X-Hub-Signature-256'), $repository->webhook_secret)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $repository->forceFill(['last_webhook_at' => now()])->save();
        $event = $request->header('X-GitHub-Event');

        if ($event === 'ping') {
            return response()->json(['message' => 'pong']);
        }
        if ($event !== 'pull_request' || ! is_array($request->input('pull_request'))) {
            return response()->json(['message' => 'Ignored.']);
        }

        GitHub::for($repository->workspace)->upsert($repository, $request->input('pull_request'));

        return response()->json(['message' => 'OK']);
    }
}
