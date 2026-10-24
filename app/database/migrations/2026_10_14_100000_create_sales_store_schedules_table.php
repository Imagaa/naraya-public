<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A sales person's weekly store schedule: store X on weekday N, every week. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_store_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO: 1 = Senin … 7 = Minggu
            $table->timestamps();
            $table->unique(['sales_user_id', 'buyer_id', 'weekday']);
            $table->index(['sales_user_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_store_schedules');
    }
};
