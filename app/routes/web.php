<?php

use Illuminate\Support\Facades\Route;

// buyer.ready: a logged-in buyer with an unmet onboarding gate (new password,
// store data, approval) is held on that gate page; guests pass through.
Route::domain(config('app.domain'))->middleware(['portal.open:marketplace', 'buyer.ready'])->group(function () {
    Route::get('/', \App\Livewire\Marketplace\Home::class);
    Route::post('/lini', \App\Http\Controllers\LineController::class)->name('line');
    Route::get('/produk', \App\Livewire\Marketplace\ProductsIndex::class)->name('products.index');
    Route::get('/produk/{product:slug}', \App\Livewire\Marketplace\ProductShow::class)->name('products.show');
    Route::get('/login', \App\Livewire\Auth\Login::class)
        ->middleware('guest')
        ->name('login');
    Route::get('/checkout', \App\Livewire\Marketplace\Checkout::class)
        ->middleware(['portal.open:marketplace_purchasing', 'auth', 'role:user'])
        ->name('checkout');
    Route::get('/pesanan', \App\Livewire\Marketplace\Orders::class)
        ->middleware(['auth', 'role:user'])
        ->name('orders.index');
    Route::get('/pesanan/{order}', \App\Livewire\Marketplace\Orders\Show::class)
        ->middleware(['auth', 'role:user'])
        ->name('orders.show');
    Route::get('/pesanan/{order}/nota', [\App\Http\Controllers\ReceiptController::class, 'show'])
        ->middleware(['auth', 'role:user'])
        ->name('orders.receipt');
    Route::get('/pesanan/{order}/nota.pdf', [\App\Http\Controllers\ReceiptController::class, 'pdf'])
        ->middleware(['auth', 'role:user'])
        ->name('orders.receipt.pdf');
    Route::get('/pesanan/{order}/komplain', \App\Livewire\Marketplace\Complaints\Create::class)
        ->middleware(['auth', 'role:user'])
        ->name('complaints.create');
    Route::get('/hadiah', \App\Livewire\Marketplace\Rewards\Index::class)
        ->middleware(['auth', 'role:user'])
        ->name('rewards.index');
    // /akun is the overview (store card + menu); each section has its own URL so it opens
    // as a dedicated page on phones and the back button works.
    Route::get('/akun/{section?}', \App\Livewire\Marketplace\Account::class)
        ->where('section', 'profil|alamat|keamanan')
        ->middleware(['auth', 'role:user'])
        ->name('account');
    Route::get('/akun/kata-sandi-baru', \App\Livewire\Marketplace\Onboarding\NewPassword::class)
        ->middleware(['auth', 'role:user'])
        ->name('onboarding.password');
    Route::get('/akun/lengkapi-toko', \App\Livewire\Marketplace\Onboarding\CompleteStore::class)
        ->middleware(['auth', 'role:user'])
        ->name('onboarding.store');
    Route::get('/akun/status', \App\Livewire\Marketplace\Onboarding\AccountStatus::class)
        ->middleware(['auth', 'role:user'])
        ->name('onboarding.status');
    Route::get('/daftar', \App\Livewire\Auth\Register::class)
        ->middleware('guest')
        ->name('register');
    Route::get('/investasi', \App\Livewire\Marketplace\Investments\Index::class)
        ->middleware(['auth', 'role:user'])
        ->name('investments.index');
    Route::get('/notifikasi', \App\Livewire\Marketplace\Notifications::class)
        ->middleware(['auth', 'role:user'])
        ->name('notifications');
    Route::post('/logout', \App\Http\Controllers\Auth\LogoutController::class)
        ->middleware('auth')
        ->name('logout');
});

// Public webhook — Midtrans calls this directly with no session/subdomain context.
Route::post('/midtrans/webhook', \App\Http\Controllers\MidtransWebhookController::class);
