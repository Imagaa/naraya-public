<?php

namespace App\Support;

/**
 * Which build is running: the Docker image writes `.build-id` (a timestamp) at build time, so every deploy has a new
 * one. The sales app compares it with the page it shows and reloads itself after a deploy (resources/js/sales-pwa.js).
 * Null where there is no image build (local, tests): the reload then never triggers.
 */
class BuildId
{
    private static ?string $id = null;

    public static function current(): ?string
    {
        return self::$id ??= (is_file($f = base_path('.build-id')) ? trim((string) file_get_contents($f)) : '') ?: null;
    }
}
