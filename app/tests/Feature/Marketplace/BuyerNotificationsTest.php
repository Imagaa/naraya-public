<?php

use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\Complaint;
use App\Models\LoyaltySetting;
use App\Models\Order;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;

function ntfBuyer(): User
{
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id]);

    return $buyer;
}

function ntfAdmin(): User
{
    return User::factory()->create(['role' => Role::Admin]);
}

/** @return array<int, array<string, string>> data payloads of the user's notifications, oldest first */
function ntfData(User $user): array
{
    return $user->notifications()->reorder()->orderBy('created_at')->get()->pluck('data')->all();
}

it('notifies the buyer once for each order status change', function (OrderStatus $to, string $body) {
    $buyer = ntfBuyer();
    $other = ntfBuyer();
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::New, 'total' => 0]);

    $this->actingAs(ntfAdmin());
    $order->update(['status' => $to]);

    $data = ntfData($buyer);
    expect($data)->toHaveCount(1)
        ->and($data[0]['body'])->toBe(str_replace('{id}', (string) $order->id, $body))
        ->and($data[0]['url'])->toBe('/pesanan/'.$order->id)
        ->and($data[0])->toHaveKeys(['title', 'icon', 'tone'])
        ->and(ntfData($other))->toBe([]);
})->with([
    'processing' => [OrderStatus::Processing, 'Pesanan #{id} dikonfirmasi dan sedang disiapkan'],
    'cancelled' => [OrderStatus::Cancelled, 'Pesanan #{id} dibatalkan'],
    'rejected' => [OrderStatus::RejectedByUser, 'Penolakan pesanan #{id} dicatat dan diteruskan ke Admin'],
    'completed, no points' => [OrderStatus::Completed, 'Pesanan #{id} selesai.'],
]);

it('names the sales rep when the order ships, and falls back without one', function () {
    $buyer = ntfBuyer();
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi Santoso']);
    $withSales = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Processing, 'sales_id' => $sales->id]);
    $direct = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Processing]);

    $this->actingAs($sales);
    $withSales->update(['status' => OrderStatus::Shipped]);
    $this->travel(1)->minutes();
    $direct->update(['status' => OrderStatus::Shipped]);

    expect(array_column(ntfData($buyer), 'body'))->toBe([
        "Pesanan #{$withSales->id} sedang diantar oleh Budi Santoso",
        "Pesanan #{$direct->id} sedang diantar",
    ]);
});

it('no longer mentions points in the completed notice', function () {
    LoyaltySetting::first()->update(['rupiah_per_point' => 1000]);
    $buyer = ntfBuyer();
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Shipped, 'total' => 125000]);

    $this->actingAs(ntfAdmin());
    $order->update(['status' => OrderStatus::Completed, 'completed_at' => now()]);

    expect(ntfData($buyer)[0]['body'])->toBe("Pesanan #{$order->id} selesai.");
});

it('tells the buyer when sales marks the order delivered', function () {
    $buyer = ntfBuyer();
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Shipped]);

    $this->actingAs(User::factory()->create(['role' => Role::Sales]));
    $order->update(['sales_delivered_at' => now()]);

    expect(array_column(ntfData($buyer), 'body'))
        ->toBe(["Pesanan #{$order->id} sudah diantar. Konfirmasi penerimaan dalam 3 hari."]);
});

it('notifies payment success and failure with no logged-in actor', function () {
    $buyer = ntfBuyer();
    $paid = Order::factory()->create(['buyer_id' => $buyer->id, 'payment_status' => PaymentStatus::Pending]);
    $failed = Order::factory()->create(['buyer_id' => $buyer->id, 'payment_status' => PaymentStatus::Pending]);

    $paid->update(['payment_status' => PaymentStatus::Paid]);
    $this->travel(1)->minutes();
    $failed->update(['payment_status' => PaymentStatus::Failed]);

    expect(array_column(ntfData($buyer), 'body'))->toBe([
        "Pembayaran pesanan #{$paid->id} berhasil",
        "Pembayaran pesanan #{$failed->id} gagal, silakan coba lagi",
    ]);
});

it('ignores updates that do not change status', function () {
    $buyer = ntfBuyer();
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Processing]);

    $this->actingAs(ntfAdmin());
    $order->update(['estimated_delivery_at' => now()->addDay()]);
    $order->update(['status' => OrderStatus::Processing]);

    expect(ntfData($buyer))->toBe([]);
});

it('skips the buyer\'s own change', function () {
    $buyer = ntfBuyer();
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::New]);

    $this->actingAs($buyer);
    $order->update(['status' => OrderStatus::Cancelled]);

    expect(ntfData($buyer))->toBe([]);
});

it('does not notify when an order is created', function () {
    $buyer = ntfBuyer();
    Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::New, 'payment_status' => PaymentStatus::Paid]);

    expect(ntfData($buyer))->toBe([]);
});

it('notifies the buyer when their complaint is resolved or closed', function (ComplaintStatus $to, string $body) {
    $buyer = ntfBuyer();
    $order = Order::factory()->create(['buyer_id' => $buyer->id]);
    $complaint = Complaint::factory()->create(['order_id' => $order->id]);

    $this->actingAs(ntfAdmin());
    $complaint->update(['status' => $to]);

    $data = ntfData($buyer);
    expect($data)->toHaveCount(1)
        ->and($data[0]['body'])->toBe(str_replace('{id}', (string) $order->id, $body))
        ->and($data[0]['url'])->toBe('/pesanan/'.$order->id);
})->with([
    'resolved' => [ComplaintStatus::Diresolusi, 'Komplain pesanan #{id} diselesaikan'],
    'closed' => [ComplaintStatus::Ditutup, 'Komplain pesanan #{id} ditutup'],
]);

it('notifies the buyer when a redemption is processed', function (RedemptionStatus $to, string $label) {
    $buyer = ntfBuyer();
    $redemption = Redemption::factory()->create([
        'user_id' => $buyer->id,
        'reward_id' => Reward::factory()->create(['name' => 'Kaos Naraya'])->id,
    ]);

    $this->actingAs(ntfAdmin());
    $redemption->update(['status' => $to]);

    $data = ntfData($buyer);
    expect($data)->toHaveCount(1)
        ->and($data[0]['body'])->toBe("Penukaran Kaos Naraya: {$label}")
        ->and($data[0]['url'])->toBe('/hadiah');
})->with([
    [RedemptionStatus::Disetujui, 'Disetujui'],
    [RedemptionStatus::Ditolak, 'Ditolak'],
    [RedemptionStatus::Diserahkan, 'Diserahkan'],
    [RedemptionStatus::Dibatalkan, 'Dibatalkan'],
]);

it('stays quiet for other complaint and redemption saves', function () {
    $buyer = ntfBuyer();
    $complaint = Complaint::factory()->create(['order_id' => Order::factory()->create(['buyer_id' => $buyer->id])->id]);
    $redemption = Redemption::factory()->create(['user_id' => $buyer->id]);

    $this->actingAs(ntfAdmin());
    $complaint->update(['catatan_admin' => 'dicek']);
    $redemption->update(['point_cost' => 25]);

    expect(ntfData($buyer))->toBe([]);
});
