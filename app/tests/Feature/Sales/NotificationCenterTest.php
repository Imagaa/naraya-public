<?php

use App\Enums\Role;
use App\Livewire\Sales\NotificationBell;
use App\Livewire\Sales\Notifications;
use App\Models\User;
use App\Notifications\SalesNotice;
use Livewire\Livewire;

function noticeFor(User $sales, string $title = 'Tugas kirim baru'): void
{
    $sales->notify(new SalesNotice($title, 'Pesanan #1 untuk Toko A', '/deliveries/1', 'package'));
}

it('shows the unread count on the bell and exposes it for the app badge', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    noticeFor($sales);
    noticeFor($sales);

    Livewire::actingAs($sales)->test(NotificationBell::class)
        ->assertSeeHtml('data-unread="2"')
        ->assertSeeHtml('<span class="nav-badge">2</span>');
});

it('lists notifications, opens one and marks it read', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    noticeFor($sales, 'Siap diambil di gudang');
    $id = $sales->notifications()->first()->id;

    Livewire::actingAs($sales)->test(Notifications::class)
        ->assertSee('Siap diambil di gudang')
        ->call('openNotification', $id)
        ->assertRedirect('/deliveries/1');

    expect($sales->unreadNotifications()->count())->toBe(0);
});

it('marks everything read', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    noticeFor($sales);
    noticeFor($sales);

    Livewire::actingAs($sales)->test(Notifications::class)->call('markAllNotificationsRead');

    expect($sales->unreadNotifications()->count())->toBe(0);
});

it('puts the bell in the sales topbar, opening the notifications sheet on Lainnya', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)->get(salesUrl('/'))->assertSee('class="sales-bell"', false)
        ->assertSee(e(route('sales.more', ['buka' => 'notifikasi'])), false);
    $this->actingAs($sales)->get(salesUrl('/lainnya?buka=notifikasi'))->assertOk()->assertSee('Belum ada notifikasi.');
    $this->actingAs($sales)->get(salesUrl('/lainnya'))->assertSee("open('notifikasi')", false);
});
