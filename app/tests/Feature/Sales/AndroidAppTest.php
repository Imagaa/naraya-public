<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('s3'));

it('says there is no app yet until one is published', function () {
    $this->getJson(salesUrl('/aplikasi/versi'))->assertOk()->assertJson(['versionCode' => 0]);
    $this->get(salesUrl('/aplikasi'))->assertOk()->assertSee('Aplikasi belum tersedia');
});

it('reports the running build, so an open page reloads itself after a deploy', function () {
    $file = base_path('.build-id');
    expect(file_exists($file))->toBeFalse(); // only Docker images write it
    $this->getJson(salesUrl('/aplikasi/versi'))->assertJsonPath('build', null)->assertHeader('Cache-Control', 'no-store, private');

    file_put_contents($file, "1760400000
");
    try {
        (fn () => self::$id = null)->bindTo(null, \App\Support\BuildId::class)();
        $this->getJson(salesUrl('/aplikasi/versi'))->assertJsonPath('build', '1760400000');
    } finally {
        unlink($file);
        (fn () => self::$id = null)->bindTo(null, \App\Support\BuildId::class)();
    }
});

it('publishes a release that the app and the download page then offer, and refuses an older versionCode', function () {
    $apk = tempnam(sys_get_temp_dir(), 'apk');
    file_put_contents($apk, 'PK fake apk');

    expect(Artisan::call('android:publish', ['apk' => $apk, 'code' => 3, 'name' => '1.0.2', '--notes' => 'GPS lebih awet']))->toBe(0);
    Storage::disk('s3')->assertExists('apps/android/naraya-sales-1.0.2-3.apk');

    $this->getJson(salesUrl('/aplikasi/versi'))->assertOk()
        ->assertJson(['versionCode' => 3, 'versionName' => '1.0.2', 'notes' => 'GPS lebih awet'])
        ->assertJsonPath('apkUrl', fn ($url) => str_contains($url, 'naraya-sales-1.0.2-3.apk'));
    $this->get(salesUrl('/aplikasi'))->assertSee('Unduh aplikasi (versi 1.0.2)')->assertSee('GPS lebih awet');

    expect(Artisan::call('android:publish', ['apk' => $apk, 'code' => 3, 'name' => '1.0.3']))->toBe(1);
});

it('links Lainnya to the download page', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Sales]))->get(salesUrl('/lainnya'))
        ->assertSee('Unduh aplikasi Android')->assertSee(route('sales.app'), false);
});
