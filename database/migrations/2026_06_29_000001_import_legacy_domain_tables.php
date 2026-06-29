<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Imports the 20 legacy domain tables from sistemspk verbatim (names + columns preserved).
 * Source dump: database/schema/legacy-domain.sql (mysqldump --no-data, user tables excluded).
 * Auth tables are NOT here — they are unified into `users` by the create_users_table migration.
 */
return new class extends Migration
{
    private array $tables = [
        'audit_trail', 'butiran_oyd', 'butiran_peguam_panel', 'butiran_peguam_panel_2',
        'forms', 'items', 'laporan_kes', 'mahkamah_sivil', 'mahkamah_syariah',
        'pegawai_jbg', 'peguam_panel', 'posters', 'ref_cuti', 'ref_kes',
        'ref_lokasi_berguam', 'ref_negeri', 'sejarah_pegawai', 'sejarah_peguam_panel',
        'sejarah_sidang', 'uploaded_files',
    ];

    public function up(): void
    {
        $sql = file_get_contents(database_path('schema/legacy-domain.sql'));

        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('database/schema/legacy-domain.sql missing or empty.');
        }

        DB::unprepared($sql);
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }
};
