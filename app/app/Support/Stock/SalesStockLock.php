<?php

namespace App\Support\Stock;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * One writer at a time per sales person for anything that changes their
 * carried stock (pickups, returns, stock checks, sales notes), so two taps
 * from two tabs can't both pass the "within carried / within warehouse" caps.
 */
final class SalesStockLock
{
    public static function run(User $sales, callable $work): mixed
    {
        $lock = Cache::lock("stock-sales:{$sales->id}", 15);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['items' => 'Catatan sebelumnya masih diproses, coba lagi sebentar.']);
        }

        try {
            return $work();
        } finally {
            $lock->release();
        }
    }
}
