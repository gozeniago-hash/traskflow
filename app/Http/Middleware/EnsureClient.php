<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Client portal area. Internal users are sent to the main app. */
class EnsureClient
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->isClient()) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
