<?php

namespace App\Support\Admin;

use App\Enums\AttendanceType;
use App\Enums\BusinessLine;
use App\Enums\CashDepositStatus;
use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Enums\StockTransferKind;
use App\Models\Attendance;
use App\Models\BuyerProfile;
use App\Models\CashDeposit;
use App\Models\ConsignmentMove;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentWithdrawal;
use App\Models\Order;
use App\Models\SalesNote;
use App\Models\SalesTrackPoint;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\AdminNotice;
use App\Support\AttendanceCalendar;
use App\Support\BusinessDay;
use App\Support\Sales\Consignments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Time-based owner notices: things that went too long, told once per subject
 * (a key in admin_notice_marks), plus the H-1 payout reminder and the daily and weekly recaps.
 */
final class OwnerChecks
{
    public const ORDER_UNCONFIRMED_HOURS = 12;
    public const HANDOVER_HOURS = 24;
    public const NOT_CHECKED_IN_MINUTES = 60;
    public const GPS_GAP_MINUTES = 30;
    public const FORGOT_CHECKOUT_HOUR = 21;
    public const LONG_SHIFT_HOURS = 12;
    public const DEPOSIT_HOURS = 24;
    public const INVEST_CASH_HOURS = 24;
    public const INVEST_PROOF_HOURS = 24;
    public const DISTRIBUTE_HOURS = 48;
    public const WITHDRAWAL_HOURS = 24;
    public const RETURNS_DAYS = 3;

    /** Every 5 minutes. @return int notices sent */
    public function run(): int
    {
        return $this->orders() + $this->field() + $this->money() + $this->returns();
    }

    private function orders(): int
    {
        $sent = 0;
        foreach (Order::with('buyer')->where('status', OrderStatus::New)->where('created_at', '<', now()->subHours(self::ORDER_UNCONFIRMED_HOURS))->get() as $o) {
            $sent += $this->once("order-unconfirmed:{$o->id}", 'Order belum dikonfirmasi', "#{$o->id} {$o->buyer?->name} — lebih dari ".self::ORDER_UNCONFIRMED_HOURS.' jam', route('admin.orders.show', $o, absolute: false), 'shopping-cart', 'warning');
        }
        foreach (Order::with('buyer', 'salesUser')->where('status', OrderStatus::Processing)->whereNotNull('sales_id')->whereNull('gudang_handover_at')
            ->where('sales_assigned_at', '<', now()->subHours(self::HANDOVER_HOURS))->get() as $o) {
            $sent += $this->once("handover:{$o->id}", 'Barang belum diserahkan ke sales', "#{$o->id} {$o->buyer?->name} · {$o->salesUser?->name} — lebih dari ".self::HANDOVER_HOURS.' jam', route('admin.orders.show', $o, absolute: false), 'package', 'warning');
        }

        return $sent;
    }

    private function field(): int
    {
        $sent = 0;
        $now = BusinessDay::now();
        $today = BusinessDay::today();
        $calendar = new AttendanceCalendar;
        $day = Attendance::whereBetween('created_at', BusinessDay::bounds())->get()->groupBy('sales_id');

        foreach (User::activeSales()->orderBy('name')->get() as $s) {
            $mine = $day->get($s->id, collect());
            $masuk = $mine->firstWhere('tipe', AttendanceType::Masuk);
            $pulang = $mine->firstWhere('tipe', AttendanceType::Pulang);
            $url = route('admin.attendances.index', absolute: false);

            if (! $masuk) {
                $plan = $calendar->for($s, $today);
                if ($plan && $now->gte($plan['masuk']->copy()->addMinutes(self::NOT_CHECKED_IN_MINUTES))) {
                    $sent += $this->once("not-checked-in:{$s->id}:{$today->toDateString()}", 'Belum absen masuk', "{$s->name} — jadwal {$plan['masuk']->format('H.i')}", $url, 'clock', 'warning');
                }
                continue;
            }
            if ($pulang) {
                continue;
            }
            $start = $masuk->created_at->wib();
            $last = SalesTrackPoint::where('attendance_id', $masuk->id)->max('recorded_at');
            $last = $last ? Carbon::parse($last) : $masuk->created_at;
            if ($last->lt(now()->subMinutes(self::GPS_GAP_MINUTES))) {
                $sent += $this->once("gps-gap:{$s->id}:{$last->timestamp}", 'GPS sales mati', "{$s->name} — tidak ada sinyal sejak {$last->wib()->format('H.i')}", route('admin.tracking.index', absolute: false), 'map-pin-off', 'warning');
            }
            if ($now->hour >= self::FORGOT_CHECKOUT_HOUR) {
                $sent += $this->once("forgot-checkout:{$masuk->id}", 'Lupa absen pulang', "{$s->name} masuk {$start->format('H.i')}, belum absen pulang", $url, 'log-out', 'warning');
            }
            if ($masuk->created_at->lt(now()->subHours(self::LONG_SHIFT_HOURS))) {
                $sent += $this->once("long-shift:{$masuk->id}", 'Jam kerja lebih dari '.self::LONG_SHIFT_HOURS.' jam', "{$s->name} bekerja sejak {$start->format('H.i')}", $url, 'timer', 'warning');
            }
        }

        return $sent;
    }

