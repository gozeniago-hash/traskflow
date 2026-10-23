<?php

namespace App\Actions\Docs;

use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Adds a doc at the end of the project's list of docs. */
class CreateDoc
{
    public function handle(Project $project, User $by, string $title, ?string $content): ProjectDoc
    {
        return DB::transaction(function () use ($project, $by, $title, $content): ProjectDoc {
            Project::whereKey($project->id)->lockForUpdate()->first();

            return $project->docs()->create([
                'title' => mb_substr($title, 0, 255),
                'content' => $content,
                'position' => (int) $project->docs()->max('position') + 1,
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ]);
        });
    }
}
