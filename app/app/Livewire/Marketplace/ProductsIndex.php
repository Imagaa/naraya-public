<?php

namespace App\Livewire\Marketplace;

use App\Enums\OrderStatus;
use App\Livewire\Marketplace\Concerns\HasMarketplaceShell;
use App\Models\Product;
use App\Models\SearchHistory;
use App\Support\Cart;
use App\Support\Catalog;
use App\Support\ProductSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProductsIndex extends Component
{
    use HasMarketplaceShell, WithPagination;

    public const SORTS = [
        'relevan' => 'Paling sesuai',
        'populer' => 'Terpopuler',
        'termurah' => 'Termurah',
        'termahal' => 'Termahal',
        'terbaru' => 'Terbaru',
        'promo' => 'Promo',
    ];

    #[Url]
    public string $category = '';

    #[Url]
    public string $q = '';

    #[Url]
    public string $sort = 'populer';

    #[Url]
    public bool $available = false;

    /** A search lands sorted by relevance, and is remembered in the buyer's history. */
    public function mount(): void
    {
        if (trim($this->q) !== '') {
            if ($this->sort === 'populer') {
                $this->sort = 'relevan';
            }
            if (auth()->user()?->isUser()) {
                SearchHistory::remember(auth()->user(), $this->q);
            }
        }
    }

    /** Any filter change goes back to page 1, so page 3 of the old result never shows empty. */
    public function updated(string $property): void
    {
        if (in_array($property, ['category', 'q', 'sort', 'available'], true)) {
            $this->resetPage();
        }
        if ($property === 'q' && trim($this->q) === '' && $this->sort === 'relevan') {
            $this->sort = 'populer';
        }
    }

    public function clearFilters(): void
    {
        $this->reset('category', 'q', 'available');
        $this->resetPage();
    }

    public function render()
    {
        $search = trim($this->q);
        $sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'populer';
        if ($sort === 'relevan' && $search === '') {
            $sort = 'populer';
        }
        $ranked = $search === '' ? [] : array_keys(ProductSearch::rank($search));

        $query = Product::query()->select('products.*')->forLine(\App\Support\ActiveLine::current())->where('products.is_active', true)
            ->with('category')
            ->when($this->category, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('name', $this->category)))
            ->when($search !== '', fn ($q) => $q->whereIn('products.id', $ranked ?: [0]))
            ->when($this->available, fn ($q) => $q->available());

        $this->applySort($query, $sort, $ranked);

        return view('livewire.marketplace.products-index', [
            'products' => $query->paginate(24),
            'categories' => Catalog::categories(),
            'sorts' => $search === '' ? array_diff_key(self::SORTS, ['relevan' => true]) : self::SORTS,
            'search' => $search,
            'cartQty' => $this->cartQuantities(),
            'cartCount' => $this->cartCount(),
            'cartTotal' => Cart::total(),
            'alertIds' => $this->stockAlertIds(),
        ])->layout('layouts.marketplace')->title('Produk — Naraya');
    }

    private function applySort(Builder $query, string $sort, array $ranked): void
    {
        // The price a buyer actually pays: the discount price while a discount is live.
        $effectivePrice = 'CASE WHEN products.discount_price IS NOT NULL AND products.discount_price < products.price'
            .' AND (products.discount_ends_at IS NULL OR products.discount_ends_at > ?) THEN products.discount_price ELSE products.price END';

        match ($sort) {
            // ponytail: CASE over the matched ids, fine at catalogue size; a search engine sorts this itself.
            'relevan' => $ranked === [] ? $query : $query->orderByRaw('CASE products.id '.collect($ranked)->map(fn (int $id, int $i) => "WHEN {$id} THEN {$i}")->implode(' ').' END'),
            'termurah' => $query->orderByRaw($effectivePrice.' ASC', [now()])->orderBy('products.name'),
            'termahal' => $query->orderByRaw($effectivePrice.' DESC', [now()])->orderBy('products.name'),
            'terbaru' => $query->orderByDesc('products.id'),
            'promo' => $query->promo()->orderByRaw('(products.price - products.discount_price) * 1.0 / products.price DESC')->orderBy('products.name'),
            default => $query
                ->leftJoinSub(
                    DB::table('order_items')
                        ->join('orders', 'orders.id', '=', 'order_items.order_id')
                        ->where('orders.status', OrderStatus::Completed->value)
                        ->where('orders.completed_at', '>=', now()->subDays(90))
                        ->groupBy('order_items.product_id')
                        ->selectRaw('order_items.product_id, SUM(order_items.quantity) AS sold'),
                    'sales', 'sales.product_id', '=', 'products.id'
                )
                ->orderByRaw('COALESCE(sales.sold, 0) DESC')
                ->orderBy('products.name'),
        };
    }
}
