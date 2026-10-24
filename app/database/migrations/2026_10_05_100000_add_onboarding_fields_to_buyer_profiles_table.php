<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_profiles', function (Blueprint $table) {
            $table->string('owner_name')->nullable()->after('user_id');
            $table->string('store_photo_path')->nullable()->after('address');
            // Defaults to approved so every existing and staff-created buyer
            // keeps shopping; only self-registration starts as pending.
            $table->string('status', 16)->default('approved')->index()->after('is_active');
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_profiles', function (Blueprint $table) {
            $table->dropColumn(['owner_name', 'store_photo_path', 'status', 'rejection_reason']);
        });
    }
};
