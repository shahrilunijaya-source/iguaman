<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W15 — central claim ledger (lejar tuntutan bayaran).
 *
 * Polymorphic by (sumber, sumber_id) so wishes 5 (KN -> external lawyer),
 * 9 (pembelaan awam) and 19 (mediation) all reuse it by varying only `sumber`.
 * Hard FK id_kes -> forms.id (the int spine) for the litigation link; the
 * heterogeneous sumber_id carries no FK. Carries the receipt fields legacy L3
 * never ported (audit gap G-M3: KN payment computed but never confirmed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lejar_tuntutan_bayaran', function (Blueprint $table) {
            $table->id();
            $table->string('no_tuntutan', 30)->nullable()->unique();

            // Polymorphic origin.
            $table->enum('sumber', ['KN', 'PEMBELAAN_AWAM', 'MEDIASI', 'PEGUAM_LUAR', 'LAIN'])->index();
            $table->unsignedBigInteger('sumber_id')->nullable()->index();

            // Case + claimant links.
            $table->integer('id_kes')->nullable()->index();                  // -> forms.id (signed int, legacy)
            $table->unsignedBigInteger('id_khidmat_nasihat')->nullable()->index();
            $table->unsignedBigInteger('id_peguam_panel')->nullable()->index();
            $table->string('kp_peguam', 20)->nullable()->index();            // lawyer IC, legacy join key
            $table->unsignedBigInteger('id_pengguna')->nullable();           // claimant user
            $table->unsignedBigInteger('cawangan_id')->nullable();
            $table->string('cawangan', 50)->nullable()->index();             // legacy name snapshot

            // Claim body.
            $table->string('jenis_tuntutan', 100)->nullable();
            $table->text('keterangan')->nullable();
            $table->decimal('jumlah_tuntutan', 12, 2)->default(0);
            $table->decimal('jumlah_diluluskan', 12, 2)->nullable();
            $table->decimal('jumlah_bayaran', 12, 2)->nullable();

            // Lifecycle.
            $table->enum('status_tuntutan', ['DRAF', 'DIHANTAR', 'SEMAKAN', 'DILULUS', 'DITOLAK', 'DIBAYAR', 'BATAL'])
                ->default('DRAF')->index();

            // Receipt step (G-M3 fix).
            $table->boolean('status_bayaran')->default(false);
            $table->string('nombor_resit', 50)->nullable();
            $table->date('tarikh_resit')->nullable();
            $table->string('kaedah_bayaran', 50)->nullable();
            $table->string('rujukan_bayaran', 100)->nullable();

            // Dates + approver snapshots.
            $table->date('tarikh_tuntutan')->nullable();
            $table->dateTime('tarikh_lulus')->nullable();
            $table->dateTime('tarikh_bayar')->nullable();
            $table->string('diluluskan_oleh')->nullable();
            $table->text('ulasan_pemohon')->nullable();
            $table->text('ulasan_pelulus')->nullable();
            $table->string('cipta_oleh')->nullable();
            $table->string('kemaskini_oleh')->nullable();

            $table->timestamps();

            $table->index(['sumber', 'sumber_id'], 'ltb_sumber_idx');
            // One ledger row per KN claim — guards the auto-create bridge (D4).
            $table->unique(['sumber', 'id_khidmat_nasihat'], 'ltb_sumber_kn_unique');

            $table->foreign('id_kes', 'ltb_id_kes_fk')->references('id')->on('forms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lejar_tuntutan_bayaran');
    }
};
