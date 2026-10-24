<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Every per-line record gets `line` (existing rows are Naraya).
return new class extends Migration
{
    private const TABLES = ['products', 'categories', 'cash_deposits', 'cash_book_entries', 'cash_book_closings'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('line', 16)->default('naraya')->index();
            });
        }

        // Unique per line, not globally: both lines may have a "Lainnya" category and a September closing.
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['name', 'line']);
        });
        Schema::table('cash_book_closings', function (Blueprint $table) {
            $table->dropUnique(['month']);
            $table->unique(['month', 'line']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_book_closings', function (Blueprint $table) {
            $table->dropUnique(['month', 'line']);
            $table->unique('month');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name', 'line']);
            $table->unique('name');
        });
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['line']);
                $table->dropColumn('line');
            });
        }
    }
};
