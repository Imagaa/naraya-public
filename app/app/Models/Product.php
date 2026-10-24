<?php

namespace App\Models;

use App\Models\Concerns\HasBusinessLine;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Observers\ProductObserver;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[ObservedBy(ProductObserver::class)]
#[Fillable(['name', 'slug', 'sku', 'barcode','category_id', 'size_value', 'size_unit', 'price', 'deposit_price', 'discount_price', 'discount_ends_at', 'wholesale_price', 'stock', 'min_stock', 'min_purchase', 'unit', 'pieces_per_unit', 'description', 'photo_path', 'is_active', 'line'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasBusinessLine;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'size_value' => 'float',
            'discount_ends_at' => 'datetime',
            'price_changed_at' => 'datetime',
            'pieces_per_unit' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Set once at creation and never regenerated on rename, so links
        // buyers already shared (WhatsApp) keep working.
        static::creating(function (Product $product) {
            $product->slug ??= static::uniqueSlug($product->name);
        });
        // "Harga per …" on grosir product pages: the last time the price or the promo price changed
        static::saving(function (Product $product) {
            if (! $product->exists || $product->isDirty(['price', 'discount_price'])) {
                $product->price_changed_at = now();
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $n = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function hasDiscount(): bool
    {
        return $this->discount_price !== null
            && $this->discount_price < $this->price
            && ($this->discount_ends_at === null || $this->discount_ends_at->isFuture());
    }

    public function effectivePrice(): int
    {
        return $this->hasDiscount() ? (int) $this->discount_price : (int) $this->price;
    }

    public function discountPercent(): int
    {
        return $this->hasDiscount()
            ? (int) round(($this->price - $this->discount_price) / $this->price * 100)
            : 0;
    }

    /** Whole days until the discount ends, rounded up; null when there is no end date or no active discount. */
    public function discountDaysLeft(): ?int
    {
        if (! $this->hasDiscount() || $this->discount_ends_at === null) {
            return null;
        }

        return max(1, (int) ceil(now()->diffInMinutes($this->discount_ends_at) / 1440));
    }

    /** Promo end date as buyers read it (Jakarta time), e.g. "06/10/2030"; null when there is none. */
    public function discountEndsLabel(): ?string
    {
        return $this->hasDiscount() && $this->discount_ends_at
            ? $this->discount_ends_at->timezone(config('app.business_timezone'))->format('d/m/Y')
            : null;
    }

    public function pricePerPiece(): ?int
    {
        return $this->pieces_per_unit ? (int) round($this->effectivePrice() / $this->pieces_per_unit) : null;
    }

    /**
     * Stock is counted in pcs; `price` is per unit
     * (e.g. per lembar) and a unit holds pieces_per_unit pcs — 1 when not set.
     */
    public function ppu(): int
    {
        return max(1, (int) $this->pieces_per_unit);
    }

    /** Whole units in the warehouse — what the marketplace can sell. */
    public function unitsInStock(): int
    {
        return intdiv((int) $this->stock, $this->ppu());
    }

    /** What the sales person deposits for $pcs pieces sold: the admin's deposit price per unit, or the price when none is set. */
    public function depositOf(int $pcs): int
    {
        return (int) round($pcs * (int) ($this->deposit_price ?? $this->price) / $this->ppu());
    }

    /** Rupiah value of $pcs pieces at the unit price. */
    public function valueOf(int $pcs): int
    {
        return (int) round($pcs * (int) $this->price / $this->ppu());
    }

    /** "2 lembar 5 pcs", "2 lembar", "5 pcs"; "7 pcs" (or "7 <unit>") when a unit is one piece. */
    public function qtyLabel(int $pcs): string
    {
        $unit = $this->unit ?: 'pcs';
        if ($this->ppu() === 1) {
            return number_format($pcs, 0, ',', '.').' '.$unit;
        }
        $whole = intdiv($pcs, $this->ppu());
        $rest = $pcs % $this->ppu();

        return trim(($whole ? number_format($whole, 0, ',', '.').' '.$unit : '').($rest || ! $whole ? ' '.$rest.' pcs' : ''));
    }

    public function minQuantity(): int
    {
        return max(1, (int) $this->min_purchase);
    }

    /** Stock below the minimum order is unbuyable, so it counts as sold out. */
    public function isAvailable(): bool
    {
        return $this->unitsInStock() >= $this->minQuantity();
    }

    /**
     * isAvailable() in SQL: min_purchase is nullable and minQuantity() treats null/0 as 1, so a
     * bare `stock >= min_purchase` would wrongly count every product without a minimum as sold out.
     */
    public const AVAILABLE_SQL = 'products.stock >= COALESCE(NULLIF(products.min_purchase, 0), 1) * COALESCE(NULLIF(products.pieces_per_unit, 0), 1)';

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereRaw(self::AVAILABLE_SQL);
    }

    public function sizeLabel(): ?string
    {
        if (! $this->size_value) {
            return null;
        }

        $value = fmod($this->size_value, 1) === 0.0 ? (int) $this->size_value : $this->size_value;

        return trim($value.' '.$this->size_unit);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function stockAlerts(): HasMany
    {
        return $this->hasMany(StockAlert::class);
    }

    /** @return array<int, string> cover first, then extra photos in order */
    public function galleryPaths(): array
    {
        return collect([$this->photo_path])
            ->merge($this->photos->pluck('path'))
            ->filter()
            ->values()
            ->all();
    }

    public function url(): string
    {
        return url('/produk/'.$this->slug);
    }

    public function scopePromo(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('discount_price')
            ->whereColumn('discount_price', '<', 'price')
            ->where(fn (Builder $q) => $q->whereNull('discount_ends_at')->orWhere('discount_ends_at', '>', now()));
    }

    /**
     * Apply a signed stock delta inside a locked transaction, so two
     * near-simultaneous adjustments can't both read stale stock and
     * both succeed past zero.
     */
    public function adjustStock(int $delta, StockMovementType $type, User $actor, ?string $note = null, ?string $reference = null): void
    {
        DB::transaction(function () use ($delta, $type, $actor, $note, $reference) {
            $locked = static::query()->lockForUpdate()->findOrFail($this->id);

            $newStock = $locked->stock + $delta;

            if ($newStock < 0) {
                throw new InsufficientStockException($locked->stock, abs($delta));
            }

            $locked->update(['stock' => $newStock]);

            $locked->stockMovements()->create([
                'type' => $type,
                'quantity' => $delta,
                'reference' => $reference,
                'note' => $note,
                'user_id' => $actor->id,
            ]);

            $this->stock = $newStock;
        });
    }
}
