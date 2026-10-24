<?php

namespace App\Livewire\Marketplace;

use App\Livewire\Concerns\OpensNotifications;
use Livewire\Component;

/**
 * Its own component (not markup in x-mp.header), so its wire: actions
 * bind to this component on every page that shows the header.
 */
class NotificationBell extends Component
{
    use OpensNotifications;

    public function render()
    {
        $user = auth()->user();

        return view('livewire.marketplace.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->limit(8)->get(),
        ]);
    }
}
