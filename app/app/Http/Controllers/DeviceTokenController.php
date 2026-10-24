<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Saves this phone's FCM token (sent by the Android app, resources/js/sales.js); logout forgets it (LogoutController). */
class DeviceTokenController
{
    public function store(Request $request): Response
    {
        $data = $request->validate(['token' => 'required|string|max:255']);

        // Keyed by token: whoever logs in on the phone now gets its pushes.
        DeviceToken::updateOrCreate(['token' => $data['token']], ['user_id' => $request->user()->id]);

        return response()->noContent();
    }
}
