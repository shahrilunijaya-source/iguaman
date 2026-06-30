<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W1 — separate Khidmat Nasihat intake by applicant source (prison / clinic vs public)
 * and link a fee-waiver attachment.
 *
 * `applicant_source` is an explicit, queryable column derived from the existing
 * jenis_permohonan + jenis_wakil pair (so KPI/reports can split by source without
 * decoding two columns). `id_lampiran_waiver` soft-links to the uploaded_files row
 * holding the fee-waiver proof when is_percuma is set. No hard FK — uploaded_files.id
 * is a legacy int (matches the soft-link pattern used elsewhere).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->enum('applicant_source', ['PUBLIC', 'PRISON', 'CLINIC', 'COURT'])
                ->default('PUBLIC')->after('jenis_wakil');
            $table->unsignedBigInteger('id_lampiran_waiver')->nullable()->after('is_percuma');
            $table->index('applicant_source');
        });

        // Backfill existing rows from the jenis_permohonan + jenis_wakil pair.
        DB::table('khidmat_nasihat')->where('jenis_permohonan', 'SEBAGAI_WAKIL')
            ->where('jenis_wakil', 'PENJARA')->update(['applicant_source' => 'PRISON']);
        DB::table('khidmat_nasihat')->where('jenis_permohonan', 'SEBAGAI_WAKIL')
            ->where('jenis_wakil', 'JKM')->update(['applicant_source' => 'CLINIC']);
        DB::table('khidmat_nasihat')->where('jenis_permohonan', 'SEBAGAI_WAKIL')
            ->where('jenis_wakil', 'MAHKAMAH')->update(['applicant_source' => 'COURT']);
    }

    public function down(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->dropIndex(['applicant_source']);
            $table->dropColumn(['applicant_source', 'id_lampiran_waiver']);
        });
    }
};
