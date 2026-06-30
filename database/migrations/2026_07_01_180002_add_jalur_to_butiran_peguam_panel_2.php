<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W10 — derived application track for tier routing. The practice areas are already
 * captured per-row in butiran_peguam_panel_6.category; this denormalises a single
 * jalur (JENAYAH | SIVIL_SYARIAH) so the approval workflow can fork the approver
 * tier (criminal -> Pembelaan Awam; civil/syariah -> Peguam Panel) without scanning
 * the child rows on every gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('butiran_peguam_panel_2', function (Blueprint $table) {
            $table->string('jalur_permohonan', 15)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('butiran_peguam_panel_2', function (Blueprint $table) {
            $table->dropColumn('jalur_permohonan');
        });
    }
};
