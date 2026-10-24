<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `portal.open:marketplace` — while that portal is
 * switched off (config/naraya.php), every page on it shows a short notice
 * instead (HTTP 200, not indexed). The frozen code stays in place.
 */
class RequirePortalOpen
{
    private const VIEWS = ['marketplace' => 'marketplace.on-hold', 'marketplace_purchasing' => 'marketplace.on-hold'];

    public function handle(Request $request, Closure $next, string $portal): Response
    {
        if (config("naraya.{$portal}_open") ?? config("naraya.{$portal}_portal_open")) {
            return $next($request);
        }

        return response()->view(self::VIEWS[$portal])->header('X-Robots-Tag', 'noindex');
    }
}
