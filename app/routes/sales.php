<?php

use Illuminate\Support\Facades\Route;

// This portal's own door (host-only session, App\Http\Middleware\UsePortalSession).
Route::domain(config('app.sales_domain'))
    ->middleware('guest')
    ->get('/login', \App\Livewire\Auth\PortalLogin::class)
    ->name('sales.login');

Route::domain(config('app.sales_domain'))
    ->middleware('auth')
    ->post('/logout', \App\Http\Controllers\Auth\LogoutController::class)
    ->name('sales.logout');

// The Android app: download page and the version the app updates to.
// Public: the app checks before anyone logs in.
Route::domain(config('app.sales_domain'))->group(function () {
    Route::get('/aplikasi', fn () => view('sales.app-download', ['release' => \App\Support\Mobile\AndroidRelease::latest()]))->name('sales.app');
    // `build`: the running deploy, so an open page knows to reload itself (resources/js/sales-pwa.js)
    Route::get('/aplikasi/versi', fn () => response()->json((\App\Support\Mobile\AndroidRelease::latest() ?? ['versionCode' => 0]) + ['build' => \App\Support\BuildId::current()])
        ->header('Cache-Control', 'no-store'))->name('sales.app.version');
});

// Offline fallback, precached by public/service-worker.js — public so it can be fetched at install time.
Route::domain(config('app.sales_domain'))
    ->get('/offline', fn () => view('sales.offline'))
    ->name('sales.offline');

Route::domain(config('app.sales_domain'))
    ->middleware(['auth', 'role:sales'])
    ->name('sales.')
    ->group(function () {
        // Three pages — Kerja, Rute, Lainnya; every other screen is a sheet over them.
        Route::get('/', \App\Livewire\Sales\Home::class)->name('home');
        Route::get('/rute', \App\Livewire\Sales\Routes\Index::class)->name('routes.index');
        Route::get('/lainnya', \App\Livewire\Sales\More::class)->name('more');
        Route::post('/lini', \App\Http\Controllers\LineController::class)->name('line');
        Route::post('/push-subscriptions', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.store');
        Route::delete('/push-subscriptions', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.destroy');
        Route::post('/perangkat', [\App\Http\Controllers\DeviceTokenController::class, 'store'])->name('device.store');
        Route::get('/offline/data', [\App\Http\Controllers\OfflineController::class, 'data'])->name('offline.data');
        Route::post('/offline/sync', [\App\Http\Controllers\OfflineController::class, 'sync'])->name('offline.sync');
        Route::post('/absensi/sesi', [\App\Http\Controllers\AttendanceController::class, 'session'])->name('absensi.session');
        Route::post('/absensi/submit', [\App\Http\Controllers\AttendanceController::class, 'store'])->name('absensi.submit');
        Route::post('/lokasi', [\App\Http\Controllers\TrackController::class, 'store'])->name('track.store');
        Route::get('/absensi/status', [\App\Http\Controllers\AttendanceController::class, 'status'])->name('absensi.status');

        // The old screens' addresses (links, push notifications) open the matching sheet.
        $sheet = fn (string $page, string $sheet) => fn (\Illuminate\Http\Request $r) => redirect()->route($page, array_filter(['buka' => $sheet, 'id' => $r->route('id')]));
        Route::get('/absensi', $sheet('sales.home', 'masuk'))->name('absensi.index');
        Route::get('/stok', $sheet('sales.home', 'ambil'))->name('stock.index');

        Route::get('/toko', $sheet('sales.home', ''))->name('buyers.index');
        Route::get('/toko/baru', $sheet('sales.home', 'toko-baru'))->name('buyers.create');
        Route::get('/toko/{id}', $sheet('sales.home', 'toko'))->whereNumber('id')->name('buyers.show');
        Route::get('/nota/baru', $sheet('sales.home', 'nota'))->name('notes.create');
        Route::get('/setoran', $sheet('sales.home', 'setor'))->name('deposits.index');
        Route::get('/investasi', $sheet('sales.home', 'investasi'))->name('investments.index');
        Route::get('/akun', $sheet('sales.more', 'akun'))->name('account');
        Route::get('/notifikasi', $sheet('sales.more', 'notifikasi'))->name('notifications');
        Route::get('/nota', $sheet('sales.more', 'nota-saya'))->name('notes.index');
        Route::get('/nota/{id}', $sheet('sales.more', 'nota-detail'))->whereNumber('id')->name('notes.show');
        Route::get('/kunjungan', $sheet('sales.more', 'kunjungan'))->name('visits.index');
        Route::get('/target', $sheet('sales.more', 'target'))->name('target.index');

        // Marketplace orders and rewards: unchanged, unreachable while MARKETPLACE_PURCHASING_OPEN is off.
        Route::get('/pesanan', \App\Livewire\Sales\Orders\Index::class)->name('orders.index');
        Route::get('/deliveries/{order}', \App\Livewire\Sales\Deliveries\Show::class)->name('deliveries.show');
        Route::get('/deliveries/{order}/tolak', \App\Livewire\Sales\Deliveries\Reject::class)->name('deliveries.reject');
        Route::get('/deliveries/{order}/nota', [\App\Http\Controllers\ReceiptController::class, 'show'])->name('deliveries.receipt');
        Route::get('/deliveries/{order}/nota.pdf', [\App\Http\Controllers\ReceiptController::class, 'pdf'])->name('deliveries.receipt.pdf');
        Route::get('/hadiah', \App\Livewire\Sales\Redemptions\Index::class)->name('redemptions.index');
    });
