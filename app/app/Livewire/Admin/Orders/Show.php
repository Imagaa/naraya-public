<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\CancelReason;
use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\OrderNotCancellableException;
use App\Models\Order;
use App\Services\OrderCanceller;
use App\Services\SalesAssigner;
use App\Support\Orders\TransferPayments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Livewire\Admin\Concerns\HasAdminShell;

class Show extends Component
{
    use WithFileUploads, HasAdminShell;

    public Order $order;

    public ?int $salesUserId = null;

    public string $rejectReason = '';


    public $deliveryProof;

    public string $cancelReason = '';

    public string $cancelNote = '';

    public function mount(Order $order): void
    {
        $this->order = $order->load('items.product', 'buyer.buyerProfile', 'salesUser', 'gudangHandoverBy', 'cancelledBy');
        $this->salesUserId = $order->sales_id;
    }

    public function assignSales()
    {
        $this->validate(['salesUserId' => 'required|exists:users,id']);

        $this->order->update(['sales_id' => $this->salesUserId, 'sales_assigned_at' => now()]);
        $this->order->refresh();
    }

    public function confirm()
    {
        abort_unless($this->order->status === OrderStatus::New, 403);

        if ($this->order->payment_method !== 'cash' && $this->order->payment_status !== PaymentStatus::Paid) {
            $this->addError('stock', 'Pembayaran belum berhasil — order belum bisa diproses.');

            return;
        }

        // Estimate is set at confirmation, not at dispatch — the buyer should
        // know roughly when to expect their order as soon as it's confirmed,
        // not only once it's already out for delivery. markShipped() can
        // still adjust it closer to the actual dispatch date.
        // The delivery estimate is set by the system at checkout (DeliveryEstimate), not typed here.
        $this->validate(['salesUserId' => 'nullable|exists:users,id']);

        try {
            DB::transaction(function () {
                foreach ($this->order->items as $item) {
                    $item->product->adjustStock(
                        -$item->quantity * $item->product->ppu(), // the order is in units, stock in pcs
                        StockMovementType::Out,
                        auth()->user(),
                        "Order #{$this->order->id}",
                    );
                }

                $data = ['status' => OrderStatus::Processing];

                // Respect a manual assignment made via assignSales() before
                // confirming — auto-assign only fills in what's still empty.
                // A sales picked in the next-step box (or assigned earlier) wins; auto-assign only fills a gap.
                if ($this->order->sales_id === null && $this->salesUserId) {
                    $data['sales_id'] = $this->salesUserId;
                    $data['sales_assigned_at'] = now();
                } elseif ($this->order->sales_id === null) {
                    $sales = app(SalesAssigner::class)->assign($this->order);

                    if ($sales) {
                        $data['sales_id'] = $sales->id;
                        $data['sales_assigned_at'] = now();
                    }
                }

                $this->order->update($data);
            });
        } catch (InsufficientStockException $e) {
            $this->addError('stock', $e->getMessage());

            return;
        }

        $this->order->refresh();
    }

    public function acknowledgePaymentReview(): void
    {
        abort_unless($this->order->needs_payment_review, 403);

        $this->order->update(['needs_payment_review' => false]);
        $this->order->refresh();
    }

    public function resolveRejection(string $condition)
    {
        abort_unless($this->order->status === OrderStatus::RejectedByUser, 403);
        abort_unless(in_array($condition, ['good', 'damaged'], true), 422);

        $complaint = $this->order->complaints()
            ->where('tipe', ComplaintType::TolakSaatTerima)
            ->where('status', ComplaintStatus::Diajukan)
            ->latest()
            ->firstOrFail();

        DB::transaction(function () use ($condition, $complaint) {
            foreach ($this->order->items as $item) {
                if ($condition === 'good') {
                    $item->product->adjustStock(
                        $item->quantity * $item->product->ppu(),
                        StockMovementType::Correction,
                        auth()->user(),
                        "Retur Order #{$this->order->id} — kondisi baik",
                    );
                } else {
                    $item->product->adjustStock(
                        0,
                        StockMovementType::WriteOff,
                        auth()->user(),
                        "Retur Order #{$this->order->id} — kondisi rusak",
                    );
                }
            }

            $this->order->update(['status' => OrderStatus::Cancelled]);

            $complaint->update([
                'status' => ComplaintStatus::Diresolusi,
                'resolusi' => $condition === 'good' ? 'restock' : 'writeoff',
                'diproses_oleh' => auth()->id(),
            ]);
        });

        $this->order->refresh();
    }

