<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('parent_order_id')->nullable()->after('sales_id')->constrained('orders')->nullOnDelete();
            $table->string('rejection_reason')->nullable()->after('notes');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('delivered_quantity')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_order_id');
            $table->dropColumn('rejection_reason');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('delivered_quantity');
        });
    }
};
