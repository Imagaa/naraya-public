<?php

namespace App\Livewire\Marketplace;

use App\Livewire\Marketplace\Concerns\HasMarketplaceShell;
use App\Livewire\Marketplace\Concerns\ReordersOrders;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Orders extends Component
{
    use HasMarketplaceShell, ReordersOrders, WithPagination;

    /** all | new | processing | shipped | completed | cancelled (also covers rejected_by_user) */
    public string $filter = 'all';

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->resetPage();
    }

    public function confirmReceived(int $orderId): void
    {
        $this->ownOrder($orderId)->confirmReceivedByBuyer();
    }

    public function reorder(int $orderId)
    {
        return $this->reorderInto($this->ownOrder($orderId));
    }

    /** Fits a third of a phone screen: 2940000 → "2,9 jt", 385000 → "385 rb". */
    public static function shortRupiah(int $n): string
    {
        return match (true) {
            $n >= 1_000_000 => str_replace('.', ',', rtrim(rtrim(number_format($n / 1_000_000, 1, '.', ''), '0'), '.')).' jt',
            $n >= 1_000 => number_format(intdiv($n, 1_000), 0, ',', '.').' rb',
            default => (string) $n,
        };
    }

    private function ownOrder(int $orderId): Order
    {
        $order = Order::findOrFail($orderId);
        abort_unless($order->buyer_id === auth()->id(), 403);

        return $order;
    }

    public function render()
    {
        $query = Order::where('buyer_id', auth()->id());

        if ($this->filter === 'cancelled') {
            $query->whereIn('status', ['cancelled', 'rejected_by_user']);
        } elseif ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }

        $orders = $query->with(['items.product.category', 'salesUser'])->orderByDesc('id')->paginate(10);

        // Plain query builder (not the Eloquent model) so `status` comes back
        // as the raw string, not cast to the OrderStatus enum — needed as a
        // plain array key below.
        $counts = DB::table('orders')
            ->where('buyer_id', auth()->id())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $zone = config('app.business_timezone');
        $month = Order::where('buyer_id', auth()->id())
            ->whereNotIn('status', ['cancelled', 'rejected_by_user'])
            ->where('created_at', '>=', now()->timezone($zone)->startOfMonth()->utc());

        return view('livewire.marketplace.orders', [
            'orders' => $orders,
            'statusCounts' => [
                'all' => $counts->sum(),
                'new' => $counts->get('new', 0),
                'processing' => $counts->get('processing', 0),
                'shipped' => $counts->get('shipped', 0),
                'completed' => $counts->get('completed', 0),
                'cancelled' => $counts->get('cancelled', 0) + $counts->get('rejected_by_user', 0),
            ],
            'monthLabel' => now()->timezone($zone)->locale('id')->translatedFormat('F Y'),
            'monthCount' => (clone $month)->count(),
            'monthSpent' => self::shortRupiah((int) (clone $month)->sum('total')),
            'monthDone' => (clone $month)->where('status', 'completed')->count(),
            'cartCount' => $this->cartCount(),
        ])->layout('layouts.marketplace')->title('Pesanan — Naraya');
    }
}
