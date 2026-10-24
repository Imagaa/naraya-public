<?php

namespace App\Support\Admin;

use App\Enums\BuyerStatus;
use App\Enums\CashDepositStatus;
use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Enums\RedemptionStatus;
use App\Models\BuyerProfile;
use App\Models\CashDeposit;
use App\Models\InvestmentPurchase;
use App\Models\InvestmentWithdrawal;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Product;
use App\Models\Redemption;
use App\Models\SalesNote;
use App\Models\SalesProfile;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Everything Admin must act on, defined once. The
 * dashboard, the sidebar badges and the list pages' ?antrean= filter all
 * read these definitions, so a badge, a dashboard count and a filtered list
 * can never disagree. Thresholds are constants on purpose (not editable yet).
 */
final class WorkQueues
{
    public const GROUPS = ['Penjualan', 'Keuangan', 'Gudang', 'Loyalty', 'Tim Sales'];

    /** Handover stages only count as "macet" once they have waited this long. */
    private const STUCK_HOURS = 24;

    /**
     * Non-empty queues in definition order, computed once per request
     * (the layout's badges and the dashboard both ask).
     *
     * @return list<array{key: string, group: string, label: string, count: int, severity: string, meta: string, url: string}>
     */
    public static function all(): array
    {
        return once(fn () => collect(self::definitions())
            ->map(fn (array $def, string $key) => self::evaluate($key, $def))
            ->filter()
            ->values()
            ->all());
    }

    public static function count(string ...$keys): int
    {
        return (int) collect(self::all())->whereIn('key', $keys)->sum('count');
    }

    /** One-column id subquery for a list page's ?antrean= filter; null when the key is unknown or another page's. */
    public static function filter(string $page, string $key): ?Builder
    {
        $def = self::definitions()[$key] ?? null;

        return $def && $def['page'] === $page ? ($def['query'])()->select($def['id']) : null;
    }

    public static function label(string $key): ?string
    {
        return self::definitions()[$key]['label'] ?? null;
    }

    private static function definitions(): array
    {
        $stuckSince = now()->subHours(self::STUCK_HOURS);
        $late = fn () => 'late';
        $outOfStock = fn (Builder $q) => (clone $q)->where('stock', 0);

        return [
            'note-proofs' => self::def('Penjualan', 'Bukti transfer/QRIS perlu dicek', 'admin.notes.index',
                fn () => SalesNote::forLine(\App\Support\ActiveLine::current())->where('proof_status', ProofStatus::Pending), age: 'created_at', hours: 24),
            'payment-review' => self::def('Penjualan', 'Review pembayaran', 'admin.orders.index',
                fn () => Order::where('needs_payment_review', true), age: 'created_at', hours: 4),
            'new-orders' => self::def('Penjualan', 'Order baru belum dikonfirmasi', 'admin.orders.index',
                fn () => Order::where('status', OrderStatus::New), age: 'created_at', hours: 12),
            // ponytail: orders have no confirmed_at, so age counts from checkout; add the column if late confirmations make this noisy
            'unassigned' => self::def('Penjualan', 'Belum ada sales', 'admin.orders.index',
                fn () => Order::where('status', OrderStatus::Processing)->whereNull('sales_id'), age: 'created_at', hours: 4),
            'ready-to-ship' => self::def('Penjualan', 'Siap dikirim', 'admin.orders.index',
                fn () => Order::where('status', OrderStatus::Processing)->whereNotNull('sales_received_at'), age: 'sales_received_at', hours: 12),
            'awaiting-close' => self::def('Penjualan', 'Menunggu ditutup', 'admin.orders.index',
                fn () => Order::where('status', OrderStatus::Shipped)->whereNotNull('sales_delivered_at')->whereNotNull('buyer_confirmed_at'), age: 'buyer_confirmed_at', hours: 24),
            'stuck-gudang' => self::def('Penjualan', 'Macet di gudang', 'admin.orders.index',
                fn () => Order::where('status', OrderStatus::Processing)->whereNotNull('sales_id')->whereNull('gudang_handover_at')->where('sales_assigned_at', '<=', $stuckSince),
                age: 'sales_assigned_at', severity: $late),
            'stuck-sales' => self::def('Penjualan', 'Macet di sales', 'admin.orders.index',
                fn () => Order::where('status', OrderStatus::Processing)->whereNull('sales_received_at')->where('gudang_handover_at', '<=', $stuckSince),
                age: 'gudang_handover_at', severity: $late),
            'complaints' => self::def('Penjualan', 'Retur & komplain', 'admin.complaints.index',
                fn () => Complaint::where('status', ComplaintStatus::Diajukan), age: 'created_at', hours: 4),
            'registrations' => self::def('Penjualan', 'Toko baru menunggu persetujuan', 'admin.buyers.index',
                fn () => BuyerProfile::where('status', BuyerStatus::Pending), age: 'created_at', hours: 24, id: 'user_id'),
            'deposits' => self::def('Keuangan', 'Setoran perlu verifikasi', 'admin.deposits.index',
                fn () => CashDeposit::forLine(\App\Support\ActiveLine::current())->whereIn('status', [CashDepositStatus::Submitted, CashDepositStatus::Disputed]),
                age: 'created_at', hours: 24,
                severity: fn (Builder $q) => (clone $q)->where('status', CashDepositStatus::Disputed)->exists() ? 'late' : null,
                meta: function (Builder $q, ?string $age) {
                    $disputed = (clone $q)->where('status', CashDepositStatus::Disputed)->count();

                    return $disputed ? "{$disputed} disengketakan · ".lcfirst((string) $age) : $age;
                }),
            'investment-proofs' => self::def('Keuangan', 'Bukti beli investasi perlu dicek', 'admin.investments.index',
                fn () => InvestmentPurchase::where('status', 'menunggu_verifikasi'), age: 'created_at', hours: 24,
                url: fn () => route('admin.investments.index', ['tab' => 'pembelian', 'status' => 'menunggu_verifikasi'])),
            'investment-forward' => self::def('Keuangan', 'Investasi dibayar, belum disalurkan ke mitra', 'admin.investments.index',
                fn () => InvestmentPurchase::where('status', 'dibayar'), age: 'created_at', hours: 48,
                url: fn () => route('admin.investments.index', ['tab' => 'pembelian', 'status' => 'dibayar'])),
            'investment-withdrawals' => self::def('Keuangan', 'Penarikan dana investasi menunggu transfer', 'admin.investments.index',
                fn () => InvestmentWithdrawal::where('status', 'menunggu'), age: 'created_at', hours: 24,
                url: fn () => route('admin.investments.index', ['tab' => 'penarikan'])),
            'refunds' => self::def('Keuangan', 'Perlu refund manual', 'admin.orders.index',
                fn () => Order::where('refund_status', 'pending'), age: 'cancelled_at', hours: 48),
            'stock' => self::def('Gudang', 'Stok habis / menipis', 'admin.products.index',
                fn () => Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->where(fn ($q) => $q->where('stock', 0)->orWhereColumn('stock', '<=', 'min_stock')),
                severity: fn (Builder $q) => $outOfStock($q)->exists() ? 'late' : 'soon',
                meta: function (Builder $q) use ($outOfStock) {
                    $out = $outOfStock($q)->count();

                    return "{$out} habis · ".((clone $q)->count() - $out).' di bawah minimum';
                },
                url: fn (Builder $q) => route('admin.products.index', ['stock' => $outOfStock($q)->exists() ? 'out' : 'low'])),
            'redemptions-decision' => self::def('Loyalty', 'Penukaran perlu keputusan', 'admin.redemptions.index',
                fn () => Redemption::where('status', RedemptionStatus::Diajukan), age: 'created_at', hours: 24),
            'redemptions-handover' => self::def('Loyalty', 'Hadiah perlu diserahkan', 'admin.redemptions.index',
                fn () => Redemption::where('status', RedemptionStatus::Disetujui), age: 'updated_at', hours: 48),
            'phone-change' => self::def('Tim Sales', 'Ganti nomor HP', 'admin.sales.index',
                fn () => SalesProfile::whereNotNull('pending_phone'), age: 'pending_phone_requested_at', hours: 24, id: 'user_id'),
        ];
    }