    private function money(): int
    {
        $sent = 0;
        $rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');
        $inv = route('admin.investments.index', absolute: false);

        foreach (CashDeposit::with('salesUser')->where('status', CashDepositStatus::Submitted)->where('created_at', '<', now()->subHours(self::DEPOSIT_HOURS))->get() as $d) {
            $sent += $this->once("deposit:{$d->id}", 'Setoran belum diverifikasi', "#{$d->id} {$d->salesUser?->name} · {$rp($d->claimed_amount)} — lebih dari ".self::DEPOSIT_HOURS.' jam', route('admin.deposits.show', $d, absolute: false), 'wallet', 'warning');
        }
        foreach (InvestmentPurchase::with('buyer', 'salesUser')->where('payment_method', 'cash_sales')->whereNull('cash_deposit_id')->whereNotIn('status', ['ditolak'])
            ->where('created_at', '<', now()->subHours(self::INVEST_CASH_HOURS))->get() as $p) {
            $sent += $this->once("invest-cash:{$p->id}", 'Uang investasi belum disetor', "{$p->salesUser?->name} · {$p->buyer?->name} · {$rp($p->amount)} ({$p->number()})", $inv, 'hand-coins', 'warning');
        }
        foreach (InvestmentPurchase::with('buyer')->where('status', 'menunggu_verifikasi')->where('created_at', '<', now()->subHours(self::INVEST_PROOF_HOURS))->get() as $p) {
            $sent += $this->once("invest-proof:{$p->id}", 'Bukti investasi belum diverifikasi', "{$p->buyer?->name} · {$rp($p->amount)} ({$p->number()})", $inv, 'badge-check', 'warning');
        }
        foreach (InvestmentPurchase::with('buyer')->where('status', 'dibayar')->where(fn ($q) => $q->where('verified_at', '<', now()->subHours(self::DISTRIBUTE_HOURS))
            ->orWhere(fn ($q) => $q->whereNull('verified_at')->where('created_at', '<', now()->subHours(self::DISTRIBUTE_HOURS))))->get() as $p) {
            $sent += $this->once("invest-distribute:{$p->id}", 'Paket belum disalurkan ke mitra', "{$p->buyer?->name} · {$rp($p->amount)} ({$p->number()}) — lebih dari 2 hari", $inv, 'send', 'warning');
        }
        foreach (InvestmentWithdrawal::with('buyer')->where('status', 'menunggu')->where('created_at', '<', now()->subHours(self::WITHDRAWAL_HOURS))->get() as $w) {
            $sent += $this->once("withdrawal:{$w->id}", 'Penarikan belum ditransfer', "{$w->buyer?->name} · {$rp($w->amount)} — lebih dari ".self::WITHDRAWAL_HOURS.' jam', $inv, 'banknote', 'warning');
        }

        return $sent;
    }

    /** Store returns a sales person has held for more than 3 days (taken at visits, not yet handed in at check-out). */
    private function returns(): int
    {
        $sent = 0;
        foreach (User::activeSales()->get() as $s) {
            foreach (BusinessLine::cases() as $line) {
                $held = Consignments::returnsHeld($s, $line)->sum();
                if ($held <= 0) {
                    continue;
                }
                $since = StockTransfer::where('sales_id', $s->id)->where('line', $line)->where('kind', StockTransferKind::ReturToko)->max('created_at');
                $oldest = ConsignmentMove::where('sales_id', $s->id)->forLine($line)->where('kind', 'retur')
                    ->when($since, fn ($q) => $q->where('created_at', '>', $since))->min('created_at');
                if ($oldest && Carbon::parse($oldest)->lt(now()->subDays(self::RETURNS_DAYS))) {
                    $sent += $this->once("returns:{$s->id}:{$line->value}:".Carbon::parse($oldest)->timestamp, 'Retur menumpuk di sales', "{$s->name} memegang {$held} pcs retur toko lebih dari ".self::RETURNS_DAYS.' hari', route('admin.carried.index', absolute: false), 'undo-2', 'warning');
                }
            }
        }

        return $sent;
    }

