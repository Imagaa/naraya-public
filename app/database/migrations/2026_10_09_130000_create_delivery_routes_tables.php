<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sales delivery routes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 16)->default('active')->index(); // active | finished
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('status', 16)->default('pending'); // pending | visited | closed
            $table->timestamp('visited_at')->nullable();
            $table->string('closed_photo_path')->nullable();
            $table->string('closed_note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_route_stops');
        Schema::dropIfExists('delivery_routes');
    }
};
