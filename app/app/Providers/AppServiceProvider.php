<?php

namespace App\Providers;

use App\Http\Middleware\EnsureBuyerReady;
use App\Models\Order;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Badge count for the Sales bottom nav's "Pesanan" icon — a view
        // composer (not each component passing it individually) since every
        // Sales page shares this one layout.
        View::composer('layouts.sales', function ($view) {
            $view->with('pendingDeliveryCount', auth()->check() ? Order::pendingSalesActionCount(auth()->id()) : 0);
        });

        // Buyer onboarding gates also stop Livewire actions, not just page
        // loads: an addToCart from a tab opened before an Admin reset the
        // password must never run. The gate pages themselves are exempt.
        \Livewire\on('call', function ($component, $method, $params, $context, $returnEarly) {
            $user = auth()->user();

            if (! $user?->isUser()
                || ! str_starts_with($component::class, 'App\\Livewire\\Marketplace\\')
                || str_starts_with($component::class, 'App\\Livewire\\Marketplace\\Onboarding\\')) {
                return;
            }

            if ($gate = EnsureBuyerReady::gateFor($user)) {
                $returnEarly();
                $component->redirectRoute($gate);
            }
        });

        // Display helper: stored UTC timestamp -> WIB (config app.business_timezone).
        \Illuminate\Support\Carbon::macro('wib', function () {
            return $this->copy()->timezone(config('app.business_timezone'));
        });

        Blade::directive('rupiah', fn (string $expression) => "<?php echo 'Rp '.number_format((int) ({$expression}), 0, ',', '.'); ?>");
    }
}
