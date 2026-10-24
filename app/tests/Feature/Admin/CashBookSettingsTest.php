<?php

use App\Enums\Role;
use App\Livewire\Admin\CashBookSettings\Edit;
use App\Models\CashBookSetting;
use App\Models\User;
use Livewire\Livewire;

it('saves the closing day between 1 and 28', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    Livewire::actingAs($admin)->test(Edit::class)->assertSet('closingDay', 5)
        ->set('closingDay', 29)->call('save')->assertHasErrors('closingDay')
        ->set('closingDay', 10)->call('save')->assertHasNoErrors();

    expect(CashBookSetting::current()->closing_day)->toBe(10);
});
