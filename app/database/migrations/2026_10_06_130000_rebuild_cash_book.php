<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Buku Kas rebuild: immutable ledger with kinds, daily summaries,
    // monthly closings and a settings row; plus the timestamps the summaries read.
    public function up(): void
    {
        Schema::table('cash_book_entries', function (Blueprint $table) {
            $table->string('kind')->default('manual')->after('type');
            $table->string('source')->nullable()->after('kind');
            $table->date('rekap_date')->nullable()->after('source'); // the day a summary covers (may differ from `date` when posted late)
            $table->foreignId('corrects_id')->nullable()->after('rekap_date')->constrained('cash_book_entries')->nullOnDelete();
            $table->string('correction_reason', 500)->nullable()->after('corrects_id');
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['kind', 'source', 'rekap_date']);
        });
        DB::table('cash_book_entries')->whereNotNull('reference')->update(['kind' => 'rekap']);

        Schema::create('cash_book_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('cash_book_entries')->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('amount');
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('cash_book_closings', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();
            $table->bigInteger('opening_balance');
            $table->unsignedBigInteger('total_in');
            $table->unsignedBigInteger('total_out');
            $table->bigInteger('closing_balance');
            $table->json('breakdown');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('auto')->default(false);
            $table->timestamp('closed_at');
        });

        Schema::create('cash_book_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('closing_day')->default(5);
            $table->date('posted_through')->nullable(); // last day already summarised
            $table->timestamps();
        });
        // History before today stays as the old per-transaction rows; summaries start tonight.
        DB::table('cash_book_settings')->insert(['closing_day' => 5, 'posted_through' => now()->subDay()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        Schema::table('orders', fn (Blueprint $table) => $table->timestamp('paid_at')->nullable()->after('payment_status'));
        Schema::table('cash_deposits', fn (Blueprint $table) => $table->timestamp('verified_at')->nullable()->after('status'));
    }

    public function down(): void
    {
        Schema::table('cash_deposits', fn (Blueprint $table) => $table->dropColumn('verified_at'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('paid_at'));
        Schema::dropIfExists('cash_book_settings');
        Schema::dropIfExists('cash_book_closings');
        Schema::dropIfExists('cash_book_entry_items');
        Schema::table('cash_book_entries', function (Blueprint $table) {
            $table->dropIndex(['kind', 'source', 'rekap_date']);
            $table->dropConstrainedForeignId('corrects_id');
            $table->dropConstrainedForeignId('edited_by');
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
            $table->dropColumn(['kind', 'source', 'rekap_date', 'correction_reason', 'edited_at']);
        });
    }
};
