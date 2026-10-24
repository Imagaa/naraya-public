<?php

namespace App\Livewire\Admin\Reports;

use App\Livewire\Admin\Reports\Concerns\ExportsTable;
use App\Livewire\Admin\Reports\Concerns\HasDateRangeFilter;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Stok extends Component
{
    use HasDateRangeFilter, ExportsTable, HasAdminShell;

    private const TYPES = ['in' => 'Masuk', 'out' => 'Keluar', 'correction' => 'Koreksi', 'write_off' => 'Dihapus / rusak'];

    public function reportTitle(): string
    {
        return 'Laporan Stok';
    }

    public function headings(): array
    {
        return ['Tanggal', 'Produk', 'Jenis', 'Jumlah', 'Referensi', 'Oleh'];
    }

    public function rows(): Collection
    {
        return StockMovement::whereBetween('created_at', [$this->rangeStart(), $this->rangeEnd()])
            ->whereHas('product', fn ($q) => $q->forLine(\App\Support\ActiveLine::current()))
            ->with(['product', 'user'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($movement) => [
                $movement->created_at->format('d/m/Y'),
                $movement->product->name,
                self::TYPES[$movement->type->value] ?? $movement->type->value,
                // with its unit: "+200 lembar", "−2 lembar 30 pcs"
                ($movement->quantity < 0 ? '−' : '+').$movement->product->qtyLabel(abs((int) $movement->quantity)),
                $movement->reference ?? '-',
                $movement->user?->name ?? '-',
            ]);
    }

    public function render()
    {
        $products = Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'stock', 'min_stock', 'price', 'unit', 'pieces_per_unit']);

        return view('livewire.admin.reports.stok', [
            'products' => $products, // the warehouse now, whatever the period
            'stockValue' => (int) $products->sum(fn ($p) => $p->valueOf((int) $p->stock)),
            'stockOut' => Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->where('stock', 0)->count(),
            'stockLow' => Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock')->count(),
        ])->layout('layouts.admin');
    }
}
