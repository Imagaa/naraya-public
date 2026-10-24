<?php

namespace App\Console\Commands;

use Database\Seeders\SandboxSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

/**
 * Wipes the sandbox back to its starting data (SandboxSeeder): the database from scratch, its files under coba/,
 * its sessions. Refuses anywhere naraya.sandbox is off — production keeps it off.
 */
class ResetSandbox extends Command
{
    protected $signature = 'sandbox:reset';

    protected $description = 'Reset the sandbox server to its demo data (sandbox only)';

    public function handle(): int
    {
        if (! config('naraya.sandbox') || app()->isProduction() || config('filesystems.disks.s3.root') === '') {
            $this->error('Bukan server coba (SANDBOX, APP_ENV dan AWS_ROOT harus diatur): tidak ada yang dihapus.');

            return self::FAILURE;
        }

        $disk = Storage::disk('s3');
        foreach ($disk->directories() as $dir) {
            if ($dir !== 'apps') { // the Naraya Coba app's releases stay
                $disk->deleteDirectory($dir);
            }
        }
        Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true, '--seeder' => SandboxSeeder::class]);
        if ((string) config('database.redis.default.database') !== '0') {
            Redis::connection('default')->flushdb(); // sandbox sessions: nobody stays logged in as a user that no longer exists
        }
        $this->info('Data coba direset.');

        return self::SUCCESS;
    }
}
