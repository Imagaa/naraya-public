<?php

use App\Enums\BuyerStatus;
use App\Enums\CashDepositStatus;
use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\CashDeposit;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Product;
use App\Models\Redemption;
use App\Models\SalesProfile;
use App\Models\User;
use App\Support\Admin\WorkQueues;

beforeEach(fn () => $this->freezeTime());

function workQueue(string $key): ?array
{
    return collect(WorkQueues::all())->firstWhere('key', $key);
}

it('omits empty queues entirely', function () {
    expect(WorkQueues::all())->toBe([]);
});

it('grades a queue by the age of its oldest item against its threshold', function (int $minutesOld, string $severity) {
    Order::factory()->create(['status' => OrderStatus::Completed, 'needs_payment_review' => true, 'created_at' => now()->subMinutes($minutesOld)]);

    expect(workQueue('payment-review'))
        ->count->toBe(1)
        ->severity->toBe($severity); // threshold 4 h: soon past 2 h, late from 4 h
})->with([[60, 'idle'], [121, 'soon'], [240, 'late']]);

it('describes the oldest item and the threshold in the meta line', function () {
    Order::factory()->create(['status' => OrderStatus::New, 'created_at' => now()->subHours(5)]);
    Order::factory()->create(['status' => OrderStatus::New, 'created_at' => now()->subHour()]);

    expect(workQueue('new-orders'))
        ->count->toBe(2)
        ->meta->toBe('Tertua 5 jam · batas 12 jam')
        ->url->toBe(route('admin.orders.index', ['antrean' => 'new-orders']));
});

it('splits processing orders into admin work and stuck handovers', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => null]); // unassigned
    Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => $sales->id, 'gudang_handover_at' => now(), 'sales_received_at' => now()]); // ready to ship
    Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => $sales->id, 'sales_assigned_at' => now()->subHours(25), 'gudang_handover_at' => null]); // stuck at gudang
    Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => $sales->id, 'sales_assigned_at' => now()->subHours(3), 'gudang_handover_at' => null]); // at gudang, not stuck yet
    Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => $sales->id, 'gudang_handover_at' => now()->subHours(30), 'sales_received_at' => null]); // stuck at sales

    expect(workQueue('unassigned')['count'])->toBe(1);
    expect(workQueue('ready-to-ship')['count'])->toBe(1);
    expect(workQueue('stuck-gudang'))->count->toBe(1)->severity->toBe('late');
    expect(workQueue('stuck-sales'))->count->toBe(1)->severity->toBe('late');
});

it('only counts orders awaiting close once sales delivered and the buyer confirmed', function () {
    Order::factory()->create(['status' => OrderStatus::Shipped, 'sales_delivered_at' => now(), 'buyer_confirmed_at' => now()]);
    Order::factory()->create(['status' => OrderStatus::Shipped, 'sales_delivered_at' => now(), 'buyer_confirmed_at' => null]);

    expect(workQueue('awaiting-close')['count'])->toBe(1);
});

it('forces deposits late while any is disputed', function () {
    CashDeposit::factory()->create(['status' => CashDepositStatus::Submitted, 'created_at' => now()->subHour()]);
    CashDeposit::factory()->create(['status' => CashDepositStatus::Disputed, 'created_at' => now()->subHour()]);
    CashDeposit::factory()->create(['status' => CashDepositStatus::Verified]);

    expect(workQueue('deposits'))
        ->count->toBe(2)
        ->severity->toBe('late')
        ->meta->toStartWith('1 disengketakan');
});

it('grades stock late when anything is out and links to the matching product filter', function () {
    Product::factory()->create(['is_active' => true, 'stock' => 0, 'min_stock' => 5]);
    Product::factory()->create(['is_active' => true, 'stock' => 3, 'min_stock' => 5]);
    Product::factory()->create(['is_active' => false, 'stock' => 0, 'min_stock' => 5]); // inactive, ignored

    expect(workQueue('stock'))
        ->count->toBe(2)
        ->severity->toBe('late')
        ->meta->toBe('1 habis · 1 di bawah minimum')
        ->url->toBe(route('admin.products.index', ['stock' => 'out']));
});

