<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One order or deposit inside a daily summary entry. */
class CashBookEntryItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
