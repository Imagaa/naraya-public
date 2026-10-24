<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Every buyer notification. The observers in App\Observers
 * decide when to send one and what it says; this class only stores it.
 */
class BuyerNotice extends Notification
{
    /** @param string $tone info | success | warning */
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $icon,
        public string $tone = 'info',
    ) {}

    /** The buyer caused this change themselves (e.g. cancelled their own order), so telling them is noise. */
    public static function skipFor(?User $recipient): bool
    {
        return $recipient === null || auth()->id() === $recipient->id;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon,
            'tone' => $this->tone,
        ];
    }
}
