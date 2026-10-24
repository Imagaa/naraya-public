<?php

namespace App\Http\Middleware;

use App\Enums\BuyerStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onboarding gates a logged-in buyer must clear, in order, before shopping.
 * Also registered as Livewire persistent middleware, so an action fired
 * from a tab opened before a gate appeared is stopped too, not just page
 * loads. Guests and staff pass straight through.
 */
class EnsureBuyerReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isUser()) {
            return $next($request);
        }

        $gate = self::gateFor($user);

        // The gate page itself and logout stay reachable, or the buyer would be stuck.
        if ($gate && ! $request->routeIs($gate, 'logout')) {
            return redirect()->route($gate);
        }

        return $next($request);
    }

    /** Route name of the first unmet gate, or null when the buyer may shop. */
    public static function gateFor(User $user): ?string
    {
        if ($user->must_change_password) {
            return 'onboarding.password';
        }

        // Every creation path (registration, Admin, Sales) makes a profile, so only an existing,
        // incomplete one is held here.
        if ($user->buyerProfile && ! $user->buyerProfile->hasCompleteStoreData()) {
            return 'onboarding.store';
        }

        if ($user->buyerProfile && $user->buyerProfile->status !== BuyerStatus::Approved) {
            return 'onboarding.status';
        }

        return null;
    }
}
