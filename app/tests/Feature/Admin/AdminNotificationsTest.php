<?php

use App\Enums\Role;
use App\Livewire\Admin\NotificationBell;
use App\Livewire\Admin\Notifications;
use App\Models\User;
use App\Notifications\AdminNotice;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    AdminNotice::toAdmins('Komplain baru', 'Pesanan #7: Retur', '/complaints/1', 'message-circle-warning', 'warning');
});

it('shows the unread count and the latest notices in the bell', function () {
    Livewire::actingAs($this->admin)->test(NotificationBell::class)
        ->assertSee('Komplain baru')
        ->assertSeeHtml('<span class="adm-badge">1</span>')
        ->call('markAllNotificationsRead')
        ->assertDontSeeHtml('class="adm-badge"');
});

it('opens a notice at its url and marks it read', function () {
    $id = $this->admin->notifications()->first()->id;

    Livewire::actingAs($this->admin)->test(Notifications::class)
        ->call('openNotification', $id)
        ->assertRedirect('/complaints/1');

    expect($this->admin->unreadNotifications()->count())->toBe(0);
});

it('serves the notifications page and the push subscription endpoint on the admin domain', function () {
    $this->actingAs($this->admin)->get(route('admin.notifications'))
        ->assertOk()
        ->assertSee('Aktifkan notifikasi di perangkat ini')
        ->assertSee('Komplain baru');

    $this->actingAs($this->admin)->postJson(route('admin.push.store'), [
        'endpoint' => 'https://push.example/xyz',
        'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
    ])->assertNoContent();

    expect($this->admin->pushSubscriptions()->count())->toBe(1);
});

it('puts the bell in the admin topbar', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('popovertarget="adm-bell-panel"', false);
});

it('serves a push-only service worker for the admin host', function () {
    expect(file_exists(public_path('admin-sw.js')))->toBeTrue();
    expect(file_get_contents(public_path('admin-sw.js')))
        ->toContain("addEventListener('push'")
        ->toContain("addEventListener('notificationclick'")
        ->not->toContain('/offline'); // the sales offline page doesn't exist on the admin host
});
