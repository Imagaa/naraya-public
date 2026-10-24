<?php

namespace App\Livewire\Admin\Products;

use App\Livewire\Concerns\HasCrudList;
use App\Models\Product;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Index extends Component
{
    use HasCrudList, HasAdminShell;

    #[Url(as: 'stock')]
    public string $stockFilter = 'all';

    public function setStockFilter(string $filter): void
    {
        $this->stockFilter = $filter;
        $this->resetPage();
    }

    public function render()
    {
        $products = Product::query()
            ->forLine(\App\Support\ActiveLine::current())
            ->with('category')
            ->when($this->search, fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")->orWhere('sku', 'like', "%{$this->search}%")))
            ->when($this->stockFilter === 'out', fn ($q) => $q->where('is_active', true)->where('stock', 0))
            ->when($this->stockFilter === 'low', fn ($q) => $q->where('is_active', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock'))
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.admin.products.index', ['products' => $products, 'counts' => $this->counts()])
            ->layout('layouts.admin');
    }

    /** Pill counts (same scopes as the filters). */
    private function counts(): array
    {
        return [
            'all' => Product::forLine(\App\Support\ActiveLine::current())->count(),
            'out' => Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->where('stock', 0)->count(),
            'low' => Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock')->count(),
        ];
    }
}
