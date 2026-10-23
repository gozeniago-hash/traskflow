<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CreateWorkspace;
use App\Http\Requests\CreateWorkspaceRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function dashboard(Request $request): RedirectResponse
    {
        return $request->user()->isClient()
            ? redirect()->route('portal.dashboard')
            : redirect()->route('home');
    }

    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($request->user()->switchWorkspace($workspace), 403);

        return redirect()->route('dashboard')->with('status', 'Switched to '.$workspace->name.'.');
    }

    public function store(CreateWorkspaceRequest $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $createWorkspace->handle($request->user(), $request->validated('name'));

        return redirect()->route('home')->with('status', 'Workspace created.');
    }
}
