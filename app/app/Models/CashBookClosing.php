<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The frozen numbers of a closed month. */
class CashBookClosing extends Model
{
    use HasBusinessLine;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['month' => 'date', 'breakdown' => 'array', 'auto' => 'boolean', 'closed_at' => 'datetime'];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
