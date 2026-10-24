<?php

use App\Enums\BusinessLine;
use App\Livewire\Sales\Home;
use App\Models\Product;
use App\Models\User;
use App\Support\Sales\Consignments;
use App\Support\Stock\CarriedStock;
use Database\Seeders\SandboxSeeder;
use Livewire\Livewire;

// The sandbox server (config naraya.sandbox): made-up data on the production catalogue, resettable.

it('seeds logins, the toys catalogue with stock, and stores ready for a visit', function () {
    config(['naraya.sandbox' => true]);
    $this->seed(SandboxSeeder::class);

    $sales = User::where('email', 'sales1@coba.naraya')->sole();
    expect(Product::forLine(BusinessLine::Toys)->pluck('stock')->all())->toBe([15000, 10000, 10000])
        ->and(User::where('role', 'user')->count())->toBe(51) // 50 for Sales Coba Satu, 1 for Dua
        ->and(\App\Models\BuyerProfile::where('sales_user_id', $sales->id)->count())->toBe(50)
        ->and(Consignments::atStore(User::where('name', 'Toko Coba Makmur')->sole()->id, BusinessLine::Toys)->sum())->toBe(350)
        ->and(CarriedStock::for($sales, BusinessLine::Toys)->all())->toBe([])
        ->and($sales->salesProfile->area?->name)->toBe('Area Sales Coba Satu');

    Livewire::actingAs($sales)->withQueryParams([])->test(Home::class)->assertSee('Toko Coba Makmur');
});

it('only tops Sales Coba Satu up to 50 stores when run again', function () {
    config(['naraya.sandbox' => true]);
    $this->seed(SandboxSeeder::class);
    $this->seed(\Database\Seeders\SandboxStoresSeeder::class);

    expect(User::where('role', 'user')->count())->toBe(51);
});

it('refuses to seed or reset anywhere but the sandbox', function () {
    expect(fn () => $this->seed(SandboxSeeder::class))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->artisan('sandbox:reset')->assertFailed();
    expect(User::count())->toBe(0);
});

it('shows the ribbon and the test logins only on the sandbox', function () {
    $this->get(salesUrl('/login'))->assertDontSee('MODE COBA');

    config(['naraya.sandbox' => true]);
    $this->get(salesUrl('/login'))->assertSee('MODE COBA')->assertSee('sales1@coba.naraya')->assertSee(SandboxSeeder::PASSWORD);
});
