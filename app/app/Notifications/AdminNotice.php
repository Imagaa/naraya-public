<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Admin bell notices, pushed to the owner's phone as well
 *; urgent ones (money or customer trust) stay on
 * screen until tapped.
 */
class AdminNotice extends SalesNotice
{
    /** DatabaseSeeder fires the same observers; muted there so the bell starts clean. */
    public static bool $muted = false;

    public function __construct(string $title, string $body, string $url, string $icon, string $tone = 'info', public bool $urgent = false)
    {
        parent::__construct($title, $body, $url, $icon, $tone);
    }

    /** Every owner notice is pushed to the phone; urgent ones stay up. */
    public function via(object $notifiable): array
    {
        return parent::via($notifiable);
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        $message = parent::toWebPush($notifiable, $notification)->icon('/icons/admin-192.png');

        return $this->urgent ? $message->requireInteraction() : $message;
    }

    /** Every admin except the one who caused it (a guest event, auth id null, reaches all). */
    public static function toAdmins(string $title, string $body, string $url, string $icon, string $tone = 'info', bool $urgent = false): void
    {
        if (self::$muted) {
            return;
        }

        $admins = User::where('role', Role::Admin)
            ->when(auth()->id(), fn ($q, $id) => $q->whereKeyNot($id))
            ->get();

        Notification::send($admins, new self($title, $body, $url, $icon, $tone, $urgent));
    }
}
