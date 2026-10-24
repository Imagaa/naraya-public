<?php

namespace App\Livewire\Admin\Reports\Concerns;

use App\Support\BusinessDay;
use Carbon\Carbon;

trait HasDateRangeFilter
{
    public string $startDate;
    public string $endDate;

    public function mount(): void
    {
        $this->startDate = BusinessDay::now()->startOfMonth()->toDateString();
        $this->endDate = BusinessDay::now()->endOfMonth()->toDateString();
    }

    /** Quick periods (WIB): this_month, last_month, last_30. */
    public function setPeriod(string $period): void
    {
        $now = BusinessDay::now();
        [$start, $end] = match ($period) {
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'last_30' => [$now->copy()->subDays(29), $now->copy()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
        $this->startDate = $start->toDateString();
        $this->endDate = $end->toDateString();
    }

    /** The quick period the current dates match, if any (lights up its pill). */
    public function activePeriod(): ?string
    {
        foreach (['this_month', 'last_month', 'last_30'] as $period) {
            [$start, $end] = [$this->startDate, $this->endDate];
            $this->setPeriod($period);
            $match = [$this->startDate, $this->endDate] === [$start, $end];
            [$this->startDate, $this->endDate] = [$start, $end];
            if ($match) {
                return $period;
            }
        }

        return null;
    }

    /** The report's dates are WIB days; timestamps are UTC. */
    protected function rangeStart(): Carbon
    {
        return BusinessDay::boundsFor($this->startDate)[0];
    }

    protected function rangeEnd(): Carbon
    {
        return BusinessDay::boundsFor($this->endDate)[1];
    }
}
