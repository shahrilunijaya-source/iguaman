<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * PERF-04 — the SLA (SlaMatrix) and KPI (KpiController) dashboards, plus the
 * statistik groupCount aggregates, all filter on `kategori_kes` (whereIn) and
 * group by `cawangan` / `kategori_kes`. A composite (kategori_kes, cawangan)
 * index serves both the whereIn selectivity and the grouping prefix.
 *
 * Date-pair indexes on the tarikh_* columns were considered but skipped: the
 * SLA/KPI queries wrap the period filter in whereYear()/whereMonth() and compute
 * DATEDIFF() in SELECT — both non-sargable, so a date-range index would not be
 * used. Short-TTL caching (added alongside this migration) removes the repeated
 * recompute those scans caused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function ($table) {
            $table->index(['kategori_kes', 'cawangan'], 'forms_kategori_cawangan_idx');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function ($table) {
            $table->dropIndex('forms_kategori_cawangan_idx');
        });
    }
};
