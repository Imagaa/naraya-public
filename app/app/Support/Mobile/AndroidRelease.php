<?php

namespace App\Support\Mobile;

use Illuminate\Support\Facades\Storage;

/**
 * The Naraya Sales Android app's latest release: the APK and a small
 * latest.json beside it on the s3 disk. The app asks /aplikasi/versi on start and offers the update.
 */
final class AndroidRelease
{
    private const DIR = 'apps/android';

    /** @return array{versionCode: int, versionName: string, apkUrl: string, notes: ?string, publishedAt: string}|null */
    public static function latest(): ?array
    {
        $disk = Storage::disk('s3');
        $json = $disk->exists(self::DIR.'/latest.json') ? json_decode((string) $disk->get(self::DIR.'/latest.json'), true) : null;

        return is_array($json) && isset($json['versionCode'], $json['apkUrl']) ? $json : null;
    }

    public static function publish(string $apkPath, int $code, string $name, ?string $notes = null): array
    {
        $disk = Storage::disk('s3');
        $key = self::DIR."/naraya-sales-{$name}-{$code}.apk";
        $stream = fopen($apkPath, 'r');
        $disk->put($key, $stream, ['ContentType' => 'application/vnd.android.package-archive', 'ContentDisposition' => 'attachment; filename="naraya-sales.apk"']);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $release = ['versionCode' => $code, 'versionName' => $name, 'apkUrl' => $disk->url($key), 'notes' => $notes, 'publishedAt' => now()->toIso8601String()];
        $disk->put(self::DIR.'/latest.json', json_encode($release, JSON_UNESCAPED_SLASHES), ['ContentType' => 'application/json']);

        return $release;
    }
}
