<?php

namespace App\Support\Sales;

use App\Enums\AttendanceType;
use App\Enums\NotePaymentMethod;
use App\Models\Attendance;
use App\Models\DeliveryRouteStop;
use App\Models\SalesNote;
use App\Models\SalesTrackPoint;
use App\Models\User;
use App\Models\Visit;
use App\Support\BusinessDay;
use App\Support\Geo;
use Illuminate\Support\Carbon;

/**
 * A sales person's shift: check-in → check-out. The phone records GPS
 * points while the shift is open; the recap is the distance travelled, how much of the shift the GPS covered,
 * and the stores visited with what each bought.
 */
final class Shift
{
    /** Points worse than this are dropped: they would add kilometres that were never driven. */
    public const MAX_ACCURACY_M = 100;

    /** Moves shorter than this are GPS jitter while standing still. */
    private const MIN_MOVE_M = 15;

    /** A jump faster than this is a bad fix, not a drive. */
    private const MAX_SPEED_KMH = 150;

    /** No point for longer than this = a gap (GPS off, app closed, phone asleep). */
    public const GAP_MINUTES = 10;

    /** Today's check-in without a check-out yet. */
    public static function open(User $sales): ?Attendance
    {
        $today = Attendance::where('sales_id', $sales->id)->whereBetween('created_at', BusinessDay::bounds())->get();

        return $today->contains('tipe', AttendanceType::Pulang) ? null : $today->firstWhere('tipe', AttendanceType::Masuk);
    }

    /** Today's check-in, open or closed — what Kerja recaps. */
    public static function today(User $sales): ?Attendance
    {
        return Attendance::where('sales_id', $sales->id)->where('tipe', AttendanceType::Masuk)->whereBetween('created_at', BusinessDay::bounds())->first();
    }

    /**
     * Store points sent by the phone, in any order and possibly twice (offline queue).
     *
     * @param  list<array{lat: float, lng: float, acc: float, t: int}>  $points  t = epoch milliseconds on the phone
     */
    public static function record(Attendance $masuk, array $points): int
    {
        $from = $masuk->created_at->copy()->subMinute();
        $until = now()->addMinute();
        $rows = [];
        foreach ($points as $p) {
            $at = Carbon::createFromTimestampMs((int) $p['t']);
            if ((float) $p['acc'] > self::MAX_ACCURACY_M || $at->lt($from) || $at->gt($until)) {
                continue;
            }
            $rows[] = [
                'sales_id' => $masuk->sales_id, 'attendance_id' => $masuk->id,
                'latitude' => round((float) $p['lat'], 7), 'longitude' => round((float) $p['lng'], 7),
                'accuracy' => (int) round((float) $p['acc']), 'recorded_at' => $at, 'created_at' => now(),
            ];
        }

        return $rows === [] ? 0 : SalesTrackPoint::insertOrIgnore($rows);
    }

    /**
     * @return array{start: Carbon, end: Carbon, open: bool, km: float, minutes: int, gaps: list<array{0: Carbon, 1: Carbon}>,
     *               points: int, stores: list<array>, total: int, cash: int, transfer: int}
     */
    public static function recap(Attendance $masuk): array
    {
        $pulang = Attendance::where('sales_id', $masuk->sales_id)->where('tipe', AttendanceType::Pulang)
            ->where('created_at', '>=', $masuk->created_at)->whereBetween('created_at', BusinessDay::boundsFor($masuk->created_at->copy()->timezone(BusinessDay::zone())))->first();
        $start = $masuk->created_at;
        $end = $pulang?->created_at ?? now();
        $points = SalesTrackPoint::where('attendance_id', $masuk->id)->orderBy('recorded_at')->get(['latitude', 'longitude', 'recorded_at']);

        return [
            'start' => $start,
            'end' => $end,
            'open' => $pulang === null,
            'km' => round(self::distanceKm($points), 1),
            'minutes' => (int) $start->diffInMinutes($end),
            'gaps' => self::gaps($points->pluck('recorded_at')->prepend($start)->push($end)->all()),
            'points' => $points->count(),
            ...self::stores($masuk->sales_id, $start, $end),
        ];
    }

    private static function distanceKm($points): float
    {
        $km = 0.0;
        $last = null;
        foreach ($points as $p) {
            if (! $last) {
                $last = $p;

                continue;
            }
            $d = Geo::distanceKm($last->latitude, $last->longitude, $p->latitude, $p->longitude);
            $hours = max($last->recorded_at->diffInSeconds($p->recorded_at) / 3600, 1 / 3600);
            if ($d * 1000 < self::MIN_MOVE_M || $d / $hours > self::MAX_SPEED_KMH) {
                continue; // standing still, or a bad fix: keep measuring from the last good point
            }
            $km += $d;
            $last = $p;
        }

        return $km;
    }

    /** @param list<Carbon> $times start, every point, end @return list<array{0: Carbon, 1: Carbon}> */
    private static function gaps(array $times): array
    {
        $gaps = [];
        for ($i = 1; $i < count($times); $i++) {
            if ($times[$i - 1]->diffInMinutes($times[$i]) > self::GAP_MINUTES) {
                $gaps[] = [$times[$i - 1], $times[$i]];
            }
        }

        return $gaps;
    }

    /** Stores visited in the shift — a visit, a nota or a "toko tutup" — with what each bought. */
    private static function stores(int $salesId, Carbon $start, Carbon $end): array
    {
        $visits = Visit::where('sales_id', $salesId)->whereBetween('visited_at', [$start, $end])->with('buyer:id,name')->get();
        $notes = SalesNote::where('sales_id', $salesId)->whereBetween('created_at', [$start, $end])->get();
        $closed = DeliveryRouteStop::where('status', 'closed')->whereBetween('updated_at', [$start, $end])
            ->whereHas('route', fn ($q) => $q->where('sales_id', $salesId))->with('buyer:id,name')->get();

        $names = $visits->pluck('buyer.name', 'buyer_id')->union($closed->pluck('buyer.name', 'buyer_id'))
            ->union(User::whereKey($notes->pluck('buyer_id'))->pluck('name', 'id'));
        $stores = $names->map(function ($name, $buyerId) use ($visits, $notes, $closed) {
            $mine = $notes->where('buyer_id', $buyerId);
            $first = $visits->where('buyer_id', $buyerId)->min('visited_at') ?? $mine->min('created_at') ?? $closed->where('buyer_id', $buyerId)->min('updated_at');

            return [
                'id' => $buyerId,
                'name' => $name,
                'at' => Carbon::parse($first),
                'closed' => $closed->contains('buyer_id', $buyerId) && $mine->isEmpty(),
                'notes' => $mine->count(),
                'total' => (int) $mine->sum('total'),
            ];
        })->sortBy('at')->values()->all();

        return [
            'stores' => $stores,
            'total' => (int) $notes->sum('total'),
            'cash' => (int) $notes->where('payment_method', NotePaymentMethod::Cash)->sum('total'),
            'transfer' => (int) $notes->where('payment_method', '!=', NotePaymentMethod::Cash)->sum('total'),
        ];
    }
}
