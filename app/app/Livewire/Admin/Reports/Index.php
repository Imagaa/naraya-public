<?php

namespace App\Livewire\Admin\Reports;

use App\Support\BusinessDay;
use App\Enums\OrderStatus;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/** Laporan landing: the charts that used to crowd the dashboard, then every report. */
class Index extends Component
{
    use HasAdminShell;

    public string $chartStart;

    public string $chartEnd;

    public function mount(): void
    {
        $this->chartStart = BusinessDay::now()->subDays(29)->toDateString();
        $this->chartEnd = BusinessDay::now()->toDateString();
    }

    public function setChartRangePreset(int $days): void
    {
        $this->chartStart = BusinessDay::now()->subDays($days - 1)->toDateString();
        $this->chartEnd = BusinessDay::now()->toDateString();
        $this->pushChartData();
    }

    public function applyChartRange(): void
    {
        $this->validate([
            'chartStart' => 'required|date',
            'chartEnd' => 'required|date|after_or_equal:chartStart',
        ]);

        $this->pushChartData();
    }

    private function pushChartData(): void
    {
        $this->dispatch('dashboard-charts-updated', trend: $this->salesTrend(), topProducts: $this->topProducts());
    }

    /** The quick range the chart currently shows (lights up its pill). */
    public function chartPreset(): ?int
    {
        foreach ([7, 30, 90] as $days) {
            if ($this->chartEnd === BusinessDay::now()->toDateString() && $this->chartStart === BusinessDay::now()->subDays($days - 1)->toDateString()) {
                return $days;
            }
        }

        return null;
    }

    public function render()
    {
        return view('livewire.admin.reports.index', [
            'salesTrend' => $this->salesTrend(),
            'topProducts' => $this->topProducts(),
            'orderPipeline' => $this->orderPipeline(),
        ])->layout('layouts.admin');
    }

    /**
     * One daily-totals query for the selected range, bucketed into
     * daily/weekly/monthly points depending on the range length so a
     * 90-day view doesn't render 90 noisy daily bars.
     */
    private function salesTrend(): array
    {
        $start = Carbon::parse($this->chartStart)->startOfDay();
        $end = Carbon::parse($this->chartEnd)->endOfDay();
        $spanDays = $start->diffInDays($end) + 1;

        // Completed orders bucketed by their WIB day (timestamps are UTC).
        $daily = Order::where('status', OrderStatus::Completed)
            ->whereBetween('completed_at', [BusinessDay::boundsFor($start)[0], BusinessDay::boundsFor($end)[1]])
            ->get(['completed_at', 'total'])
            ->groupBy(fn ($o) => $o->completed_at->wib()->format('Y-m-d'))
            ->map(fn ($g) => $g->sum('total'));

        if ($spanDays <= 31) {
            $series = collect();
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $series->push(['label' => $d->translatedFormat('d M'), 'total' => (float) ($daily[$d->format('Y-m-d')] ?? 0)]);
            }
        } elseif ($spanDays <= 120) {
            $series = collect();
            for ($w = $start->copy(); $w->lte($end); $w->addWeek()) {
                $weekEnd = min($w->copy()->addDays(6), $end);
                $sum = 0.0;
                for ($d = $w->copy(); $d->lte($weekEnd); $d->addDay()) {
                    $sum += (float) ($daily[$d->format('Y-m-d')] ?? 0);
                }
                $series->push(['label' => $w->translatedFormat('d M'), 'total' => $sum]);
            }
        } else {
            $series = collect();
            for ($m = $start->copy()->startOfMonth(); $m->lte($end); $m->addMonth()) {
                $monthEnd = min($m->copy()->endOfMonth(), $end);
                $sum = 0.0;
                for ($d = $m->copy()->max($start); $d->lte($monthEnd); $d->addDay()) {
                    $sum += (float) ($daily[$d->format('Y-m-d')] ?? 0);
                }
                $series->push(['label' => $m->translatedFormat('M Y'), 'total' => $sum]);
            }
        }

        return $series->values()->all();
    }

    /**
     * Top 5 best-selling products (by quantity) for the same date range as
     * the sales trend chart.
     */
    private function topProducts(): array
    {
        $start = Carbon::parse($this->chartStart)->startOfDay();
        $end = Carbon::parse($this->chartEnd)->endOfDay();

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereBetween('orders.completed_at', [BusinessDay::boundsFor($start)[0], BusinessDay::boundsFor($end)[1]])
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as qty')
            ->groupBy('order_items.product_name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['label' => $row->product_name, 'total' => (int) $row->qty])
            ->values()->all();
    }

    /**
     * DB::table (not the Order model) to keep plucked keys as plain status
     * strings instead of OrderStatus enum objects. All-time counts (not
     * date-ranged) — this is "where is everything right now", not a trend.
     */
    private function orderPipeline(): array
    {
        $counts = DB::table('orders')
            ->whereIn('status', ['new', 'processing', 'shipped', 'completed'])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = $counts->sum();

        return collect(['new', 'processing', 'shipped', 'completed'])->map(fn ($status) => [
            'label' => OrderStatus::from($status)->label(),
            'total' => (int) ($counts[$status] ?? 0),
            'percent' => $total > 0 ? (int) round(($counts[$status] ?? 0) / $total * 100) : 0,
        ])->values()->all();
    }
}
