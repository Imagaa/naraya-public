<?php

namespace App\Livewire\Admin\Dashboard;

use App\Support\BusinessDay;
use App\Enums\CashCollectionStatus;
use App\Enums\OrderStatus;
use App\Livewire\Admin\Concerns\HasAdminShell;
use App\Models\CashCollection;
use App\Models\Order;
use App\Models\SalesProfile;
use App\Support\Admin\WorkQueues;
use Livewire\Component;

/**
 * The admin's work queue plus four KPIs. Charts live
 * on Laporan now; this page answers "what do I do next" at a glance.
 */
class Index extends Component
{
    use HasAdminShell;

    public function render()
    {
        $queues = WorkQueues::all();
        $now = BusinessDay::now();
        $completed = fn () => Order::where('status', OrderStatus::Completed);
        $targetTotal = (int) SalesProfile::where('is_active', true)->sum('monthly_target');
        $salesMonth = (int) $completed()->where('completed_at', '>=', $now->copy()->startOfMonth()->utc())->sum('total');
        $held = fn () => CashCollection::where('status', CashCollectionStatus::Held);

        // who is checked in and not yet out, for the "sales sedang bekerja" tile
        $working = \App\Models\Attendance::where('tipe', \App\Enums\AttendanceType::Masuk)->whereBetween('created_at', \App\Support\BusinessDay::bounds())
            ->whereNotExists(fn ($q) => $q->from('attendances as p')->whereColumn('p.sales_id', 'attendances.sales_id')->where('p.tipe', \App\Enums\AttendanceType::Pulang->value)
                ->whereBetween('p.created_at', \App\Support\BusinessDay::bounds()))
            ->with('salesUser:id,name')->orderBy('created_at')->get()
            ->map(fn ($m) => ['name' => $m->salesUser?->name, 'since' => $m->created_at->wib()->format('H.i')]);

        return view('livewire.admin.dashboard.index', [
            'working' => $working,
            'workingCount' => $working->count(),
            'activeSalesCount' => \App\Models\User::activeSales()->count(),
            'groups' => collect($queues)->groupBy('group')
                ->sortBy(fn ($rows, $group) => array_search($group, WorkQueues::GROUPS, true))
                ->map->all()->all(),
            'pending' => array_sum(array_column($queues, 'count')),
            'lateQueues' => count(array_filter($queues, fn ($q) => $q['severity'] === 'late')),
            'greeting' => match (true) {
                $now->hour < 11 => 'Selamat pagi',
                $now->hour < 15 => 'Selamat siang',
                $now->hour < 18 => 'Selamat sore',
                default => 'Selamat malam',
            },
            'salesToday' => (int) $completed()->whereBetween('completed_at', BusinessDay::bounds())->sum('total'),
            'salesYesterday' => (int) $completed()->whereBetween('completed_at', BusinessDay::boundsFor($now->copy()->subDay()))->sum('total'),
            'ordersInFlight' => Order::whereIn('status', [OrderStatus::New, OrderStatus::Processing, OrderStatus::Shipped])->count(),
            'cashHeld' => (int) $held()->sum('amount'),
            'salesHoldingCash' => $held()->distinct()->count('sales_id'),
            'salesMonth' => $salesMonth,
            'targetTotal' => $targetTotal,
            'targetPercent' => $targetTotal > 0 ? min(100, (int) round($salesMonth / $targetTotal * 100)) : 0,
            'monthLabel' => $now->locale('id')->translatedFormat('F'),
        ])->layout('layouts.admin');
    }

    /** "Rp 12,4 jt": a KPI tile only has room for a short figure. */
    public function rupiah(int $amount): string
    {
        foreach ([1_000_000_000 => 'M', 1_000_000 => 'jt'] as $unit => $suffix) {
            if ($amount >= $unit) {
                $short = number_format($amount / $unit, 1, ',', '.');

                return 'Rp '.(str_ends_with($short, ',0') ? substr($short, 0, -2) : $short).' '.$suffix;
            }
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
