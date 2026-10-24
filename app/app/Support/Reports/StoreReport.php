<?php

namespace App\Support\Reports;

use App\Enums\BusinessLine;
use App\Enums\BuyerStatus;
use App\Enums\NotePaymentMethod;
use App\Enums\ProofStatus;
use App\Models\BuyerProfile;
use App\Models\ConsignmentMove;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\SalesNoteItem;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The figures behind the store detail page and Laporan Toko, for one store or, with
 * $buyerId null, every approved store. $from/$to are UTC bounds of WIB days.
 */
final class StoreReport
{
    /** @return array{terjual: int, uang: int, tunai: int, transfer: int, kunjungan: int, dibawa_kembali: int, masih_tertitip: int} */
    public function summary(BusinessLine $line, Carbon $from, Carbon $to, ?int $buyerId = null): array
    {
        $byMethod = $this->notes($line, $from, $to, $buyerId)->groupBy('payment_method')
            ->selectRaw('payment_method, SUM(total) AS uang')->pluck('uang', 'payment_method');
        $tunai = (int) ($byMethod[NotePaymentMethod::Cash->value] ?? 0);

        return [
            'terjual' => (int) $this->soldItems($line, $from, $to, $buyerId)->sum('sales_note_items.quantity'),
            'uang' => (int) $byMethod->sum(),
            'tunai' => $tunai,
            'transfer' => (int) $byMethod->sum() - $tunai,
            'kunjungan' => $this->visitDays($from, $to, $buyerId)->count(),
            'dibawa_kembali' => -(int) $this->moves($line, $buyerId)->where('kind', 'retur')->whereBetween('created_at', [$from, $to])->sum('quantity'),
            'masih_tertitip' => (int) $this->moves($line, $buyerId)->sum('quantity'),
        ];
    }

    /** @return Collection<int, object{product: Product, terjual: int, uang: int, masih_tertitip: int, dibawa_kembali: int}> */
    public function items(BusinessLine $line, Carbon $from, Carbon $to, ?int $buyerId = null): Collection
    {
        $sold = $this->soldItems($line, $from, $to, $buyerId)->groupBy('sales_note_items.product_id')
            ->selectRaw('sales_note_items.product_id, SUM(sales_note_items.quantity) AS pcs, SUM(sales_note_items.subtotal) AS uang')
            ->get()->keyBy('product_id');
        $held = $this->moves($line, $buyerId)->groupBy('product_id')->selectRaw('product_id, SUM(quantity) AS pcs')->pluck('pcs', 'product_id');
        $back = $this->moves($line, $buyerId)->where('kind', 'retur')->whereBetween('created_at', [$from, $to])
            ->groupBy('product_id')->selectRaw('product_id, SUM(quantity) AS pcs')->pluck('pcs', 'product_id');

        $ids = $sold->keys()->merge($held->keys())->merge($back->keys())->unique();

        return Product::whereKey($ids)->get()
            ->map(fn (Product $p) => (object) [
                'product' => $p,
                'terjual' => (int) ($sold[$p->id]->pcs ?? 0),
                'uang' => (int) ($sold[$p->id]->uang ?? 0),
                'masih_tertitip' => (int) ($held[$p->id] ?? 0),
                'dibawa_kembali' => -(int) ($back[$p->id] ?? 0),
            ])
            ->filter(fn ($r) => $r->terjual || $r->uang || $r->masih_tertitip || $r->dibawa_kembali)
            ->sortBy([['uang', 'desc'], [fn ($a, $b) => strcmp($a->product->name, $b->product->name)]])
            ->values();
    }

    /** @return array{total: int, dengan_penjualan: int, berakun: int, tanpa_akun: int} */
    public function storeCounts(BusinessLine $line, Carbon $from, Carbon $to): array
    {
        $total = BuyerProfile::where('status', BuyerStatus::Approved)->count();
        $berakun = BuyerProfile::where('status', BuyerStatus::Approved)->whereHas('user', fn ($q) => $q->whereNotNull('password'))->count();

        return [
            'total' => $total,
            'dengan_penjualan' => $this->notes($line, $from, $to, null)->distinct()->count('buyer_id'),
            'berakun' => $berakun,
            'tanpa_akun' => $total - $berakun,
        ];
    }

