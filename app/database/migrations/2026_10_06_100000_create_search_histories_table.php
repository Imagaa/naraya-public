<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per buyer per term; updated_at is when they last searched it.
        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('term', 60);
            $table->timestamps();
            $table->unique(['user_id', 'term']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_histories');
    }
};