it('grades low-only stock as soon', function () {
    Product::factory()->create(['is_active' => true, 'stock' => 3, 'min_stock' => 5]);

    expect(workQueue('stock'))->severity->toBe('soon')->url->toBe(route('admin.products.index', ['stock' => 'low']));
});

it('covers complaints, registrations, redemptions and phone changes', function () {
    Complaint::factory()->create(['status' => ComplaintStatus::Diajukan]);
    Complaint::factory()->create(['status' => ComplaintStatus::Ditutup]);
    BuyerProfile::factory()->create(['status' => BuyerStatus::Pending]);
    BuyerProfile::factory()->create(['status' => BuyerStatus::Approved]);
    Redemption::factory()->create(['status' => RedemptionStatus::Diajukan]);
    Redemption::factory()->create(['status' => RedemptionStatus::Disetujui]);
    SalesProfile::factory()->create(['user_id' => User::factory()->state(['role' => Role::Sales]), 'pending_phone' => '081234567890', 'pending_phone_requested_at' => now()]);

    foreach (['complaints', 'registrations', 'redemptions-decision', 'redemptions-handover', 'phone-change'] as $key) {
        expect(workQueue($key)['count'])->toBe(1, $key);
    }
});

it('sums queues for a sidebar badge', function () {
    Order::factory()->create(['status' => OrderStatus::New]);
    Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => null]);

    expect(WorkQueues::count('new-orders', 'unassigned', 'stock'))->toBe(2);
});

it('builds an id subquery for its own page only', function () {
    $flagged = Order::factory()->create(['needs_payment_review' => true]);

    expect(Order::whereIn('id', WorkQueues::filter('admin.orders.index', 'payment-review'))->pluck('id')->all())->toBe([$flagged->id]);
    expect(WorkQueues::filter('admin.deposits.index', 'payment-review'))->toBeNull();
    expect(WorkQueues::filter('admin.orders.index', 'nope'))->toBeNull();
});

it('queues paid online orders cancelled by admin until the refund is marked done', function () {
    Order::factory()->create(['status' => OrderStatus::Cancelled, 'refund_status' => 'pending', 'cancelled_at' => now()->subHours(50)]);
    Order::factory()->create(['status' => OrderStatus::Cancelled, 'refund_status' => 'done', 'cancelled_at' => now()->subHours(50)]);

    expect(workQueue('refunds'))
        ->group->toBe('Keuangan')
        ->count->toBe(1)
        ->severity->toBe('late')
        ->url->toBe(route('admin.orders.index', ['antrean' => 'refunds']));
});

it('flags each listed order with its most severe queue, graded by that order\'s own age', function () {
    $fresh = Order::factory()->create(['status' => OrderStatus::New, 'created_at' => now()->subHour()]);
    $old = Order::factory()->create(['status' => OrderStatus::New, 'created_at' => now()->subHours(13)]);
    $both = Order::factory()->create(['status' => OrderStatus::New, 'needs_payment_review' => true, 'created_at' => now()->subHours(5)]);
    $calm = Order::factory()->create(['status' => OrderStatus::Completed]);
    $unlisted = Order::factory()->create(['status' => OrderStatus::New, 'created_at' => now()->subHours(20)]);

    $flags = WorkQueues::orderFlags([$fresh->id, $old->id, $both->id, $calm->id]);

    expect($flags[$fresh->id])->toBe(['label' => 'Order baru belum dikonfirmasi', 'severity' => 'idle'])
        ->and($flags[$old->id]['severity'])->toBe('late')
        ->and($flags[$both->id])->toBe(['label' => 'Review pembayaran', 'severity' => 'late']) // 5 h > 4 h beats new-orders' "idle"
        ->and($flags)->not->toHaveKey($calm->id)
        ->and($flags)->not->toHaveKey($unlisted->id);
});
