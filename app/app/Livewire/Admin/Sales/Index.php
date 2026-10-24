<?php

namespace App\Livewire\Admin\Sales;

use App\Livewire\Admin\Concerns\FiltersByWorkQueue;
use App\Livewire\Concerns\HasCrudList;
use App\Models\User;
use App\Notifications\SalesNotice;
use App\Support\Sales\SalesPerformance;
use Livewire\Component;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Admin\Concerns\HasFilterPills;

class Index extends Component
{
    use FiltersByWorkQueue, HasAdminShell, HasFilterPills, HasCrudList;

    /** A sales user asked for a new WhatsApp number on their Akun page; it goes live only now. */
    public function approvePhone(int $userId): void
    {
        $profile = User::findOrFail($userId)->salesProfile;
        abort_unless($profile?->pending_phone, 404);

        $profile->update(['phone' => $profile->pending_phone, 'pending_phone' => null, 'pending_phone_requested_at' => null]);
        $profile->user->notify(new SalesNotice('Nomor WhatsApp disetujui', "Nomor {$profile->phone} sekarang aktif.", '/akun', 'circle-check', 'success'));
    }

    public function rejectPhone(int $userId): void
    {
        $profile = User::findOrFail($userId)->salesProfile;
        abort_unless($profile?->pending_phone, 404);

        $rejected = $profile->pending_phone;
        $profile->update(['pending_phone' => null, 'pending_phone_requested_at' => null]);
        $profile->user->notify(new SalesNotice('Perubahan nomor ditolak', "Nomor {$rejected} tidak disetujui; nomor lama tetap dipakai.", '/akun', 'circle-x', 'warning'));
    }

    public function render()
    {
        $salesUsers = $this->applyQueue(User::query(), 'admin.sales.index')
            ->currentSales()
            ->with('salesProfile.area')->withCount('assignedStores')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filter === 'active', fn ($q) => $q->whereHas('salesProfile', fn ($p) => $p->where('is_active', true)))
            ->when($this->filter === 'inactive', fn ($q) => $q->whereHas('salesProfile', fn ($p) => $p->where('is_active', false)))
            ->orderBy('name')
            ->paginate($this->perPage);

        // ponytail: full month numbers per listed sales (a page at a time); add a nightly summary table if the team grows large
        $performance = $salesUsers->getCollection()->mapWithKeys(fn (User $s) => [$s->id => app(SalesPerformance::class)->month($s, now())]);

        return view('livewire.admin.sales.index', ['salesUsers' => $salesUsers, 'performance' => $performance, 'counts' => $this->counts(), 'queueLabel' => $this->queueLabel('admin.sales.index')])
            ->layout('layouts.admin');
    }

    /** Pill counts (same scopes as the filters). */
    private function counts(): array
    {
        return [
            'all' => User::currentSales()->count(),
            'active' => User::currentSales()->whereHas('salesProfile', fn ($p) => $p->where('is_active', true))->count(),
            'inactive' => User::currentSales()->whereHas('salesProfile', fn ($p) => $p->where('is_active', false))->count(),
        ];
    }
}
