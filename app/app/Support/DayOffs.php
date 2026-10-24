<?php

namespace App\Support;

use App\Models\SalesDayOff;
use App\Models\User;
use App\Notifications\AdminNotice;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Weekly day off: a sales picks one day per Monday–Sunday week, from tomorrow
 * on (WIB), and it applies at once — no approval. The sales may cancel it
 * before the day; admin may cancel any time, with a reason.
 */
final class DayOffs
{
    /** How far ahead a day off may be taken. */
    public const MAX_DAYS_AHEAD = 30;

    public function request(User $sales, CarbonInterface|string $date, ?string $note = null): SalesDayOff
    {
        $day = $this->day($date);
        $tomorrow = BusinessDay::today()->addDay();
        $fail = fn (string $message) => throw ValidationException::withMessages(['dayOffDate' => $message]);

        if ($day->lt($tomorrow)) {
            $fail('Libur diajukan paling lambat H-1: pilih besok atau setelahnya.');
        }
        if ($day->gt(BusinessDay::today()->addDays(self::MAX_DAYS_AHEAD))) {
            $fail('Libur paling jauh '.self::MAX_DAYS_AHEAD.' hari ke depan.');
        }
        $calendar = new AttendanceCalendar;
        if ($name = $calendar->holidayOn($day)) {
            $fail("Tanggal itu sudah libur ({$name}).");
        }
        if ($calendar->for($sales, $day) === null) {
            $fail('Tanggal itu memang bukan hari kerja Anda.');
        }

        return DB::transaction(function () use ($sales, $day, $note, $fail) {
            $taken = SalesDayOff::active()->where('sales_id', $sales->id)
                ->whereDate('date', '>=', $day->copy()->startOfWeek(Carbon::MONDAY)->toDateString())->whereDate('date', '<=', $day->copy()->endOfWeek(Carbon::SUNDAY)->toDateString())
                ->lockForUpdate()->first();
            if ($taken) {
                $fail('Jatah libur minggu itu sudah dipakai ('.$taken->date->locale('id')->translatedFormat('l, j M').').');
            }

            $off = SalesDayOff::create(['sales_id' => $sales->id, 'date' => $day->toDateString(), 'note' => trim((string) $note) ?: null]);
            AdminNotice::toAdmins('Pengajuan libur', "{$sales->name} libur ".$day->copy()->locale('id')->translatedFormat('l, j M'), route('admin.attendances.day-offs', absolute: false), 'calendar-off');

            return $off;
        });
    }

    public function cancel(SalesDayOff $off, User $by, ?string $reason = null): void
    {
        $byAdmin = $by->id !== $off->sales_id;
        if (! $byAdmin && $off->date->toDateString() <= BusinessDay::today()->toDateString()) {
            throw ValidationException::withMessages(['dayOffDate' => 'Libur yang sudah berjalan tidak bisa dibatalkan.']);
        }
        if ($byAdmin && trim((string) $reason) === '') {
            throw ValidationException::withMessages(['cancelReason' => 'Tulis alasan pembatalan.']);
        }

        $off->update(['cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_reason' => $byAdmin ? trim($reason) : null]);
    }

    /** The active day off in the Monday–Sunday week containing $date, if any. */
    public function inWeek(User $sales, CarbonInterface $date): ?SalesDayOff
    {
        $day = $this->day($date);

        return SalesDayOff::active()->where('sales_id', $sales->id)
            ->whereDate('date', '>=', $day->copy()->startOfWeek(Carbon::MONDAY)->toDateString())->whereDate('date', '<=', $day->copy()->endOfWeek(Carbon::SUNDAY)->toDateString())->first();
    }

    private function day(CarbonInterface|string $date): Carbon
    {
        return Carbon::parse($date instanceof CarbonInterface ? $date->format('Y-m-d') : $date, BusinessDay::zone())->startOfDay();
    }
}
