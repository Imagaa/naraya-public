<?php

namespace App\Models;

use App\Enums\RedemptionStatus;
use App\Observers\RedemptionObserver;
use Database\Factories\RedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(RedemptionObserver::class)]
class Redemption extends Model
{
    /** @use HasFactory<RedemptionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => RedemptionStatus::class,
            'diserahkan_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diserahkan_oleh');
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }
}
