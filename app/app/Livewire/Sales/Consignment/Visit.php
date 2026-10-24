<?php

namespace App\Livewire\Sales\Consignment;

use App\Enums\NotePaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Sales\Consignments;
use App\Support\Stock\CarriedStock;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Kunjungan titip jual: 1 hitung sisa (what is left comes back
 * with the sales person; the rest was sold, its setoran shown per item as it is typed), 2 titip a fresh lot from
 * carried stock, 3 bayar what was sold. One save, all or nothing (Consignments::visit).
 */
class Visit extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $storeId;

    /** hitung | titip | bayar */
    public string $step = 'hitung';

    /** product_id => pcs counted at the store, prefilled with what the books say */
    public array $remaining = [];

    /** product_id => pcs left from carried stock */
    public array $titip = [];

    public string $method = 'cash';

    public $proof = null;

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
        $this->store();
        $this->remaining = Consignments::atStore($storeId, ActiveLine::current())->all();
    }

    public function next(): void
    {
        $this->resetErrorBag();
        if ($this->step === 'hitung') {
            foreach (Consignments::atStore($this->storeId, ActiveLine::current()) as $id => $had) {
                $left = filter_var($this->remaining[$id] ?? $had, FILTER_VALIDATE_INT);
                if ($left === false || $left < 0) {
                    throw ValidationException::withMessages(["remaining.{$id}" => 'Isi angka 0 atau lebih.']);
                }
                if ($left > $had) {
                    throw ValidationException::withMessages(["remaining.{$id}" => 'Lebih dari yang dititip.']);
                }
            }
            $this->step = 'titip';

            return;
        }
        $carried = CarriedStock::for(auth()->user(), ActiveLine::current());
        foreach ($this->titip as $id => $qty) {
            if ((int) $qty > ($carried[$id] ?? 0)) {
                throw ValidationException::withMessages(["titip.{$id}" => 'Melebihi yang Anda bawa.']);
            }
        }
        $this->step = 'bayar';
    }

    public function back(): void
    {
        $this->step = $this->step === 'bayar' ? 'titip' : 'hitung';
    }

    public function save(Consignments $consignments): void
    {
        $this->resetErrorBag();
        try {
            $note = $consignments->visit(auth()->user(), $this->storeId, ActiveLine::current(), $this->remaining, $this->titip,
                NotePaymentMethod::from($this->method), $this->method === 'cash' ? null : $this->proof);
        } catch (ValidationException $e) {
            $keys = array_keys($e->errors());
            $this->step = collect($keys)->contains(fn ($k) => str_starts_with($k, 'titip')) ? 'titip'
                : (collect($keys)->contains(fn ($k) => str_starts_with($k, 'remaining')) ? 'hitung' : 'bayar');
            throw $e;
        }

        $note
            ? $this->dispatch('sheet-open', sheet: 'nota-detail', for: $note->id) // the receipt for what was sold
            : $this->dispatch('sheet-done', message: 'Kunjungan '.$this->store()->name.' dicatat.');
    }

    private function store(): User
    {
        $store = User::with('buyerProfile')->findOrFail($this->storeId);
        abort_unless((int) $store->buyerProfile?->sales_user_id === auth()->id() && $store->buyerProfile->consignment_opened_at, 404);

        return $store;
    }

    public function render()
    {
        $line = ActiveLine::current();
        $before = Consignments::atStore($this->storeId, $line);
        $carried = CarriedStock::for(auth()->user(), $line);
        $products = Product::whereKey($before->keys()->merge($carried->keys()))->orderBy('name')->get()->keyBy('id');
        $left = $before->map(fn ($had, $id) => min($had, max(0, (int) ($this->remaining[$id] ?? $had))));
        $sold = $before->map(fn ($had, $id) => $had - $left[$id])->filter(fn ($q) => $q > 0);

        return view('livewire.sales.consignment.visit', [
            'store' => $this->store(),
            'line' => $line,
            'before' => $before,
            'lastVisit' => Consignments::lastVisitAt($this->storeId, $line),
            'carried' => $carried,
            'products' => $products,
            'sold' => $sold,
            'total' => (int) $sold->sum(fn ($q, $id) => $products[$id]->valueOf($q)),
            'returns' => $left->filter(fn ($q) => $q > 0),
            'left' => collect($this->titip)->map(fn ($q) => (int) $q)->filter(fn ($q) => $q > 0),
            'methods' => NotePaymentMethod::choices(),
            'transferTo' => config('naraya.transfer_to'),
        ]);
    }
}
