<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy uploaded_files is a flat list with no case link. Add a nullable id_kes
 * so attachments can be associated to a case (forms.id) — an improvement over
 * both source systems. Legacy rows keep id_kes NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->unsignedInteger('id_kes')->nullable()->after('file_type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_files', function (Blueprint $table) {
            $table->dropColumn('id_kes');
        });
    }
};
