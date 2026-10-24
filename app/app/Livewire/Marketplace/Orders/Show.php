<?php

namespace App\Livewire\Marketplace\Orders;

use App\Livewire\Marketplace\Concerns\HasMarketplaceShell;
use App\Livewire\Marketplace\Concerns\ReordersOrders;
use App\Models\Order;
use App\Support\Orders\TransferPayments;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use HasMarketplaceShell, ReordersOrders, WithFileUploads;

    public Order $order;

    /** The BNI transfer proof photo (TransferPayments). */
    public $proof;

    public function mount(Order $order): void
    {
        abort_unless($order->buyer_id === auth()->id(), 403);

        $this->order = $order;
    }

    public function confirmReceived(): void
    {
        $this->order->confirmReceivedByBuyer();
        $this->order->refresh();
    }

    public function uploadProof(TransferPayments $payments): void
    {
        $this->validate(['proof' => 'required|image|max:5120'], ['proof.required' => 'Pilih foto bukti transfer.', 'proof.image' => 'Bukti harus berupa foto.']);
        $payments->upload($this->order, $this->proof);
        $this->reset('proof');
        $this->order->refresh();
    }

    public function reorder()
    {
        return $this->reorderInto($this->order);
    }

    public function render()
    {
        $this->order->loadMissing(['items.product.category', 'salesUser']);

        return view('livewire.marketplace.orders.show', [
            'cartCount' => $this->cartCount(),
            'progress' => $this->order->buyerProgress(),
        ])->layout('layouts.marketplace')->title('Pesanan #'.$this->order->id.' — Naraya');
    }
}
