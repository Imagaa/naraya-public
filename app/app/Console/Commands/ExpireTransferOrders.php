<?php

namespace App\Console\Commands;

use App\Support\Orders\TransferPayments;
use Illuminate\Console\Command;

class ExpireTransferOrders extends Command
{
    protected $signature = 'orders:expire-transfers';

    protected $description = 'Cancel transfer orders whose 30 minutes ran out without a proof';

    public function handle(TransferPayments $payments): int
    {
        $this->info($payments->expireDue().' pesanan transfer dibatalkan.');

        return self::SUCCESS;
    }
}
