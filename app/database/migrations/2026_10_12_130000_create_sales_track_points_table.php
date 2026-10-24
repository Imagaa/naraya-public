<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** GPS points a sales person's phone records between check-in and check-out. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_track_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete(); // the check-in this shift started with
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('accuracy'); // metres
            $table->timestamp('recorded_at'); // on the phone; points recorded offline arrive later
            $table->timestamp('created_at')->nullable();
            $table->unique(['attendance_id', 'recorded_at']); // a point sent twice is stored once
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_track_points');
    }
};
