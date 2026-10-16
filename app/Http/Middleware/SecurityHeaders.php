<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers on every response, and a Content-Security-Policy on pages.
 *
 * Scripts must come from this site or carry the per-request nonce (Vite and Livewire add
 * it automatically), so an injected <script> or inline handler won't run. 'unsafe-eval'
 * stays because Alpine evaluates x-data / x-on expressions at runtime.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }
        $response->headers->remove('X-Powered-By');

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // The policy only means something on HTML pages; files and exports are left alone
        // so inline PDFs and images keep working.
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Security-Policy', $this->policy());
        }

        return $response;
    }

    protected function policy(): string
    {
        $nonce = Vite::cspNonce();
        $realtime = [];
        if ($host = config('taskflow.realtime.host')) {
            $secure = config('taskflow.realtime.scheme') === 'https';
            $port = (int) config('taskflow.realtime.port');
            $suffix = in_array($port, [0, 80, 443], true) ? '' : ':'.$port;
            $realtime[] = ($secure ? 'wss://' : 'ws://').$host.$suffix;
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",
            "img-src 'self' data: blob: https://avatars.githubusercontent.com",
            "connect-src 'self' ".implode(' ', $realtime),
            "media-src 'self' blob:",
            "frame-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
