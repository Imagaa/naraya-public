<?php

namespace App\Livewire\Admin\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPhoto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Form extends Component
{
    use WithFileUploads, HasAdminShell;

    public const SIZE_UNITS = ['gr', 'kg', 'ml', 'l', 'pcs', 'pack', 'box', 'sachet', 'lembar'];

    /** ~17 hues x 2 shades + grays — many distinguishable colors for a catalog that can grow into dozens of categories. */
    public const COLOR_PALETTE = [
        '#EF4444', '#F97316', '#F59E0B', '#EAB308', '#84CC16', '#22C55E', '#10B981', '#14B8A6',
        '#06B6D4', '#0EA5E9', '#3B82F6', '#6366F1', '#8B5CF6', '#A855F7', '#D946EF', '#EC4899', '#F43F5E',
        '#DC2626', '#EA580C', '#D97706', '#CA8A04', '#65A30D', '#16A34A', '#059669', '#0D9488',
        '#0891B2', '#0284C7', '#2563EB', '#4F46E5', '#7C3AED', '#9333EA', '#C026D3', '#DB2777', '#E11D48',
        '#78716C', '#71717A', '#6B7280', '#64748B',
    ];

    /** Curated subset of the installed Lucide set — the full ~1000-icon set would be unusable as a flat picker. */
    public const ICON_OPTIONS = [
        'soup', 'cup-soda', 'coffee', 'milk', 'wine', 'sandwich', 'candy', 'ice-cream-cone', 'popcorn', 'egg', 'fish',
        'shopping-bag', 'shopping-cart', 'package', 'cookie', 'chef-hat',
        'home', 'soap-dispenser-droplet', 'shirt', 'baby', 'pill', 'smartphone', 'book-open', 'gift', 'watch', 'glasses',
        'scissors', 'hammer', 'wrench', 'pencil', 'palette', 'flower-2', 'leaf', 'dog', 'cat', 'car', 'bike', 'umbrella',
        'star', 'more-horizontal',
    ];

    public const MAX_EXTRA_PHOTOS = 5;

    public ?Product $product = null;

    public string $name = '';
    public string $sku = '';
    public string $barcode = '';
    public ?int $categoryId = null;
    public ?float $sizeValue = null;
    public string $sizeUnit = '';
    public ?int $price = null;
    /** What the sales person deposits per unit sold (their cut is price − this); empty = the price. */
    public ?int $depositPrice = null;
    /** Stock correction (edit only): the counted stock in pcs, and why. */
    public ?int $countedStock = null;
    public string $stockNote = '';
    public ?int $discountPrice = null;
    public ?int $wholesalePrice = null;
    public int $stock = 0;
    public ?int $minStock = null;
    public string $unit = '';
    public string $description = '';
    public bool $isActive = true;

    public $photo;

    public ?int $piecesPerUnit = null;

    /** datetime-local value, e.g. "2030-10-05T23:59"; empty = no end date */
    public string $discountEndsAt = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $extraPhotos = [];

    public string $newCategoryName = '';
    public string $newCategoryColor = '#F0B90B';
    public string $newCategoryIcon = 'more-horizontal';

    public string $barcodeLookupMessage = '';

    public function mount(?Product $product = null): void
    {
        $this->product = $product;

        if ($product?->exists) {
            $this->name = $product->name;
            $this->sku = (string) $product->sku;
            $this->barcode = (string) $product->barcode;
            $this->categoryId = $product->category_id;
            $this->sizeValue = $product->size_value;
            $this->sizeUnit = (string) $product->size_unit;
            $this->price = $product->price;
            $this->depositPrice = $product->deposit_price;
            $this->discountPrice = $product->discount_price;
            $this->wholesalePrice = $product->wholesale_price;
            $this->stock = $product->stock;
            $this->minStock = $product->min_stock;
            $this->unit = (string) $product->unit;
            $this->description = (string) $product->description;
            $this->isActive = $product->is_active;
            $this->piecesPerUnit = $product->pieces_per_unit;
            $this->discountEndsAt = $product->discount_ends_at?->timezone(config('app.business_timezone'))->format('Y-m-d\TH:i') ?? '';
        }
    }

