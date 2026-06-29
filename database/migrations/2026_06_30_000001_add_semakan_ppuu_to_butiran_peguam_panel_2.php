<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the PPUU initial-review (semakan) step to the lawyer-application approval
 * chain so it becomes a true 3-tier flow: PPUU semak -> Pengarah sokong -> KP keputusan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('butiran_peguam_panel_2', function (Blueprint $table) {
            $table->string('semakan_ppuu', 2)->nullable()->after('keteranganKes');
            $table->string('ulasan_semakan_ppuu', 600)->nullable()->after('semakan_ppuu');
            $table->dateTime('tarikh_semakan_ppuu')->nullable()->after('ulasan_semakan_ppuu');
        });
    }

    public function down(): void
    {
        Schema::table('butiran_peguam_panel_2', function (Blueprint $table) {
            $table->dropColumn(['semakan_ppuu', 'ulasan_semakan_ppuu', 'tarikh_semakan_ppuu']);
        });
    }
};
