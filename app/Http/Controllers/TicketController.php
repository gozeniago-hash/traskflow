<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Short links like /t/BWR-12 that open the task wherever the viewer can see it. */
class TicketController extends Controller
{
    public function show(Request $request, string $ticket): RedirectResponse
    {
        abort_unless(preg_match('/^([A-Za-z][A-Za-z0-9]{0,9})-(\d+)$/', $ticket, $m), 404);

        $user = $request->user();
        $project = Project::query()->visibleTo($user)->where('key', strtoupper($m[1]))->first();
        abort_unless($project, 404, 'Ticket not found in this workspace.');

        $task = $project->tasks()->where('number', (int) $m[2])->first();
        abort_unless($task && $user->can('view', $task), 404, 'Ticket not found.');

        if ($user->isClient()) {
            return redirect()->route('portal.projects.show', ['project' => $project, 'tab' => 'tasks', 'task' => $task->id]);
        }

        return redirect()->to(route('projects.show', [$project, $project->default_view ?: 'list']).'?task='.$task->id);
    }
}