    /**
     * Free, keyless, food/FMCG-focused — a reasonable fit for this catalog,
     * though coverage of smaller local Indonesian brands is incomplete.
     * Only prefills name (and only if still empty); admin fills the rest.
     */
    public function lookupBarcode(): void
    {
        $this->barcodeLookupMessage = '';

        if (! $this->barcode) {
            return;
        }

        try {
            $response = Http::timeout(8)->get("https://world.openfoodfacts.org/api/v2/product/{$this->barcode}.json");
            $data = $response->json();
        } catch (\Throwable) {
            $this->barcodeLookupMessage = 'Tidak dapat menghubungi layanan pencarian barcode. Isi manual.';

            return;
        }

        if (($data['status'] ?? 0) === 1 && ! empty($data['product']['product_name'])) {
            if (! $this->name) {
                $this->name = $data['product']['product_name'];
            }
            $this->barcodeLookupMessage = 'Ditemukan: '.$data['product']['product_name'];
        } else {
            $this->barcodeLookupMessage = 'Produk tidak ditemukan di database barcode — isi manual.';
        }
    }

    public function createCategory(): void
    {
        $this->validate([
            'newCategoryName' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->where('line', $this->line()->value)],
            'newCategoryColor' => ['required', Rule::in(self::COLOR_PALETTE)],
            'newCategoryIcon' => ['required', Rule::in(self::ICON_OPTIONS)],
        ]);

        $category = Category::create([
            'line' => $this->line(),
            'name' => $this->newCategoryName,
            'color' => $this->newCategoryColor,
            'icon' => $this->newCategoryIcon,
        ]);

