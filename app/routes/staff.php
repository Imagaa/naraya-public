<?php

use Illuminate\Support\Facades\Route;

// The old shared staff address. Every portal now has its own door on its own
// subdomain, so this only points staff (and old bookmarks) to the right one.
Route::domain(config('app.staff_domain'))->group(function () {
    Route::view('/', 'auth.staff-portals')->name('staff.portals');
    Route::view('/login', 'auth.staff-portals');
});
