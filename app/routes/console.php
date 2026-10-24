<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cashbook:post-daily', function () {
    $this->info(app(\App\Services\CashBook\DailyPoster::class)->postPending().' entri rekap dibukukan.');
})->purpose('Buku Kas: post the daily summary entries for every unposted day up to yesterday');

Artisan::command('cashbook:close-months', function () {
    $this->info(app(\App\Services\CashBook\Ledger::class)->closeDue().' bulan ditutup.');
})->purpose('Buku Kas: close every month that is past its closing day');

Artisan::command('investments:pay', function () {
    $this->info(app(\App\Support\Investment\Investments::class)->payDue().' pembayaran investasi dikreditkan.');
})->purpose('Investasi: credit every monthly payment (and returned principal) that has fallen due');

// Owner push: things that went too long, told once each, and the recaps
Artisan::command('admin:notify-checks', function () {
    $this->info(app(\App\Support\Admin\OwnerChecks::class)->run().' notifikasi.');
})->purpose('Owner: tell what has waited too long (orders, field, money, returns), once per subject');

Artisan::command('admin:payout-reminder', function () {
    $this->info(app(\App\Support\Admin\OwnerChecks::class)->payoutReminder().' notifikasi.');
})->purpose('Owner: the investment payouts due tomorrow');

Artisan::command('admin:daily-recap', function () {
    $this->info(app(\App\Support\Admin\OwnerChecks::class)->dailyRecap().' notifikasi.');
})->purpose('Owner: the day in one notice');

Artisan::command('admin:weekly-recap', function () {
    $this->info(app(\App\Support\Admin\OwnerChecks::class)->weeklyRecap().' notifikasi.');
})->purpose('Owner: last week in one notice');
