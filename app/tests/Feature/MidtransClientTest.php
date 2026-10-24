<?php

use App\Exceptions\MidtransChargeException;
use App\Services\Midtrans\MidtransClient;
use Illuminate\Support\Facades\Http;

it('charges successfully and returns the Midtrans response', function () {
    Http::fake([
        '*/v2/charge' => Http::response([
            'transaction_id' => 'txn-1', 'transaction_status' => 'pending',
            'qr_string' => 'fake-qr-data',
        ], 200),
    ]);

    $result = app(MidtransClient::class)->charge('NR-1', 15000, 'qris');

    expect($result['transaction_id'])->toBe('txn-1');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/charge')
        && $request['transaction_details']['order_id'] === 'NR-1'
        && $request['transaction_details']['gross_amount'] === 15000);
});

it('retries on connection failure and eventually succeeds', function () {
    $attempt = 0;
    Http::fake([
        '*/v2/charge' => function () use (&$attempt) {
            $attempt++;

            if ($attempt < 2) {
                throw new \Illuminate\Http\Client\ConnectionException('timeout');
            }

            return Http::response(['transaction_id' => 'txn-2', 'transaction_status' => 'pending'], 200);
        },
    ]);

    $result = app(MidtransClient::class)->charge('NR-2', 10000, 'gopay');

    expect($result['transaction_id'])->toBe('txn-2');
    expect($attempt)->toBe(2);
});

it('throws MidtransChargeException when charge ultimately fails', function () {
    Http::fake(['*/v2/charge' => Http::response(['status_message' => 'error'], 500)]);

    expect(fn () => app(MidtransClient::class)->charge('NR-3', 10000, 'qris'))
        ->toThrow(MidtransChargeException::class);
});

it('verifies a correctly-signed webhook payload', function () {
    $serverKey = config('services.midtrans.server_key');
    $payload = ['order_id' => 'NR-1', 'status_code' => '200', 'gross_amount' => '15000.00'];
    $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey);

    expect(app(MidtransClient::class)->verifySignature($payload))->toBeTrue();
});

it('rejects a tampered webhook payload', function () {
    $payload = ['order_id' => 'NR-1', 'status_code' => '200', 'gross_amount' => '15000.00', 'signature_key' => 'wrong'];

    expect(app(MidtransClient::class)->verifySignature($payload))->toBeFalse();
});
