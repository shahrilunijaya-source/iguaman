<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 9 slice 3 — Sebagai-Wakil variants + eligibility screening.
 *
 * Additive only (slices 1/2 columns untouched). Two concerns:
 *
 *  1. SEBAGAI_WAKIL representative context — derived in the Nuxt FE from the
 *     logged-in officer profile (penjara/JKM officer). We have no such citizen/
 *     officer accounts yet (batch 13), so the wizard captures the context as an
 *     explicit selector. Three contexts:
 *       - PENJARA  → cawangan(jenis=PENJARA) via existing cawangan_id; fee RM0.
 *       - JKM      → cawangan(jenis=JKM)     via existing cawangan_id; fee RM0.
 *       - MAHKAMAH → court from mahkamah_sivil|mahkamah_syariah (no FK: legacy
 *                    reference tables, mirror id_negeri convention); normal fee.
 *     Represented party = the FE "MANGSA" block (reuses slice-1 nama_mangsa etc).
 *     "ORANG YANG DIWAKILI" third party + wakil identity get their own columns.
 *
 *  2. Eligibility 3-modal screening outcome (FE khidmatnasihat/index): the
 *     jenis (sivil_syariah|pendamping_jenayah), the per-stage pass flag, and the
 *     contribution-path flag (>RM50k → Laluan Sumbangan). jumlah_pendapatan
 *     already exists (slice 2); perakuan already exists (slice 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            // ---- Sebagai-Wakil representative context ----
            // Null for DIRI_SENDIRI; one of PENJARA|JKM|MAHKAMAH for SEBAGAI_WAKIL.
            $table->enum('jenis_wakil', ['PENJARA', 'JKM', 'MAHKAMAH'])->nullable()->after('jenis_permohonan');

            // Wakil (the representative) identity. nama_wakil already exists (slice 1).
            $table->string('no_pengenalan_wakil')->nullable()->after('nama_wakil');
            $table->string('jawatan_wakil')->nullable()->after('no_pengenalan_wakil');

            // Orang yang diwakili (the represented third party — FE diwakili* block).
            $table->string('nama_diwakili')->nullable()->after('jawatan_wakil');
            $table->string('id_pengenalan_diwakili')->nullable()->after('nama_diwakili');

            // MAHKAMAH context: court reference (no FK — legacy reference tables).
            $table->enum('jenis_mahkamah_pihak', ['SIVIL', 'SYARIAH'])->nullable()->after('id_negeri');
            $table->unsignedBigInteger('id_mahkamah')->nullable()->after('jenis_mahkamah_pihak'); // -> mahkamah_sivil|syariah.id

            // ---- Eligibility screening outcome ----
            // saringan_jenis: 'SIVIL_SYARIAH' | 'PENDAMPING' (FE selectedJenisKhidmat).
            $table->string('saringan_jenis')->nullable()->after('jumlah_pendapatan');
            $table->boolean('saringan_lulus')->default(false)->after('saringan_jenis');
            $table->boolean('is_laluan_sumbangan')->default(false)->after('saringan_lulus');
        });
    }

    public function down(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->dropColumn([
                'jenis_wakil',
                'no_pengenalan_wakil',
                'jawatan_wakil',
                'nama_diwakili',
                'id_pengenalan_diwakili',
                'jenis_mahkamah_pihak',
                'id_mahkamah',
                'saringan_jenis',
                'saringan_lulus',
                'is_laluan_sumbangan',
            ]);
        });
    }
};
