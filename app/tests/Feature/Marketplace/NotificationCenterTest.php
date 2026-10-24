<?php

use App\Enums\Role;
use App\Livewire\Marketplace\NotificationBell;
use App\Livewire\Marketplace\Notifications;
use App\Models\User;
use App\Notifications\BuyerNotice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

function centerBuyer(): User
{
    return User::factory()->create(['role' => Role::User]);
}

function sendNotice(User $user, string $title = 'Pesanan dikirim', string $url = '/pesanan/1'): void
{
    $user->notify(new BuyerNotice($title, 'Pesanan #1 sedang diantar', $url, 'truck'));
}

it('shows the unread count, capped at 9+', function () {
    $buyer = centerBuyer();
    sendNotice($buyer);
    sendNotice($buyer);

    Livewire::actingAs($buyer)->test(NotificationBell::class)
        ->assertSeeHtml('<span class="bell-badge num">2</span>');

    foreach (range(1, 8) as $i) {
        sendNotice($buyer);
    }

    Livewire::actingAs($buyer)->test(NotificationBell::class)
        ->assertSeeHtml('<span class="bell-badge num">9+</span>');
});

it('shows no badge and the empty state with no notifications', function () {
    Livewire::actingAs(centerBuyer())->test(NotificationBell::class)
        ->assertDontSeeHtml('bell-badge')
        ->assertSee('Belum ada notifikasi.');
});

it('lists the latest 8 notifications, newest first', function () {
    $buyer = centerBuyer();
    foreach (range(1, 9) as $i) {
        $this->travel(1)->minutes();
        sendNotice($buyer, "Kabar {$i}");
    }

    Livewire::actingAs($buyer)->test(NotificationBell::class)
        ->assertSeeInOrder(['Kabar 9', 'Kabar 2'])
        ->assertDontSeeHtml('<b>Kabar 1</b>');
});

it('marks one read and opens its link', function () {
    $buyer = centerBuyer();
    sendNotice($buyer, url: '/pesanan/42');
    $id = $buyer->notifications()->first()->id;

    Livewire::actingAs($buyer)->test(NotificationBell::class)
        ->call('openNotification', $id)
        ->assertRedirect('/pesanan/42');

    expect($buyer->unreadNotifications()->count())->toBe(0);
});

it('refuses to open another buyer\'s notification', function () {
    $owner = centerBuyer();
    sendNotice($owner);
    $id = $owner->notifications()->first()->id;

    // Livewire's test client rethrows it; over HTTP the handler renders a 404.
    expect(fn () => Livewire::actingAs(centerBuyer())->test(NotificationBell::class)->call('openNotification', $id))
        ->toThrow(ModelNotFoundException::class);

    expect($owner->unreadNotifications()->count())->toBe(1);
});

it('marks all read', function () {
    $buyer = centerBuyer();
    $other = centerBuyer();
    sendNotice($buyer);
    sendNotice($buyer);
    sendNotice($other);

    Livewire::actingAs($buyer)->test(NotificationBell::class)
        ->call('markAllNotificationsRead')
        ->assertDontSeeHtml('bell-badge');

    expect($buyer->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});

it('puts the bell in the header for buyers only', function () {
    $this->actingAs(centerBuyer())->blade('<x-mp.header />')->assertSee('aria-label="Notifikasi', false);
    $this->actingAs(User::factory()->create(['role' => Role::Admin]))->blade('<x-mp.header />')->assertDontSee('aria-label="Notifikasi', false);
    auth()->logout();
    $this->blade('<x-mp.header />')->assertDontSee('aria-label="Notifikasi', false);
});

it('requires a logged-in buyer', function () {
    $this->get(route('notifications'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]))->get(route('notifications'))->assertForbidden();
    $this->actingAs(centerBuyer())->get(route('notifications'))->assertOk()->assertSee('Notifikasi');
});

it('groups notifications into today and earlier, newest first', function () {
    $buyer = centerBuyer();
    $this->travelTo(now()->subDays(2));
    sendNotice($buyer, 'Kabar lama');
    $this->travelBack();
    sendNotice($buyer, 'Kabar baru');

    Livewire::actingAs($buyer)->test(Notifications::class)
        ->assertSeeInOrder(['Hari ini', 'Kabar baru', 'Sebelumnya', 'Kabar lama']);
});

it('paginates 20 per page', function () {
    $buyer = centerBuyer();
    foreach (range(1, 21) as $i) {
        $this->travel(1)->minutes();
        sendNotice($buyer, "Kabar ke-{$i}x");
    }

    Livewire::actingAs($buyer)->test(Notifications::class)
        ->assertSee('Kabar ke-21x')
        ->assertDontSee('Kabar ke-1x');
});

it('shows the empty state and opens or clears from the page', function () {
    $empty = centerBuyer();
    Livewire::actingAs($empty)->test(Notifications::class)->assertSee('Belum ada notifikasi.');

    $buyer = centerBuyer();
    sendNotice($buyer, url: '/hadiah');
    sendNotice($buyer);

    Livewire::actingAs($buyer)->test(Notifications::class)
        ->assertSee('Tandai semua dibaca')
        ->call('markAllNotificationsRead')
        ->assertDontSee('Tandai semua dibaca');
    expect($buyer->unreadNotifications()->count())->toBe(0);

    Livewire::actingAs($buyer)->test(Notifications::class)
        ->call('openNotification', $buyer->notifications()->get()->firstWhere('data.url', '/hadiah')->id)
        ->assertRedirect('/hadiah');
});

it('polls every 15s and refreshes as soon as the tab is visible again', function () {
    Livewire::actingAs(centerBuyer())->test(NotificationBell::class)
        ->assertSeeHtml('wire:poll.15s')
        ->assertSeeHtml('x-on:visibilitychange.document="document.hidden || $wire.$refresh()"');
});
