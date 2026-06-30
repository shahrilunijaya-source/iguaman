<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W5 — assign a completed (SELESAI) Khidmat Nasihat to an EXTERNAL panel lawyer,
 * via GRAB (open to a pool, first-come within 7 days) or ASSIGN (officer picks from
 * the W11 workload shortlist). Adds an OWN external-lawyer state machine column
 * (status_agihan_pl) rather than overloading status_kn — mirroring the W12
 * status_kes_terbuka separate-column precedent. status_agihan_pl is written only by
 * AgihanLuarService. Distinct from the legacy forms.status_agihan distribution column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            // Assigned external panel lawyer (peguam_panel.id surrogate). Soft link (indexed,
            // no hard FK) — peguam_panel.id is a legacy signed int, mirroring lejar_tuntutan_bayaran.
            $table->unsignedBigInteger('id_peguam_panel')->nullable()->index()->after('id_pegawai_kn');
            // How the lawyer got the KN: GRAB (self-claimed) | ASSIGN (officer-assigned).
            $table->string('mod_agihan_peguam', 10)->nullable()->after('id_peguam_panel');

            // External-lawyer assignment state: BUKA_GRAB | DIAGIH | LUPUT (null = not in flow).
            $table->string('status_agihan_pl', 20)->nullable()->index()->after('status_kes_terbuka');
            // When the grab pool was opened (the 7-day expiry threshold column).
            $table->dateTime('tarikh_buka_grab')->nullable()->after('status_agihan_pl');
            // When the lawyer was assigned/claimed the KN.
            $table->dateTime('tarikh_agihan_pl')->nullable()->after('tarikh_buka_grab');
        });
    }

    public function down(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->dropColumn([
                'id_peguam_panel',
                'mod_agihan_peguam',
                'status_agihan_pl',
                'tarikh_buka_grab',
                'tarikh_agihan_pl',
            ]);
        });
    }
};
