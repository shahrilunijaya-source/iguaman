<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 10 slice 2 — per-branch session config ("penetapan sesi janji temu").
 *
 * Drives SlotGenerator + SlotAvailabilityService:
 *   hari_minggu       — comma list of WEEKEND day-numbers in ISO order (1=Mon … 7=Sun),
 *                       e.g. "6,7" Sat/Sun (default), "5,6" Fri/Sat. NULL = service falls
 *                       back to its Sat/Sun WEEKEND constant.
 *   masa_buka/tutup   — branch operating window; slots generated between them.
 *   tempoh_slot_minit — slot length in minutes (default 30).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cawangan', function (Blueprint $table) {
            $table->string('hari_minggu', 20)->nullable()->after('negeri_id');
            $table->time('masa_buka')->nullable()->after('hari_minggu');
            $table->time('masa_tutup')->nullable()->after('masa_buka');
            $table->unsignedSmallInteger('tempoh_slot_minit')->default(30)->after('masa_tutup');
        });
    }

    public function down(): void
    {
        Schema::table('cawangan', function (Blueprint $table) {
            $table->dropColumn(['hari_minggu', 'masa_buka', 'masa_tutup', 'tempoh_slot_minit']);
        });
    }
};
