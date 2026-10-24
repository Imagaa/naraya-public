<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One signed movement in a store's investment wallet; the balance is their sum. */
class InvestmentWalletEntry extends Model
{
    protected $guarded = [];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(InvestmentPurchase::class, 'investment_purchase_id');
    }
}
