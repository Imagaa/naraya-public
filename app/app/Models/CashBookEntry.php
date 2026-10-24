<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessLine;
use App\Enums\CashBookEntryKind;
use App\Enums\CashBookEntryType;
use App\Enums\CashBookSource;
use Database\Factories\CashBookEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBookEntry extends Model
{
    /** @use HasFactory<CashBookEntryFactory> */
    use HasFactory, SoftDeletes, HasBusinessLine;

    /** The author may change or delete a manual entry this long after creating it; afterwards only a correction. */
    public const EDIT_MINUTES = 15;

    protected $guarded = [];

    protected $attributes = ['kind' => 'manual'];

    protected function casts(): array
    {
        return [
            'type' => CashBookEntryType::class,
            'kind' => CashBookEntryKind::class,
            'source' => CashBookSource::class,
            'date' => 'date',
            'rekap_date' => 'date',
            'edited_at' => 'datetime',
        ];
    }

    public function signedAmount(): int
    {
        return $this->type === CashBookEntryType::Pemasukan ? (int) $this->amount : -(int) $this->amount;
    }

    public function isWithinEditWindow(): bool
    {
        return $this->kind === CashBookEntryKind::Manual && $this->created_at->gt(now()->subMinutes(self::EDIT_MINUTES));
    }

    /** Rows from before the daily summaries carry "Order #12" / "Setoran #3" instead of items; link that source directly. */
    public function legacySubjectUrl(): ?string
    {
        if (! preg_match('/^(Order|Setoran) #(\d+)$/', (string) $this->reference, $m)) {
            return null;
        }

        return $m[1] === 'Order' ? route('admin.orders.show', (int) $m[2]) : route('admin.deposits.show', (int) $m[2]);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CashBookEntryItem::class, 'entry_id');
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_id')->withTrashed();
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_id');
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
