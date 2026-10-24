<?php

namespace App\Livewire\Admin\Stock;

use App\Enums\StockTransferKind;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Admin\Concerns\HasFilterPills;
use App\Livewire\Concerns\HasCrudList;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\ActiveLine;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Pengambilan sales: every pickup, return and stock-check shortfall of the active line. */
class Transfers extends Component
{
    use HasAdminShell, HasCrudList, HasFilterPills;

    private const KINDS = [StockTransferKind::Ambil, StockTransferKind::Retur, StockTransferKind::Opname];

    #[Url(as: 'sales')]
    public ?int $salesId = null;

    public function updatedSalesId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $line = ActiveLine::current();
        $base = fn () => StockTransfer::forLine($line)->whereIn('kind', self::KINDS)
            ->when($this->salesId, fn ($q) => $q->where('sales_id', $this->salesId));

        $transfers = $base()
            ->when(StockTransferKind::tryFrom($this->filter), fn ($q, $kind) => $q->where('kind', $kind))
            ->with(['items.product:id,name,unit,pieces_per_unit', 'salesUser:id,name', 'creator:id,name'])
            ->latest('id')
            ->paginate($this->perPage);

        $counts = ['all' => $base()->count()];
        foreach (self::KINDS as $kind) {
            $counts[$kind->value] = $base()->where('kind', $kind)->count();
        }

        return view('livewire.admin.stock.transfers', [
            'line' => $line,
            'transfers' => $transfers,
            'counts' => $counts,
            'kinds' => self::KINDS,
            'salesPeople' => User::activeSales()->orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.admin');
    }
}
