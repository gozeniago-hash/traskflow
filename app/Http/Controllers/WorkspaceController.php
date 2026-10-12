<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $user = $request->user();

        $workspace = DB::transaction(function () use ($data, $user) {
            $workspace = Workspace::create(['name' => $data['name'], 'owner_id' => $user->id]);
            $workspace->users()->attach($user->id, ['role' => 'owner']);
            $workspace->teams()->create(['name' => 'General'])->users()->attach($user->id);

            return $workspace;
        });

        $user->switchWorkspace($workspace);

        return redirect()->route('home')->with('status', 'Workspace created.');
    }
}