        $this->categoryId = $category->id;
        $this->newCategoryName = '';
        $this->newCategoryColor = '#F0B90B';
        $this->newCategoryIcon = 'more-horizontal';
        $this->dispatch('category-created');
    }

    /** A product keeps its own line; a new one is created in the line admin is working in. */
    private function line(): \App\Enums\BusinessLine
    {
        return $this->product?->exists ? $this->product->line : \App\Support\ActiveLine::current();
    }

    public function save()
    {
        $isNew = ! $this->product?->exists;

        $this->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                $isNew ? 'required' : 'nullable',
                'string', 'max:100',
                Rule::unique('products', 'sku')->ignore($this->product?->id),
            ],
            'barcode' => ['nullable', 'digits_between:8,14', Rule::unique('products', 'barcode')->ignore($this->product?->id)],
            'categoryId' => ['required', Rule::exists('categories', 'id')->where('line', $this->line()->value)],
            'sizeValue' => 'nullable|numeric|min:0',
            'sizeUnit' => 'nullable|string|max:20',
            'price' => 'required|integer|min:0',
            'depositPrice' => 'nullable|integer|min:0|lte:price',
            'discountPrice' => 'nullable|integer|min:0|lt:price',
            'discountEndsAt' => 'nullable|date',
            'piecesPerUnit' => 'nullable|integer|min:1',
            'unit' => 'nullable|string|max:50',
            'photo' => 'nullable|image|max:5120',
            'extraPhotos' => ['array', function ($attribute, $value, $fail) {
                if ($this->existingPhotoCount() + count($value) > self::MAX_EXTRA_PHOTOS) {
                    $fail('Maksimal '.self::MAX_EXTRA_PHOTOS.' foto tambahan.');
                }
            }],
            'extraPhotos.*' => 'image|max:5120',
        ]);

        $data = [
            'name' => $this->name,
            'sku' => $this->sku ?: null,
            'barcode' => $this->barcode ?: null,
            'category_id' => $this->categoryId,
            'size_value' => $this->sizeValue,
            'size_unit' => $this->sizeValue ? ($this->sizeUnit ?: null) : null,
            'price' => $this->price,
            'deposit_price' => $this->depositPrice,
            'discount_price' => $this->discountPrice,
            'discount_ends_at' => $this->discountPrice && $this->discountEndsAt
                ? Carbon::parse($this->discountEndsAt, config('app.business_timezone'))->utc()
                : null,
            'wholesale_price' => $this->wholesalePrice,
            'min_stock' => $this->minStock,
            'unit' => $this->unit ?: null,
            'pieces_per_unit' => $this->piecesPerUnit,
            'description' => $this->description ?: null,
            'is_active' => $this->isActive,
        ];

        if ($this->photo) {
            $data['photo_path'] = $this->photo->store('products', 's3');
        }

        if ($this->product?->exists) {
            $this->product->update($data);
            $product = $this->product;
        } else {
            $product = Product::create($data + ['stock' => $this->stock, 'line' => $this->line()]);
        }

        $next = (int) $product->photos()->max('sort_order') + 1;
        foreach ($this->extraPhotos as $file) {
            $product->photos()->create(['path' => $file->store('products', 's3'), 'sort_order' => $next++]);
        }

        return redirect()->route('admin.products.index');
    }

    /** Set the warehouse stock to what was counted; the difference is logged as a correction. */
    public function correctStock(): void
    {
        abort_unless($this->product?->exists, 404);
        $this->validate(['countedStock' => 'required|integer|min:0', 'stockNote' => 'required|string|max:255'], [
            'countedStock.required' => 'Isi stok hasil hitung (pcs).',
            'stockNote.required' => 'Tulis alasannya, mis. hasil stok opname / barang rusak.',
        ]);
        $delta = $this->countedStock - (int) $this->product->fresh()->stock;
        if ($delta !== 0) {
            $this->product->adjustStock($delta, \App\Enums\StockMovementType::Correction, auth()->user(), trim($this->stockNote));
        }
        $this->stock = (int) $this->product->fresh()->stock;
        $this->reset('countedStock', 'stockNote');
        session()->flash('stock_ok', 'Stok disesuaikan menjadi '.$this->product->fresh()->qtyLabel($this->stock).'.');
    }

    /** Delete a product nothing refers to yet; one already in orders, notes or stock movements of sales can only be turned off. */
    public function destroy()
    {
        abort_unless($this->product?->exists, 404);
        try {
            \Illuminate\Support\Facades\DB::transaction(fn () => $this->product->delete());
        } catch (\Illuminate\Database\QueryException) {
            $this->addError('destroy', 'Produk ini sudah dipakai di pesanan, nota atau stok sales, jadi tidak bisa dihapus. Matikan "Aktif" agar tidak dijual lagi.');

            return null;
        }

        return redirect()->route('admin.products.index');
    }

    public function removePhoto(int $photoId): void
    {
        $photo = $this->product->photos()->findOrFail($photoId);
        \App\Support\Media::forget($photo->path);
        $photo->delete();
        $this->product->load('photos');
    }

    /** @param int $direction -1 = earlier, 1 = later */
    public function movePhoto(int $photoId, int $direction): void
    {
        $photos = $this->product->photos()->get()->values();
        $i = $photos->search(fn (ProductPhoto $p) => $p->id === $photoId);
        $j = $i === false ? null : $i + $direction;

        if ($j === null || ! isset($photos[$j])) {
            return;
        }

        // Renumber everything first, so photos that share a sort_order still swap cleanly.
        $ordered = $photos->all();
        [$ordered[$i], $ordered[$j]] = [$ordered[$j], $ordered[$i]];
        foreach ($ordered as $position => $photo) {
            $photo->update(['sort_order' => $position + 1]);
        }

        $this->product->load('photos');
    }

    private function existingPhotoCount(): int
    {
        return $this->product?->exists ? $this->product->photos()->count() : 0;
    }

    public function render()
    {
        return view('livewire.admin.products.form', [
            'categories' => Category::forLine($this->line())->orderBy('name')->get(),
        ])->layout('layouts.admin');
    }
}
