<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Work out the visitor's address from the hop our own host added, not from anything
 * the visitor could have typed into the request.
 *
 * X-Forwarded-For is a list: "<whatever the client sent>, …, <address seen by the edge>".
 * Railway's edge adds (or overwrites with) the real address, so only the nearest public
 * entry can be trusted. Without this, someone could send "X-Forwarded-For: 1.2.3.4" with every request
 * and slip past the per-address limits on sign-in, sign-up and password resets.
 * (If Cloudflare is put in front later, prefer its CF-Connecting-IP header here.)
 */
class UseNearestForwardedIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $forwarded = $request->headers->get('X-Forwarded-For');
        if ($forwarded !== null) {
            $client = self::nearestPublic(explode(',', $forwarded));
            $client !== null
                ? $request->headers->set('X-Forwarded-For', $client)
                : $request->headers->remove('X-Forwarded-For');
        }
        // Other address headers aren't used by the app; drop them so nothing relies on them by accident.
        $request->headers->remove('X-Real-IP');
        $request->headers->remove('Forwarded');

        return $next($request);
    }

    /**
     * Walk the hops from the right (nearest to us) and return the first public address:
     * internal proxy hops are skipped, and anything a visitor added sits further left.
     */
    public static function nearestPublic(array $hops): ?string
    {
        foreach (array_reverse(array_map('trim', $hops)) as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                && ! self::inRange($ip, '100.64.0.0', 10); // carrier-grade NAT, used by hosting proxies
            if ($public) {
                return $ip;
            }
        }

        return null;
    }

    protected static function inRange(string $ip, string $net, int $bits): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        $mask = -1 << (32 - $bits);

        return (ip2long($ip) & $mask) === (ip2long($net) & $mask);
    }
}
