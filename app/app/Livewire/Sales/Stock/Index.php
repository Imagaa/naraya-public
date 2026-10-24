<?php

namespace App\Livewire\Sales\Stock;

use App\Enums\AttendanceType;
use App\Models\Product;
use App\Support\AbsenCamera;
use App\Support\ActiveLine;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\StockTransfers;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The stock sheet: pick from the warehouse (masuk = check-in,
 * ambil = more during the day) or what I carry (retur) — choose, then confirm. Check-in confirms with the
 * camera: the attendance submit records the pickup with it.
 */
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 15;

    /** masuk | ambil | retur */
    public string $mode = 'ambil';

    /** Filters the list by name, SKU or barcode. */
    public string $search = '';

    /** pilih | konfirmasi | foto (check-in) */
    public string $step = 'pilih';

    /** product_id => quantity typed */
    public array $qty = [];

    public string $note = '';

    public function mount(string $mode = 'ambil'): void
    {
        $this->mode = in_array($mode, ['masuk', 'ambil', 'retur'], true) ? $mode : 'ambil';
    }

    public function updatedSearch(): void
    {
        $this->resetPage('hal');
    }

    public function back(): void
    {
        $this->step = 'pilih';
    }

    public function review(): void
    {
        $this->resetErrorBag();
        $items = $this->items(); // check-in takes goods too: a sales day without stock is not a sales day
        $limits = $this->limits();

        foreach ($items as $id => $qty) {
            if (! isset($limits[$id])) {
                throw ValidationException::withMessages(["qty.{$id}" => 'Barang ini tidak tersedia.']);
            }
            if ($qty > $limits[$id]) {
                throw ValidationException::withMessages([
                    "qty.{$id}" => ($this->mode === 'retur' ? 'Yang dibawa' : 'Stok gudang').' hanya '.Product::find($id)?->qtyLabel($limits[$id]).'.',
                ]);
            }
        }

        $this->step = $this->mode === 'masuk' ? 'foto' : 'konfirmasi';
    }

    public function confirm(StockTransfers $transfers): void
    {
        abort_if($this->mode === 'masuk', 404); // check-in confirms through the camera
        $line = ActiveLine::current();
        $items = $this->items();
        $user = auth()->user();

        try {
            $transfer = $this->mode === 'ambil'
                ? $transfers->pickup($user, $line, $items, trim($this->note) ?: null)
                : $transfers->returnGoods($user, $line, $items, trim($this->note) ?: null);
        } catch (ValidationException $e) {
            $this->step = 'pilih'; // the warehouse changed since review: show the message on the form
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => [str_replace('items', 'qty', $k) => $m])->all());
        }

        $done = $this->mode === 'ambil' ? 'Barang diambil' : 'Retur ke gudang dicatat';
        $this->dispatch('sheet-done', message: "{$done}: {$transfer->totalQuantity()} pcs.");
    }

    /** @return array<int, int> product_id => qty for every row with a positive number */
    private function items(bool $allowNone = false): array
    {
        $items = [];
        foreach ($this->qty as $id => $value) {
            $n = filter_var($value, FILTER_VALIDATE_INT);
            if ($value !== '' && $value !== null && ($n === false || $n < 0)) {
                throw ValidationException::withMessages(["qty.{$id}" => 'Isi angka 0 atau lebih.']);
            }
            if ($n > 0) {
                $items[(int) $id] = $n;
            }
        }
        if ($items === [] && ! $allowNone) {
            throw ValidationException::withMessages(['qty' => 'Isi jumlah minimal satu barang.']);
        }

        return $items;
    }

    /** @return array<int, int> what can be taken (warehouse stock) or returned (carried) per product */
    private function limits(): array
    {
        $line = ActiveLine::current();

        return $this->mode === 'retur'
            ? CarriedStock::for(auth()->user(), $line)->all()
            : Product::forLine($line)->where('is_active', true)->where('stock', '>', 0)->pluck('stock', 'id')->map(fn ($s) => (int) $s)->all();
    }

    public function render()
    {
        $limits = $this->limits();
        $chosen = in_array($this->step, ['konfirmasi', 'foto'], true) ? $this->items(allowNone: $this->mode === 'masuk') : [];
        $term = trim($this->search);

        return view('livewire.sales.stock.index', [
            'line' => ActiveLine::current(),
            'limits' => $limits,
            // the list to pick from: searched, by name, a page at a time; typed quantities live in $qty by id, so they survive paging
            'choices' => $this->step === 'pilih' ? Product::whereKey(array_keys($limits))
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")->orWhere('barcode', 'like', "%{$term}%")))
                ->orderBy('name')->paginate(self::PER_PAGE, ['id', 'name', 'unit', 'pieces_per_unit'], 'hal') : null,
            'pickedCount' => count(array_filter($this->qty, fn ($v) => (int) $v > 0)),
            'chosen' => $chosen,
            'chosenProducts' => Product::whereKey(array_keys($chosen))->get(['id', 'name', 'unit', 'pieces_per_unit'])->keyBy('id'),
            'cfg' => $this->step === 'foto' ? AbsenCamera::config(auth()->user(), AttendanceType::Masuk, $chosen) : null,
        ]);
    }
}
