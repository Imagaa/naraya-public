<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('sales_id')->constrained('users');
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('held');
            $table->foreignId('cash_deposit_id')->nullable()->constrained('cash_deposits')->nullOnDelete();
            $table->timestamp('collected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_collections');
    }
};
