<?php

namespace App\Livewire\Sales\Consignment;

use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Sales\Consignments;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * First time a store is opened in the app: the goods of ours already there, left from before
 *. Until this is saved the store's visits are locked.
 */
class Opening extends Component
{
    use WithPagination;

    #[Locked]
    public int $storeId;

    public string $search = '';

    /** product_id => pcs */
    public array $qty = [];

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    public function updatedSearch(): void
    {
        $this->resetPage('hal');
    }

    public function save(Consignments $consignments): void
    {
        $this->resetErrorBag();
        $consignments->open(auth()->user(), $this->storeId, ActiveLine::current(), $this->qty);
        $this->dispatch('sheet-open', sheet: 'toko', for: $this->storeId); // back to the store, now unlocked
    }

    public function render()
    {
        $store = User::with('buyerProfile')->findOrFail($this->storeId);
        abort_unless((int) $store->buyerProfile?->sales_user_id === auth()->id(), 404);
        $term = trim($this->search);

        return view('livewire.sales.consignment.opening', [
            'store' => $store,
            'line' => ActiveLine::current(),
            'products' => Product::forLine(ActiveLine::current())->where('is_active', true)
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")->orWhere('barcode', 'like', "%{$term}%")))
                ->orderBy('name')->paginate(15, ['id', 'name', 'unit', 'pieces_per_unit'], 'hal'),
            'picked' => count(array_filter($this->qty, fn ($v) => (int) $v > 0)),
        ]);
    }
}
