<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('paid')->after('payment_method');
            $table->string('midtrans_order_id')->nullable()->unique()->after('payment_status');
            $table->string('midtrans_transaction_id')->nullable()->after('midtrans_order_id');
            $table->string('midtrans_payment_type')->nullable()->after('midtrans_transaction_id');
            $table->string('midtrans_raw_status')->nullable()->after('midtrans_payment_type');
            $table->timestamp('payment_expires_at')->nullable()->after('midtrans_raw_status');
            $table->boolean('needs_payment_review')->default(false)->after('payment_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status', 'midtrans_order_id', 'midtrans_transaction_id',
                'midtrans_payment_type', 'midtrans_raw_status', 'payment_expires_at', 'needs_payment_review',
            ]);
        });
    }
};
