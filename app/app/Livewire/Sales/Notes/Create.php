<?php

namespace App\Livewire\Sales\Notes;

use App\Enums\NotePaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Support\ActiveLine;
use App\Support\Sales\DeliveryRoutes;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\CarriedStock;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/** New sales note sheet: store → items from what I carry → payment → confirm; then its receipt. */
class Create extends Component
{
    use WithFileUploads;

    public ?int $buyerId = null;

    /** The route stop this note is made from, so the route can mark it visited. */
    public ?int $stopId = null;

    /** product_id => quantity typed */
    public array $qty = [];

    public string $method = 'cash';

    public $proof = null;

    /** 'pilih' | 'konfirmasi' */
    public string $step = 'pilih';

    public function mount(?int $toko = null): void
    {
        $this->buyerId = $toko;
    }

    public function back(): void
    {
        $this->step = 'pilih';
    }

    public function review(): void
    {
        $this->resetErrorBag();
        $this->validate([
            'buyerId' => ['required', Rule::exists('buyer_profiles', 'user_id')->where('sales_user_id', auth()->id())],
            'method' => ['required', Rule::in(array_map(fn ($m) => $m->value, NotePaymentMethod::choices()))],
            'proof' => [NotePaymentMethod::tryFrom($this->method)?->needsProof() ? 'required' : 'nullable', 'image', 'max:5120'],
        ], [
            'buyerId.required' => 'Pilih tokonya.',
            'buyerId.exists' => 'Toko ini tidak ditugaskan ke Anda.',
            'proof.required' => 'Ambil foto bukti '.(NotePaymentMethod::tryFrom($this->method)?->label() ?? 'pembayaran').'.',
        ]);

        $carried = CarriedStock::for(auth()->user(), ActiveLine::current());
        foreach ($this->items() as $id => $qty) {
            if ($qty > ($carried[$id] ?? 0)) {
                throw ValidationException::withMessages(["qty.{$id}" => 'Yang Anda bawa hanya '.(Product::find($id)?->qtyLabel($carried[$id] ?? 0) ?? 0).'.']);
            }
        }

        $this->step = 'konfirmasi';
    }

    public function save(SalesNotes $notes): void
    {
        // the store's stop on today's route, so the route marks it visited
        $this->stopId = DeliveryRoutes::active(auth()->user())?->stops()->where('buyer_id', $this->buyerId)->where('status', 'pending')->value('id');

        try {
            $note = $notes->create(
                auth()->user(),
                ActiveLine::current(),
                (int) $this->buyerId,
                $this->items(),
                NotePaymentMethod::from($this->method),
                $this->method === 'cash' ? null : $this->proof,
                $this->stopId,
            );
        } catch (ValidationException $e) {
            $this->step = 'pilih';
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => [str_replace('items', 'qty', $k) => $m])->all());
        }

        $this->dispatch('sheet-open', sheet: 'nota-detail', for: $note->id); // the receipt, ready to share or print
    }

    /** @return array<int, int> */
    private function items(): array
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
        if ($items === []) {
            throw ValidationException::withMessages(['qty' => 'Isi jumlah minimal satu barang.']);
        }

        return $items;
    }

    public function render()
    {
        $line = ActiveLine::current();
        $carried = CarriedStock::for(auth()->user(), $line);
        $products = Product::whereKey($carried->keys())->orderBy('name')->get(['id', 'name', 'price', 'unit', 'pieces_per_unit'])->keyBy('id');
        $chosen = $this->step === 'konfirmasi' ? $this->items() : [];

        return view('livewire.sales.notes.create', [
            'line' => $line,
            'stores' => User::assignedTo(auth()->id())->orderBy('name')->get(['id', 'name']),
            'carried' => $carried,
            'products' => $products,
            'methods' => NotePaymentMethod::choices(),
            'transferTo' => config('naraya.transfer_to'),
            'chosen' => $chosen,
            'total' => collect($chosen)->map(fn ($q, $id) => $products[$id]?->valueOf($q) ?? 0)->sum(),
            'store' => $this->buyerId ? User::find($this->buyerId) : null,
        ]);
    }
}