    public function verifyTransfer(TransferPayments $payments): void
    {
        $payments->verify($this->order, auth()->user());
        $this->order->refresh();
    }

    public function rejectTransfer(TransferPayments $payments): void
    {
        $this->validate(['rejectReason' => 'required|string|max:200'], ['rejectReason.required' => 'Tuliskan alasan penolakan.']);
        $payments->reject($this->order, auth()->user(), $this->rejectReason);
        $this->reset('rejectReason');
        $this->order->refresh();
    }

    /** The admin hands the goods to the sales person (this was the gudang portal's step until 2026-10-23). */
    public function handOverToSales(): void
    {
        abort_unless($this->order->status === OrderStatus::Processing && $this->order->sales_id !== null && $this->order->gudang_handover_at === null, 403);

        $this->order->update(['gudang_handover_at' => now(), 'gudang_handover_by' => auth()->id()]);
        $this->order->refresh();
    }

    public function markShipped()
    {
        abort_unless($this->order->status === OrderStatus::Processing, 403);
        // Sales-assisted orders can't be marked shipped until Sales has
        // confirmed receiving the goods from Gudang; orders with no Sales
        // assigned skip the handover chain entirely (Admin manages them directly).
        abort_unless($this->order->sales_id === null || $this->order->sales_received_at !== null, 403);

        $this->order->update(['status' => OrderStatus::Shipped]);
        $this->order->refresh();
    }

    /**
     * Terminal step for orders with no Sales assigned — Admin manages the
     * whole delivery themselves (e.g. buyer pickup), so there's no Sales/
     * buyer confirmation chain to wait on; Admin's own photo is the proof.
     */
    public function markCompleted()
    {
        abort_unless($this->order->status === OrderStatus::Shipped, 403);
        abort_unless($this->order->sales_id === null, 403);

        $this->validate(
            ['deliveryProof' => 'required|image|max:5120'],
            ['deliveryProof.required' => 'Foto bukti pengiriman wajib diunggah.']
        );

        $path = $this->deliveryProof->store('order-proofs', 's3');

        $this->order->update([
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
            'delivery_proof_path' => $path,
        ]);

        $this->order->refresh();
    }

    /**
     * Terminal step for Sales-assisted orders — Admin reviews Sales's
     * delivery photo and the buyer's confirmation, then closes the order.
     */
    public function closeOrder()
    {
        abort_unless($this->order->awaitingAdminClose(), 403);

        $this->order->update([
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
        ]);
        $this->order->refresh();
    }

    public function cancel(OrderCanceller $canceller): void
    {
        $this->validate(
            ['cancelReason' => ['required', Rule::enum(CancelReason::class)]],
            ['cancelReason.required' => 'Pilih alasan pembatalan.'],
        );

        try {
            $canceller->cancel($this->order, CancelReason::from($this->cancelReason), $this->cancelNote, auth()->user());
        } catch (OrderNotCancellableException $e) {
            $this->addError('cancel', $e->getMessage());

            return;
        }

        $this->reset('cancelReason', 'cancelNote');
        $this->order->refresh();
        $this->dispatch('order-cancelled');
    }

