<?php

namespace App\Livewire\Sales\Stock;

use App\Enums\StockTransferKind;
use App\Models\StockTransfer;
use App\Support\ActiveLine;
use Livewire\Component;

/** "Riwayat stok" sheet on Lainnya: my last pickups, returns and stock checks in the active line. */
class History extends Component
{
    public function render()
    {
        return view('livewire.sales.stock.history', [
            'line' => ActiveLine::current(),
            'history' => StockTransfer::forLine(ActiveLine::current())->where('sales_id', auth()->id())
                ->whereIn('kind', [StockTransferKind::Ambil, StockTransferKind::Retur, StockTransferKind::Opname])
                ->with('items.product:id,name,unit,pieces_per_unit')->latest('id')->limit(20)->get(),
        ]);
    }
}
