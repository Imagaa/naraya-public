<?php

use App\Enums\Role;
use App\Models\User;
use Livewire\Livewire;

it('lays every settings page out at the same width, so the tab row never needs to scroll', function (string $component) {
    $admin = User::factory()->create(['role' => Role::Admin]);
    \App\Models\LoyaltySetting::firstOrCreate([], ['rupiah_per_point' => 10000]);
    \App\Models\WarehouseSetting::firstOrCreate([], ['lat' => -6.2, 'lng' => 106.8]);

    Livewire::actingAs($admin)->test($component)->assertSeeHtml('class="adm-page adm-settings"');
})->with([
    \App\Livewire\Admin\LoyaltySettings\Edit::class,
    \App\Livewire\Admin\WarehouseSettings\Edit::class,
    \App\Livewire\Admin\CashBookSettings\Edit::class,
    \App\Livewire\Admin\AttendanceSettings\Edit::class,
    \App\Livewire\Admin\ExpenseCategories\Index::class,
]);
