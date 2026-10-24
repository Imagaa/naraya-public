<?php

namespace App\Notifications;

use App\Support\Mobile\Fcm;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Every sales-portal notification. Same payload as
 * BuyerNotice; additionally pushed to the user's devices: browsers that enabled
 * it on Lainnya (Web Push) and phones running the Android app (FCM). Sent
 * synchronously (no queue worker in production).
 */
class SalesNotice extends BuyerNotice
{
    public function via(object $notifiable): array
    {
        // database first: the pushes count unread rows, which must include this one.
        return array_merge(
            ['database'],
            $notifiable->pushSubscriptions()->exists() ? [WebPushChannel::class] : [],
            $notifiable->deviceTokens()->exists() ? [Fcm::class] : [],
        );
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-96.png')
            ->tag((string) $this->id)
            ->data(['url' => $this->url, 'unread' => $notifiable->unreadNotifications()->count()]);
    }

    /** FCM HTTP v1 message minus its token; the app opens data.url when the notification is tapped. */
    public function toFcm(object $notifiable): array
    {
        $unread = $notifiable->unreadNotifications()->count();

        return [
            'notification' => ['title' => $this->title, 'body' => $this->body],
            'data' => ['url' => $this->url, 'unread' => (string) $unread], // FCM data values are strings
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'naraya', // created by the app (resources/js/sales.js)
                    'icon' => 'ic_stat_naraya',
                    'tag' => (string) $this->id,
                    'notification_count' => $unread, // launcher badge
                ],
            ],
        ];
    }
}
