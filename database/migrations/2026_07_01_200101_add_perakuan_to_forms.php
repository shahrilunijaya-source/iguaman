<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W14 — interim → muktamad legal-aid certificate (Perakuan Bantuan Guaman) state on the
 * Pembelaan Awam case. A SEGERA (urgent) criminal case may be issued an INTERIM certificate
 * immediately, later finalised to MUKTAMAD once the application is fully approved.
 *
 * Distinct from the existing `tarikh_perakuan` column (set on Peringkat-2 approval) — these
 * track the certificate lifecycle, not the application-approval date.
 *
 * sql_mode relaxed for the rebuild (legacy '0000-00-00' dates on forms — see migration 160003).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('forms', 'status_perakuan')) {
            return;
        }

        $mode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = ''");

        try {
            Schema::table('forms', function ($table) {
                $table->string('status_perakuan', 10)->nullable(); // null | INTERIM | MUKTAMAD
                $table->string('no_perakuan', 50)->nullable();
                $table->date('tarikh_perakuan_interim')->nullable();
                $table->date('tarikh_perakuan_muktamad')->nullable();
            });
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
        }
    }

    public function down(): void
    {
        Schema::table('forms', function ($table) {
            $table->dropColumn([
                'status_perakuan',
                'no_perakuan',
                'tarikh_perakuan_interim',
                'tarikh_perakuan_muktamad',
            ]);
        });
    }
};
