<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('gudang_handover_at')->nullable()->after('sales_id');
            $table->foreignId('gudang_handover_by')->nullable()->after('gudang_handover_at')->constrained('users')->nullOnDelete();

            $table->timestamp('sales_received_at')->nullable()->after('gudang_handover_by');
            $table->string('sales_received_photo_path')->nullable()->after('sales_received_at');

            $table->timestamp('sales_delivered_at')->nullable()->after('delivery_proof_path');
            $table->string('sales_delivered_photo_path')->nullable()->after('sales_delivered_at');

            $table->timestamp('buyer_confirmed_at')->nullable()->after('sales_delivered_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['buyer_confirmed_at', 'sales_delivered_at', 'sales_delivered_photo_path']);
            $table->dropColumn(['sales_received_at', 'sales_received_photo_path']);
            $table->dropConstrainedForeignId('gudang_handover_by');
            $table->dropColumn('gudang_handover_at');
        });
    }
};