    /** 08.00: tomorrow's payouts, once a day, nothing when there are none. */
    public function payoutReminder(): int
    {
        $tomorrow = BusinessDay::today()->addDay()->toDateString();
        $due = InvestmentPurchase::where('status', 'aktif')->whereDate('next_payout_on', $tomorrow)->get();
        if ($due->isEmpty()) {
            return 0;
        }

        return $this->once("payout-h1:{$tomorrow}", 'Besok bagi hasil', $due->pluck('buyer_id')->unique()->count().' toko · Rp '.number_format($due->sum(fn ($p) => $p->monthlyTotal()), 0, ',', '.'), route('admin.investments.index', absolute: false), 'calendar-clock');
    }

    /** 21.00: the day in one line. */
    public function dailyRecap(): int
    {
        $today = BusinessDay::today();
        $notes = SalesNote::whereBetween('created_at', BusinessDay::bounds())->where(fn ($q) => $q->whereNull('proof_status')->orWhere('proof_status', '!=', ProofStatus::Rejected))->get();
        $deposits = CashDeposit::whereBetween('created_at', BusinessDay::bounds())->sum('claimed_amount');
        $stores = Visit::whereBetween('visited_at', BusinessDay::bounds())->distinct()->count('buyer_id');
        $present = Attendance::whereBetween('created_at', BusinessDay::bounds())->where('tipe', AttendanceType::Masuk)->distinct()->count('sales_id');
        $rp = fn ($v) => 'Rp '.number_format($v, 0, ',', '.');

        return $this->once("daily:{$today->toDateString()}", 'Rekap hari ini', "Omzet {$rp($notes->sum('total'))} · {$notes->count()} nota · setoran {$rp($deposits)} · {$stores} toko dikunjungi · {$present} sales hadir", route('admin.dashboard', absolute: false), 'chart-column');
    }

    /** Monday 07.00: last week's best stores and sales, stores to visit, stores that earned points. */
    public function weeklyRecap(): int
    {
        $from = BusinessDay::today()->subDays(7);
        $range = [BusinessDay::boundsFor($from)[0], now()];
        $notes = SalesNote::with('buyer', 'salesUser')->whereBetween('created_at', $range)->get();
        $top = $notes->groupBy('buyer_id')->map(fn ($n) => ['name' => $n->first()->buyer?->name, 'total' => $n->sum('total')])->sortByDesc('total')->take(3)->pluck('name')->filter()->implode(', ');
        $best = $notes->groupBy('sales_id')->map(fn ($n) => ['name' => $n->first()->salesUser?->name, 'total' => $n->sum('total')])->sortByDesc('total')->first()['name'] ?? '—';
        $visited = Visit::where('visited_at', '>=', now()->subDays(14))->distinct()->pluck('buyer_id');
        $idle = BuyerProfile::where('status', 'approved')->whereNotIn('user_id', $visited)->count();
        $gained = 0;
        foreach (BuyerProfile::where('toys_points_awarded', '>', 0)->get(['user_id', 'toys_points_awarded']) as $p) {
            $mark = DB::table('admin_notice_marks')->where('key', "points:{$p->user_id}")->value('value');
            if ((int) $mark < $p->toys_points_awarded) {
                $gained++;
            }
            DB::table('admin_notice_marks')->updateOrInsert(['key' => "points:{$p->user_id}"], ['value' => (string) $p->toys_points_awarded, 'sent_at' => now()]);
        }

        return $this->once('weekly:'.BusinessDay::today()->toDateString(), 'Rekap minggu ini',
            'Terlaris: '.($top ?: '—')." · sales terbaik: {$best} · {$idle} toko belum dikunjungi >14 hari · {$gained} toko dapat poin baru",
            route('admin.reports.toko', absolute: false), 'chart-column');
    }

    /** Sends the notice unless this key was told before. @return int 1 when sent */
    private function once(string $key, string $title, string $body, string $url, string $icon, string $tone = 'info'): int
    {
        $inserted = DB::table('admin_notice_marks')->insertOrIgnore(['key' => $key, 'sent_at' => now()]);
        if ($inserted === 0) {
            return 0;
        }
        AdminNotice::toAdmins($title, $body, $url, $icon, $tone);

        return 1;
    }
}
