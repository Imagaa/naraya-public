<?php

namespace App\Support\Sales;

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\StockTransferKind;
use App\Models\BuyerProfile;
use App\Models\ConsignmentMove;
use App\Models\DeliveryRouteStop;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\User;
use App\Models\Visit;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\SalesStockLock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Titip jual: our goods at a store, in pcs, as a ledger.
 * A store's first visit records what is already there (awal); every later visit counts what is left —
 * the difference was sold and becomes a nota (source titipan) — takes everything left back (retur, held by
 * the sales person until check-out; user ruling 2026-10-12: "sisa di toko adalah retur") and leaves a fresh
 * lot from carried stock (titip).
 */
final class Consignments
{
    /** @return Collection<int, int> product_id => pcs at the store now (> 0 only) */
    public static function atStore(int $buyerId, BusinessLine $line): Collection
    {
        return ConsignmentMove::where('buyer_id', $buyerId)->forLine($line)->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as qty')->pluck('qty', 'product_id')
            ->map(fn ($q) => (int) $q)->filter(fn (int $q) => $q > 0)->sortKeys();
    }

    /** @return Collection<int, int> product_id => pcs of store returns this sales person still holds (> 0 only) */
    public static function returnsHeld(User $sales, BusinessLine $line): Collection
    {
        $taken = ConsignmentMove::where('sales_id', $sales->id)->forLine($line)->where('kind', 'retur')
            ->groupBy('product_id')->selectRaw('product_id, -SUM(quantity) as qty')->pluck('qty', 'product_id')->map(fn ($q) => (int) $q);
        $handedIn = DB::table('stock_transfer_items')->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->where('stock_transfers.sales_id', $sales->id)->where('stock_transfers.line', $line->value)
            ->where('stock_transfers.kind', StockTransferKind::ReturToko->value)
            ->groupBy('stock_transfer_items.product_id')->selectRaw('stock_transfer_items.product_id as product_id, SUM(stock_transfer_items.quantity) as qty')
            ->pluck('qty', 'product_id')->map(fn ($q) => (int) $q);

        return $taken->map(fn (int $q, int $id) => $q - ($handedIn[$id] ?? 0))->filter(fn (int $q) => $q > 0)->sortKeys();
    }

    /**
     * Our goods at the store now, per line, and what they are worth: to the sales person (the price the store pays,
     * Product::valueOf) and to the admin (the sales deposit price, Product::depositOf). Admin's "Cek stok toko".
     *
     * @return Collection<string, array{line: BusinessLine, rows: Collection, sales: int, admin: int, last: ?\Illuminate\Support\Carbon}>
     */
    public static function valuation(int $buyerId): Collection
    {
        return collect(BusinessLine::cases())->mapWithKeys(function (BusinessLine $line) use ($buyerId) {
            $at = self::atStore($buyerId, $line);
            if ($at->isEmpty()) {
                return [];
            }
            $products = Product::whereKey($at->keys())->get()->keyBy('id');
            $rows = $at->map(fn (int $pcs, int $id) => [
                'name' => $products[$id]->name, 'qty' => $products[$id]->qtyLabel($pcs), 'pcs' => $pcs,
                'sales' => $products[$id]->valueOf($pcs), 'admin' => $products[$id]->depositOf($pcs),
            ])->values();

            return [$line->value => ['line' => $line, 'rows' => $rows, 'sales' => (int) $rows->sum('sales'), 'admin' => (int) $rows->sum('admin'), 'last' => self::lastVisitAt($buyerId, $line)]];
        });
    }

    /** When goods last moved at the store (the previous visit or the first count), for "kunjungan terakhir". */
    public static function lastVisitAt(int $buyerId, BusinessLine $line): ?\Illuminate\Support\Carbon
    {
        $at = ConsignmentMove::where('buyer_id', $buyerId)->forLine($line)->max('created_at');

        return $at ? \Illuminate\Support\Carbon::parse($at) : null;
    }

    /** First visit: what is already at the store (left from before); may be nothing. Once per store. */
    public function open(User $sales, int $buyerId, BusinessLine $line, array $items): void
    {
        $profile = $this->assigned($sales, $buyerId);
        if ($profile->consignment_opened_at) {
            throw ValidationException::withMessages(['items' => 'Barang awal toko ini sudah pernah dicatat.']);
        }
        $items = $this->pcs($items, 'items');
        $products = Product::forLine($line)->whereKey(array_keys($items))->pluck('id');
        if ($products->count() !== count($items)) {
            throw ValidationException::withMessages(['items' => 'Ada barang yang bukan dari lini '.$line->label().'.']);
        }

        DB::transaction(function () use ($sales, $buyerId, $line, $items, $profile) {
            foreach ($items as $id => $qty) {
                $this->move($buyerId, $id, $line, $sales, 'awal', $qty);
            }
            $profile->forceFill(['consignment_opened_at' => now()])->save();
        });
    }

