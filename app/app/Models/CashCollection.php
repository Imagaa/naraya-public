<?php

namespace App\Models;

use App\Enums\CashCollectionStatus;
use Database\Factories\CashCollectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashCollection extends Model
{
    /** @use HasFactory<CashCollectionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => CashCollectionStatus::class,
            'collected_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(CashDeposit::class, 'cash_deposit_id');
    }
}
