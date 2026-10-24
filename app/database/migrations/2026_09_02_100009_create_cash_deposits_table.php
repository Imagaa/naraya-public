<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('users');
            $table->unsignedBigInteger('expected_amount');
            $table->unsignedBigInteger('claimed_amount');
            $table->string('status')->default('submitted');
            $table->string('proof_note')->nullable();
            $table->string('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_deposits');
    }
};
