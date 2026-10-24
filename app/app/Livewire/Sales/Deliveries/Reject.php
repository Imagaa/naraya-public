<?php

namespace App\Livewire\Sales\Deliveries;

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\OrderStatus;
use App\Models\Complaint;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * A dedicated page for "Barang Ditolak Pembeli" — deliberately its own
 * route rather than a reveal-in-place form on the delivery page, since a
 * toggled section next to the normal completion flow read as easy to
 * trigger by mistake.
 */
class Reject extends Component
{
    use WithFileUploads;

    public Order $order;

    public $rejectionPhoto;

    public string $rejectionReason = '';

    public function mount(Order $order): void
    {
        abort_unless($order->sales_id === auth()->id(), 403);
        abort_unless($order->status === OrderStatus::Shipped, 403);
        abort_unless($order->sales_delivered_at === null, 403);

        $this->order = $order->load('items.product');
    }

    public function rejectDelivery()
    {
        abort_unless($this->order->status === OrderStatus::Shipped, 403);
        abort_unless($this->order->sales_delivered_at === null, 403);

        $this->validate([
            'rejectionReason' => 'required|string|max:500',
            'rejectionPhoto' => 'required|image|max:5120',
        ], ['rejectionPhoto.required' => 'Foto barang ditolak wajib diunggah.']);

        $photoPath = $this->rejectionPhoto->store('complaints', 's3');

        $this->order->update(['status' => OrderStatus::RejectedByUser]);

        Complaint::create([
            'order_id' => $this->order->id,
            'tipe' => ComplaintType::TolakSaatTerima,
            'alasan' => $this->rejectionReason,
            'foto_path' => $photoPath,
            'status' => ComplaintStatus::Diajukan,
        ]);

        return redirect()->route('sales.orders.index');
    }

    public function render()
    {
        return view('livewire.sales.deliveries.reject')->layout('layouts.sales');
    }
}
