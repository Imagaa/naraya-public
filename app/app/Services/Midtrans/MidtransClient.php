<?php

namespace App\Services\Midtrans;

use App\Exceptions\MidtransChargeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MidtransClient
{
    private function baseUrl(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    private function http()
    {
        return Http::withBasicAuth((string) config('services.midtrans.server_key'), '')
            ->acceptJson()
            ->retry(3, 1000, fn ($exception) => $exception instanceof ConnectionException, throw: false);
    }

    public function charge(string $orderId, int $grossAmount, string $paymentType): array
    {
        $payload = [
            'payment_type' => $paymentType,
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
        ];

        try {
            $response = $this->http()->post("{$this->baseUrl()}/v2/charge", $payload);
        } catch (ConnectionException $e) {
            throw new MidtransChargeException("Midtrans charge failed: {$e->getMessage()}", 0, $e);
        }

        if ($response->failed()) {
            throw new MidtransChargeException(
                'Midtrans charge failed: '.($response->json('status_message') ?? $response->status())
            );
        }

        return $response->json();
    }

    public function checkStatus(string $orderId): array
    {
        $response = $this->http()->get("{$this->baseUrl()}/v2/{$orderId}/status");

        if ($response->failed()) {
            throw new MidtransChargeException(
                'Midtrans status check failed: '.($response->json('status_message') ?? $response->status())
            );
        }

        return $response->json();
    }

    public function verifySignature(array $payload): bool
    {
        $expected = hash('sha512',
            ($payload['order_id'] ?? '')
            .($payload['status_code'] ?? '')
            .($payload['gross_amount'] ?? '')
            .config('services.midtrans.server_key')
        );

        return hash_equals($expected, $payload['signature_key'] ?? '');
    }
}
