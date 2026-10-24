<?php

use App\Enums\BusinessLine;
use App\Enums\NotePaymentMethod;
use App\Enums\ProofStatus;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\SalesNote;
use App\Models\User;
use App\Models\Visit;
use App\Support\Sales\SalesNotes;
use App\Support\Stock\CarriedStock;
use App\Support\Stock\StockTransfers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Toko Maju']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->sales->id]);
    $this->mie = Product::factory()->create(['name' => 'Mie', 'stock' => 20, 'price' => 3000, 'discount_price' => 2500]);
    $this->toy = Product::factory()->create(['name' => 'Mobil', 'stock' => 5, 'price' => 50000, 'line' => BusinessLine::Toys]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Naraya, [$this->mie->id => 10]);
    app(StockTransfers::class)->pickup($this->sales, BusinessLine::Toys, [$this->toy->id => 2]);
    $this->notes = app(SalesNotes::class);
});

it('sells carried goods to an assigned store at the admin price, paid cash, and logs the visit', function () {
    $note = $this->notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$this->mie->id => 4], NotePaymentMethod::Cash);

    expect($note->total)->toBe(12000)                       // admin price, not the marketplace promo price
        ->and($note->items->sole()->price)->toBe(3000)
        ->and($note->items->sole()->product_name)->toBe('Mie')
        ->and($note->proof_status)->toBeNull()
        ->and($note->number())->toBe('NJ-N'.str_pad($note->id, 5, '0', STR_PAD_LEFT))
        ->and(CarriedStock::for($this->sales, BusinessLine::Naraya)->all())->toBe([$this->mie->id => 6])
        ->and(Visit::sole()->buyer_id)->toBe($this->store->id)
        ->and(Visit::sole()->notes)->toContain($note->number());
});

it('caps each item at what the sales carries in that line', function () {
    expect(fn () => $this->notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$this->mie->id => 11], NotePaymentMethod::Cash))
        ->toThrow(ValidationException::class);
    expect(fn () => $this->notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$this->toy->id => 1], NotePaymentMethod::Cash))
        ->toThrow(ValidationException::class);
    expect(SalesNote::count())->toBe(0);
});

it('only sells to stores assigned to the sales', function () {
    $other = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $other->id, 'sales_user_id' => User::factory()->create(['role' => Role::Sales])->id]);

    expect(fn () => $this->notes->create($this->sales, BusinessLine::Naraya, $other->id, [$this->mie->id => 1], NotePaymentMethod::Cash))
        ->toThrow(ValidationException::class);
});

it('requires a proof photo for transfer and QRIS, which then waits for admin', function () {
    Storage::fake('s3');

    expect(fn () => $this->notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$this->mie->id => 1], NotePaymentMethod::Transfer))
        ->toThrow(ValidationException::class);

    $note = $this->notes->create($this->sales, BusinessLine::Toys, $this->store->id, [$this->toy->id => 1], NotePaymentMethod::Transfer,
        UploadedFile::fake()->image('bukti.jpg'));

    expect($note->proof_status)->toBe(ProofStatus::Pending)
        ->and($note->number())->toStartWith('NJ-T');
    Storage::disk('s3')->assertExists($note->proof_path);
});

it('lets admin verify a proof, or reject it with a reason', function () {
    Storage::fake('s3');
    $a = $this->notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$this->mie->id => 1], NotePaymentMethod::Transfer, UploadedFile::fake()->image('a.jpg'));
    $b = $this->notes->create($this->sales, BusinessLine::Naraya, $this->store->id, [$this->mie->id => 1], NotePaymentMethod::Transfer, UploadedFile::fake()->image('b.jpg'));

    $this->notes->verify($a, $this->admin);
    expect(fn () => $this->notes->reject($b, $this->admin, ''))->toThrow(ValidationException::class);
    $this->notes->reject($b, $this->admin, 'Nominal di bukti tidak sesuai');

    expect($a->fresh()->proof_status)->toBe(ProofStatus::Verified)->and($a->fresh()->proof_reviewed_by)->toBe($this->admin->id)
        ->and($b->fresh()->proof_status)->toBe(ProofStatus::Rejected)->and($b->fresh()->proof_note)->toBe('Nominal di bukti tidak sesuai');
    expect(fn () => $this->notes->verify($a->fresh(), $this->admin))->toThrow(ValidationException::class); // already reviewed
});
