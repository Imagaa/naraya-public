<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\LoyaltySetting;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\User;
use App\Support\Loyalty\StorePoints;
use App\Support\Sales\Consignments;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;

// Points from toys sold
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->store = User::factory()->create(['role' => Role::User, 'password' => null]);
    $this->profile = BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id, 'loyalty_points' => 0]);
    $this->toy = Product::factory()->create(['line' => BusinessLine::Toys, 'stock' => 1000, 'price' => 1000]);
    $this->sell = fn (int $pcs, BusinessLine $line = BusinessLine::Toys, ?Product $product = null, NotePaymentMethod $method = NotePaymentMethod::Cash): SalesNote => app(SalesNotes::class)
        ->record($this->sales, $line, $this->store->id, [($product ?? $this->toy)->id => $pcs], $method, null, null, 'bawaan');
});

it('gives 1 point per 150 toys pcs sold, carrying the rest', function () {
    ($this->sell)(200);
    ($this->sell)(120);

    expect($this->profile->fresh()->loyalty_points)->toBe(2)
        ->and(app(StorePoints::class)->progress($this->store->id))->toBe(['pcs' => 20, 'per' => 150]);
});

it('counts consigned goods only once sold', function () {
    $this->profile->forceFill(['consignment_opened_at' => null])->save();
    app(Consignments::class)->open($this->sales, $this->store->id, BusinessLine::Toys, [$this->toy->id => 200]);
    expect($this->profile->fresh()->loyalty_points)->toBe(0);

    app(Consignments::class)->visit($this->sales, $this->store->id, BusinessLine::Toys, [$this->toy->id => 40], [], NotePaymentMethod::Cash);

    expect($this->profile->fresh()->loyalty_points)->toBe(1)
        ->and(app(StorePoints::class)->pcsCounted($this->store->id))->toBe(160);
});

it('ignores Naraya line notas', function () {
    $grosir = Product::factory()->create(['line' => BusinessLine::Naraya, 'stock' => 1000]);
    ($this->sell)(300, BusinessLine::Naraya, $grosir);

    expect($this->profile->fresh()->loyalty_points)->toBe(0)
        ->and(app(StorePoints::class)->pcsCounted($this->store->id))->toBe(0);
});

it('takes points back when a transfer is rejected, never below zero', function () {
    $note = ($this->sell)(150, method: NotePaymentMethod::Transfer);
    expect($this->profile->fresh()->loyalty_points)->toBe(1);

    $this->profile->update(['loyalty_points' => 0]); // already spent on a reward
    app(SalesNotes::class)->reject($note, $this->admin, 'Bukti tidak sesuai');

    expect($this->profile->fresh())->loyalty_points->toBe(0)->toys_points_awarded->toBe(0);
});

it('is idempotent and backfills old notas', function () {
    $note = SalesNote::create(['line' => BusinessLine::Toys, 'sales_id' => $this->sales->id, 'buyer_id' => $this->store->id,
        'payment_method' => NotePaymentMethod::Cash, 'source' => 'bawaan', 'total' => 0]);
    $note->items()->create(['product_id' => $this->toy->id, 'product_name' => $this->toy->name, 'quantity' => 310, 'price' => 1000, 'subtotal' => 310000]);

    app(StorePoints::class)->syncAll();
    app(StorePoints::class)->syncAll();

    expect($this->profile->fresh())->loyalty_points->toBe(2)->toys_points_awarded->toBe(2);
});

it('keeps points for a store without an account', function () {
    ($this->sell)(150);
    expect($this->store->fresh()->hasAccount())->toBeFalse()
        ->and($this->profile->fresh()->loyalty_points)->toBe(1);

    $this->store->update(['password' => 'rahasia123']);
    app(StorePoints::class)->sync($this->store->id);

    expect($this->profile->fresh()->loyalty_points)->toBe(1);
});

it('recomputes everyone when pcs_per_point changes', function () {
    ($this->sell)(300);
    expect($this->profile->fresh()->loyalty_points)->toBe(2);

    LoyaltySetting::first()->update(['pcs_per_point' => 100]);
    app(StorePoints::class)->syncAll();

    expect($this->profile->fresh()->loyalty_points)->toBe(3)
        ->and(app(StorePoints::class)->perPoint())->toBe(100);
});
