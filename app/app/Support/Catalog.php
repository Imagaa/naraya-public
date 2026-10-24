<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

/** Read-only storefront queries shared by Home and the product page. */
class Catalog
{
    /** Categories that have at least one active product — empty ones are hidden everywhere in the shop. */
    public static function categories(): Collection
    {
        return Category::forLine(\App\Support\ActiveLine::current())->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $category) => $category->products_count > 0)
            ->values();
    }

    public static function promos(int $limit = 4): Collection
    {
        return Product::forLine(\App\Support\ActiveLine::current())->promo()
            ->with('category')
            ->orderByRaw('(price - discount_price) * 1.0 / price DESC')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Area best sellers (last 30 days), else global ones, always topped up to
     * $limit — next with 90-day and all-time sellers, then the newest
     * products — so the rail is never a three-card stub.
     *
     * @return array{title: string, products: Collection}
     */
    public static function bestSellers(?User $buyer, int $limit = 10): array
    {
        $area = $buyer?->buyerProfile?->area;
        $areaIds = $area ? self::topSellingIds($limit, $area->id) : [];
        $ids = $areaIds ?: self::topSellingIds($limit);

        $title = match (true) {
            $areaIds !== [] => 'Terlaris di '.$area->name,
            $ids !== [] => 'Terlaris',
            default => 'Produk Terbaru',
        };

        $ids = collect($ids)
            ->concat(self::topSellingIds($limit * 2, days: 90))
            ->concat(self::topSellingIds($limit * 2, days: 0))
            ->unique()->values()->all();

        $products = self::activeInOrder($ids)->take($limit);

        if ($products->count() < $limit) {
            $products = $products->concat(
                Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->with('category')
                    ->whereNotIn('id', $products->pluck('id'))
                    ->latest('id')->limit($limit - $products->count())->get()
            );
        }

        return ['title' => $title, 'products' => $products->values()];
    }

    /**
     * "Rekomendasi untuk Toko Anda": products co-bought with anything in the
     * buyer's last 10 orders, minus what they bought in the last 14 days,
     * topped up with best sellers. Active products only, no repeats.
     */
    public static function recommendedFor(User $buyer, int $limit = 10): Collection
    {
        $orders = Order::where('buyer_id', $buyer->id)->where('status', '!=', OrderStatus::Cancelled->value);
        $history = OrderItem::whereIn('order_id', (clone $orders)->latest('id')->limit(10)->pluck('id'))->pluck('product_id')->unique()->all();
        $recent = OrderItem::whereIn('order_id', (clone $orders)->where('created_at', '>=', now()->subDays(14))->pluck('id'))->pluck('product_id')->unique()->all();

        $ids = $history === [] ? [] : OrderItem::whereIn('order_id', OrderItem::select('order_id')->whereIn('product_id', $history))
            ->whereNotIn('product_id', array_merge($history, $recent))
            ->groupBy('product_id')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('product_id')
            ->limit($limit * 3)
            ->pluck('product_id')
            ->all();

        $picked = self::activeInOrder($ids)->take($limit);

        if ($picked->count() < $limit) {
            $exclude = array_merge($recent, $picked->pluck('id')->all());
            $picked = $picked->concat(
                self::bestSellers($buyer, $limit + count($exclude))['products']
                    ->reject(fn (Product $p) => in_array($p->id, $exclude, true))
                    ->take($limit - $picked->count())
            );
        }

        return $picked->unique('id')->values();
    }

    /**
     * Categories for the Home category rails: the buyer's most-bought ones,
     * or the most popular (90 days) for guests and new buyers, topped up by
     * product count. Only categories with 3+ active products qualify; each
     * comes back with ->railProducts (up to 10, buyable first).
     */
    public static function topCategoriesFor(?User $buyer, int $count = 3): Collection
    {
        $eligible = Category::forLine(\App\Support\ActiveLine::current())->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->filter(fn (Category $c) => $c->products_count >= 3)
            ->keyBy('id');

        $rankedBy = fn ($query) => $query
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->groupBy('products.category_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->pluck('products.category_id');

        $ranked = $buyer
            ? $rankedBy(OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.buyer_id', $buyer->id))
            : collect();

        if ($ranked->isEmpty()) {
            $ranked = $rankedBy(OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', OrderStatus::Completed->value)
                ->where('orders.completed_at', '>=', now()->subDays(90)));
        }

        return $ranked->filter(fn ($id) => $eligible->has($id))
            ->concat($eligible->sortByDesc('products_count')->keys())
            ->unique()
            ->take($count)
            ->map(function ($id) use ($eligible) {
                $category = $eligible[$id];
                $category->railProducts = Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->where('category_id', $id)->with('category')
                    ->orderByRaw('CASE WHEN '.Product::AVAILABLE_SQL.' THEN 0 ELSE 1 END')
                    ->orderBy('name')
                    ->limit(10)
                    ->get();

                return $category;
            })
            ->values();
    }

    public static function buyAgain(User $buyer, int $limit = 8): Collection
    {
        $orders = Order::where('buyer_id', $buyer->id)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->latest('id')
            ->limit(5)
            ->with('items')
            ->get();

        $lines = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $lines[$item->product_id] ??= ['quantity' => (int) $item->quantity, 'orderedAt' => $order->created_at];
            }
        }

        return self::activeInOrder(array_keys($lines))
            ->take($limit)
            ->map(fn (Product $product) => ['product' => $product] + $lines[$product->id])
            ->values();
    }

    public static function boughtWith(Product $product, int $limit = 5): Collection
    {
        // Subquery, not a plucked id list: a best seller sits in so many orders
        // that one placeholder per order would exceed MySQL's 65,535 limit.
        $ids = OrderItem::whereIn('order_id', OrderItem::select('order_id')->where('product_id', $product->id))
            ->where('product_id', '!=', $product->id)
            ->groupBy('product_id')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('product_id')
            ->limit($limit * 3)
            ->pluck('product_id')
            ->all();

        $related = self::activeInOrder($ids)->take($limit);

        if ($related->count() < $limit && $product->category_id) {
            $related = $related->concat(
                Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)
                    ->with('category')
                    ->where('category_id', $product->category_id)
                    ->whereKeyNot($product->id)
                    ->whereNotIn('id', $related->pluck('id'))
                    ->orderBy('name')
                    ->limit($limit - $related->count())
                    ->get()
            );
        }

        return $related->values();
    }

    /** "Produk serupa": same category, effective price within ±40%, nearest price first. */
    public static function similarTo(Product $product, array $excludeIds = [], int $limit = 10): Collection
    {
        if (! $product->category_id) {
            return collect();
        }

        $price = $product->effectivePrice();

        return Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->with('category')
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->whereNotIn('id', $excludeIds)
            ->get()
            ->filter(fn (Product $p) => abs($p->effectivePrice() - $price) <= $price * 0.4)
            ->sortBy(fn (Product $p) => abs($p->effectivePrice() - $price))
            ->take($limit)
            ->values();
    }

    /** Cart recommendations: co-bought with anything in the cart, topped up with best sellers. */
    public static function boughtWithAny(array $productIds, ?User $buyer, int $limit = 6): Collection
    {
        if ($productIds === []) {
            return self::bestSellers($buyer, $limit)['products'];
        }

        $ids = OrderItem::whereIn('order_id', OrderItem::select('order_id')->whereIn('product_id', $productIds))
            ->whereNotIn('product_id', $productIds)
            ->groupBy('product_id')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('product_id')
            ->limit($limit * 3)
            ->pluck('product_id')
            ->all();

        $picked = self::activeInOrder($ids)->take($limit);

        if ($picked->count() < $limit) {
            $exclude = array_merge($productIds, $picked->pluck('id')->all());
            $picked = $picked->concat(
                self::bestSellers($buyer, $limit + count($exclude))['products']
                    ->reject(fn (Product $p) => in_array($p->id, $exclude, true))
                    ->take($limit - $picked->count())
            );
        }

        return $picked->values();
    }

    /** @return array<int, int> */
    private static function topSellingIds(int $limit, ?int $areaId = null, int $days = 30): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.is_active', true)
            ->where('orders.status', OrderStatus::Completed->value)
            ->when($days > 0, fn ($q) => $q->where('orders.completed_at', '>=', now()->subDays($days)))
            ->when($areaId, fn ($q) => $q
                ->join('buyer_profiles', 'buyer_profiles.user_id', '=', 'orders.buyer_id')
                ->where('buyer_profiles.area_id', $areaId))
            ->groupBy('order_items.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->orderBy('order_items.product_id')
            ->limit($limit)
            ->pluck('order_items.product_id')
            ->all();
    }

    /** Active products for the given ids, in the ids' order. */
    private static function activeInOrder(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $products = Product::forLine(\App\Support\ActiveLine::current())->whereIn('id', $ids)->where('is_active', true)->with('category')->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $products->get($id))->filter()->values();
    }
}
