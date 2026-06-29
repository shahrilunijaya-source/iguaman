<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds keys the legacy schema lacked:
 *  - primary key on peguam_panel (legacy had none → Eloquent needs it)
 *  - foreign keys for case-history tables (id_kes int -> forms.id)
 *  - indexes on hot lookup columns
 * sejarah_pegawai already ships its FK in the baseline dump.
 */
return new class extends Migration
{
    public function up(): void
    {
        // peguam_panel had no PK. Add a surrogate id.
        if (! Schema::hasColumn('peguam_panel', 'id')) {
            DB::statement('ALTER TABLE `peguam_panel` ADD `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
        }

        // Case-history FKs (int id_kes -> forms.id). sejarah_pegawai already has its FK.
        Schema::table('sejarah_peguam_panel', function ($table) {
            $table->index('id_kes', 'spp_id_kes_idx');
            $table->foreign('id_kes', 'spp_id_kes_fk')->references('id')->on('forms')->nullOnDelete();
        });

        Schema::table('sejarah_sidang', function ($table) {
            $table->index('id_kes', 'ss_id_kes_idx');
            // id_kes is NOT NULL here; restrict delete.
            $table->foreign('id_kes', 'ss_id_kes_fk')->references('id')->on('forms')->restrictOnDelete();
        });

        // Hot lookups used across reports / queues.
        Schema::table('forms', function ($table) {
            $table->index('no_fail', 'forms_no_fail_idx');
            $table->index('nokp', 'forms_nokp_idx');
            $table->index('cawangan', 'forms_cawangan_idx');
            $table->index('status', 'forms_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function ($table) {
            $table->dropIndex('forms_no_fail_idx');
            $table->dropIndex('forms_nokp_idx');
            $table->dropIndex('forms_cawangan_idx');
            $table->dropIndex('forms_status_idx');
        });

        Schema::table('sejarah_sidang', function ($table) {
            $table->dropForeign('ss_id_kes_fk');
            $table->dropIndex('ss_id_kes_idx');
        });

        Schema::table('sejarah_peguam_panel', function ($table) {
            $table->dropForeign('spp_id_kes_fk');
            $table->dropIndex('spp_id_kes_idx');
        });

        if (Schema::hasColumn('peguam_panel', 'id')) {
            DB::statement('ALTER TABLE `peguam_panel` DROP PRIMARY KEY, DROP COLUMN `id`');
        }
    }
};
