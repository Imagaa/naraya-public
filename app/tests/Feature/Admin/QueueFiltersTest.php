<?php

use App\Enums\BuyerStatus;
use App\Enums\CashDepositStatus;
use App\Enums\ComplaintStatus;
use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Livewire\Admin\Buyers\Index as BuyersIndex;
use App\Livewire\Admin\Complaints\Index as ComplaintsIndex;
use App\Livewire\Admin\Deposits\Index as DepositsIndex;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Livewire\Admin\Redemptions\Index as RedemptionsIndex;
use App\Livewire\Admin\Sales\Index as SalesIndex;
use App\Models\BuyerProfile;
use App\Models\CashDeposit;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Redemption;
use App\Models\SalesProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->admin = User::factory()->create(['role' => Role::Admin]));

function ids($paginator): array
{
    return collect($paginator->items())->pluck('id')->sort()->values()->all();
}

it('narrows the orders list to one dashboard queue and shows a removable chip', function () {
    $flagged = Order::factory()->create(['needs_payment_review' => true]);
    Order::factory()->create(['needs_payment_review' => false]);

    Livewire::actingAs($this->admin)->withQueryParams(['antrean' => 'payment-review'])
        ->test(OrdersIndex::class)
        ->assertViewHas('orders', fn ($p) => ids($p) === [$flagged->id])
        ->assertSee('Antrean: Review pembayaran')
        ->call('clearQueue')
        ->assertSet('queue', '')
        ->assertViewHas('orders', fn ($p) => count($p->items()) === 2);
});

it('ignores an unknown queue key', function () {
    Order::factory()->count(2)->create();

    Livewire::actingAs($this->admin)->withQueryParams(['antrean' => 'tidak-ada'])
        ->test(OrdersIndex::class)
        ->assertViewHas('orders', fn ($p) => count($p->items()) === 2)
        ->assertDontSee('Antrean:');
});

it('ignores a queue key that belongs to another page', function () {
    Order::factory()->count(2)->create();
    CashDeposit::factory()->create(['status' => CashDepositStatus::Submitted]);

    Livewire::actingAs($this->admin)->withQueryParams(['antrean' => 'deposits'])
        ->test(OrdersIndex::class)
        ->assertViewHas('orders', fn ($p) => count($p->items()) === 2);
});

// Pest resolves a closure in a dataset (after beforeEach) and passes its return value.
it('filters every other list page by its own queues', function (string $component, string $key, string $viewVar, array $seeded) {
    [$in, $out] = $seeded;

    Livewire::actingAs($this->admin)->withQueryParams(['antrean' => $key])
        ->test($component)
        ->assertViewHas($viewVar, fn ($p) => in_array($in, ids($p), true) && ! in_array($out, ids($p), true));
})->with([
    'complaints' => [ComplaintsIndex::class, 'complaints', 'complaints', fn () => [
        Complaint::factory()->create(['status' => ComplaintStatus::Diajukan])->id,
        Complaint::factory()->create(['status' => ComplaintStatus::Ditutup])->id,
    ]],
    'deposits' => [DepositsIndex::class, 'deposits', 'deposits', fn () => [
        CashDeposit::factory()->create(['status' => CashDepositStatus::Disputed])->id,
        CashDeposit::factory()->create(['status' => CashDepositStatus::Verified])->id,
    ]],
    'redemptions' => [RedemptionsIndex::class, 'redemptions-handover', 'redemptions', fn () => [
        Redemption::factory()->create(['status' => RedemptionStatus::Disetujui])->id,
        Redemption::factory()->create(['status' => RedemptionStatus::Diajukan])->id,
    ]],
    'buyers' => [BuyersIndex::class, 'registrations', 'buyers', fn () => [
        BuyerProfile::factory()->create(['status' => BuyerStatus::Pending])->user_id,
        BuyerProfile::factory()->create(['status' => BuyerStatus::Approved])->user_id,
    ]],
    'sales' => [SalesIndex::class, 'phone-change', 'salesUsers', fn () => [
        SalesProfile::factory()->create(['user_id' => User::factory()->state(['role' => Role::Sales]), 'pending_phone' => '081234567890', 'pending_phone_requested_at' => now()])->user_id,
        SalesProfile::factory()->create(['user_id' => User::factory()->state(['role' => Role::Sales]), 'pending_phone' => null])->user_id,
    ]],
]);

it('leaves the "Perlu tindakan" cell empty for an order that needs nothing, so phones hide its label', function () {
    Order::factory()->create(['status' => App\Enums\OrderStatus::Completed]);

    $html = preg_replace('/<!--.*?-->/s', '', Livewire::actingAs($this->admin)->test(OrdersIndex::class)->html());

    // Livewire's block comments may sit inside; CSS :empty ignores comments
    expect($html)->toMatch('#<span></span></a>#')
        ->not->toContain('adm-flag');
});
