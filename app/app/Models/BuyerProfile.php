<?php

namespace App\Models;

use App\Enums\BuyerStatus;
use Database\Factories\BuyerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'area_id', 'sales_user_id', 'owner_name', 'phone', 'address', 'store_photo_path', 'latitude', 'longitude', 'loyalty_points', 'toys_points_awarded', 'credit_balance', 'is_active', 'status', 'rejection_reason'])]
class BuyerProfile extends Model
{
    /** @use HasFactory<BuyerProfileFactory> */
    use HasFactory;

    /** A store's pin or sales person changed: their areas follow (App\Support\SalesAreas). */
    protected static function booted(): void
    {
        static::saved(function (self $profile) {
            if ($profile->wasRecentlyCreated || $profile->wasChanged(['sales_user_id', 'latitude', 'longitude'])) {
                \App\Support\SalesAreas::refresh($profile->sales_user_id);
                if ($profile->wasChanged('sales_user_id')) {
                    \App\Support\SalesAreas::refresh($profile->getOriginal('sales_user_id'));
                }
            }
        });
        static::deleted(fn (self $profile) => \App\Support\SalesAreas::refresh($profile->sales_user_id));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status' => BuyerStatus::class,
            'loyalty_points' => 'integer',
            'toys_points_awarded' => 'integer',
            'credit_balance' => 'integer',
            'consignment_opened_at' => 'datetime',
        ];
    }

    /** Every buyer needs a storefront photo and a store pin before shopping (onboarding gate 2). */
    public function hasCompleteStoreData(): bool
    {
        return $this->store_photo_path !== null && $this->latitude !== null && $this->longitude !== null;
    }

    public function storePhotoUrl(): ?string
    {
        return $this->store_photo_path ? Storage::disk('s3')->url($this->store_photo_path) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }
}
