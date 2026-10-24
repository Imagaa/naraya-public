<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // a deleted sales account loses its open sessions too (App\Support\Sales\SalesAccounts)
        if (! $request->user() || $request->user()->role !== Role::from($role) || $request->user()->account_deleted_at) {
            abort(403);
        }

        return $next($request);
    }
}
