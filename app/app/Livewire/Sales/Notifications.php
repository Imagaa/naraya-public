<?php

namespace App\Livewire\Sales;

use App\Livewire\Concerns\OpensNotifications;
use Livewire\Component;
use Livewire\WithPagination;

class Notifications extends Component
{
    use OpensNotifications, WithPagination;

    public function render()
    {
        $user = auth()->user();

        return view('livewire.sales.notifications', [
            'notifications' => $user->notifications()->paginate(20),
            'unread' => $user->unreadNotifications()->count(),
        ])->layout('layouts.sales');
    }
}
