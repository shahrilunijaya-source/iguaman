<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W22 / C.3 (D7) — forms has no numeric mediator-officer column today (only the
 * varchars lokasi_pegawai_pengantara / nama_pegawai). Add id_pegawai_pengantara
 * as the spine column W19 (mediation assignment) will populate going forward.
 * Historical rows stay NULL (no backfill source). FK -> users.id, nullOnDelete.
 *
 * sql_mode relaxed for the rebuild: the legacy forms table carries '0000-00-00'
 * across several date columns, which strict mode would re-validate on ALTER.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('forms', 'id_pegawai_pengantara')) {
            return;
        }

        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            Schema::table('forms', function ($table) {
                $table->unsignedBigInteger('id_pegawai_pengantara')->nullable();
                $table->index('id_pegawai_pengantara', 'forms_id_pegawai_pengantara_idx');
                $table->foreign('id_pegawai_pengantara', 'forms_pegawai_pengantara_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down(): void
    {
        Schema::table('forms', function ($table) {
            $table->dropForeign('forms_pegawai_pengantara_fk');
            $table->dropIndex('forms_id_pegawai_pengantara_idx');
            $table->dropColumn('id_pegawai_pengantara');
        });
    }
};
