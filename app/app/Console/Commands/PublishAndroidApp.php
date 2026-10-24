<?php

namespace App\Console\Commands;

use App\Support\Mobile\AndroidRelease;
use Illuminate\Console\Command;

/** Upload a signed Naraya Sales APK and make it the version the app updates to. */
class PublishAndroidApp extends Command
{
    protected $signature = 'android:publish {apk : path to the signed APK} {code : versionCode, higher than the last} {name : versionName, e.g. 1.0.0} {--notes= : what changed, shown in the update dialog}';

    protected $description = 'Publish a Naraya Sales Android release (APK + latest.json on the s3 disk)';

    public function handle(): int
    {
        $apk = (string) $this->argument('apk');
        $code = (int) $this->argument('code');
        if (! is_file($apk)) {
            $this->error("APK tidak ditemukan: {$apk}");

            return self::FAILURE;
        }
        if (($latest = AndroidRelease::latest()) && $code <= $latest['versionCode']) {
            $this->error("versionCode harus lebih dari {$latest['versionCode']}.");

            return self::FAILURE;
        }

        $release = AndroidRelease::publish($apk, $code, (string) $this->argument('name'), $this->option('notes'));
        $this->info("Terbit: {$release['versionName']} ({$release['versionCode']}) — {$release['apkUrl']}");

        return self::SUCCESS;
    }
}
