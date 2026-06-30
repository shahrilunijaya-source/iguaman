<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data-review HIGH — add indexes on the hot query paths the consolidation surfaced.
 * Legacy-imported tables shipped with almost no indexes; the agihan queues filter
 * forms.status_agihan constantly, the KN worklist filters by branch/officer/status,
 * and the appointment + court-report joins run unindexed.
 *
 * Defensive + idempotent: each index is skipped if the column is absent or the index
 * already exists, so this is safe on partially-indexed legacy schemas.
 */
return new class extends Migration
{
    /** [indexName => [table, [columns...]]] */
    private const INDEXES = [
        'idx_forms_status_agihan' => ['forms', ['status_agihan']],
        'idx_forms_agih_kepada' => ['forms', ['agih_kepada']],
        'idx_lk_id_kes' => ['laporan_kes', ['id_kes']],
        'idx_kn_pengguna' => ['khidmat_nasihat', ['id_pengguna']],
        'idx_kn_pegawai' => ['khidmat_nasihat', ['id_pegawai_kn']],
        'idx_kn_cawangan' => ['khidmat_nasihat', ['cawangan_id']],
        'idx_kn_temu' => ['khidmat_nasihat', ['id_temu_janji']],
        'idx_kn_status' => ['khidmat_nasihat', ['status_kn']],
        'idx_tj_kn' => ['temu_janji', ['id_khidmat_nasihat']],
        'idx_tj_status' => ['temu_janji', ['status']],
        'idx_pp_kp' => ['peguam_panel', ['kp_peguam']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => [$table, $cols]) {
            if (! $this->columnsExist($table, $cols)) {
                continue;
            }
            try {
                Schema::table($table, fn (Blueprint $t) => $t->index($cols, $name));
            } catch (Throwable $e) {
                // Index already present on this (legacy) schema — leave it.
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $name => [$table, $cols]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            } catch (Throwable $e) {
                // Not present — nothing to drop.
            }
        }
    }

    private function columnsExist(string $table, array $cols): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }
        foreach ($cols as $c) {
            if (! Schema::hasColumn($table, $c)) {
                return false;
            }
        }

        return true;
    }
};
