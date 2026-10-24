<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\OpensNotifications;
use Livewire\Component;

/** Topbar bell with a popover of the latest notices; polls so new ones show without a reload. */
class NotificationBell extends Component
{
    use OpensNotifications;

    public function render()
    {
        $user = auth()->user();

        return view('livewire.admin.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->limit(8)->get(),
        ]);
    }
}
