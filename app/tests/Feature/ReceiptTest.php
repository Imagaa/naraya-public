<?php

use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;

function receiptOrder(array $attributes = []): Order
{
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi Sales']);
    $buyer = User::factory()->create(['role' => Role::User, 'name' => 'Warung Bu Sri']);
    $order = Order::factory()->create(array_merge([
        'buyer_id' => $buyer->id,
        'sales_id' => $sales->id,
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => 'cash',
        'subtotal' => 362400,
        'credit_applied' => 25000,
        'total' => 337400,
        'delivery_address' => 'Jl. Melati 3',
        'completed_at' => Carbon::parse('2026-10-04 14:25', 'Asia/Jakarta')->utc(),
    ], $attributes));
    OrderItem::factory()->create(['order_id' => $order->id, 'product_name' => 'Teh Melati 250g', 'price' => 52800, 'quantity' => 3, 'subtotal' => 158400]);

    return $order;
}

function marketUrl(string $path): string
{
    return 'http://'.config('app.domain').$path;
}

function adminUrl(string $path): string
{
    return 'http://'.config('app.admin_domain').$path;
}

it('is ready only for a completed, paid order with no open or resolved complaint', function () {
    expect(receiptOrder()->isReceiptReady())->toBeTrue()
        ->and(receiptOrder(['status' => OrderStatus::Shipped])->isReceiptReady())->toBeFalse()
        ->and(receiptOrder(['payment_status' => PaymentStatus::Pending])->isReceiptReady())->toBeFalse()
        ->and(receiptOrder(['needs_payment_review' => true])->isReceiptReady())->toBeFalse();

    foreach ([[ComplaintStatus::Diajukan, false], [ComplaintStatus::Diresolusi, false], [ComplaintStatus::Ditutup, true]] as [$status, $ready]) {
        $order = receiptOrder();
        Complaint::factory()->create(['order_id' => $order->id, 'status' => $status]);
        expect($order->fresh()->isReceiptReady())->toBe($ready);
    }
});

it('numbers the receipt by its WIB completion date and padded order id', function () {
    $order = receiptOrder(['completed_at' => Carbon::parse('2026-10-04 23:30', 'UTC')]); // 06:30 WIB on the 5th

    expect($order->receiptNumber())->toBe('NRY-20261005-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT));
});

it('shows the buyer their own receipt with the totals and a print button', function () {
    $order = receiptOrder();

    $this->actingAs($order->buyer)->get(marketUrl("/pesanan/{$order->id}/nota"))
        ->assertOk()
        ->assertSee('NOTA PENJUALAN')
        ->assertSee($order->receiptNumber())
        ->assertSee('Teh Melati 250g')
        ->assertSee('3 x 52.800')
        ->assertSee('158.400')
        ->assertSee('-25.000')
        ->assertSee('337.400')
        ->assertSee('LUNAS')
        ->assertSee('Budi Sales')
        ->assertSee('@page { size: auto; margin: 0; }', false)
        ->assertSee('Cetak Nota');
});

it('hides receipts from other buyers, other sales and unfinished orders', function () {
    $order = receiptOrder();
    $otherBuyer = User::factory()->create(['role' => Role::User]);
    $otherSales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($otherBuyer)->get(marketUrl("/pesanan/{$order->id}/nota"))->assertNotFound();
    $this->actingAs($otherSales)->get(salesUrl("/deliveries/{$order->id}/nota"))->assertNotFound();

    $open = receiptOrder(['status' => OrderStatus::Shipped]);
    $this->actingAs($open->buyer)->get(marketUrl("/pesanan/{$open->id}/nota"))->assertNotFound();
});

it('lets the assigned sales user view and download, without the print button', function () {
    $order = receiptOrder();

    $this->actingAs($order->salesUser)->get(salesUrl("/deliveries/{$order->id}/nota"))
        ->assertOk()
        ->assertSee($order->receiptNumber())
        ->assertSee('Unduh PDF')
        ->assertDontSee('Cetak Nota');
});

it('lets admin view any finished order receipt', function () {
    $order = receiptOrder();
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)->get(adminUrl("/orders/{$order->id}/nota"))->assertOk()->assertSee('Cetak Nota');
});

it('downloads the receipt as an 80mm PDF named after the receipt number', function () {
    $order = receiptOrder();

    $response = $this->actingAs($order->buyer)->get(marketUrl("/pesanan/{$order->id}/nota.pdf"))->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain($order->receiptNumber().'.pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('links to the receipt from each portal only once it is ready', function () {
    $ready = receiptOrder();
    $notReady = receiptOrder(['status' => OrderStatus::Shipped, 'sales_delivered_at' => now()]);
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($ready->buyer)->get(marketUrl("/pesanan/{$ready->id}"))->assertSee("/pesanan/{$ready->id}/nota", false);
    $this->actingAs($notReady->buyer)->get(marketUrl("/pesanan/{$notReady->id}"))->assertDontSee('/nota', false);

    $this->actingAs($ready->salesUser)->get(salesUrl("/deliveries/{$ready->id}"))->assertSee("/deliveries/{$ready->id}/nota", false);
    $this->actingAs($admin)->get(adminUrl("/orders/{$ready->id}"))->assertSee("/orders/{$ready->id}/nota", false);
});