    /**
     * A visit: $remaining = pcs counted at the store per product (missing = unchanged), all of which the sales
     * person takes back; $titip = pcs left from carried stock. What went missing is sold: a nota (paid by
     * $method) unless nothing was sold. All or nothing.
     *
     * @return SalesNote|null the nota for what was sold
     */
    public function visit(User $sales, int $buyerId, BusinessLine $line, array $remaining, array $titip, NotePaymentMethod $method, ?UploadedFile $proof = null): ?SalesNote
    {
        $profile = $this->assigned($sales, $buyerId);
        if (! $profile->consignment_opened_at) {
            throw ValidationException::withMessages(['remaining' => 'Catat dulu barang yang sudah ada di toko ini.']);
        }
        $remaining = $this->pcs($remaining, 'remaining', allowZero: true);
        $titip = $this->pcs($titip, 'titip');

        return SalesStockLock::run($sales, fn () => DB::transaction(function () use ($sales, $buyerId, $line, $remaining, $titip, $method, $proof) {
            $before = self::atStore($buyerId, $line);
            $names = Product::whereKey($before->keys()->merge(array_keys($titip)))->get()->keyBy('id');
            $sold = [];
            $retur = [];
            foreach ($before as $id => $had) {
                $left = $remaining[$id] ?? $had;
                if ($left > $had) {
                    throw ValidationException::withMessages(["remaining.{$id}" => "Lebih dari catatan ({$names[$id]->qtyLabel($had)})."]);
                }
                if ($had - $left > 0) {
                    $sold[$id] = $had - $left;
                }
                if ($left > 0) {
                    $retur[$id] = $left; // what is left goes back with the sales person
                }
            }
            foreach ($remaining as $id => $left) {
                if (! isset($before[$id]) && $left > 0) {
                    throw ValidationException::withMessages(["remaining.{$id}" => 'Barang ini tidak tercatat di toko.']);
                }
            }
            $carried = CarriedStock::for($sales, $line);
            foreach ($titip as $id => $qty) {
                if ($qty > ($carried[$id] ?? 0)) {
                    throw ValidationException::withMessages(["titip.{$id}" => 'Yang Anda bawa hanya '.($names[$id]?->qtyLabel($carried[$id] ?? 0) ?? 0).'.']);
                }
            }
            if ($sold !== [] && ! in_array($method, NotePaymentMethod::choices(), true)) {
                throw ValidationException::withMessages(['method' => 'Pembayaran hanya tunai atau transfer.']);
            }
            if ($sold !== [] && $method->needsProof() && ! $proof) {
                throw ValidationException::withMessages(['proof' => 'Foto bukti '.$method->label().' wajib dilampirkan.']);
            }

            $stopId = DeliveryRoutes::active($sales)?->stops()->where('buyer_id', $buyerId)->where('status', 'pending')->value('id');
            $note = $sold === [] ? null : app(SalesNotes::class)->record($sales, $line, $buyerId, $sold, $method, $proof, $stopId, 'titipan');
            foreach ($sold as $id => $qty) {
                $this->move($buyerId, $id, $line, $sales, 'terjual', -$qty, $note);
            }
            foreach ($retur as $id => $qty) {
                $this->move($buyerId, $id, $line, $sales, 'retur', -$qty);
            }
            foreach ($titip as $id => $qty) {
                $this->move($buyerId, $id, $line, $sales, 'titip', $qty);
            }

            if (! $note) { // the nota already logs the visit and the route stop
                Visit::create(['sales_id' => $sales->id, 'buyer_id' => $buyerId, 'notes' => 'Kunjungan titip jual', 'visited_at' => now()]);
                if ($stopId) {
                    DeliveryRouteStop::whereKey($stopId)->update(['status' => 'visited', 'visited_at' => now()]);
                }
            }

            return $note;
        }));
    }

    private function assigned(User $sales, int $buyerId): BuyerProfile
    {
        return BuyerProfile::where('user_id', $buyerId)->where('sales_user_id', $sales->id)->first()
            ?? throw ValidationException::withMessages(['buyerId' => 'Toko ini tidak ditugaskan ke Anda.']);
    }

    /** @return array<int, int> product_id => pcs, positive only (or ≥ 0 with $allowZero) */
    private function pcs(array $items, string $field, bool $allowZero = false): array
    {
        $out = [];
        foreach ($items as $id => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $n = filter_var($value, FILTER_VALIDATE_INT);
            if ($n === false || $n < 0) {
                throw ValidationException::withMessages(["{$field}.{$id}" => 'Isi angka 0 atau lebih.']);
            }
            if ($n > 0 || $allowZero) {
                $out[(int) $id] = $n;
            }
        }

        return $out;
    }

    private function move(int $buyerId, int $productId, BusinessLine $line, User $sales, string $kind, int $qty, ?SalesNote $note = null): void
    {
        ConsignmentMove::create([
            'buyer_id' => $buyerId, 'product_id' => $productId, 'line' => $line, 'sales_id' => $sales->id,
            'kind' => $kind, 'quantity' => $qty, 'sales_note_id' => $note?->id,
        ]);
    }
}
