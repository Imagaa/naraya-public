<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every staff portal (admin, sales) is its own door: its own session
 * cookie, scoped to its own host only, and its own guard (own remember-me
 * cookie). One browser can therefore be signed in as admin on the admin portal
 * and as sales on the sales portal at once, and signing in or out of one never
 * touches another. The marketplace keeps the default cookie and "web" guard,
 * also host-only (SESSION_DOMAIN is ignored), so none of its cookies — the
 * XSRF-TOKEN included — are ever sent to a staff portal.
 * The old shared staff host only shows a portal chooser. Runs before StartSession.
 */
class UsePortalSession
{
    private static ?string $marketplaceCookie = null;

    public function handle(Request $request, Closure $next): Response
    {
        self::$marketplaceCookie ??= config('session.cookie');

        $host = $request->getHost();
        $portal = Role::staffPortalFor($host);
        $staffHost = $portal !== null || $host === config('app.staff_domain');

        config([
            'session.cookie' => $staffHost ? config('session.staff_cookie').'-'.($portal?->value ?? 'staf') : self::$marketplaceCookie,
            // host-only everywhere: no cookie is ever sent to a sibling subdomain
            'session.domain' => null,
        ]);
        // Rename rather than rebuild: a store resolved earlier keeps its data but must use this cookie.
        app('session')->driver()->setName(config('session.cookie'));
        // Remember-me cookies come from the cookie jar, whose domain default was fixed at boot.
        app('cookie')->setDefaultPathAndDomain(config('session.path'), config('session.domain'), config('session.secure'), config('session.same_site'));
        Auth::shouldUse($portal?->value ?? 'web');

        return $next($request);
    }
}
