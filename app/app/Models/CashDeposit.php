<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessLine;
use App\Enums\CashDepositStatus;
use App\Observers\CashDepositObserver;
use Database\Factories\CashDepositFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(CashDepositObserver::class)]
class CashDeposit extends Model
{
    /** @use HasFactory<CashDepositFactory> */
    use HasFactory, HasBusinessLine;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => CashDepositStatus::class,
            'resolved_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(CashCollection::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SalesNote::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(InvestmentPurchase::class);
    }

}
