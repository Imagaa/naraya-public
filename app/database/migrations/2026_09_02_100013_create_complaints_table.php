<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('tipe');
            $table->text('alasan');
            $table->string('foto_path')->nullable();
            $table->string('status')->default('diajukan');
            $table->string('resolusi')->nullable();
            $table->unsignedInteger('nominal_penyesuaian')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
