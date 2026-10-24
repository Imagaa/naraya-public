<?php

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function () {
            Route::middleware('web')->group(base_path('routes/admin.php'));
            Route::middleware('web')->group(base_path('routes/sales.php'));
            Route::middleware('web')->group(base_path('routes/staff.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'portal.open' => \App\Http\Middleware\RequirePortalOpen::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'buyer.ready' => \App\Http\Middleware\EnsureBuyerReady::class,
        ]);

        // Logs out every other session once a user's password changes (the
        // buyer replacing a staff-issued temporary password, or an Admin reset).
        $middleware->web(append: [\Illuminate\Session\Middleware\AuthenticateSession::class]);

        // Separate login per portal family (marketplace vs staff); must run before StartSession.
        $middleware->web(prepend: [\App\Http\Middleware\UsePortalSession::class]);
        // A switched-off portal shows its notice before auth/guest can redirect to a login it doesn't have.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\RequirePortalOpen::class,
        );

        // An already-authenticated user hitting /login (guest middleware)
        // goes to their own portal, not a generic default — same mapping
        // Login::authenticate() uses after a fresh login.
        // Guests on the marketplace log in at the buyer door; guests on any
        // staff portal go to that portal's own door, never the marketplace.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->getHost() === config('app.domain')
            ? route('login')
            : $request->getScheme().'://'.$request->getHost().'/login');

        RedirectIfAuthenticated::redirectUsing(
            fn (Request $request) => $request->getScheme().'://'.$request->user()->role->portalDomain().'/'
        );

        // nginx only binds to 127.0.0.1 (docker-compose.prod.yml) — Cloudflare
        // Tunnel is the only thing that can reach it, terminating TLS and
        // forwarding plain HTTP with X-Forwarded-* headers. Trusting all
        // proxies is safe here because nothing else can originate a request.
        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('payments:reconcile')->everyFiveMinutes();
        $schedule->command('orders:expire-transfers')->everyMinute();
        $schedule->command('admin:notify-checks')->everyFiveMinutes();
        $schedule->command('admin:payout-reminder')->dailyAt('08:00')->timezone(config('app.business_timezone'));
        $schedule->command('admin:daily-recap')->dailyAt('21:00')->timezone(config('app.business_timezone'));
        $schedule->command('admin:weekly-recap')->weeklyOn(1, '07:00')->timezone(config('app.business_timezone'));
        $schedule->command('orders:auto-complete-deliveries')->hourly();
        $schedule->command('cashbook:post-daily')->dailyAt('00:05')->timezone(config('app.business_timezone'));
        $schedule->command('cashbook:close-months')->dailyAt('00:15')->timezone(config('app.business_timezone'));
        $schedule->command('investments:pay')->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
