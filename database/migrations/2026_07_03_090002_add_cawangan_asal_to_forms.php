<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W7 (D2) — origin-branch retention column on the legacy `forms` spine.
 *
 * Set to the originating branch NAME when a case is transferred; CawanganScope
 * OR's it so the origin keeps the case in its worklists after the label moves to
 * the destination. NULL for every non-transferred case (so the scope OR is a
 * no-op for existing rows — branch isolation is unchanged for untouched data).
 *
 * `forms` holds legacy '0000-00-00' dates that strict sql_mode re-validates on
 * ALTER, so the mode is relaxed for the ALTER and restored in finally.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('forms', 'cawangan_asal')) {
            return;
        }

        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            Schema::table('forms', function ($table) {
                $table->string('cawangan_asal')->nullable()->after('cawangan');
                $table->index('cawangan_asal', 'forms_cawangan_asal_idx');
            });
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('forms', 'cawangan_asal')) {
            return;
        }

        Schema::table('forms', function ($table) {
            $table->dropIndex('forms_cawangan_asal_idx');
            $table->dropColumn('cawangan_asal');
        });
    }
};
