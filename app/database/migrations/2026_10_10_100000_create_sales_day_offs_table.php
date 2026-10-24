<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly day off chosen by each sales (one per Monday–Sunday week, asked for
 * from the day before). With it Sunday stops being a fixed day off: the
 * general schedule's Sunday takes Saturday's hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_day_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('note', 300)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 300)->nullable();
            $table->timestamps();
            $table->index(['sales_id', 'date']);
        });

        $saturday = DB::table('attendance_schedules')->whereNull('user_id')->where('weekday', 6)->first();
        if ($saturday?->jam_masuk) {
            DB::table('attendance_schedules')->whereNull('user_id')->where('weekday', 7)->whereNull('jam_masuk')
                ->update(['jam_masuk' => $saturday->jam_masuk, 'jam_pulang' => $saturday->jam_pulang, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_day_offs');
        DB::table('attendance_schedules')->whereNull('user_id')->where('weekday', 7)->update(['jam_masuk' => null, 'jam_pulang' => null]);
    }
};
