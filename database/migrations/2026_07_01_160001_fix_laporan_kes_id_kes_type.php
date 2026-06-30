<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W22 / C.1 — laporan_kes.id_kes is legacy varchar(20) while forms.id is int.
 * PeguamController/MahkamahController::storeLaporan wrote `(string) $kes->id` as a
 * workaround and LaporanKes::form() relied on MySQL string<->int coercion.
 * Convert to INT + add the FK so the relation is type-safe. The column already has
 * the index `idx_lk_id_kes` (from the hot-path-indexes migration) which the FK reuses.
 *
 * sql_mode is relaxed for the rebuild: strict mode re-validates EVERY column and legacy
 * rows carried '0000-00-00' in tarikh_sebutan. Those zero-dates are nulled here too.
 * Pre-checked on the dev DB: 0 non-numeric and 0 orphan id_kes rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('laporan_kes', 'id_kes')) {
            return;
        }

        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            DB::statement("UPDATE `laporan_kes` SET `tarikh_sebutan` = NULL WHERE `tarikh_sebutan` = '0000-00-00'");
            DB::statement("UPDATE `laporan_kes` SET `id_kes` = NULL WHERE `id_kes` IS NOT NULL AND `id_kes` NOT REGEXP '^[0-9]+$'");
            DB::statement('UPDATE `laporan_kes` lk LEFT JOIN `forms` f ON lk.`id_kes` = f.`id` SET lk.`id_kes` = NULL WHERE lk.`id_kes` IS NOT NULL AND f.`id` IS NULL');

            DB::statement('ALTER TABLE `laporan_kes` MODIFY `id_kes` INT NULL');

            $hasFk = DB::selectOne(
                "SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE
                 WHERE table_schema = DATABASE() AND table_name = 'laporan_kes'
                   AND constraint_name = 'laporan_kes_id_kes_fk'"
            )->c;

            if (! $hasFk) {
                // Reuses the existing idx_lk_id_kes index; nullOnDelete mirrors 000002.
                Schema::table('laporan_kes', function ($table) {
                    $table->foreign('id_kes', 'laporan_kes_id_kes_fk')->references('id')->on('forms')->nullOnDelete();
                });
            }
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down(): void
    {
        Schema::table('laporan_kes', function ($table) {
            $table->dropForeign('laporan_kes_id_kes_fk');
        });

        DB::statement('ALTER TABLE `laporan_kes` MODIFY `id_kes` VARCHAR(20) NULL');
    }
};
