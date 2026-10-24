<?php

namespace App\Models;

use App\Enums\CancelReason;
use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Observers\OrderObserver;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'paid_at' => 'datetime',
            'payment_expires_at' => 'datetime',
            'payment_proof_uploaded_at' => 'datetime',
            'payment_reviewed_at' => 'datetime',
            'needs_payment_review' => 'boolean',
            'completed_at' => 'datetime',
            'estimated_delivery_at' => 'datetime',
            'sales_assigned_at' => 'datetime',
            'gudang_handover_at' => 'datetime',
            'sales_received_at' => 'datetime',
            'sales_delivered_at' => 'datetime',
            'buyer_confirmed_at' => 'datetime',
            'cancel_reason' => CancelReason::class,
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'delivery_lat' => 'float',
            'delivery_lng' => 'float',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function gudangHandoverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gudang_handover_by');
    }

    /**
     * Awaiting Admin's final review-and-close: Sales has confirmed
     * delivery to the buyer, and the buyer has confirmed receipt (or the
     * 3-day auto-confirm window already closed it directly).
     */
    public function awaitingAdminClose(): bool
    {
        return $this->status === OrderStatus::Shipped
            && $this->sales_delivered_at !== null
            && $this->buyer_confirmed_at !== null;
    }

    /** @var array<string, array{label: string, variant: string}> */
    public const SALES_STAGE_META = [
        'awaiting_handover' => ['label' => 'Menunggu Gudang', 'variant' => 'neutral'],
        'ready_to_receive' => ['label' => 'Perlu Diambil', 'variant' => 'danger'],
        'ready_to_ship' => ['label' => 'Siap Kirim', 'variant' => 'accent'],
        'in_transit' => ['label' => 'Dalam Perjalanan', 'variant' => 'accent-2'],
        'awaiting_buyer_confirm' => ['label' => 'Terkirim', 'variant' => 'success'],
    ];

    /** Count of a sales user's orders still needing action (everything but "awaiting buyer confirmation") — drives the nav badge. */
    public static function pendingSalesActionCount(int $salesId): int
    {
        return self::where('sales_id', $salesId)
            ->where(function ($query) {
                $query->where('status', OrderStatus::Processing)
                    ->orWhere(function ($query) {
                        $query->where('status', OrderStatus::Shipped)->whereNull('sales_delivered_at');
                    });
            })
            ->count();
    }

    /** Where this order sits in Sales's own delivery pipeline — drives the Tugas Kirim list's badges and sort order. */
    public function salesStage(): string
    {
        if ($this->status === OrderStatus::Shipped) {
            return $this->sales_delivered_at !== null ? 'awaiting_buyer_confirm' : 'in_transit';
        }

        if ($this->gudang_handover_at === null) {
            return 'awaiting_handover';
        }

        return $this->sales_received_at === null ? 'ready_to_receive' : 'ready_to_ship';
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    public function childOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /**
     * How this order's state reads to the buyer: the one source for the
     * list card, the detail banner and the timeline, so they can't disagree.
     *
     * @return array{step: int, tone: string, icon: string, label: string, note: string}
     */
    public function buyerProgress(): array
    {
        $sales = $this->salesUser?->name ?? 'sales Anda';

        return match (true) {
            $this->status === OrderStatus::New => ['step' => 1, 'tone' => 'neutral', 'icon' => 'clock', 'label' => 'Baru', 'note' => 'Menunggu konfirmasi admin'],
            $this->status === OrderStatus::Processing => ['step' => 2, 'tone' => 'brand', 'icon' => 'package', 'label' => 'Diproses',
                'note' => 'Disiapkan'.($this->estimated_delivery_at ? ' · estimasi tiba '.self::shortDate($this->estimated_delivery_at) : '')],
            $this->status === OrderStatus::Shipped && ! $this->sales_delivered_at => ['step' => 3, 'tone' => 'info', 'icon' => 'truck', 'label' => 'Dikirim', 'note' => "Sedang diantar {$sales}"],
            $this->status === OrderStatus::Shipped && ! $this->buyer_confirmed_at => ['step' => 3, 'tone' => 'info', 'icon' => 'circle-alert', 'label' => 'Dikirim', 'note' => 'Sudah diantar. Konfirmasi penerimaan dalam 3 hari.'],
            $this->status === OrderStatus::Shipped => ['step' => 3, 'tone' => 'info', 'icon' => 'circle-check', 'label' => 'Dikirim', 'note' => 'Anda sudah konfirmasi. Menunggu penyelesaian.'],
            $this->status === OrderStatus::Completed => ['step' => 4, 'tone' => 'ok', 'icon' => 'circle-check', 'label' => 'Selesai',
                'note' => 'Selesai'.($this->completed_at ? ' '.self::shortDate($this->completed_at) : '')],
            default => ['step' => 0, 'tone' => 'sale', 'icon' => 'circle-x', 'label' => $this->status->label(), 'note' => 'Pesanan tidak diproses lebih lanjut'],
        };
    }

    /** "2 Okt" in the buyer's wall-clock zone and language. */
    private static function shortDate(\Carbon\CarbonInterface $at): string
    {
        return $at->copy()->timezone(config('app.business_timezone'))->locale('id')->translatedFormat('j M');
    }

    /**
     * What the store sees about paying:
     * awaiting_proof | awaiting_review | verified (transfer) | cash | other.
     */
    public function paymentState(): string
    {
        return match (true) {
            $this->payment_method === 'cash' => 'cash',
            $this->payment_method !== 'transfer' => 'other',
            $this->payment_status === PaymentStatus::Paid => 'verified',
            $this->payment_status === PaymentStatus::Pending && $this->payment_proof_path !== null => 'awaiting_review',
            $this->payment_status === PaymentStatus::Pending => 'awaiting_proof',
            default => 'other',
        };
    }

    public function canBeConfirmedByBuyer(): bool
    {
        return $this->status === OrderStatus::Shipped
            && $this->sales_delivered_at !== null
            && $this->buyer_confirmed_at === null;
    }

    public function confirmReceivedByBuyer(): void
    {
        abort_unless($this->canBeConfirmedByBuyer(), 403);

        $this->update(['buyer_confirmed_at' => now()]);
    }

    /** "Pesan Lagi" is offered once an order can no longer change. */
    public function canBeReordered(): bool
    {
        return in_array($this->status, [OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::RejectedByUser], true);
    }

    /**
     * A receipt (nota) exists only once the order is truly finished: completed,
     * paid, not under payment review, and with no complaint that was raised or
     * upheld (a rejected/closed complaint is fine).
     */
    public function isReceiptReady(): bool
    {
        return $this->status === OrderStatus::Completed
            && $this->payment_status === PaymentStatus::Paid
            && ! $this->needs_payment_review
            && $this->completed_at !== null
            && ! $this->complaints()->whereIn('status', [ComplaintStatus::Diajukan, ComplaintStatus::Diresolusi])->exists();
    }

    /** e.g. NRY-20261004-000332: completion date in the business timezone, then the padded order id. */
    public function receiptNumber(): string
    {
        return 'NRY-'.$this->completed_at->copy()->timezone(config('app.business_timezone'))->format('Ymd')
            .'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

}
