<?php

namespace App\Models;

use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Observers\RewardObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(RewardObserver::class)]
class Reward extends Model
{
    /** @use HasFactory<RewardFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }
}
