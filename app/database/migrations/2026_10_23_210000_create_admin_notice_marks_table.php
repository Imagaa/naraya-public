<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Owner checks tell each subject once: a key per subject that was told.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notice_marks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->string('value')->nullable();
            $table->timestamp('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notice_marks');
    }
};
