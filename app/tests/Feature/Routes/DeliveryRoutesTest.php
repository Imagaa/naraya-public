<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\DeliveryRoute;
use App\Models\Product;
use App\Models\User;
use App\Support\Sales\DeliveryRoutes;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\StockTransfers;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->stores = collect([[-6.20, 106.80], [-6.30, 106.90], [-6.21, 106.81]])->map(function ($p) {
        $store = User::factory()->create(['role' => Role::User]);
        BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $this->sales->id, 'latitude' => $p[0], 'longitude' => $p[1]]);

        return $store;
    });
    $this->routes = app(DeliveryRoutes::class);
});

it('orders stops nearest-neighbour from a starting point', function () {
    [$a, $b, $c] = $this->stores->pluck('id')->all();
    $points = [$a => [-6.20, 106.80], $b => [-6.30, 106.90], $c => [-6.21, 106.81]];

    expect(DeliveryRoutes::nearestOrder($points, -6.31, 106.91))->toBe([$b, $c, $a])
        ->and(DeliveryRoutes::nearestOrder($points, null, null))->toBe([$a, $c, $b]);
});

it('starts one active route of assigned stores in the given order', function () {
    [$a, $b, $c] = $this->stores->pluck('id')->all();
    $route = $this->routes->start($this->sales, [$c, $a]);

    expect($route->stops->pluck('buyer_id')->all())->toBe([$c, $a])
        ->and($route->stops->pluck('position')->all())->toBe([1, 2])
        ->and(DeliveryRoutes::active($this->sales)->is($route))->toBeTrue();

    expect(fn () => $this->routes->start($this->sales, [$b]))->toThrow(ValidationException::class);

    $other = User::factory()->create(['role' => Role::User]);
    $this->routes->finish($route);
    expect(fn () => $this->routes->start($this->sales, [$other->id]))->toThrow(ValidationException::class)
        ->and(fn () => $this->routes->start($this->sales, []))->toThrow(ValidationException::class);
});

it('marks a stop visited by a note made from it, or closed with a photo', function () {
    [$a, $b] = $this->stores->pluck('id')->all();
    $route = $this->routes->start($this->sales, [$a, $b]);
    [$first, $second] = $route->stops;

    $mie = Product::factory()->create(['stock' => 10, 'price' => 1000]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$mie->id => 5]);
    $note = app(SalesNotes::class)->create($this->sales, BusinessLine::Naraya, $a, [$mie->id => 1], NotePaymentMethod::Cash, routeStopId: $first->id);

    expect($first->fresh()->status)->toBe('visited')->and($note->route_stop_id)->toBe($first->id);

    expect(fn () => $this->routes->close($second, null, null))->toThrow(ValidationException::class);
    $this->routes->close($second, UploadedFile::fake()->image('tutup.jpg'), 'Tutup sampai sore');
    expect($second->fresh())->status->toBe('closed')->closed_note->toBe('Tutup sampai sore')->closed_photo_path->not->toBeNull();

    $this->routes->finish($route);
    expect($route->fresh())->status->toBe('finished')->finished_at->not->toBeNull()
        ->and(DeliveryRoutes::active($this->sales))->toBeNull();
});

it('ignores a stop id that is not on the sales person\'s active route', function () {
    [$a] = $this->stores->pluck('id')->all();
    $otherSales = User::factory()->create(['role' => Role::Sales]);
    BuyerProfile::where('user_id', $a)->update(['sales_user_id' => $otherSales->id]);
    $foreign = app(DeliveryRoutes::class)->start($otherSales, [$a])->stops->first();
    BuyerProfile::where('user_id', $a)->update(['sales_user_id' => $this->sales->id]);

    $mie = Product::factory()->create(['stock' => 10, 'price' => 1000]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$mie->id => 5]);
    $note = app(SalesNotes::class)->create($this->sales, BusinessLine::Naraya, $a, [$mie->id => 1], NotePaymentMethod::Cash, routeStopId: $foreign->id);

    expect($note->route_stop_id)->toBeNull()->and($foreign->fresh()->status)->toBe('pending');
});
