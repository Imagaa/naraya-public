<?php

namespace App\Livewire\Admin\Stock;

use App\Enums\StockTransferKind;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Support\ActiveLine;
use App\Support\Stock\StockTransfers;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/** Barang masuk: admin records goods received into the warehouse of the active line. */
class GoodsIn extends Component
{
    use HasAdminShell, WithPagination;

    /** qty = whole units (e.g. lembar), pcs = loose pieces; stock is kept in pcs. */
    private const EMPTY_ROW = ['product_id' => '', 'qty' => '', 'pcs' => ''];

    public array $rows = [self::EMPTY_ROW];

    public string $note = '';

    public string $flash = '';

    public function addRow(): void
    {
        $this->rows[] = self::EMPTY_ROW;
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows) ?: [self::EMPTY_ROW];
    }

    public function save(StockTransfers $transfers): void
    {
        $this->resetErrorBag();
        $this->flash = '';

        $items = [];
        foreach ($this->rows as $i => $row) {
            $product = (string) ($row['product_id'] ?? '');
            $qtyRaw = (string) ($row['qty'] ?? '');
            $pcsRaw = (string) ($row['pcs'] ?? '');
            if ($product === '' && $qtyRaw === '' && $pcsRaw === '') {
                continue; // a blank row is simply ignored
            }
            if ($product === '') {
                throw ValidationException::withMessages(["rows.{$i}.product_id" => 'Pilih barangnya.']);
            }
            $units = $qtyRaw === '' ? 0 : filter_var($qtyRaw, FILTER_VALIDATE_INT);
            $loose = $pcsRaw === '' ? 0 : filter_var($pcsRaw, FILTER_VALIDATE_INT);
            $pcs = $units === false || $loose === false || $units < 0 || $loose < 0 ? 0 : $units * (Product::find($product)?->ppu() ?? 1) + $loose;
            if ($pcs < 1) {
                throw ValidationException::withMessages(["rows.{$i}.qty" => 'Jumlah minimal 1 pcs.']);
            }
            $items[(int) $product] = ($items[(int) $product] ?? 0) + $pcs;
        }

        $transfer = $transfers->receive(ActiveLine::current(), $items, auth()->user(), trim($this->note) ?: null);

        $this->rows = [self::EMPTY_ROW];
        $this->note = '';
        $this->flash = "Barang masuk dicatat: {$transfer->items->count()} barang, {$transfer->totalQuantity()} pcs.";
        $this->resetPage();
    }

    public function render()
    {
        $line = ActiveLine::current();

        return view('livewire.admin.stock.goods-in', [
            'line' => $line,
            'products' => Product::forLine($line)->orderBy('name')->get(['id', 'name', 'stock', 'unit', 'pieces_per_unit']),
            'history' => StockTransfer::forLine($line)->where('kind', StockTransferKind::Masuk)
                ->with(['items.product:id,name,unit,pieces_per_unit', 'creator:id,name'])->latest('id')->paginate(10),
        ])->layout('layouts.admin');
    }

    public function paginationView(): string
    {
        return 'components.admin.pagination';
    }
}
