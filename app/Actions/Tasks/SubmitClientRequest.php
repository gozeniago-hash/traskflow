<?php

namespace App\Actions\Tasks;

use App\Actions\Files\StoreUploads;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * A client asks the team for something from the portal. The request becomes a shared task
 * in the project's "Client requests" section, which is created the first time. The project
 * row is locked while the section is looked up, so two requests at once share one section.
 */
class SubmitClientRequest
{
    public function __construct(protected StoreUploads $storeUploads) {}

    /**
     * @param array{title: string, description?: string|null, due_date?: string|null} $request
     * @param array<int, UploadedFile> $files
     */
    public function handle(Project $project, User $client, array $request, array $files = []): Task
    {
        return DB::transaction(function () use ($project, $client, $request, $files): Task {
            Project::whereKey($project->id)->lockForUpdate()->first();
            $section = $project->sections()->where('name', 'Client requests')->first()
                ?? Section::create(['project_id' => $project->id, 'name' => 'Client requests', 'position' => 0]);

            $task = $project->tasks()->create([
                'section_id' => $section->id,
                'title' => mb_substr(trim($request['title']), 0, 255),
                'description' => ($request['description'] ?? null) ?: null,
                'due_date' => ($request['due_date'] ?? null) ?: null,
                'creator_id' => $client->id,
                'client_visible' => true,
                'requested_by_client' => true,
                'position' => $project->nextTaskPosition($section->id),
            ]);
            Activity::record($task, $client, 'client_request');
            $this->storeUploads->handle($files, $project, $client, $task);

            return $task;
        });
    }
}
