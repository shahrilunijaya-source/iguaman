<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live sistemspk.forms drifted ahead of the imported baseline with 4 newer columns
 * (panel-referral justification + record status + advice-request date). Add them so
 * the legacy ETL copies them and the target reaches full forms parity (98 cols).
 * All nullable, matching source; currently unpopulated in source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('justifikasi_rujuk_pp', 255)->nullable()->after('is_duplicate');
            $table->string('justifikasi_lain_rujuk_pp', 255)->nullable()->after('justifikasi_rujuk_pp');
            $table->string('status_rekod', 255)->nullable()->after('justifikasi_lain_rujuk_pp');
            $table->dateTime('tarikh_mohon_khidmat_pp')->nullable()->after('status_rekod');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn([
                'justifikasi_rujuk_pp',
                'justifikasi_lain_rujuk_pp',
                'status_rekod',
                'tarikh_mohon_khidmat_pp',
            ]);
        });
    }
};
