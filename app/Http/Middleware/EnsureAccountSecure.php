<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Before using the app: new accounts confirm their email (when email is set up), and
 * internal users turn on two-step verification if their workspace requires it.
 */
class EnsureAccountSecure
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($user->needsToVerifyEmail()) {
            return $this->redirect($request, route('verification.notice')); // nosemgrep: symfony-non-literal-redirect -- named route, not user input
        }

        $workspace = $user->currentWorkspace;
        if ($workspace?->require_two_factor && ! $user->isClient() && ! $user->hasTwoFactor() && ! $request->routeIs('profile')) {
            // nosemgrep: symfony-non-literal-redirect -- named route, not user input
            return $this->redirect($request, route('profile'), 'Your workspace requires two-step verification. Turn it on below to continue.');
        }

        return $next($request);
    }

    protected function redirect(Request $request, string $to, ?string $status = null): Response
    {
        if ($request->hasHeader('X-Livewire')) {
            abort(403, 'Finish securing your account first.');
        }

        $response = redirect()->to($to);

        return $status ? $response->with('status', $status) : $response;
    }
}
