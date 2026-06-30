<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 9 — Khidmat Nasihat (legal-advisory application) core record.
 *
 * Adapted from the .NET KhidmatNasihat entity (parity map §1/§2) into MySQL
 * bigint/snake_case. This is the foundation slice: schema + list/show only. The
 * 4-step wizard create flow + eligibility screening land in a later slice.
 *
 * FK conventions (per batch-8 migration note):
 *   - id_pengguna -> users.id (nullable; citizen accounts arrive in batch 13)
 *   - cawangan_id -> cawangan.id, id_kategori/id_subkategori -> ref_*_kn.id
 *     are new bigint tables: real FKs, nullOnDelete (reference data outlives a row).
 *   - id_negeri -> ref_negeri.id is legacy `int`: plain indexed unsignedInteger, NO FK.
 *   - id_temu_janji is wired at integration (temu_janji is built in parallel batch 10): no FK yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('khidmat_nasihat', function (Blueprint $table) {
            $table->id();
            $table->string('no_permohonan')->nullable()->unique();
            $table->enum('jenis_permohonan', ['DIRI_SENDIRI', 'SEBAGAI_WAKIL'])->default('DIRI_SENDIRI');

            // Ownership + branch
            $table->foreignId('id_pengguna')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cawangan_id')->nullable()->constrained('cawangan')->nullOnDelete();

            // Advisory category tree (kategori -> subkategori) + state
            $table->foreignId('id_kategori')->nullable()->constrained('ref_kategori_kn')->nullOnDelete();
            $table->foreignId('id_subkategori')->nullable()->constrained('ref_subkategori_kn')->nullOnDelete();
            $table->unsignedInteger('id_negeri')->nullable()->index(); // -> ref_negeri.id (legacy int, no FK)

            // Applicant / victim (mangsa) particulars
            $table->string('nama_mangsa')->nullable();
            $table->string('id_pengenalan_mangsa')->nullable();
            $table->string('jenis_pengenalan_mangsa')->nullable();
            $table->string('jantina_mangsa')->nullable();
            $table->string('umur_mangsa')->nullable();
            $table->string('bangsa')->nullable();
            $table->string('agama')->nullable();
            $table->date('tarikh_lahir_mangsa')->nullable();
            $table->string('nama_wakil')->nullable();
            $table->string('alamat_surat1')->nullable();
            $table->string('alamat_surat2')->nullable();
            $table->string('alamat_surat3')->nullable();
            $table->string('poskod', 10)->nullable();

            // Case + declaration
            $table->string('jenis_kes')->nullable();
            $table->boolean('perakuan')->default(false);

            // Payment
            $table->decimal('jumlah_bayaran', 8, 2)->default(0);
            $table->boolean('status_bayaran')->default(false);
            $table->boolean('is_percuma')->default(false);

            // Remarks
            $table->text('ulasan_permohonan')->nullable();
            $table->text('ulasan_pegawai')->nullable();

            // Lifecycle
            $table->enum('status_kn', ['DRAF', 'BAHARU', 'DALAM_PROSES', 'SELESAI', 'BATAL'])->default('DRAF')->index();

            // Appointment link — temu_janji built in parallel batch 10, wired at integration (no FK).
            $table->unsignedBigInteger('id_temu_janji')->nullable();

            // Audit stamps (string actor names, mirror legacy)
            $table->string('cipta_oleh')->nullable();
            $table->string('kemaskini_oleh')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('khidmat_nasihat');
    }
};
