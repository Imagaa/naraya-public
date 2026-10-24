<?php

use App\Enums\Role;
use App\Exports\GenericTableExport;
use App\Models\User;

it('exports headings and mapped rows in order', function () {
    $export = new GenericTableExport(
        ['Nama', 'Jumlah'],
        collect([['Budi', 'Rp 10.000'], ['Ani', 'Rp 20.000']])
    );

    expect($export->headings())->toBe(['Nama', 'Jumlah']);
    expect($export->collection())->toHaveCount(2);
    expect($export->map(['Budi', 'Rp 10.000']))->toBe(['Budi', 'Rp 10.000']);
});

it('shows the reports index page to admin and blocks other roles', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get('http://admin.'.config('app.domain').'/laporan')
        ->assertOk()
        ->assertSee('Laporan');

    $sales = User::factory()->create(['role' => Role::Sales]);
    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/laporan')
        ->assertStatus(403);
});
