<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BNI transfer proof on marketplace orders. Additive.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_proof_path')->nullable()->after('payment_expires_at');
            $table->timestamp('payment_proof_uploaded_at')->nullable()->after('payment_proof_path');
            $table->timestamp('payment_reviewed_at')->nullable()->after('payment_proof_uploaded_at');
            $table->foreignId('payment_reviewed_by')->nullable()->after('payment_reviewed_at')->constrained('users')->nullOnDelete();
            $table->string('payment_reject_reason')->nullable()->after('payment_reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_reviewed_by');
            $table->dropColumn(['payment_proof_path', 'payment_proof_uploaded_at', 'payment_reviewed_at', 'payment_reject_reason']);
        });
    }
};
