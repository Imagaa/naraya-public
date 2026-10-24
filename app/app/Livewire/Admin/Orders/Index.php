<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\OrderStatus;
use App\Livewire\Admin\Concerns\FiltersByWorkQueue;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Concerns\HasCrudList;
use App\Models\Order;
use App\Support\Admin\WorkQueues;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    use FiltersByWorkQueue, HasAdminShell, HasCrudList;

    /** 'all', 'closed' (cancelled + rejected, as the marketplace groups them) or one OrderStatus value. */
    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    private const CLOSED = [OrderStatus::Cancelled, OrderStatus::RejectedByUser];

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function render()
    {
        $statusCounts = DB::table('orders')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $term = trim($this->search);

        $query = $this->applyQueue(Order::query(), 'admin.orders.index')
            ->with(['buyer', 'salesUser'])
            ->when($term !== '', fn ($q) => $this->applySearch($q, $term))
            ->when($this->statusFilter === 'closed', fn ($q) => $q->whereIn('status', self::CLOSED))
            ->when(! in_array($this->statusFilter, ['all', 'closed'], true), fn ($q) => $q->where('status', $this->statusFilter));

        // A queue and the New tab are both "work waiting", so the longest-waiting
        // order comes first; everything else defaults to newest first.
        $query = $this->statusFilter === 'new' || $this->queue !== '' ? $query->oldest() : $query->orderByDesc('id');
        $orders = $query->paginate($this->perPage);

        return view('livewire.admin.orders.index', [
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'flags' => WorkQueues::orderFlags($orders->pluck('id')->all()),
            'inFlight' => (int) collect([OrderStatus::New, OrderStatus::Processing, OrderStatus::Shipped])->sum(fn ($s) => $statusCounts[$s->value] ?? 0),
            'queueLabel' => $this->queueLabel('admin.orders.index'),
        ])->layout('layouts.admin');
    }

    /** Exact order number, store name, or the store's phone however it was typed. */
    private function applySearch(Builder $query, string $term): Builder
    {
        $phone = Phone::searchPattern($term);

        return $query->where(function (Builder $w) use ($term, $phone) {
            if (ctype_digit($term)) {
                $w->orWhere('orders.id', (int) $term);
            }
            $w->orWhereHas('buyer', fn ($b) => $b->where('name', 'like', "%{$term}%"));
            if ($phone) {
                $w->orWhereHas('buyer.buyerProfile', fn ($p) => $p->where('phone', 'like', $phone));
            }
        });
    }
}
