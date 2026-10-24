<?php

namespace App\Livewire\Admin\Stock;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\StockTransfers;
use Livewire\Component;

/** Stok bawaan sales: what each sales person carries in the active line, and the stock check. */
class Carried extends Component
{
    use HasAdminShell;

    public ?int $checkingId = null;

    /** product_id => counted quantity */
    public array $counted = [];

    public string $checkNote = '';

    public string $flash = '';

    public function startCheck(int $salesId): void
    {
        $sales = User::activeSales()->findOrFail($salesId);
        $this->checkingId = $sales->id;
        $this->counted = CarriedStock::for($sales, ActiveLine::current())->all();
        $this->checkNote = '';
        $this->flash = '';
        $this->resetErrorBag();
    }

    public function cancelCheck(): void
    {
        $this->reset('checkingId', 'counted', 'checkNote');
        $this->resetErrorBag();
    }

    public function saveCheck(StockTransfers $transfers): void
    {
        $sales = User::findOrFail($this->checkingId);
        $counted = array_map(fn ($q) => (int) $q, $this->counted);

        $transfer = $transfers->stockCheck($sales, ActiveLine::current(), $counted, auth()->user(), trim($this->checkNote) ?: null);

        $this->flash = $transfer
            ? "Cek stok {$sales->name} dicatat: kurang {$transfer->totalQuantity()} pcs (Rp ".number_format($transfer->totalValue(), 0, ',', '.').').'
            : "Cek stok {$sales->name}: semua cocok.";
        $this->reset('checkingId', 'counted', 'checkNote');
    }

    public function render()
    {
        $line = ActiveLine::current();
        $sales = User::activeSales()->with('salesProfile')->orderBy('name')->get();
        $carried = $sales->mapWithKeys(fn (User $s) => [$s->id => CarriedStock::for($s, $line)]);
        $products = Product::whereKey($carried->flatMap(fn ($c) => $c->keys())->unique()->all())->get(['id', 'name', 'price', 'unit', 'pieces_per_unit'])->keyBy('id');

        return view('livewire.admin.stock.carried', [
            'line' => $line,
            'rows' => $sales->map(fn (User $s) => [
                'sales' => $s,
                'items' => $carried[$s->id],
                'qty' => (int) $carried[$s->id]->sum(),
                'value' => (int) $carried[$s->id]->map(fn ($q, $id) => $products[$id]?->valueOf($q) ?? 0)->sum(),
            ]),
            'products' => $products,
        ])->layout('layouts.admin');
    }
}
