<?php

namespace App\Livewire\Admin\Buyers;

use App\Livewire\Admin\Concerns\FiltersByWorkQueue;
use App\Enums\Role;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Concerns\HasCrudList;
use App\Models\User;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    use FiltersByWorkQueue, HasAdminShell, HasCrudList;

    /** '' (all) | pending | rejected — self-registrations waiting on, or turned down by, an Admin. */
    #[Url]
    public string $status = '';

    /** The store whose goods "Cek stok" shows. */
    public ?int $stockFor = null;

    public function showStock(int $id): void
    {
        $this->stockFor = $id;
        $this->dispatch('store-stock-open');
    }

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, ['pending', 'rejected'], true) ? $status : '';
        $this->resetPage();
    }

    public function render()
    {
        $buyers = $this->applyQueue(User::query(), 'admin.buyers.index')
            ->where('role', Role::User)
            ->with(['buyerProfile.area', 'buyerProfile.salesUser.salesProfile'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->status, fn ($q) => $q->whereHas('buyerProfile', fn ($p) => $p->where('status', $this->status)))
            ->orderBy('name')
            ->paginate($this->perPage);

        $stockStore = $this->stockFor ? User::where('role', Role::User)->find($this->stockFor) : null;

        return view('livewire.admin.buyers.index', ['buyers' => $buyers, 'counts' => $this->counts(), 'queueLabel' => $this->queueLabel('admin.buyers.index'),
            'stockStore' => $stockStore, 'stock' => $stockStore ? \App\Support\Sales\Consignments::valuation($stockStore->id) : collect()])
            ->layout('layouts.admin');
    }

    /** Pill counts (same scopes as the filters). */
    private function counts(): array
    {
        return [
            '' => User::where('role', Role::User)->count(),
            'pending' => User::where('role', Role::User)->whereHas('buyerProfile', fn ($p) => $p->where('status', 'pending'))->count(),
            'rejected' => User::where('role', Role::User)->whereHas('buyerProfile', fn ($p) => $p->where('status', 'rejected'))->count(),
        ];
    }
}
