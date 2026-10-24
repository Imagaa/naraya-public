<?php

namespace App\Livewire\Admin\Routes;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\DeliveryRoute;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Rute sales: every sales person's routes, newest first, with the stops of one. */
class Index extends Component
{
    use HasAdminShell, WithPagination;

    #[Url(as: 'sales')]
    public ?int $salesId = null;

    #[Url(as: 'rute')]
    public ?int $routeId = null;

    public function updatedSalesId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.admin.routes.index', [
            'salesPeople' => User::currentSales()->orderBy('name')->get(['id', 'name']),
            'routes' => DeliveryRoute::with('salesUser.salesProfile')->withCount([
                'stops', 'stops as visited_count' => fn ($q) => $q->where('status', 'visited'), 'stops as closed_count' => fn ($q) => $q->where('status', 'closed'),
            ])->when($this->salesId, fn ($q) => $q->where('sales_id', $this->salesId))->latest('id')->paginate(20),
            'selected' => $this->routeId ? DeliveryRoute::with(['salesUser:id,name', 'stops.buyer:id,name', 'stops.notes'])->find($this->routeId) : null,
        ])->layout('layouts.admin');
    }
}