    public function lastVisitAt(int $buyerId): ?Carbon
    {
        $at = Visit::where('buyer_id', $buyerId)->max('visited_at');

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * Every approved store with its figures; sort uang | terjual | lama (longest without a visit first).
     * Sums are correlated sub-selects; visit days come from one query for the page.
     */
    public function ranking(BusinessLine $line, Carbon $from, Carbon $to, string $sort = 'uang', int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()
            ->whereHas('buyerProfile', fn ($q) => $q->where('status', BuyerStatus::Approved))
            ->with('buyerProfile.salesUser')
            ->select('users.*')
            ->addSelect([
                'terjual' => SalesNoteItem::query()->join('sales_notes', 'sales_notes.id', '=', 'sales_note_items.sales_note_id')
                    ->tap(fn ($q) => $this->counted($q, $line, $from, $to))->whereColumn('sales_notes.buyer_id', 'users.id')
                    ->selectRaw('COALESCE(SUM(sales_note_items.quantity), 0)'),
                'uang' => SalesNote::query()->tap(fn ($q) => $this->counted($q, $line, $from, $to))
                    ->whereColumn('sales_notes.buyer_id', 'users.id')->selectRaw('COALESCE(SUM(sales_notes.total), 0)'),
                'kunjungan_terakhir' => Visit::query()->whereColumn('visits.buyer_id', 'users.id')->selectRaw('MAX(visits.visited_at)'),
                'masih_tertitip' => ConsignmentMove::query()->where('line', $line)->whereColumn('consignment_moves.buyer_id', 'users.id')
                    ->selectRaw('COALESCE(SUM(consignment_moves.quantity), 0)'),
            ]);

        match ($sort) {
            'terjual' => $query->orderByDesc('terjual'),
            'lama' => $query->orderByRaw('kunjungan_terakhir IS NOT NULL')->orderBy('kunjungan_terakhir'),
            default => $query->orderByDesc('uang'),
        };
        $page = $query->orderBy('users.name')->paginate($perPage);

        $days = $this->visitDays($from, $to, null, $page->getCollection()->pluck('id')->all())->countBy(fn (string $key) => explode('#', $key)[1]);

        return $page->through(function (User $u) use ($days) {
            $u->terjual = (int) $u->terjual;
            $u->uang = (int) $u->uang;
            $u->masih_tertitip = (int) $u->masih_tertitip;
            $u->kunjungan = (int) ($days[$u->id] ?? 0);
            $u->kunjungan_terakhir = $u->kunjungan_terakhir ? Carbon::parse($u->kunjungan_terakhir) : null;

            return $u;
        });
    }

    /** Notas that count: this line, in the period, not a rejected transfer. */
    private function counted(Builder $q, BusinessLine $line, Carbon $from, Carbon $to): void
    {
        $q->where('sales_notes.line', $line)->whereBetween('sales_notes.created_at', [$from, $to])
            ->where(fn ($q) => $q->whereNull('sales_notes.proof_status')->orWhere('sales_notes.proof_status', '!=', ProofStatus::Rejected));
    }

    private function notes(BusinessLine $line, Carbon $from, Carbon $to, ?int $buyerId): Builder
    {
        return SalesNote::query()->tap(fn ($q) => $this->counted($q, $line, $from, $to))->tap(fn ($q) => $this->forStores($q, 'sales_notes.buyer_id', $buyerId));
    }

    private function soldItems(BusinessLine $line, Carbon $from, Carbon $to, ?int $buyerId): Builder
    {
        return SalesNoteItem::query()->join('sales_notes', 'sales_notes.id', '=', 'sales_note_items.sales_note_id')
            ->tap(fn ($q) => $this->counted($q, $line, $from, $to))->tap(fn ($q) => $this->forStores($q, 'sales_notes.buyer_id', $buyerId));
    }

    private function moves(BusinessLine $line, ?int $buyerId): Builder
    {
        return ConsignmentMove::query()->where('line', $line)->tap(fn ($q) => $this->forStores($q, 'buyer_id', $buyerId));
    }

    /**
     * "storeId#WIB date" keys, one per store per visited day (as SalesPerformance counts rounds).
     *
     * @param  list<int>|null  $only
     * @return Collection<int, string>
     */
    private function visitDays(Carbon $from, Carbon $to, ?int $buyerId, ?array $only = null): Collection
    {
        return Visit::query()->whereBetween('visited_at', [$from, $to])
            ->when($only !== null, fn ($q) => $q->whereIn('buyer_id', $only), fn ($q) => $this->forStores($q, 'buyer_id', $buyerId))
            ->get(['buyer_id', 'visited_at'])
            ->map(fn (Visit $v) => $v->visited_at->wib()->format('Y-m-d').'#'.$v->buyer_id)
            ->unique()->values();
    }

    /** One store, or every approved store. */
    private function forStores(Builder $q, string $column, ?int $buyerId): void
    {
        $buyerId !== null
            ? $q->where($column, $buyerId)
            : $q->whereIn($column, BuyerProfile::where('status', BuyerStatus::Approved)->select('user_id'));
    }
}
