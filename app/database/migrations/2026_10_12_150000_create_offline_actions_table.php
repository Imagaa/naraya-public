<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Field work recorded on a phone with no signal and synced later. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_actions', function (Blueprint $table) {
            $table->uuid()->primary(); // made on the phone: an item sent twice is applied once
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16); // awal | titip | nota | tutup | toko
            $table->timestamp('recorded_at'); // on the phone
            $table->timestamp('created_at')->nullable(); // synced
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_actions');
    }
};
