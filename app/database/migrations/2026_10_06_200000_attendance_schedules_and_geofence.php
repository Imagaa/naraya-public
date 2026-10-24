<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('toleransi_menit')->default(10);
            $table->unsignedSmallInteger('pengingat_menit')->default(15);
            $table->unsignedSmallInteger('max_akurasi_m')->default(100);
            $table->timestamps();
        });

        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO 1 = Senin … 7 = Minggu
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'weekday']);
        });

        Schema::create('attendance_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->string('nama');
            $table->timestamps();
        });

        Schema::create('attendance_locations', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('radius_m')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendance_location_user', function (Blueprint $table) {
            $table->foreignId('attendance_location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['attendance_location_id', 'user_id']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('attendance_location_id')->nullable()->after('longitude')->constrained()->nullOnDelete();
            $table->unsignedInteger('jarak_m')->nullable()->after('attendance_location_id');
            $table->unsignedInteger('akurasi_m')->nullable()->after('jarak_m');
            $table->time('jadwal_jam')->nullable()->after('akurasi_m');
            $table->integer('selisih_menit')->nullable()->after('jadwal_jam');
            $table->string('status')->nullable()->after('selisih_menit');
            $table->json('flags')->nullable()->after('status');
        });

        $now = now();
        DB::table('attendance_settings')->insert(['created_at' => $now, 'updated_at' => $now]);
        foreach (range(1, 7) as $weekday) {
            DB::table('attendance_schedules')->insert([
                'user_id' => null,
                'weekday' => $weekday,
                'jam_masuk' => $weekday === 7 ? null : '08:00',
                'jam_pulang' => $weekday === 7 ? null : '17:00',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_location_id');
            $table->dropColumn(['jarak_m', 'akurasi_m', 'jadwal_jam', 'selisih_menit', 'status', 'flags']);
        });
        Schema::dropIfExists('attendance_location_user');
        Schema::dropIfExists('attendance_locations');
        Schema::dropIfExists('attendance_holidays');
        Schema::dropIfExists('attendance_schedules');
        Schema::dropIfExists('attendance_settings');
    }
};
