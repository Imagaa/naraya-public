<?php

namespace App\Livewire\Sales\Deliveries;

use App\Enums\CashCollectionStatus;
use App\Enums\OrderStatus;
use App\Models\CashCollection;
use App\Models\Order;
use App\Models\WarehouseSetting;
use App\Services\OpenRouteService\RouteClient;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public Order $order;

    public $receivedPhoto;

    public $deliveredPhoto;

    public bool $cashRecountConfirmed = false;

    /** @var array<int, array{0: float, 1: float}> */
    public array $routePoints = [];

    public function mount(Order $order): void
    {
        abort_unless($order->sales_id === auth()->id(), 403);

        $this->order = $order->load('items.product', 'buyer.buyerProfile');
    }

    /** Called from the map once the browser reports (or fails to report) Sales's live location. */
    public function fetchRoute(?float $originLat, ?float $originLng): void
    {
        abort_unless($this->order->delivery_lat !== null && $this->order->delivery_lng !== null, 404);

        if ($originLat === null || $originLng === null) {
            $warehouse = WarehouseSetting::first();
            $originLat = $warehouse->lat;
            $originLng = $warehouse->lng;
        }

        $this->routePoints = app(RouteClient::class)->route(
            $originLat, $originLng,
            $this->order->delivery_lat, $this->order->delivery_lng,
        );
    }

    /**
     * Sales's confirmation of receiving the goods Gudang just handed over —
     * gated on Gudang having confirmed the handover first.
     */
    public function confirmReceivedFromGudang()
    {
        abort_unless($this->order->status === OrderStatus::Processing, 403);
        abort_unless($this->order->gudang_handover_at !== null, 403);
        abort_unless($this->order->sales_received_at === null, 403);

        $this->validate(
            ['receivedPhoto' => 'required|image|max:5120'],
            ['receivedPhoto.required' => 'Foto barang diterima wajib diunggah.']
        );

        $path = $this->receivedPhoto->store('sales-receipts', 's3');

        $this->order->update([
            'sales_received_at' => now(),
            'sales_received_photo_path' => $path,
        ]);
        $this->order->refresh();
    }

    public function markShipped()
    {
        abort_unless($this->order->status === OrderStatus::Processing, 403);
        abort_unless($this->order->sales_received_at !== null, 403);

        $this->order->update(['status' => OrderStatus::Shipped]);
        $this->order->refresh();
    }

    /**
     * Sales's confirmation that the goods reached the buyer. Quantities are
     * not editable here — the final quantity was already fixed at the
     * Gudang-to-Sales handover, so this step is purely "mark it done";
     * Admin verifies afterward (or the buyer disputes it — see
     * Deliveries\Reject). This used to jump straight to Completed — it no
     * longer does: Admin closes the order only after the buyer has also
     * confirmed receipt (or the 3-day auto-confirm window passes).
     */
    public function confirmDeliveredToBuyer()
    {
        abort_unless($this->order->status === OrderStatus::Shipped, 403);
        abort_unless($this->order->sales_delivered_at === null, 403);

        $rules = ['deliveredPhoto' => 'required|image|max:5120'];
        $messages = ['deliveredPhoto.required' => 'Foto barang diterima pembeli wajib diunggah.'];

        if ($this->order->payment_method === 'cash') {
            $rules['cashRecountConfirmed'] = 'accepted';
            $messages['cashRecountConfirmed.accepted'] = 'Konfirmasi hitung ulang uang tunai wajib dicentang.';
        }

        $this->validate($rules, $messages);

        $photoPath = $this->deliveredPhoto->store('sales-deliveries', 's3');

        DB::transaction(function () use ($photoPath) {
            $collectedAmount = 0;

            foreach ($this->order->items as $item) {
                $item->update(['delivered_quantity' => $item->quantity]);
                $collectedAmount += $item->price * $item->quantity;
            }

            $this->order->update([
                'sales_delivered_at' => now(),
                'sales_delivered_photo_path' => $photoPath,
            ]);

            if ($this->order->payment_method === 'cash') {
                CashCollection::create([
                    'order_id' => $this->order->id,
                    'sales_id' => auth()->id(),
                    'amount' => max(0, $collectedAmount - $this->order->credit_applied),
                    'status' => CashCollectionStatus::Held,
                    'collected_at' => now(),
                ]);
            }
        });

        $this->order->refresh();
    }

    public function render()
    {
        return view('livewire.sales.deliveries.show')->layout('layouts.sales');
    }
}
