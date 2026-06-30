<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W12 — reverse sync forms -> khidmat_nasihat. A KN is already SELESAI before
 * Buka Kes, so the downstream litigation state needs its own column rather than
 * overloading status_kn (which the KN officer machine owns). KesKnSyncService is
 * the single writer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            // TERBUKA (case opened) | SELESAI (lawyer done) | DITUTUP (file closed).
            $table->string('status_kes_terbuka', 20)->nullable()->after('id_forms');
            $table->dateTime('tarikh_kes_dikemaskini')->nullable()->after('status_kes_terbuka');
        });
    }

    public function down(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->dropColumn(['status_kes_terbuka', 'tarikh_kes_dikemaskini']);
        });
    }
};
