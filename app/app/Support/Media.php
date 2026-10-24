<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class Media
{
    /**
     * Delete a stored media file that is no longer referenced. Files under
     * seed/ are demo assets shared by several records (one sales photo for
     * many sales, one brand photo for many products), so they are never
     * deleted; real uploads always get a unique path of their own.
     */
    public static function forget(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'seed/')) {
            Storage::disk('s3')->delete($path);
        }
    }
}
