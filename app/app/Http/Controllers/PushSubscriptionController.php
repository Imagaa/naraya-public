<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Saves/removes this device's Web Push subscription (sent by resources/js/sales-pwa.js). */
class PushSubscriptionController
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => 'required|url|max:500',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
            'content_encoding' => 'nullable|in:aesgcm,aes128gcm',
        ]);

        // Keyed by endpoint: the same device posting again updates its row instead of adding one.
        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['content_encoding'] ?? 'aes128gcm',
        );

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $request->validate(['endpoint' => 'required|url|max:500']);
        $request->user()->deletePushSubscription($request->input('endpoint'));

        return response()->noContent();
    }
}
