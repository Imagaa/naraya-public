<?php

namespace App\Observers;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Notifications\AdminNotice;
use App\Notifications\BuyerNotice;

class ComplaintObserver
{
    public function created(Complaint $complaint): void
    {
        AdminNotice::toAdmins('Komplain baru', "Pesanan #{$complaint->order_id}: {$complaint->tipe->label()}", route('admin.complaints.show', $complaint, absolute: false), 'message-circle-warning', 'warning', urgent: true);
    }

    public function updated(Complaint $complaint): void
    {
        $buyer = $complaint->order?->buyer;

        if (! $complaint->wasChanged('status') || BuyerNotice::skipFor($buyer)) {
            return;
        }

        $id = $complaint->order_id;

        [$title, $body, $tone] = match ($complaint->status) {
            ComplaintStatus::Diresolusi => ['Komplain diselesaikan', "Komplain pesanan #{$id} diselesaikan", 'success'],
            ComplaintStatus::Ditutup => ['Komplain ditutup', "Komplain pesanan #{$id} ditutup", 'info'],
            default => [null, null, null],
        };

        if ($title) {
            $buyer->notify(new BuyerNotice($title, $body, route('orders.show', $id, absolute: false), 'message-circle', $tone));
        }
    }
}
