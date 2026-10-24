<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Investasi Naraya: packages, the store's wallet ledger, withdrawals. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('monthly_return'); // per package
            $table->unsignedTinyInteger('months');
            $table->unsignedBigInteger('amount');
            $table->string('payment_method', 16); // transfer | qris | wallet | cash_sales
            $table->string('source', 16);          // naraya | toys | sales
            $table->string('proof_path')->nullable();
            $table->string('status', 24)->index(); // menunggu_verifikasi | dibayar | aktif | selesai | ditolak
            $table->string('reject_reason', 300)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->string('partner_ref', 100)->nullable();
            $table->unsignedTinyInteger('months_paid')->default(0);
            $table->date('next_payout_on')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('cash_deposit_id')->nullable()->constrained('cash_deposits')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('investment_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('bank_name', 60);
            $table->string('account_number', 40);
            $table->string('account_name', 100);
            $table->string('status', 16)->index(); // menunggu | ditransfer | ditolak
            $table->string('proof_path')->nullable();
            $table->string('reject_reason', 300)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('investment_wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 24); // payout | principal | purchase | withdrawal | withdrawal_refund
            $table->bigInteger('amount'); // signed
            $table->foreignId('investment_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('investment_withdrawal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 200);
            $table->timestamps();
            $table->index(['buyer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_wallet_entries');
        Schema::dropIfExists('investment_withdrawals');
        Schema::dropIfExists('investment_purchases');
    }
};
