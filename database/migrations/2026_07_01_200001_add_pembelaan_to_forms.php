<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W9 — Pembelaan Awam (public criminal defence) rides the existing `forms` litigation
 * spine (decision D3: reuse forms + 3-tier AgihanService, no separate table). These rows
 * are discriminated by the `is_pembelaan_awam` tag so civil lists/KPI can filter them out
 * while assignment/closure flow through the shared spine. Criminal-specific intake fields
 * (charge no., offence section, court, charge date) are added here; accused identity reuses
 * the existing `nama`/`nokp` columns.
 *
 * sql_mode relaxed for the rebuild: legacy `forms` rows carry '0000-00-00' across several
 * date columns, which strict mode would re-validate on ALTER (see migration 160003).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('forms', 'is_pembelaan_awam')) {
            return;
        }

        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            Schema::table('forms', function ($table) {
                $table->boolean('is_pembelaan_awam')->default(false)->index('forms_is_pembelaan_awam_idx');
                $table->string('jenis_pemohonan_pembelaan', 80)->nullable();
                $table->string('no_pertuduhan', 100)->nullable();
                $table->string('seksyen_kesalahan', 150)->nullable();
                $table->string('mahkamah_pembelaan', 150)->nullable();
                $table->date('tarikh_pertuduhan')->nullable();
                // Urgent (segera) flag — set at intake, gates the W14 interim certificate.
                $table->boolean('is_segera')->default(false);
            });
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down(): void
    {
        Schema::table('forms', function ($table) {
            $table->dropIndex('forms_is_pembelaan_awam_idx');
            $table->dropColumn([
                'is_pembelaan_awam',
                'jenis_pemohonan_pembelaan',
                'no_pertuduhan',
                'seksyen_kesalahan',
                'mahkamah_pembelaan',
                'tarikh_pertuduhan',
                'is_segera',
            ]);
        });
    }
};
