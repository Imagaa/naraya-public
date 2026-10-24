<?php

use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Livewire\Sales\Home;
use App\Models\Attendance;
use App\Models\SalesProfile;
use App\Models\User;
use Livewire\Livewire;

// Kerja: the greeting lives in the Absensi card; no manual offline switch
beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    SalesProfile::factory()->create(['user_id' => $this->sales->id]);
    $this->kerja = fn () => Livewire::actingAs($this->sales)->test(Home::class);
});

it('greets and reminds before check-in', function () {
    ($this->kerja)()
        ->assertSeeInOrder(['Halo, Budi', 'Jangan lupa absen!', 'Absen masuk &amp; pilih stok'], false)
        ->assertDontSee('<h1>Halo, Budi</h1>', false)
        ->assertDontSee('Sinyal lemah? Kerja offline')
        ->assertDontSee('Sinyal sudah bagus');
});

it('drops the reminder once checked in', function () {
    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Masuk, 'created_at' => now()]);

    ($this->kerja)()->assertSee('Halo, Budi')->assertDontSee('Jangan lupa absen!')->assertSee('Absen pulang');
});

it('thanks the sales person after check-out', function () {
    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Masuk, 'created_at' => now()]);
    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Pulang, 'created_at' => now()]);

    ($this->kerja)()->assertSee('Halo, Budi')
        ->assertSee('Terima kasih atas kerja kerasnya! Selamat beristirahat!')
        ->assertDontSee('Absen pulang')->assertDontSee('Absen masuk')->assertDontSee('Jangan lupa absen!');
});
