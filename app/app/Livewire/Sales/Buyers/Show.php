<?php

namespace App\Livewire\Sales\Buyers;

use App\Models\DeliveryRouteStop;
use App\Models\SalesNote;
use App\Models\User;
use App\Models\Visit;
use App\Support\ActiveLine;
use App\Support\Sales\Consignments;
use App\Support\Sales\DeliveryRoutes;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The store sheet: who, where, contact; "toko tutup" on today's
 * route; log a visit; investment; the store's login; its latest notes.
 */
class Show extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $buyerId;

    public bool $akun = false;

    public bool $closing = false;

    public $closedPhoto = null;

    public string $closedNote = '';

    /** Only the sales user the store is assigned to may see it; anyone else gets a 404, not a hint it exists. */
    public function mount(int|User $buyer, bool $akun = false): void
    {
        $this->buyerId = $buyer instanceof User ? $buyer->id : $buyer;
        $this->akun = $akun;
        $this->buyer();
    }

    public function saveClose(DeliveryRoutes $routes): void
    {
        $this->resetErrorBag();
        $this->validate(['closedPhoto' => 'nullable|image|max:8192', 'closedNote' => 'nullable|string|max:500']);
        $stop = $this->pendingStop();
        abort_unless($stop, 404);
        $routes->close($stop, $this->closedPhoto, $this->closedNote);
        $this->dispatch('sheet-done', message: $this->buyer()->name.' dicatat tutup.');
    }

    private function buyer(): User
    {
        $buyer = User::with('buyerProfile')->findOrFail($this->buyerId);
        abort_unless((int) $buyer->buyerProfile?->sales_user_id === auth()->id(), 404);

        return $buyer;
    }

    private function pendingStop(): ?DeliveryRouteStop
    {
        return DeliveryRoutes::active(auth()->user())?->stops()->where('buyer_id', $this->buyerId)->where('status', 'pending')->first();
    }

    public function render()
    {
        $buyer = $this->buyer();

        return view('livewire.sales.buyers.show', [
            'buyer' => $buyer,
            'profile' => $buyer->buyerProfile,
            'stop' => $this->pendingStop(),
            'opened' => $buyer->buyerProfile->consignment_opened_at !== null,
            'atStore' => ($at = Consignments::atStore($buyer->id, ActiveLine::current())),
            'atStoreProducts' => \App\Models\Product::whereKey($at->keys())->get(['id', 'name', 'unit', 'pieces_per_unit'])->keyBy('id'),
            'lastVisit' => Consignments::lastVisitAt($buyer->id, ActiveLine::current()),
            'visits' => Visit::where('buyer_id', $buyer->id)->where('sales_id', auth()->id())->latest('visited_at')->take(5)->get(),
            'notes' => SalesNote::forLine(ActiveLine::current())->where('buyer_id', $buyer->id)
                ->where('sales_id', auth()->id())->latest('id')->take(5)->get(),
        ]);
    }
}
