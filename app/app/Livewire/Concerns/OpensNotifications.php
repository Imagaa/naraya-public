<?php

namespace App\Livewire\Concerns;

/** Shared by the marketplace and sales notification lists; both serve the list at /notifikasi on their own domain. */
trait OpensNotifications
{
    /** Scoped to the user's own notifications, so a forged id from someone else 404s. */
    public function openNotification(string $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->redirect($notification->data['url'] ?? '/notifikasi');
    }

    public function markAllNotificationsRead(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
    }
}
