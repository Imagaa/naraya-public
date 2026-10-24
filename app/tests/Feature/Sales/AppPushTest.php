<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\SalesNotice;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// Pushes to the Android app through FCM.

function fakeFcmCredentials(): void
{
    // Windows PHP finds no default openssl.cnf; it ships one under extras/
    $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
    $options = is_file($cnf) ? ['config' => $cnf] : [];
    openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048] + $options), $pem, null, $options);
    $path = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($path, json_encode([
        'project_id' => 'naraya-test', 'client_email' => 'push@naraya-test.iam.gserviceaccount.com',
        'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));
    config(['services.fcm.credentials' => $path]);
}

it('stores the app token once and hands it to whoever logs in on that phone', function () {
    $budi = User::factory()->create(['role' => Role::Sales]);
    $ani = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($budi)->postJson(salesUrl('/perangkat'), ['token' => 'tok-1'])->assertNoContent();
    $this->actingAs($budi)->postJson(salesUrl('/perangkat'), ['token' => 'tok-1'])->assertNoContent();
    $this->actingAs($ani)->postJson(salesUrl('/perangkat'), ['token' => 'tok-1'])->assertNoContent();

    expect($budi->deviceTokens()->count())->toBe(0)
        ->and($ani->deviceTokens()->pluck('token')->all())->toBe(['tok-1']);
});

it('forgets the app token on logout', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $sales->deviceTokens()->create(['token' => 'tok-1']);

    $this->actingAs($sales)->post(salesUrl('/logout'), ['fcm_token' => 'tok-1']);

    expect($sales->deviceTokens()->count())->toBe(0);
});

it('sends a notice to the app with its link, and drops a token FCM no longer knows', function () {
    fakeFcmCredentials();
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => fn (Request $r) => $r['message']['token'] === 'gone'
            ? Http::response(['error' => ['status' => 'NOT_FOUND']], 404)
            : Http::response(['name' => 'projects/naraya-test/messages/1']),
    ]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    $sales->deviceTokens()->create(['token' => 'live']);
    $sales->deviceTokens()->create(['token' => 'gone']);

    $sales->notify(new SalesNotice('Pesanan baru', 'Toko Sumber Rejeki', '/?buka=pesanan', 'bell'));

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/projects/naraya-test/messages:send')
        && $r->hasHeader('Authorization', 'Bearer ya29.test')
        && $r['message']['token'] === 'live'
        && $r['message']['notification']['title'] === 'Pesanan baru'
        && $r['message']['data'] === ['url' => '/?buka=pesanan', 'unread' => '1']);
    expect($sales->deviceTokens()->pluck('token')->all())->toBe(['live'])
        ->and($sales->notifications()->count())->toBe(1);
});

it('still saves the notice when FCM is down or not set up', function () {
    Http::fake(['*' => Http::response('', 500)]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    $sales->deviceTokens()->create(['token' => 'live']);

    config(['services.fcm.credentials' => null]);
    $sales->notify(new SalesNotice('A', 'b', '/x', 'bell'));
    fakeFcmCredentials();
    $sales->notify(new SalesNotice('B', 'b', '/x', 'bell'));

    expect($sales->notifications()->count())->toBe(2)
        ->and($sales->deviceTokens()->count())->toBe(1);
});
