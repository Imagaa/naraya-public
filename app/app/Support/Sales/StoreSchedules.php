<?php

namespace App\Support\Sales;

use App\Models\BuyerProfile;
use App\Models\StoreSchedule;
use App\Models\User;
use App\Support\BusinessDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A sales person's weekly store schedule: store X on weekday N, every
 * week. A store counts only while it is still assigned to that sales person, so a reassigned store drops out by itself.
 */
class StoreSchedules
{
    public const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public const SHORT = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    public static function today(): int
    {
        return BusinessDay::today()->isoWeekday();
    }

    /** @return array<int, list<int>> buyer id => its weekdays, ascending */
    public static function daysByStore(User $sales): array
    {
        return self::assigned($sales)->orderBy('weekday')->get(['buyer_id', 'weekday'])
            ->groupBy('buyer_id')->map(fn ($rows) => $rows->pluck('weekday')->all())->all();
    }

    /** @return list<int> buyer ids scheduled on that weekday */
    public static function storeIdsFor(User $sales, int $weekday): array
    {
        return self::assigned($sales)->where('weekday', $weekday)->pluck('buyer_id')->all();
    }

    /** Replaces that weekday's stores with exactly these. */
    public function saveDay(User $sales, int $weekday, array $buyerIds): void
    {
        if (! isset(self::DAYS[$weekday])) {
            throw ValidationException::withMessages(['jadwal' => 'Hari tidak dikenal.']);
        }
        $buyerIds = array_values(array_unique(array_map('intval', $buyerIds)));
        if (BuyerProfile::where('sales_user_id', $sales->id)->whereIn('user_id', $buyerIds)->count() !== count($buyerIds)) {
            throw ValidationException::withMessages(['jadwal' => 'Ada toko yang bukan toko Anda.']);
        }

        DB::transaction(function () use ($sales, $weekday, $buyerIds) {
            StoreSchedule::where('sales_user_id', $sales->id)->where('weekday', $weekday)->whereNotIn('buyer_id', $buyerIds)->delete();
            $have = StoreSchedule::where('sales_user_id', $sales->id)->where('weekday', $weekday)->pluck('buyer_id')->all();
            foreach (array_diff($buyerIds, $have) as $id) {
                StoreSchedule::create(['sales_user_id' => $sales->id, 'buyer_id' => $id, 'weekday' => $weekday]);
            }
        });
    }

    /** "Sen · Kam", or null when unscheduled. */
    public static function label(array $weekdays): ?string
    {
        return $weekdays ? implode(' · ', array_map(fn ($d) => self::SHORT[$d], $weekdays)) : null;
    }

    private static function assigned(User $sales)
    {
        return StoreSchedule::where('sales_user_id', $sales->id)
            ->whereIn('buyer_id', BuyerProfile::where('sales_user_id', $sales->id)->select('user_id'));
    }
}
