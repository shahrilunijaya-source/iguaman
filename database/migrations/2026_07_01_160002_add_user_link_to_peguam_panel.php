<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W22 / C.2 (D6) — give peguam_panel a clean numeric link to users.
 * Today the lawyer<->user join is the IC string users.id_peguam_panel = peguam_panel.kp_peguam
 * (declared "tentative; confirm at ETL" in User::lawyerProfile()).
 *
 * Backfill is UNIQUE-MATCH ONLY: the dev DB has 115 duplicate id_peguam_panel
 * groups in users (shared/seed ICs) and a collation mismatch between the two
 * columns. We cast collation in the join and only set id_user where exactly one
 * user owns that IC. Ambiguous/duplicate ICs are left NULL pending data cleanup;
 * the FK is nullable + nullOnDelete so that is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('peguam_panel', 'id_user')) {
            Schema::table('peguam_panel', function ($table) {
                $table->unsignedBigInteger('id_user')->nullable()->after('id');
                $table->index('id_user', 'peguam_panel_id_user_idx');
            });
        }

        // Unique-match backfill only (collation-cast join; skip duplicate ICs).
        DB::statement(<<<'SQL'
            UPDATE `peguam_panel` pp
            JOIN (
                SELECT u.`id_peguam_panel` AS ic, MIN(u.`id`) AS uid
                FROM `users` u
                WHERE u.`id_peguam_panel` IS NOT NULL AND u.`id_peguam_panel` <> ''
                GROUP BY u.`id_peguam_panel`
                HAVING COUNT(*) = 1
            ) m ON m.ic = pp.`kp_peguam` COLLATE utf8mb4_unicode_ci
            SET pp.`id_user` = m.uid
            WHERE pp.`id_user` IS NULL
        SQL);

        Schema::table('peguam_panel', function ($table) {
            $table->foreign('id_user', 'peguam_panel_id_user_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('peguam_panel', function ($table) {
            $table->dropForeign('peguam_panel_id_user_fk');
            $table->dropIndex('peguam_panel_id_user_idx');
            $table->dropColumn('id_user');
        });
    }
};