    private static function def(
        string $group, string $label, string $page, Closure $query,
        ?string $age = null, ?int $hours = null, string $id = 'id',
        ?Closure $severity = null, ?Closure $meta = null, ?Closure $url = null,
    ): array {
        return compact('group', 'label', 'page', 'query', 'age', 'hours', 'id', 'severity', 'meta', 'url');
    }

    /** @return array<string, mixed>|null null for an empty queue */
    private static function evaluate(string $key, array $def): ?array
    {
        $query = ($def['query'])();
        $count = (clone $query)->count();

        if ($count === 0) {
            return null;
        }

        $oldest = $def['age'] ? (clone $query)->min($def['age']) : null;
        $ageHours = $oldest ? abs(Carbon::parse($oldest)->diffInMinutes(now())) / 60 : null;

        $severity = ($def['severity'] ? ($def['severity'])($query) : null) ?? self::grade($ageHours, $def['hours']);

        $ageText = $ageHours === null ? null
            : 'Tertua '.self::age($ageHours).($def['hours'] ? ' · batas '.$def['hours'].' jam' : '');

        return [
            'key' => $key,
            'group' => $def['group'],
            'label' => $def['label'],
            'count' => $count,
            'severity' => $severity,
            'meta' => (string) ($def['meta'] ? ($def['meta'])($query, $ageText) : $ageText),
            'url' => $def['url'] ? ($def['url'])($query) : route($def['page'], ['antrean' => $key]),
        ];
    }

    private static function grade(?float $ageHours, ?int $hours): string
    {
        return match (true) {
            $ageHours === null || $hours === null => 'idle',
            $ageHours >= $hours => 'late',
            $ageHours >= $hours / 2 => 'soon',
            default => 'idle',
        };
    }

    /**
     * For the order list's "Perlu tindakan" column: the most severe order queue
     * each of these orders sits in, graded by that order's own age. Only the
     * given ids are queried, so the cost follows the page size.
     *
     * @param  list<int>  $ids
     * @return array<int, array{label: string, severity: string}>
     */
    public static function orderFlags(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rank = ['idle' => 1, 'soon' => 2, 'late' => 3];
        $flags = [];

        foreach (self::definitions() as $def) {
            if ($def['page'] !== 'admin.orders.index') {
                continue;
            }

            $query = ($def['query'])()->whereIn('orders.id', $ids);
            $forced = $def['severity'] ? ($def['severity'])($query) : null;

            foreach ($query->get(array_values(array_filter(['orders.id', $def['age']]))) as $order) {
                $age = $def['age'] && $order->{$def['age']} ? abs(Carbon::parse($order->{$def['age']})->diffInMinutes(now())) / 60 : null;
                $severity = $forced ?? self::grade($age, $def['hours']);

                if ($rank[$severity] > $rank[$flags[$order->id]['severity'] ?? 'idle'] || ! isset($flags[$order->id])) {
                    $flags[$order->id] = ['label' => $def['label'], 'severity' => $severity];
                }
            }
        }

        return $flags;
    }

    private static function age(float $hours): string
    {
        return match (true) {
            $hours < 1 => max(1, (int) round($hours * 60)).' menit',
            $hours < 48 => (int) floor($hours).' jam',
            default => (int) floor($hours / 24).' hari',
        };
    }
}
