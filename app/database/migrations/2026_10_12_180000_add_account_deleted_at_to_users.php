<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sales account the admin deleted (App\Support\Sales\SalesAccounts::delete): the person can't log in and is gone
 * from every pick list, but the row stays so their notas, absensi, stock and visits keep their name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('account_deleted_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('account_deleted_at'));
    }
};
