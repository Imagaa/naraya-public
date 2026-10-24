<?php

namespace App\Livewire\Sales\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Component;

class Index extends Component
{
    /** Lower sorts first — how urgently Sales needs to act on this stage. */
    private const STAGE_PRIORITY = [
        'ready_to_receive' => 0,
        'ready_to_ship' => 1,
        'in_transit' => 2,
        'awaiting_handover' => 3,
        'awaiting_buyer_confirm' => 4,
    ];

    public function render()
    {
        $orders = Order::where('sales_id', auth()->id())
            ->whereIn('status', [OrderStatus::Processing, OrderStatus::Shipped])
            ->with('buyer.buyerProfile')
            ->get();

        // One band per stage, most urgent first; empty stages are simply absent.
        $groups = $orders->groupBy(fn (Order $order) => $order->salesStage())
            ->sortBy(fn ($group, string $stage) => self::STAGE_PRIORITY[$stage]);

        return view('livewire.sales.orders.index', ['groups' => $groups])->layout('layouts.sales');
    }
}
