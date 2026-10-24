<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Registered on every domain (web/admin/sales/staf) rather than as a
 * Livewire component action — a wire:click placed in a layout file (not a
 * component's own view) sits outside that component's DOM boundary and
 * Livewire never binds it, so the button silently does nothing on click.
 * A real form POST has no such boundary to get wrong.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Sales PWA sends this phone's push endpoint so the next person on a
        // shared device doesn't get this user's notifications; other devices keep theirs.
        if (is_string($endpoint = $request->input('push_endpoint')) && $endpoint !== '') {
            $request->user()?->deletePushSubscription($endpoint);
        }
        // ...and the Android app sends its FCM token for the same reason.
        if (is_string($token = $request->input('fcm_token')) && $token !== '') {
            $request->user()?->deviceTokens()->where('token', $token)->delete();
        }

        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Buyers land back on the browsable-as-guest homepage; staff go back to
        // this portal's own door (other portals stay signed in).
        if ($request->getHost() === config('app.domain')) {
            return redirect('/');
        }

        return redirect($request->getScheme().'://'.$request->getHost().'/login');
    }
}
