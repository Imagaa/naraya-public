<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMidtransNotification;
use App\Models\MidtransNotification;
use App\Models\Order;
use App\Services\Midtrans\MidtransClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransClient $client): JsonResponse
    {
        $payload = $request->all();
        $signatureValid = $client->verifySignature($payload);

        $orderId = null;
        if (! empty($payload['order_id'])) {
            $order = Order::where('midtrans_order_id', $payload['order_id'])->first();
            $orderId = $order?->id;
        }

        $notification = MidtransNotification::create([
            'order_id' => $orderId,
            'raw_payload' => json_encode($payload),
            'signature_valid' => $signatureValid,
        ]);

        if (! $signatureValid) {
            return response()->json(['message' => 'invalid signature'], 403);
        }

        ProcessMidtransNotification::dispatch($notification->id);

        return response()->json(['message' => 'ok']);
    }
}
