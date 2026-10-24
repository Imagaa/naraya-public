<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A sales user's WhatsApp change waits here until Admin approves it; the live number stays in `phone`.
    public function up(): void
    {
        Schema::table('sales_profiles', function (Blueprint $table) {
            $table->string('pending_phone')->nullable()->after('phone');
            $table->timestamp('pending_phone_requested_at')->nullable()->after('pending_phone');
        });
    }

    public function down(): void
    {
        Schema::table('sales_profiles', function (Blueprint $table) {
            $table->dropColumn(['pending_phone', 'pending_phone_requested_at']);
        });
    }
};
