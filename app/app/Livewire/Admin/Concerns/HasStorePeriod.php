<?php

namespace App\Livewire\Admin\Concerns;

use App\Support\BusinessDay;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * The period of the store report pages:
 * today | 7d (today + the 6 days before) | month (1st to today) | range (whole months). WIB days.
 */
trait HasStorePeriod
{
    #[Url]
    public string $period = 'month';

    #[Url]
    public string $fromMonth = '';

    #[Url]
    public string $toMonth = '';

    /** Livewire calls this before the component's own mount(). */
    public function mountHasStorePeriod(): void
    {
        $this->fromMonth = $this->validMonth($this->fromMonth);
        $this->toMonth = $this->validMonth($this->toMonth);
    }

    public function setPeriod(string $period): void
    {
        $this->period = in_array($period, ['today', '7d', 'month', 'range'], true) ? $period : 'month';
    }

    /** @return array{0: Carbon, 1: Carbon} UTC bounds */
    public function bounds(): array
    {
        [$start, $end] = $this->days();

        return [BusinessDay::boundsFor($start)[0], BusinessDay::boundsFor($end)[1]];
    }

    /** "23 Okt 2026", "17–23 Okt 2026", "Agu – Sep 2026". */
    public function periodLabel(): string
    {
        [$start, $end] = array_map(fn (Carbon $d) => $d->copy()->locale('id'), $this->days());
        if ($this->period === 'range') {
            return $start->isSameMonth($end) ? $start->translatedFormat('F Y')
                : $start->translatedFormat($start->isSameYear($end) ? 'M' : 'M Y').' – '.$end->translatedFormat('M Y');
        }

        return $start->isSameDay($end) ? $start->translatedFormat('j M Y') : $start->format('j').'–'.$end->translatedFormat('j M Y');
    }

    /** @return array{0: Carbon, 1: Carbon} first and last WIB day */
    private function days(): array
    {
        $today = BusinessDay::today();

        if ($this->period === 'range') {
            $from = Carbon::parse($this->validMonth($this->fromMonth).'-01', BusinessDay::zone());
            $to = Carbon::parse($this->validMonth($this->toMonth).'-01', BusinessDay::zone());
            [$from, $to] = $from->gt($to) ? [$to, $from] : [$from, $to];

            return [$from->startOfMonth(), $to->endOfMonth()->startOfDay()];
        }

        return match ($this->period) {
            'today' => [$today, $today->copy()],
            '7d' => [$today->copy()->subDays(6), $today],
            default => [$today->copy()->startOfMonth(), $today],
        };
    }

    /** "Y-m", or this month when empty or malformed (it comes from the query string). */
    private function validMonth(string $month): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : BusinessDay::today()->format('Y-m');
    }
}
