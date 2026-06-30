<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W22 / C.4 (D8) — forms references case type by the composite string pair
 * (jenis_kes, kategori_kes); ref_kes has the same pair plus its own id, but the
 * kategori_kes widths differ (forms 20 vs ref_kes 100) so a true composite FK is
 * fragile. This slice adds matching composite indexes only (no numeric column,
 * no FK) to speed the join; the numeric id_ref_kes decompose is deferred.
 *
 * sql_mode relaxed for the forms rebuild (legacy '0000-00-00' date columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            if (Schema::hasColumn('forms', 'jenis_kes') && Schema::hasColumn('forms', 'kategori_kes')) {
                Schema::table('forms', function ($table) {
                    $table->index(['jenis_kes', 'kategori_kes'], 'forms_jenis_kategori_idx');
                });
            }

            if (Schema::hasColumn('ref_kes', 'jenis_kes') && Schema::hasColumn('ref_kes', 'kategori_kes')) {
                Schema::table('ref_kes', function ($table) {
                    $table->index(['jenis_kes', 'kategori_kes'], 'ref_kes_jenis_kategori_idx');
                });
            }
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down(): void
    {
        Schema::table('forms', function ($table) {
            $table->dropIndex('forms_jenis_kategori_idx');
        });

        Schema::table('ref_kes', function ($table) {
            $table->dropIndex('ref_kes_jenis_kategori_idx');
        });
    }
};
