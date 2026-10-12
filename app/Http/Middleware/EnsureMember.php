<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Internal (non-client) area. Clients are sent to their portal. */
class EnsureMember
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->isClient()) {
            return redirect()->route('portal.dashboard');
        }

        return $next($request);
    }
}
