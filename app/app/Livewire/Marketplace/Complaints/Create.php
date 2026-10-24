<?php

namespace App\Livewire\Marketplace\Complaints;

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\OrderStatus;
use App\Models\Complaint;
use App\Models\Order;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public Order $order;

    public string $alasan = '';

    public $foto;

    public function mount(Order $order): void
    {
        abort_unless($order->buyer_id === auth()->id(), 403);
        abort_unless($order->status === OrderStatus::Completed, 403);

        $this->order = $order;
    }

    public function submit()
    {
        $this->validate([
            'alasan' => 'required|string|max:1000',
            'foto' => 'required|image|max:5120',
        ]);

        $path = $this->foto->store('complaints', 's3');

        Complaint::create([
            'order_id' => $this->order->id,
            'tipe' => ComplaintType::KomplainPascaSelesai,
            'alasan' => $this->alasan,
            'foto_path' => $path,
            'status' => ComplaintStatus::Diajukan,
        ]);

        return redirect()->route('orders.index');
    }

    public function render()
    {
        return view('livewire.marketplace.complaints.create', [
            'order' => $this->order->loadMissing('items.product.category'),
            'progress' => $this->order->buyerProgress(),
        ])->layout('layouts.marketplace')->title('Komplain — Naraya');
    }
}
