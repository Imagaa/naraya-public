<?php

namespace App\Models;

use App\Enums\StockTransferKind;
use App\Models\Concerns\HasBusinessLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A goods movement between the warehouse and a sales person (or into the warehouse). Written by App\Support\Stock\StockTransfers only. */
class StockTransfer extends Model
{
    use HasBusinessLine;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['kind' => StockTransferKind::class];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function totalValue(): int
    {
        return (int) $this->items->sum('value');
    }
}
