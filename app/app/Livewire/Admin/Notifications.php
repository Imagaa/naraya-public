<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Livewire\Concerns\OpensNotifications;
use App\Notifications\AdminNotice;
use Livewire\Component;
use Livewire\WithPagination;

class Notifications extends Component
{
    use HasAdminShell, OpensNotifications, WithPagination;

    /** "Kirim notifikasi uji": proves the push reaches this admin's devices. */
    public function sendTest(): void
    {
        auth()->user()->notify(new AdminNotice('Notifikasi uji', 'Kalau pesan ini muncul di HP, notifikasi Naraya Admin sudah aktif.', route('admin.notifications', absolute: false), 'bell', 'info', urgent: true));
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.admin.notifications', [
            'notifications' => $user->notifications()->paginate(20),
            'unread' => $user->unreadNotifications()->count(),
        ])->layout('layouts.admin');
    }
}
