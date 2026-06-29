<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel-lawyer active/inactive lifecycle (legacy selenggara-peguampanel-detail.php).
 * Deactivation captures a justification (JK Disiplin / Meninggal Dunia / Lain-lain); the
 * deceased path triggers death-redistribution of the lawyer's active cases back to the pool.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peguam_panel', function (Blueprint $table) {
            $table->string('statusAktif', 2)->default('1')->after('kp_peguam'); // 1 = aktif, 0 = tidak aktif
            $table->string('sebabTidakAktif', 255)->nullable()->after('statusAktif');
            $table->date('tarikhTidakAktif')->nullable()->after('sebabTidakAktif');
        });
    }

    public function down(): void
    {
        Schema::table('peguam_panel', function (Blueprint $table) {
            $table->dropColumn(['statusAktif', 'sebabTidakAktif', 'tarikhTidakAktif']);
        });
    }
};
