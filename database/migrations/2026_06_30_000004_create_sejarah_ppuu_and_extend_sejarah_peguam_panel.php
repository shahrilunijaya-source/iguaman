<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Case-assignment history spine (pp-agihan). sejarah_ppuu records the PPUU pick →
 * Pengarah sokong → Ketua Pengarah keputusan chain with aktif/tutup rotation; it lived
 * in the live peguam-panel DB but was never dumped (columns reconstructed from the legacy
 * INSERTs in formAgihanBaru/Pengarah/Semula). sejarah_peguam_panel gains the reassignment
 * + withdrawal (tarik diri) columns the legacy flow writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sejarah_ppuu', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_kes')->index();           // forms.id
            $table->unsignedInteger('idPPUU')->nullable()->index(); // assigned PPUU (users.id)
            $table->dateTime('tarikh_diberiAgihan')->nullable();
            $table->string('statusAgihan', 2)->nullable();         // mirrors forms.status_agihan
            $table->string('statusMohonPP', 2)->nullable();        // PPUU accept/reject (semasa)
            $table->dateTime('tarikh_syorPPUU')->nullable();       // PPUU recommendation date
            $table->string('status_rekod', 10)->nullable()->index(); // aktif | tutup

            // Pengarah endorsement
            $table->string('status_sokonganPengarah', 2)->nullable(); // 0 endorsed / 1 not
            $table->string('ulasanPengarah', 600)->nullable();
            $table->dateTime('tarikh_PengarahKemaskini')->nullable();

            // Ketua Pengarah decision
            $table->string('status_KP', 2)->nullable();            // 0 approved / 1 rejected
            $table->string('ulasanKP', 200)->nullable();
            $table->dateTime('tarikh_KPKemaskini')->nullable();

            // PPUU lawyer pick (Pilihan A own-cawangan / B other-negeri)
            $table->string('pilihan_Agihan')->nullable();          // A | B
            $table->string('cawangan_peguampanel')->nullable();
            $table->string('nama_peguampanel')->nullable();
            $table->string('kpBaru_peguampanel')->nullable();
            $table->string('ulasanPPUU', 350)->nullable();

            $table->dateTime('createdDate')->nullable();
            $table->string('createdBy')->nullable();
            $table->string('modifiedBy')->nullable();
            $table->dateTime('modifiedDate')->nullable();
        });

        Schema::table('sejarah_peguam_panel', function (Blueprint $table) {
            $table->string('status_rekod', 50)->nullable()->after('status_agihan')->index(); // aktif | selesai | tutup
            $table->integer('permohonan_kali')->nullable()->after('status_rekod');            // reassignment counter (MAX+1 per id_kes)
            $table->dateTime('createdDate')->nullable()->after('permohonan_kali');
            $table->string('createdBy')->nullable()->after('createdDate');

            // Tarik Diri (withdrawal) columns
            $table->string('pilihanTarikDiri')->nullable()->after('createdBy');  // 1 of 9 reasons
            $table->date('tarikhNextBicaraKes')->nullable()->after('pilihanTarikDiri');
            $table->string('ulasanPPUU', 350)->nullable()->after('tarikhNextBicaraKes');
            $table->string('ulasanPengarah', 350)->nullable()->after('ulasanPPUU');
            $table->string('ulasanKetuaPengarah', 350)->nullable()->after('ulasanPengarah');
            $table->string('keputusan_tarikDiriHQ', 1)->nullable()->after('ulasanKetuaPengarah'); // 0 approve / 1 reject
        });
    }

    public function down(): void
    {
        Schema::table('sejarah_peguam_panel', function (Blueprint $table) {
            $table->dropColumn([
                'status_rekod', 'permohonan_kali', 'createdDate', 'createdBy',
                'pilihanTarikDiri', 'tarikhNextBicaraKes', 'ulasanPPUU',
                'ulasanPengarah', 'ulasanKetuaPengarah', 'keputusan_tarikDiriHQ',
            ]);
        });
        Schema::dropIfExists('sejarah_ppuu');
    }
};
