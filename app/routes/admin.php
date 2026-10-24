<?php

use Illuminate\Support\Facades\Route;

// The sandbox server's "Reset data coba" (config naraya.sandbox; 404 anywhere else).
Route::domain(config('app.admin_domain'))
    ->middleware(['auth', 'role:admin'])
    ->post('/coba/reset', [\App\Http\Controllers\SandboxController::class, 'reset'])
    ->name('admin.sandbox.reset');

// This portal's own door (host-only session, App\Http\Middleware\UsePortalSession).
Route::domain(config('app.admin_domain'))
    ->middleware('guest')
    ->get('/login', \App\Livewire\Auth\PortalLogin::class)
    ->name('admin.login');

Route::domain(config('app.admin_domain'))
    ->middleware('auth')
    ->post('/logout', \App\Http\Controllers\Auth\LogoutController::class)
    ->name('admin.logout');

Route::domain(config('app.admin_domain'))
    ->middleware(['auth', 'role:admin'])
    ->name('admin.')
    ->group(function () {
        Route::get('/', \App\Livewire\Admin\Dashboard\Index::class)->name('dashboard');
        Route::post('/lini', \App\Http\Controllers\LineController::class)->name('line');
        Route::get('/nav-badges', fn () => response()->json(\App\Support\Admin\AdminNav::badges()))->name('nav-badges');
        Route::get('/notifikasi', \App\Livewire\Admin\Notifications::class)->name('notifications');
        Route::get('/pasang', \App\Livewire\Admin\Install::class)->name('install');
        Route::post('/push-subscriptions', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.store');
        Route::delete('/push-subscriptions', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.destroy');

        Route::get('/nota', \App\Livewire\Admin\Notes\Index::class)->name('notes.index');
        Route::get('/nota/{note}', \App\Livewire\Admin\Notes\Show::class)->whereNumber('note')->name('notes.show');
        Route::get('/products', \App\Livewire\Admin\Products\Index::class)->name('products.index');
        Route::get('/barang-masuk', \App\Livewire\Admin\Stock\GoodsIn::class)->name('goods-in.index');
        Route::get('/pengambilan', \App\Livewire\Admin\Stock\Transfers::class)->name('transfers.index');
        Route::get('/stok-bawaan', \App\Livewire\Admin\Stock\Carried::class)->name('carried.index');
        Route::get('/products/create', \App\Livewire\Admin\Products\Form::class)->name('products.create');
        Route::get('/products/{product}/edit', \App\Livewire\Admin\Products\Form::class)->name('products.edit');
        Route::get('/buyers', \App\Livewire\Admin\Buyers\Index::class)->name('buyers.index');
        Route::get('/buyers/create', \App\Livewire\Admin\Buyers\Form::class)->name('buyers.create');
        Route::get('/buyers/{buyer}', \App\Livewire\Admin\Buyers\Show::class)->name('buyers.show');
        Route::get('/buyers/{buyer}/edit', \App\Livewire\Admin\Buyers\Form::class)->name('buyers.edit');
        Route::get('/sales', \App\Livewire\Admin\Sales\Index::class)->name('sales.index');
        Route::get('/sales/create', \App\Livewire\Admin\Sales\Form::class)->name('sales.create');
        Route::get('/sales/map', \App\Livewire\Admin\Sales\Map::class)->name('sales.map');
        Route::get('/perjalanan', \App\Livewire\Admin\Tracking\Index::class)->name('tracking.index');
        Route::get('/sales/{sales}/edit', \App\Livewire\Admin\Sales\Form::class)->name('sales.edit');
        Route::get('/areas', \App\Livewire\Admin\Areas\Index::class)->name('areas.index');
        Route::get('/orders', \App\Livewire\Admin\Orders\Index::class)->name('orders.index');
        Route::get('/orders/{order}', \App\Livewire\Admin\Orders\Show::class)->name('orders.show');
        Route::get('/orders/{order}/nota', [\App\Http\Controllers\ReceiptController::class, 'show'])->name('orders.receipt');
        Route::get('/orders/{order}/nota.pdf', [\App\Http\Controllers\ReceiptController::class, 'pdf'])->name('orders.receipt.pdf');
        Route::get('/rute-sales', \App\Livewire\Admin\Routes\Index::class)->name('routes.index');
        Route::get('/investasi', \App\Livewire\Admin\Investments\Index::class)->name('investments.index');
        Route::get('/pencocokan', \App\Livewire\Admin\Reconciliation\Index::class)->name('reconciliation.index');
        Route::get('/deposits', \App\Livewire\Admin\Deposits\Index::class)->name('deposits.index');
        Route::get('/deposits/{deposit}', \App\Livewire\Admin\Deposits\Show::class)->name('deposits.show');
        Route::get('/complaints', \App\Livewire\Admin\Complaints\Index::class)->name('complaints.index');
        Route::get('/complaints/{complaint}', \App\Livewire\Admin\Complaints\Show::class)->name('complaints.show');
        Route::get('/rewards', \App\Livewire\Admin\Rewards\Index::class)->name('rewards.index');
        Route::get('/rewards/create', \App\Livewire\Admin\Rewards\Form::class)->name('rewards.create');
        Route::get('/rewards/{reward}/edit', \App\Livewire\Admin\Rewards\Form::class)->name('rewards.edit');
        Route::get('/loyalty-settings', \App\Livewire\Admin\LoyaltySettings\Edit::class)->name('loyalty-settings.edit');
        Route::get('/warehouse-settings', \App\Livewire\Admin\WarehouseSettings\Edit::class)->name('warehouse-settings.edit');
        Route::get('/redemptions', \App\Livewire\Admin\Redemptions\Index::class)->name('redemptions.index');
        Route::get('/redemptions/{redemption}', \App\Livewire\Admin\Redemptions\Show::class)->name('redemptions.show');
        Route::get('/attendances', \App\Livewire\Admin\Attendances\Index::class)->name('attendances.index');
        Route::get('/attendances/libur', \App\Livewire\Admin\Attendances\DayOffs::class)->name('attendances.day-offs');
        Route::get('/attendances/rekap',\App\Livewire\Admin\Attendances\Recap::class)->name('attendances.recap');
        Route::get('/attendances/rekap/{user}', \App\Livewire\Admin\Attendances\RecapSales::class)->name('attendances.recap-sales');
        Route::get('/expense-categories', \App\Livewire\Admin\ExpenseCategories\Index::class)->name('expense-categories.index');
        Route::get('/cash-book', \App\Livewire\Admin\CashBook\Index::class)->name('cash-book.index');
        Route::get('/pengaturan/buku-kas', \App\Livewire\Admin\CashBookSettings\Edit::class)->name('cash-book-settings.edit');
        Route::get('/pengaturan/absensi', \App\Livewire\Admin\AttendanceSettings\Edit::class)->name('attendance-settings.edit');

        Route::prefix('laporan')->name('reports.')->group(function () {
            Route::get('/', \App\Livewire\Admin\Reports\Index::class)->name('index');
            Route::get('/penjualan', \App\Livewire\Admin\Reports\Penjualan::class)->name('penjualan');
            Route::get('/toko', \App\Livewire\Admin\Reports\Toko::class)->name('toko');
            Route::get('/order', \App\Livewire\Admin\Reports\OrderReport::class)->name('order');
            Route::get('/stok', \App\Livewire\Admin\Reports\Stok::class)->name('stok');
            Route::get('/cash', \App\Livewire\Admin\Reports\Cash::class)->name('cash');
            Route::get('/absensi', \App\Livewire\Admin\Reports\Absensi::class)->name('absensi');
            Route::get('/target-sales', \App\Livewire\Admin\Reports\TargetSales::class)->name('target-sales');
            Route::get('/buku-kas', \App\Livewire\Admin\Reports\BukuKas::class)->name('buku-kas');
            Route::get('/loyalty', \App\Livewire\Admin\Reports\Loyalty::class)->name('loyalty');
            Route::get('/komplain', \App\Livewire\Admin\Reports\Komplain::class)->name('komplain');
        });
    });
