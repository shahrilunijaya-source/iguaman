<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lawyer registration/profile uploads (18 PDF doc types) are stored in uploaded_files.
 * Legacy keyed them only via the file_name string ({kpBaru}_{docType}.pdf). Add explicit
 * nullable kpBaru + doc_type columns so a lawyer's documents are queryable without parsing
 * filenames. Case attachments keep these NULL and use id_kes instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->string('kpBaru', 20)->nullable()->after('nama')->index();
            $table->string('doc_type', 60)->nullable()->after('kpBaru')->index();
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->dropColumn(['kpBaru', 'doc_type']);
        });
    }
};
