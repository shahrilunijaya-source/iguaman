<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add nullable id_khidmat column to uploaded_files so citizen portal uploads
 * can be linked to a khidmat_nasihat record. Mirrors the id_kes pattern used
 * for case attachments. Legacy rows keep this NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->unsignedBigInteger('id_khidmat')->nullable()->after('id_kes')->index();
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->dropColumn('id_khidmat');
        });
    }
};
