<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 mediation (W17/W18/W19) — forms columns.
 *
 *  - no_pengantaraan (unique): the mediation's OWN file number (W17), generated as
 *    PGT.{STATE}({jenis_kes}){seq}/{MMYYYY} — never reuses the litigation no_fail.
 *  - sumber_pengantaraan (TERUS|LITIGASI): how the mediation was opened (W18/D11) —
 *    a standalone intake (TERUS) vs spun out of an existing case (LITIGASI).
 *  - tarikh_agih_pengantara + nama_pegawai_pengantara: mediator assignment (W19),
 *    paired with the numeric id_pegawai_pengantara FK added by W22 (160003).
 *
 * sql_mode relaxed for the rebuild: legacy forms carries '0000-00-00' across several
 * date columns that strict mode would re-validate on ALTER (mirrors 160003).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('forms', 'no_pengantaraan')) {
            return;
        }

        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            Schema::table('forms', function ($table) {
                $table->string('no_pengantaraan', 50)->nullable()->unique();
                $table->enum('sumber_pengantaraan', ['TERUS', 'LITIGASI'])->nullable()->index();
                $table->date('tarikh_agih_pengantara')->nullable();
                $table->string('nama_pegawai_pengantara')->nullable();
            });
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down(): void
    {
        Schema::table('forms', function ($table) {
            $table->dropUnique(['no_pengantaraan']);
            $table->dropColumn(['no_pengantaraan', 'sumber_pengantaraan', 'tarikh_agih_pengantara', 'nama_pegawai_pengantara']);
        });
    }
};
