<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Outright sales notes (jual putus), one per store per line.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_notes', function (Blueprint $table) {
            $table->id();
            $table->string('line', 16)->index();
            $table->foreignId('sales_id')->constrained('users');
            $table->foreignId('buyer_id')->constrained('users');
            $table->string('payment_method', 16); // App\Enums\NotePaymentMethod
            $table->unsignedBigInteger('total');
            $table->string('proof_path')->nullable();
            $table->string('proof_status', 16)->nullable()->index(); // App\Enums\ProofStatus; null for cash
            $table->foreignId('proof_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('proof_reviewed_at')->nullable();
            $table->string('proof_note')->nullable();
            $table->foreignId('cash_deposit_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('route_stop_id')->nullable()->index();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['sales_id', 'line']);
        });

        Schema::create('sales_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_note_items');
        Schema::dropIfExists('sales_notes');
    }
};
