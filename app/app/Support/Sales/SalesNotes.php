<?php

namespace App\Support\Sales;

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\ProofStatus;
use App\Models\BuyerProfile;
use App\Models\DeliveryRouteStop;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\AdminNotice;
use App\Support\Loyalty\StorePoints;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\SalesStockLock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales notes (jual putus): a sales person sells goods they carry to
 * one of their stores, at the admin's price, paid on the spot. Transfer/QRIS
 * need a proof photo that admin then verifies or rejects.
 */
final class SalesNotes
{
    /** @param array<int, int> $items product_id => quantity */
    public function create(
        User $sales,
        BusinessLine $line,
        int $buyerId,
        array $items,
        NotePaymentMethod $method,
        ?UploadedFile $proof = null,
        ?int $routeStopId = null,
    ): SalesNote {
        $profile = BuyerProfile::where('user_id', $buyerId)->where('sales_user_id', $sales->id)->first();
        if (! $profile) {
            throw ValidationException::withMessages(['buyerId' => 'Toko ini tidak ditugaskan ke Anda.']);
        }
        if (! $profile->consignment_opened_at) { // titip jual: the first count of what is already there comes first
            throw ValidationException::withMessages(['buyerId' => 'Catat dulu barang yang sudah ada di toko ini (buka tokonya).']);
        }
        $items = array_filter(array_map('intval', $items), fn (int $q) => $q > 0);
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Isi jumlah minimal satu barang.']);
        }
        if (! in_array($method, NotePaymentMethod::choices(), true)) {
            throw ValidationException::withMessages(['method' => 'Pembayaran hanya tunai atau transfer.']);
        }
        if ($method->needsProof() && ! $proof) {
            throw ValidationException::withMessages(['proof' => 'Foto bukti '.$method->label().' wajib dilampirkan.']);
        }

        return SalesStockLock::run($sales, function () use ($sales, $line, $buyerId, $items, $method, $proof, $routeStopId) {
            $carried = CarriedStock::for($sales, $line);
            $products = Product::forLine($line)->whereKey(array_keys($items))->get()->keyBy('id');

            foreach ($items as $id => $qty) {
                if (! isset($products[$id]) || $qty > ($carried[$id] ?? 0)) {
                    throw ValidationException::withMessages([
                        "items.{$id}" => ($products[$id]->name ?? 'Barang').': yang Anda bawa hanya '.(isset($products[$id]) ? $products[$id]->qtyLabel($carried[$id] ?? 0) : 0).'.',
                    ]);
                }
            }

            return DB::transaction(fn () => $this->record($sales, $line, $buyerId, $items, $method, $proof, $routeStopId, 'bawaan'));
        });
    }

    /**
     * Writes the nota (and its visit, and the route stop) for items whose stock was checked by the caller,
     * inside the caller's lock and transaction. $source: bawaan (carried goods) | titipan (consigned goods sold).
     *
     * @param array<int, int> $items product_id => pcs
     */
    public function record(User $sales, BusinessLine $line, int $buyerId, array $items, NotePaymentMethod $method, ?UploadedFile $proof, ?int $routeStopId, string $source): SalesNote
    {
        $products = Product::forLine($line)->whereKey(array_keys($items))->get()->keyBy('id');
        $proofPath = $proof?->store('sales-notes', 's3');
        $routeStopId = DeliveryRoutes::stopFor($sales, $routeStopId, $buyerId)?->id;

        $note = SalesNote::create([
            'line' => $line,
            'sales_id' => $sales->id,
            'buyer_id' => $buyerId,
            'payment_method' => $method,
            'source' => $source,
            'total' => 0,
            'proof_path' => $proofPath,
            'proof_status' => $method->needsProof() ? ProofStatus::Pending : null,
            'route_stop_id' => $routeStopId,
        ]);

        $total = 0;
        $deposit = 0;
        foreach ($items as $id => $qty) {
            // the admin's unit price (the marketplace promo doesn't apply); quantity in pcs
            $subtotal = $products[$id]->valueOf($qty);
            $note->items()->create([
                'product_id' => $id,
                'product_name' => $products[$id]->name,
                'quantity' => $qty,
                'price' => (int) $products[$id]->price,
                'subtotal' => $subtotal,
            ]);
            $total += $subtotal;
            $deposit += $products[$id]->depositOf($qty); // what the sales person owes the admin for it
        }

        $visit = Visit::create([
            'sales_id' => $sales->id,
            'buyer_id' => $buyerId,
            'notes' => 'Nota '.$note->number(),
            'visited_at' => now(),
        ]);
        $note->update(['total' => $total, 'deposit_total' => $deposit, 'visit_id' => $visit->id]);
        if ($routeStopId) {
            DeliveryRouteStop::whereKey($routeStopId)->update(['status' => 'visited', 'visited_at' => now()]);
        }
        if ($line === BusinessLine::Toys) {
            app(StorePoints::class)->sync($buyerId);
        }
        if ($method->needsProof()) {
            AdminNotice::toAdmins('Nota transfer perlu dicek', "{$sales->name} · ".User::find($buyerId)?->name.' · Rp '.number_format($total, 0, ',', '.'), route('admin.notes.show', $note, absolute: false), 'receipt-text');
        }

        return $note->load('items');
    }

    public function verify(SalesNote $note, User $admin): void
    {
        $this->review($note, $admin, ProofStatus::Verified, null);
    }

    public function reject(SalesNote $note, User $admin, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Tuliskan alasan penolakan.']);
        }
        $this->review($note, $admin, ProofStatus::Rejected, trim($reason));
    }

    private function review(SalesNote $note, User $admin, ProofStatus $status, ?string $reason): void
    {
        $updated = SalesNote::whereKey($note->id)->where('proof_status', ProofStatus::Pending)->update([
            'proof_status' => $status,
            'proof_reviewed_by' => $admin->id,
            'proof_reviewed_at' => now(),
            'proof_note' => $reason,
        ]);

        if ($updated === 0) {
            throw ValidationException::withMessages(['proof' => 'Bukti nota ini sudah diperiksa.']);
        }
        $note->refresh();
        if ($status === ProofStatus::Rejected && $note->line === BusinessLine::Toys) {
            app(StorePoints::class)->sync($note->buyer_id); // a rejected transfer earns nothing
        }
    }
}
