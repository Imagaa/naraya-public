<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('wholesale_price')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('min_stock')->nullable();
            $table->unsignedInteger('min_purchase')->nullable();
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
