<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\SalesNotice;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

function pushBody(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKeyValue', 'auth' => 'AuthSecret'], 'content_encoding' => 'aes128gcm'];
}

it('stores one subscription per endpoint, even when posted twice', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)->postJson(salesUrl('/push-subscriptions'), pushBody())->assertNoContent();
    $this->actingAs($sales)->postJson(salesUrl('/push-subscriptions'), pushBody())->assertNoContent();

    expect($sales->pushSubscriptions()->count())->toBe(1);
});

it('removes a subscription', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $this->actingAs($sales)->postJson(salesUrl('/push-subscriptions'), pushBody());

    $this->actingAs($sales)->deleteJson(salesUrl('/push-subscriptions'), ['endpoint' => pushBody()['endpoint']])->assertNoContent();

    expect($sales->pushSubscriptions()->count())->toBe(0);
});

it('validates the subscription payload', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Sales]))
        ->postJson(salesUrl('/push-subscriptions'), ['endpoint' => 'not-a-url'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

it('forbids non-sales users', function () {
    $this->actingAs(User::factory()->create(['role' => Role::User]))
        ->postJson(salesUrl('/push-subscriptions'), pushBody())
        ->assertForbidden();
});

it('pushes to devices only when the user has subscribed', function () {
    Notification::fake();
    $quiet = User::factory()->create(['role' => Role::Sales]);
    $subscribed = User::factory()->create(['role' => Role::Sales]);
    $subscribed->updatePushSubscription(pushBody()['endpoint'], 'BPublicKeyValue', 'AuthSecret', 'aes128gcm');

    $quiet->notify(new SalesNotice('A', 'b', '/x', 'bell'));
    $subscribed->notify(new SalesNotice('A', 'b', '/x', 'bell'));

    Notification::assertSentTo($quiet, SalesNotice::class, fn ($n, array $channels) => $channels === ['database']);
    Notification::assertSentTo($subscribed, SalesNotice::class, fn ($n, array $channels) => in_array(WebPushChannel::class, $channels, true));
});

it('builds a push payload with the link and the unread count including this notice', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $sales->notify(new SalesNotice('Lama', 'b', '/x', 'bell'));
    $notice = new SalesNotice('Tugas kirim baru', 'Pesanan #7 untuk Toko A', '/deliveries/7', 'package');
    $sales->notify($notice); // database channel stores it first, as in a real send

    $payload = $notice->toWebPush($sales, $notice)->toArray();

    expect($payload['title'])->toBe('Tugas kirim baru')
        ->and($payload['body'])->toBe('Pesanan #7 untuk Toko A')
        ->and($payload['icon'])->toBe('/icons/icon-192.png')
        ->and($payload['badge'])->toBe('/icons/badge-96.png')
        ->and($payload['data'])->toBe(['url' => '/deliveries/7', 'unread' => 2]);
});

it('forgets this phone on logout but keeps the user\'s other devices', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $sales->updatePushSubscription('https://fcm.googleapis.com/fcm/send/this-phone', 'k', 't', 'aes128gcm');
    $sales->updatePushSubscription('https://fcm.googleapis.com/fcm/send/other-phone', 'k', 't', 'aes128gcm');

    $this->actingAs($sales)
        ->post(salesUrl('/logout'), ['push_endpoint' => 'https://fcm.googleapis.com/fcm/send/this-phone'])
        ->assertRedirect('http://'.config('app.sales_domain').'/login');

    expect($sales->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://fcm.googleapis.com/fcm/send/other-phone']);
});

it('puts the device endpoint field in the Lainnya logout form', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Sales]))->get(salesUrl('/lainnya'))
        ->assertSee('id="sales-logout"', false)
        ->assertSee('name="push_endpoint"', false);
});

it('clears this device\'s push subscription, cached pages and badge before logging out', function () {
    // Shared with the admin portal: resources/js/logout-cleanup.js, on every form[data-push-logout].
    $js = file_get_contents(resource_path('js/logout-cleanup.js'));

    expect(file_get_contents(resource_path('js/sales-pwa.js')))->toContain("import './logout-cleanup'")
        ->and(file_get_contents(resource_path('views/livewire/sales/more.blade.php')))->toContain('data-push-logout');
    expect($js)->toContain('form[data-push-logout]')
        ->toContain('unsubscribe()')
        ->toContain("startsWith('naraya-sales-pages-')")
        ->toContain('clearAppBadge');
});
