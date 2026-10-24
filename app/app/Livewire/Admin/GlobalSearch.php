<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Ctrl K search across the four things admin looks up when a store calls:
 * orders, stores, products and sales. Five hits per group.
 */
class GlobalSearch extends Component
{
    public string $term = '';

    /** Enter jumps to the first hit, like a command palette. */
    public function go()
    {
        $first = collect($this->results())->flatten(1)->first();

        return $first ? $this->redirect($first['url']) : null;
    }

    /** @return array<string, list<array{label: string, meta: string, url: string}>> */
    public function results(): array
    {
        $term = trim($this->term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.$term.'%';
        // Phones are stored as 62… (stores) or 0… (sales); match on the digits after either prefix.
        $phoneLike = Phone::searchPattern($term);
        $byPhone = fn (Builder $q, string $relation) => $phoneLike
            ? $q->orWhereHas($relation, fn ($p) => $p->where('phone', 'like', $phoneLike))
            : $q;

        return array_filter([
            'Order' => Order::with('buyer')
                ->when(ctype_digit($term),
                    fn ($q) => $q->whereKey((int) $term),
                    fn ($q) => $q->whereHas('buyer', fn ($b) => $b->where('name', 'like', $like)))
                ->latest('id')->limit(5)->get()
                ->map(fn (Order $o) => ['label' => "#{$o->id} · {$o->buyer?->name}", 'meta' => $o->status->label(), 'url' => route('admin.orders.show', $o)])
                ->all(),
            'Toko' => User::where('role', Role::User)->with('buyerProfile')
                ->where(fn ($q) => $byPhone($q->where('name', 'like', $like), 'buyerProfile'))
                ->orderBy('name')->limit(5)->get()
                ->map(fn (User $u) => ['label' => $u->name, 'meta' => (string) $u->buyerProfile?->phone, 'url' => route('admin.buyers.edit', $u)])
                ->all(),
            'Produk' => Product::forLine(\App\Support\ActiveLine::current())->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like))
                ->orderBy('name')->limit(5)->get()
                ->map(fn (Product $p) => ['label' => $p->name, 'meta' => (string) $p->sku, 'url' => route('admin.products.edit', $p)])
                ->all(),
            'Sales' => User::currentSales()->with('salesProfile')
                ->where(fn ($q) => $byPhone($q->where('name', 'like', $like), 'salesProfile'))
                ->orderBy('name')->limit(5)->get()
                ->map(fn (User $u) => ['label' => $u->name, 'meta' => (string) $u->salesProfile?->phone, 'url' => route('admin.sales.edit', $u)])
                ->all(),
        ]);
    }

    public function render()
    {
        return view('livewire.admin.global-search', ['groups' => $this->results()]);
    }
}
