<?php

namespace App\Livewire\Marketplace;

use App\Enums\OrderStatus;
use App\Livewire\Marketplace\Concerns\HasMarketplaceShell;
use App\Models\Order;
use App\Models\Product;
use App\Support\Cart;
use App\Support\Catalog;
use Livewire\Component;

/**
 * Home as a run of product rails: personal rails for a logged-in
 * buyer, public ones for guests, and an "explore all" grid that grows 20 at a
 * time and never repeats a product already shown in a rail above it.
 */
class Home extends Component
{
    use HasMarketplaceShell;

    public int $perPage = 20;

    public function loadMore(): void
    {
        $this->perPage += 20;
    }

    public function render()
    {
        $user = auth()->user();
        $buyer = $user?->buyerProfile ? $user : null;

        $promos = Catalog::promos(10);
        $best = Catalog::bestSellers($buyer);
        $buyAgain = $buyer ? Catalog::buyAgain($buyer, 10)->pluck('product') : collect();
        $recommended = $buyer ? Catalog::recommendedFor($buyer, 10) : collect();
        $topCategories = Catalog::topCategoriesFor($buyer, 3);
        $newest = Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->with('category')->latest('id')->limit(10)->get();

        // Only rails that actually render (3+ products) count as "already shown"; a product sitting
        // in a skipped rail must still reach the explore grid.
        $railIds = collect([$promos, $best['products'], $buyAgain, $recommended, $newest])
            ->concat($topCategories->pluck('railProducts'))
            ->filter(fn ($rail) => $rail->count() >= 3)
            ->flatten()
            ->pluck('id')
            ->unique()
            ->values()
            ->all();

        $exploreQuery = Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->whereNotIn('id', $railIds);
        $explore = (clone $exploreQuery)->with('category')
            ->orderByRaw('CASE WHEN '.Product::AVAILABLE_SQL.' THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->limit($this->perPage)
            ->get();

        $cartQty = $this->cartQuantities();

        return view('livewire.marketplace.home', [
            'investment' => $buyer ? \App\Support\Investment\Portfolio::for($buyer) : null,
            'categories' => Catalog::categories(),
            'heroPromos' => $promos->take(3),
            'promos' => $promos,
            'promoEndsAt' => $promos->pluck('discount_ends_at')->filter()->min(),
            'best' => $best,
            'buyAgain' => $buyAgain,
            'recommended' => $recommended,
            'topCategories' => $topCategories,
            'newest' => $newest,
            'explore' => $explore,
            'exploreHasMore' => $exploreQuery->count() > $explore->count(),
            'railIds' => $railIds,
            'activeOrders' => $buyer
                ? Order::where('buyer_id', $buyer->id)
                    ->whereIn('status', [OrderStatus::Processing->value, OrderStatus::Shipped->value])
                    ->with('salesUser')->latest('id')->get()
                : collect(),
            'salesName' => $buyer?->buyerProfile->salesUser?->name,
            'cartQty' => $cartQty,
            'cartCount' => array_sum($cartQty),
            'cartTotal' => Cart::total(),
            'alertIds' => $this->stockAlertIds(),
        ])->layout('layouts.marketplace')->title('Naraya — Grosir, mainan & investasi untuk warung');
    }
}
