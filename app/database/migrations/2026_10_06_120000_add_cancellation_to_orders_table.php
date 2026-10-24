<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Admin cancel: why, when and by whom, plus the manual-refund flag for paid online orders.
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancel_reason')->nullable()->after('status');
            $table->text('cancel_note')->nullable()->after('cancel_reason');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_note');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('refund_status')->nullable()->after('cancelled_by');
            $table->timestamp('refunded_at')->nullable()->after('refund_status');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn(['cancel_reason', 'cancel_note', 'cancelled_at', 'refund_status', 'refunded_at']);
        });
    }
};
