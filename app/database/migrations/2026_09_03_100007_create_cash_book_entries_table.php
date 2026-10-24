<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_book_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // App\Enums\CashBookEntryType
            $table->unsignedInteger('amount');
            $table->date('date');
            $table->text('catatan')->nullable();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('foto_path')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_book_entries');
    }
};
