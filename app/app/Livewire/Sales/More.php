<?php

namespace App\Livewire\Sales;

use App\Livewire\Sales\Concerns\HostsSheets;
use Livewire\Component;

/** Lainnya — the sales app's second page: account and history, each opened as a sheet; PWA controls, logout. */
class More extends Component
{
    use HostsSheets;

    protected function sheets(): array
    {
        return ['akun', 'notifikasi', 'absensi', 'nota-saya', 'nota-detail', 'kunjungan', 'target', 'riwayat-stok', 'setor'];
    }

    public function render()
    {
        return view('livewire.sales.more', [
            'unread' => auth()->user()->unreadNotifications()->count(),
        ])->layout('layouts.sales');
    }
}
