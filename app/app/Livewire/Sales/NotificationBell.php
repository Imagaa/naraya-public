<?php

namespace App\Livewire\Sales;

use Livewire\Component;

/** Topbar bell. A plain link to /notifikasi (phones first), polling so the count and the app badge stay fresh. */
class NotificationBell extends Component
{
    public function render()
    {
        return view('livewire.sales.notification-bell', [
            'unread' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
