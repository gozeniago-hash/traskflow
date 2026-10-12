<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes sure the signed-in user has a valid current workspace, and shares it with views.
 */
class EnsureWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->membership()) {
            $first = $user->workspaces()->orderBy('workspace_user.id')->first();

            if (! $first) {
                Auth::logout();
                $request->session()->invalidate();

                return redirect()->route('login')->withErrors([
                    'email' => 'Your account is not a member of any workspace. Ask an admin for a new invitation.',
                ]);
            }

            $user->switchWorkspace($first);
        }

        view()->share('workspace', $user->currentWorkspace);

        return $next($request);
    }
}
