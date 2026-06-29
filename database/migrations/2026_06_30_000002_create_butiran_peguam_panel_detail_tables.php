<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lawyer-panel profile detail tables _3/_4/_5/_6 — present in the live peguam-panel
 * app but never captured in any sistemspk dump (peguam-panel had no .sql). Columns
 * reconstructed from the legacy registration/update source (daftar.php / daftarNew.php /
 * profilUpdate.php) so a future ETL maps 1:1. Names kept camelCase to match _2 + legacy.
 *
 *   _3 — professional qualifications (CLP / CSO 1-5 / YBGK / ADR / Sijil Ahli & Akreditasi / eVendor)
 *   _4 — law firm + professional-indemnity insurance
 *   _5 — payment bank account
 *   _6 — practice-area specialisation rows (one lawyer -> many; bidang pengkhususan)
 *
 * All keyed by kpBaru (IC), matching the legacy implicit relation to _2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('butiran_peguam_panel_3', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kpBaru')->nullable()->index();

            // CLP (Common Law Practice)
            $table->string('clpNumber')->nullable();
            $table->date('clpMula')->nullable();
            $table->date('clpAkhir')->nullable();

            // CSO 1-5 (Syarie advocate certificates)
            foreach (range(1, 5) as $i) {
                $table->string("csoNumber{$i}")->nullable();
                $table->string("cso{$i}Tauliah")->nullable();
                $table->date("cso{$i}Mula")->nullable();
                $table->date("cso{$i}Akhir")->nullable();
            }

            // Lokasi berguam 1-5 (derived from CSO tauliah)
            foreach (range(1, 5) as $i) {
                $table->string("lokasiBerguam{$i}")->nullable();
                $table->string("lokasiBerguam{$i}_status")->nullable();
            }

            // YBGK (Yayasan Bantuan Guaman Kebangsaan)
            $table->string('ybgk_kelulusan')->nullable(); // Ya / Tidak / Pengecualian
            $table->date('ybgk_tarikhLulus_A')->nullable();
            $table->date('ybgk_tarikhLulus_B')->nullable();
            $table->string('ybgk_daftar')->nullable();

            // ADR (Alternative Dispute Resolution)
            $table->string('adr_penimbangtara')->nullable(); // Ya / Tidak
            $table->string('adr_pengantara')->nullable();     // Ya / Tidak

            // Sijil Ahli (membership/expert certificate)
            $table->string('sijilAhli_nombor')->nullable();
            $table->string('sijilAhli_namaBadan')->nullable();
            $table->date('sijilAhli_mula')->nullable();
            $table->date('sijilAhli_akhir')->nullable();

            // Sijil Akreditasi
            $table->string('sijilAkreditasi_nombor')->nullable();
            $table->string('sijilAkreditasi_namaBadan')->nullable();
            $table->date('sijilAkreditasi_mula')->nullable();
            $table->date('sijilAkreditasi_akhir')->nullable();

            // eVendor
            $table->string('eVendor_daftar')->nullable(); // Ya / Tidak
            $table->string('eVendor_ID')->nullable();
        });

        Schema::create('butiran_peguam_panel_4', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kpBaru')->nullable()->index();

            $table->string('namaFirma')->nullable();
            $table->string('alamatFirma1')->nullable();
            $table->string('alamatFirma2')->nullable();
            $table->string('alamatFirma3')->nullable();
            $table->string('poskodFirma')->nullable();
            $table->string('bandarFirma')->nullable();
            $table->string('negeriFirma')->nullable();
            $table->string('noTelFirma')->nullable();
            $table->string('noFaksFirma')->nullable();

            // Professional indemnity insurance
            $table->string('namaInsurans')->nullable();
            $table->string('noPolisi')->nullable();
            $table->string('amaunPerlindungan')->nullable();
            $table->date('polisiMula')->nullable();
            $table->date('polisiAkhir')->nullable();
        });

        Schema::create('butiran_peguam_panel_5', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kpBaru')->nullable()->index();

            $table->string('namaBank')->nullable();
            $table->string('noAkaunBank')->nullable();
            $table->string('alamatBank1')->nullable();
            $table->string('alamatBank2')->nullable();
            $table->string('alamatBank3')->nullable();
            $table->string('poskodBank')->nullable();
            $table->string('bandarBank')->nullable();
            $table->string('negeriBank')->nullable();
        });

        Schema::create('butiran_peguam_panel_6', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kpBaru')->nullable()->index();

            $table->string('category')->nullable();          // JENAYAH / SIVIL / SYARIAH / PG
            $table->string('checkbox_value')->nullable();     // ref_kes.deskripsi
            $table->integer('checkbox_value_status')->default(0); // 0=new 1=approved (drop/add machine)
            $table->string('jenisKemaskini')->nullable();
            $table->string('modifiedBy')->nullable();
            $table->string('modifiedDate')->nullable();
            $table->text('ulasanPengarah')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('butiran_peguam_panel_6');
        Schema::dropIfExists('butiran_peguam_panel_5');
        Schema::dropIfExists('butiran_peguam_panel_4');
        Schema::dropIfExists('butiran_peguam_panel_3');
    }
};
