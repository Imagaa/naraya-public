<?php

use App\Enums\CashDepositStatus;
use App\Enums\OrderStatus;
use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Models\CashDeposit;
use App\Models\Order;
use App\Models\Redemption;
use App\Models\User;
use App\Notifications\SalesNotice;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));
    $this->sales = User::factory()->create(['role' => Role::Sales]);
});

function sentTitles(User $user): array
{
    return Notification::sent($user, SalesNotice::class)->map(fn (SalesNotice $n) => $n->title)->all();
}

it('tells the sales user about a newly assigned delivery', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => null]);

    $order->update(['sales_id' => $this->sales->id, 'sales_assigned_at' => now()]);

    expect(sentTitles($this->sales))->toBe(['Tugas kirim baru']);
    Notification::assertSentTo($this->sales, SalesNotice::class, fn (SalesNotice $n) => $n->url === '/deliveries/'.$order->id);
});

it('tells the sales user when the warehouse hands an order over', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => $this->sales->id]);

    $order->update(['gudang_handover_at' => now()]);

    expect(sentTitles($this->sales))->toBe(['Barang siap diambil']);
});

it('tells the assigned sales user to hand over an approved reward', function () {
    $redemption = Redemption::factory()->create(['status' => RedemptionStatus::Diajukan]);

    $redemption->update(['status' => RedemptionStatus::Disetujui, 'sales_id' => $this->sales->id]);

    expect(sentTitles($this->sales))->toBe(['Hadiah perlu diserahkan']);
});

it('tells the sales user how their deposit was settled', function (CashDepositStatus $status, string $tone) {
    $deposit = CashDeposit::factory()->create(['sales_id' => $this->sales->id, 'status' => CashDepositStatus::Submitted, 'claimed_amount' => 35000]);

    $deposit->update(['status' => $status]);

    Notification::assertSentTo($this->sales, SalesNotice::class, fn (SalesNotice $n) => $n->tone === $tone
        && $n->body === 'Setoran Rp 35.000: '.$status->label()
        && $n->url === '/setoran');
})->with([
    'verified' => [CashDepositStatus::Verified, 'success'],
    'disputed' => [CashDepositStatus::Disputed, 'warning'],
    'resolved' => [CashDepositStatus::Resolved, 'info'],
]);

it('stays quiet for unrelated updates and for the sales user\'s own actions', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => $this->sales->id]);
    $order->update(['notes' => 'catatan']);

    $this->actingAs($this->sales);
    $order->update(['gudang_handover_at' => now()]);

    Notification::assertNotSentTo($this->sales, SalesNotice::class);
});

it('still notifies the buyer about order status changes', function () {
    $order = Order::factory()->create(['status' => OrderStatus::New, 'sales_id' => $this->sales->id]);

    $order->update(['status' => OrderStatus::Processing]);

    Notification::assertSentTo($order->buyer, \App\Notifications\BuyerNotice::class);
});