    /** Every step of this order's flow, done or pending. */
    private function timeline(): array
    {
        $o = $this->order;
        $photo = fn (?string $path) => $path ? Storage::disk('s3')->url($path) : null;
        $steps = [
            ['label' => 'Dipesan', 'done' => true, 'time' => $o->created_at, 'by' => $o->buyer->name],
            ['label' => 'Dikonfirmasi admin', 'done' => in_array($o->status, [OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed, OrderStatus::RejectedByUser], true)
                || ($o->status === OrderStatus::Cancelled && $o->estimated_delivery_at !== null)], // no confirmed_at; the estimate is set at confirmation
        ];

        if ($o->sales_id) {
            array_push($steps,
                ['label' => 'Diserahkan ke sales', 'done' => (bool) $o->gudang_handover_at, 'time' => $o->gudang_handover_at, 'by' => $o->gudangHandoverBy?->name],
                ['label' => 'Sales terima barang', 'done' => (bool) $o->sales_received_at, 'time' => $o->sales_received_at, 'by' => $o->sales_received_at ? $o->salesUser?->name : null, 'photo' => $photo($o->sales_received_photo_path)],
                ['label' => 'Diantar ke toko', 'done' => (bool) $o->sales_delivered_at, 'time' => $o->sales_delivered_at, 'photo' => $photo($o->sales_delivered_photo_path)],
                ['label' => 'Dikonfirmasi toko', 'done' => (bool) $o->buyer_confirmed_at, 'time' => $o->buyer_confirmed_at],
            );
        } else {
            $steps[] = ['label' => 'Dikirim (diantar admin)', 'done' => in_array($o->status, [OrderStatus::Shipped, OrderStatus::Completed], true)];
        }

        $steps[] = ['label' => 'Selesai', 'done' => (bool) $o->completed_at, 'time' => $o->completed_at, 'photo' => $photo($o->delivery_proof_path)];

        // A stopped order shows how far it got, then why it stopped.
        if ($o->status === OrderStatus::Cancelled || $o->status === OrderStatus::RejectedByUser) {
            $steps = array_values(array_filter($steps, fn ($s) => $s['done']));
            $steps[] = $o->status === OrderStatus::Cancelled
                ? ['label' => 'Dibatalkan', 'done' => true, 'time' => $o->cancelled_at, 'by' => $o->cancelledBy?->name, 'note' => trim(($o->cancel_reason?->label() ?? '').($o->cancel_note ? ' — '.$o->cancel_note : '')) ?: null]
                : ['label' => 'Ditolak toko saat diterima', 'done' => true];
        }

        return $steps;
    }

    /** The datetime-local input is WIB; timestamps are stored in UTC. */

    public function markRefunded(): void
    {
        abort_unless($this->order->refund_status === 'pending', 403);

        $this->order->update(['refund_status' => 'done', 'refunded_at' => now(), 'refunded_by' => auth()->id()]);
        $this->order->refresh();
    }

    public function render()
    {
        $o = $this->order;

        return view('livewire.admin.orders.show', [
            'salesUsers' => \App\Models\User::currentSales()->orderBy('name')->get(),
            'complaint' => $o->complaints()
                ->where('tipe', ComplaintType::TolakSaatTerima)
                ->where('status', ComplaintStatus::Diajukan)
                ->latest()
                ->first(),
            'timeline' => $this->timeline(),
            'canCancel' => OrderCanceller::canCancel($o),
            'cancelEffects' => array_values(array_filter([
                $o->status === OrderStatus::Processing ? 'Stok '.$o->items->count().' barang dikembalikan ke gudang.' : null,
                $o->credit_applied > 0 ? 'Kredit Rp '.number_format($o->credit_applied, 0, ',', '.').' dikembalikan ke saldo toko.' : null,
                $o->payment_method !== 'cash' && $o->payment_status === PaymentStatus::Paid ? 'Pembayaran '.strtoupper($o->payment_method).' sudah masuk — perlu refund manual.' : null,
            ])),
        ])->layout('layouts.admin');
    }
}
