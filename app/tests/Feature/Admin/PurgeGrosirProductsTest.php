<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\ConsignmentMove;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\SalesNoteItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Sales\Consignments;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;

// grosir:purge-products: every grosir product and its records go; Toys stays as it was
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->store = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id, 'consignment_opened_at' => null]);

    $this->records = function (BusinessLine $line): Product {
        $p = Product::factory()->create(['line' => $line, 'stock' => 0, 'price' => 1000, 'unit' => 'pcs', 'pieces_per_unit' => 1]);
        $p->photos()->create(['path' => 'products/x.jpg', 'sort_order' => 0]);
        app(StockTransfers::class)->receive($line, [$p->id => 500], $this->admin);          // goods in: transfer + movement
        app(StockTransfers::class)->pickup($this->sales, $line, [$p->id => 100]);
        app(SalesNotes::class)->record($this->sales, $line, $this->store->id, [$p->id => 160], NotePaymentMethod::Cash, null, null, 'bawaan');
        ConsignmentMove::create(['buyer_id' => $this->store->id, 'product_id' => $p->id, 'line' => $line, 'sales_id' => $this->sales->id, 'kind' => 'awal', 'quantity' => 12]);
        $order = Order::factory()->create(['buyer_id' => $this->store->id]);
        $order->items()->create(['product_id' => $p->id, 'product_name' => $p->name, 'price' => 1000, 'quantity' => 2, 'subtotal' => 2000]);

        return $p;
    };
    $this->toy = ($this->records)(BusinessLine::Toys);
    $this->grosir = ($this->records)(BusinessLine::Naraya);

    $this->toysSnapshot = fn () => [
        'products' => Product::where('line', BusinessLine::Toys)->get(['id', 'stock', 'price'])->toArray(),
        'movements' => StockMovement::where('product_id', $this->toy->id)->count(),
        'order_items' => OrderItem::where('product_id', $this->toy->id)->count(),
        'orders' => Order::whereHas('items', fn ($q) => $q->where('product_id', $this->toy->id))->count(),
        'nota_items' => SalesNoteItem::where('product_id', $this->toy->id)->count(),
        'notas' => SalesNote::where('line', BusinessLine::Toys)->count(),
        'transfers' => StockTransfer::where('line', BusinessLine::Toys)->count(),
        'consignment' => ConsignmentMove::where('line', BusinessLine::Toys)->sum('quantity'),
        'points' => BuyerProfile::sum('loyalty_points'),
    ];
});

it('dry-runs with counts and deletes nothing', function () {
    $this->artisan('grosir:purge-products')
        ->expectsOutputToContain('products: 1')
        ->expectsOutputToContain('Uji coba')
        ->assertSuccessful();

    expect(Product::where('line', BusinessLine::Naraya)->count())->toBe(1);
});

it('deletes grosir products and their records and leaves Toys exactly as it was', function () {
    $before = ($this->toysSnapshot)();

    $this->artisan('grosir:purge-products', ['--force' => true])->assertSuccessful();

    expect(($this->toysSnapshot)())->toBe($before)
        ->and(Product::where('line', BusinessLine::Naraya)->count())->toBe(0)
        ->and(StockMovement::where('product_id', $this->grosir->id)->count())->toBe(0)
        ->and(SalesNote::where('line', BusinessLine::Naraya)->count())->toBe(0)
        ->and(StockTransfer::where('line', BusinessLine::Naraya)->count())->toBe(0)
        ->and(ConsignmentMove::where('line', BusinessLine::Naraya)->count())->toBe(0)
        ->and(Order::whereDoesntHave('items')->count())->toBe(0);
});

it('aborts when a record mixes lines', function () {
    $mixed = Order::factory()->create(['buyer_id' => $this->store->id]);
    $mixed->items()->create(['product_id' => $this->toy->id, 'product_name' => 'toy', 'price' => 1, 'quantity' => 1, 'subtotal' => 1]);
    $mixed->items()->create(['product_id' => $this->grosir->id, 'product_name' => 'grosir', 'price' => 1, 'quantity' => 1, 'subtotal' => 1]);

    $this->artisan('grosir:purge-products', ['--force' => true])
        ->expectsOutputToContain('orders #'.$mixed->id)
        ->assertFailed();

    expect(Product::where('line', BusinessLine::Naraya)->count())->toBe(1)->and($mixed->items()->count())->toBe(2);
});
