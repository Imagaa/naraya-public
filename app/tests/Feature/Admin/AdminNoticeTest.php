<?php

use App\Enums\CashDepositStatus;
use App\Enums\Role;
use App\Livewire\Sales\Account;
use App\Models\CashDeposit;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Product;
use App\Models\Redemption;
use App\Models\SalesProfile;
use App\Models\User;
use App\Notifications\AdminNotice;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function () {
    $this->admins = User::factory()->count(2)->create(['role' => Role::Admin]);
});

function adminTitles(User $admin): array
{
    return $admin->fresh()->notifications->pluck('data.title')->all();
}

it('tells every admin about the admin events', function (Closure $cause, string $title) {
    $cause();

    foreach ($this->admins as $admin) {
        expect(adminTitles($admin))->toContain($title);
    }
})->with([
    'new order' => [fn () => Order::factory()->create(), 'Order baru'],
    'payment review' => [fn () => Order::factory()->create()->update(['needs_payment_review' => true]), 'Pembayaran perlu dicek'],
    'complaint' => [fn () => Complaint::factory()->create(), 'Komplain baru'],
    'deposit' => [fn () => CashDeposit::factory()->create(['status' => CashDepositStatus::Submitted]), 'Setoran baru'],
    'deposit disputed' => [fn () => CashDeposit::factory()->create(['status' => CashDepositStatus::Submitted])->update(['status' => CashDepositStatus::Disputed]), 'Setoran disengketakan'],
    'redemption' => [fn () => Redemption::factory()->create(), 'Penukaran hadiah baru'],
    'stock out' => [fn () => Product::factory()->create(['is_active' => true, 'stock' => 4])->update(['stock' => 0]), 'Stok habis'],
]);

it('pushes every notice to admins with a subscribed device (admin PWA, 2026-10-23)', function () {
    Notification::fake();
    [$subscribed, $plain] = $this->admins;
    $subscribed->updatePushSubscription('https://push.example/abc', 'key', 'auth', 'aes128gcm');

    AdminNotice::toAdmins('Stok habis', 'Gula habis', '/products/1/edit', 'package', 'warning', urgent: true);
    AdminNotice::toAdmins('Order baru', '#1 dari Toko A', '/orders/1', 'shopping-cart');

    Notification::assertSentTo($subscribed, AdminNotice::class, fn ($n, array $channels) => $n->urgent && in_array(WebPushChannel::class, $channels, true));
    Notification::assertSentTo($subscribed, AdminNotice::class, fn ($n, array $channels) => ! $n->urgent && in_array(WebPushChannel::class, $channels, true));
    Notification::assertSentTo($plain, AdminNotice::class, fn ($n, array $channels) => $channels === ['database']);
});

it('notifies every admin when a guest registers a store', function () {
    expect(auth()->id())->toBeNull();

    AdminNotice::toAdmins('Toko baru mendaftar', 'Toko A menunggu persetujuan', '/buyers?antrean=registrations', 'store');

    foreach ($this->admins as $admin) {
        expect(adminTitles($admin))->toContain('Toko baru mendaftar');
    }
});

it('does not notify the admin who caused the event', function () {
    [$actor, $other] = $this->admins;
    $this->actingAs($actor);

    Product::factory()->create(['is_active' => true, 'stock' => 4])->update(['stock' => 0]);

    expect(adminTitles($actor))->not->toContain('Stok habis');
    expect(adminTitles($other))->toContain('Stok habis');
});

it('tells admins when a sales asks to change their phone number', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->for($sales)->create(['phone' => '081111111111']);

    Livewire::actingAs($sales)->test(Account::class)
        ->set('phone', '082222222222')
        ->call('requestPhoneChange');

    expect(adminTitles($this->admins[0]))->toContain('Permintaan ganti nomor');
});

it('mutes admin notices while seeding and restores them afterwards', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('role', Role::Admin)->get()->sum(fn ($a) => $a->notifications()->count()))->toBe(0);
    expect(AdminNotice::$muted)->toBeFalse();
});
