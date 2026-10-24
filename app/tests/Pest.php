<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/** Absolute URL on the sales subdomain (routes there are domain-bound). */
function salesUrl(string $path = '/'): string
{
    return 'http://'.config('app.sales_domain').$path;
}

/** Make the general Sunday an off day again, for tests about schedule off days (Sunday is a working day since 2026-10-10). */
function sundayOff(): void
{
    \App\Models\AttendanceSchedule::whereNull('user_id')->where('weekday', 7)->update(['jam_masuk' => null, 'jam_pulang' => null]);
}
