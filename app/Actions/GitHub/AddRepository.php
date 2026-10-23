<?php

namespace App\Actions\GitHub;

use App\Models\GithubRepository;
use App\Models\Workspace;
use App\Support\Audit;
use App\Support\GitHub;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Links a GitHub repository to the workspace: stores it with its own webhook secret,
 * installs the webhook when the token allows it, and reads its pull requests once.
 * Adding the same repository twice, even at the same moment, gives a normal error.
 */
class AddRepository
{
    /**
     * @return array{repository: GithubRepository, hooked: bool, synced: ?int, syncError: ?string}
     *
     * @throws ValidationException when the repository is already added
     */
    public function handle(Workspace $workspace, string $fullName): array
    {
        $taken = ValidationException::withMessages(['newRepo' => 'That repository is already added.']);
        if ($workspace->githubRepositories()->where('full_name', $fullName)->exists()) {
            throw $taken;
        }

        try {
            $repository = $workspace->githubRepositories()->create(['full_name' => $fullName, 'webhook_secret' => Str::random(40)]);
        } catch (UniqueConstraintViolationException) {
            throw $taken;
        }
        Audit::log('github.repository_added', 'Added GitHub repository '.$fullName, $repository);

        $github = GitHub::for($workspace);
        $hooked = $github->installWebhook($repository);
        try {
            return ['repository' => $repository, 'hooked' => $hooked, 'synced' => $github->sync($repository), 'syncError' => null];
        } catch (Throwable $e) {
            return ['repository' => $repository, 'hooked' => $hooked, 'synced' => null, 'syncError' => $e->getMessage()];
        }
    }
}
