<?php

namespace App\Livewire\Marketplace;

use App\Livewire\Concerns\OpensNotifications;
use Livewire\Component;
use Livewire\WithPagination;

class Notifications extends Component
{
    use OpensNotifications, WithPagination;

    public function render()
    {
        $user = auth()->user();
        $notifications = $user->notifications()->paginate(20);
        $today = now(config('app.business_timezone'));

        return view('livewire.marketplace.notifications', [
            'notifications' => $notifications,
            'groups' => $notifications->getCollection()->groupBy(
                fn ($n) => $n->created_at->copy()->timezone(config('app.business_timezone'))->isSameDay($today) ? 'Hari ini' : 'Sebelumnya'
            ),
            'unread' => $user->unreadNotifications()->count(),
        ])->layout('layouts.marketplace')->title('Notifikasi — Naraya');
    }
}
