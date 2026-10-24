<?php

namespace App\Support\Loyalty;

use App\Enums\BusinessLine;
use App\Enums\ProofStatus;
use App\Models\BuyerProfile;
use App\Models\LoyaltySetting;
use App\Models\SalesNoteItem;
use Illuminate\Support\Facades\DB;

/**
 * A store's points: 1 for every pcs_per_point pcs of Naraya Toys sold there
 *. Derived from the notas,
 * so a sync is idempotent: it adds target − already awarded to the balance.
 */
final class StorePoints
{
    public function perPoint(): int
    {
        return max(1, (int) (LoyaltySetting::first()?->pcs_per_point ?? 150));
    }

    /** Toys pcs sold to the store; a rejected transfer doesn't count. */
    public function pcsCounted(int $buyerId): int
    {
        return (int) SalesNoteItem::whereHas('note', fn ($q) => $q->where('buyer_id', $buyerId)->where('line', BusinessLine::Toys)
            ->where(fn ($q) => $q->whereNull('proof_status')->orWhere('proof_status', '!=', ProofStatus::Rejected)))
            ->sum('quantity');
    }

    /** @return int the change applied to the balance */
    public function sync(int $buyerId): int
    {
        $target = intdiv($this->pcsCounted($buyerId), $this->perPoint());

        return DB::transaction(function () use ($buyerId, $target) {
            $profile = BuyerProfile::where('user_id', $buyerId)->lockForUpdate()->first();
            if (! $profile) {
                return 0;
            }
            $delta = $target - $profile->toys_points_awarded;
            if ($delta !== 0) {
                // a correction after the points were spent stops at 0
                $profile->update(['loyalty_points' => max(0, $profile->loyalty_points + $delta), 'toys_points_awarded' => $target]);
            }

            return $delta;
        });
    }

    public function syncAll(): void
    {
        BuyerProfile::pluck('user_id')->each(fn (int $buyerId) => $this->sync($buyerId));
    }

    /** @return array{pcs: int, per: int} pcs towards the next point */
    public function progress(int $buyerId): array
    {
        $per = $this->perPoint();

        return ['pcs' => $this->pcsCounted($buyerId) % $per, 'per' => $per];
    }
}
