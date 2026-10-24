<?php

namespace App\Livewire\Marketplace;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Marketplace\Concerns\HasMarketplaceShell;
use App\Livewire\Marketplace\Concerns\ManagesAddressForm;
use App\Models\Order;
use App\Support\Orders\DeliveryEstimate;
use App\Support\Cart;
use App\Support\Catalog;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Checkout extends Component
{
    use HasMarketplaceShell, ManagesAddressForm;

    /** review (keranjang) -> shipping (pengiriman) -> payment (pembayaran) */
    public string $step = 'review';

    public ?int $selectedAddressId = null;
    public bool $addingNewAddress = false;

    public string $notes = '';
    public string $paymentMethod = 'cash';

    public function mount(): void
    {
        $default = auth()->user()->addresses()->where('is_default', true)->first()
            ?? auth()->user()->addresses()->first();

        if ($default) {
            $this->selectedAddressId = $default->id;
        } else {
            $this->addingNewAddress = true;
        }
    }

    public function updateQty(int $productId, int $quantity): void
    {
        Cart::updateQuantity($productId, $quantity);
    }

    public function removeItem(int $productId): void
    {
        Cart::remove($productId);
    }

    public function clearCart(): void
    {
        Cart::clear();
    }

    public function goToShipping()
    {
        if (! $this->validateCartHasStock()) {
            return;
        }

        $this->step = 'shipping';
    }

    public function selectAddress(int $addressId): void
    {
        $this->selectedAddressId = $addressId;
        $this->addingNewAddress = false;
    }

    public function showAddAddressForm(): void
    {
        $this->addingNewAddress = true;
        $this->selectedAddressId = null;
    }

    public function cancelAddAddressForm(): void
    {
        $existing = auth()->user()->addresses()->where('is_default', true)->first()
            ?? auth()->user()->addresses()->first();

        if ($existing) {
            $this->addingNewAddress = false;
            $this->selectedAddressId = $existing->id;
        }
    }

    public function goToPayment()
    {
        if ($this->addingNewAddress) {
            $this->validateAddressForm();

            $address = auth()->user()->addresses()->create(
                $this->addressFormData() + ['is_default' => auth()->user()->addresses()->count() === 0]
            );

            $this->selectedAddressId = $address->id;
            $this->addingNewAddress = false;
        } else {
            $this->validate(
                ['selectedAddressId' => 'required|integer'],
                ['selectedAddressId.required' => 'Pilih alamat pengiriman.']
            );

            if (! auth()->user()->addresses()->whereKey($this->selectedAddressId)->exists()) {
                $this->addError('selectedAddressId', 'Alamat tidak valid.');

                return;
            }
        }

        $this->step = 'payment';
    }

    public function backToReview(): void
    {
        $this->step = 'review';
    }

    public function backToShipping(): void
    {
        $this->step = 'shipping';
    }

    public function placeOrder()
    {
        $this->validate(
            ['paymentMethod' => 'required|in:cash,transfer'],
            ['paymentMethod.required' => 'Pilih metode pembayaran.']
        );

        $address = auth()->user()->addresses()->find($this->selectedAddressId);

        if (! $address) {
            $this->addError('selectedAddressId', 'Alamat tidak valid.');

            return;
        }

        if (! $this->validateCartHasStock()) {
            return;
        }

        $items = Cart::items();
        $subtotal = $items->sum('subtotal');
        $buyerProfile = auth()->user()->buyerProfile;
        $creditApplied = $buyerProfile ? min($buyerProfile->credit_balance, $subtotal) : 0;
        $total = $subtotal - $creditApplied;
        // transfer: proof uploaded on the order page within 30 minutes (TransferPayments); nothing to pay → settled
        $isTransfer = $this->paymentMethod === 'transfer' && $total > 0;

        $order = DB::transaction(function () use ($items, $isTransfer, $subtotal, $total, $creditApplied, $buyerProfile, $address) {
            $order = Order::create([
                'buyer_id' => auth()->id(),
                'status' => OrderStatus::New,
                'payment_method' => $this->paymentMethod,
                'payment_status' => $isTransfer ? PaymentStatus::Pending : PaymentStatus::Paid,
                'payment_expires_at' => $isTransfer ? now()->addMinutes(30) : null,
                'estimated_delivery_at' => DeliveryEstimate::for(now()),
                'subtotal' => $subtotal,
                'total' => $total,
                'credit_applied' => $creditApplied,
                'delivery_address' => $address->toFullAddress(),
                'delivery_province' => $address->province_name,
                'delivery_city' => $address->city_name,
                'delivery_district' => $address->district_name,
                'delivery_village' => $address->village_name,
                'delivery_lat' => $address->lat,
                'delivery_lng' => $address->lng,
                'notes' => $this->notes ?: null,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'price' => $item['unitPrice'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            if ($creditApplied > 0) {
                $buyerProfile->decrement('credit_balance', $creditApplied);
            }

            return $order;
        });

        Cart::clear();

        return redirect()->route('orders.show', $order);
    }

    /**
     * Shared by both the "lanjut ke pengiriman" step gate and the final
     * placeOrder() submit — stock can change between steps, so it's
     * re-checked at the end too, not just when entering the flow.
     */
    private function validateCartHasStock(): bool
    {
        $items = Cart::items();

        if ($items->isEmpty()) {
            $this->addError('cart', 'Keranjang masih kosong.');

            return false;
        }

        foreach ($items as $item) {
            if ($item['quantity'] > $item['product']->unitsInStock()) {
                $this->addError('cart', "Stok {$item['product']->name} tidak cukup (tersisa {$item['product']->unitsInStock()}).");

                return false;
            }
        }

        return true;
    }

    public function render()
    {
        $items = Cart::items();
        EloquentCollection::make($items->pluck('product'))->load('category');
        $subtotal = (int) $items->sum('subtotal');
        $gross = (int) $items->sum(fn ($i) => $i['product']->price * $i['quantity']);
        $credit = (int) min(auth()->user()->buyerProfile?->credit_balance ?? 0, $subtotal);
        $grand = $subtotal - $credit;
        $titles = ['review' => 'Keranjang', 'shipping' => 'Pengiriman', 'payment' => 'Pembayaran'];

        return view('livewire.marketplace.checkout', [
            'items' => $items,
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->latest()->get(),
            'cartCount' => $this->cartCount(),
            'subtotal' => $subtotal,
            'grossTotal' => $gross,
            'savings' => $gross - $subtotal,
            'creditApplied' => $credit,
            'grandTotal' => $grand,
            'unitCount' => (int) $items->sum('quantity'),
            'recommendations' => Catalog::boughtWithAny($items->pluck('product.id')->all(), auth()->user(), 10),
            'cartQuantities' => $this->cartQuantities(),
            'salesName' => auth()->user()->buyerProfile?->salesUser?->name,
            'stepTitle' => $titles[$this->step] ?? 'Keranjang',
        ])->layout('layouts.marketplace', ['maps' => true])->title(($titles[$this->step] ?? 'Keranjang').' — Naraya');
    }
}
