<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 11 slice B — officer processing of a Khidmat Nasihat application.
 *
 * Additive only (slices 1/2/3 columns untouched). Three new columns:
 *
 *  1. id_pegawai_kn -> users.id (nullable FK, nullOnDelete): the advisory officer
 *     (Pegawai Khidmat Nasihat) assigned to process this case. Assignment moves
 *     status_kn BAHARU->DALAM_PROSES. This is the authoritative assignment column
 *     and fixes the legacy bug where CreateTemuJanji dropped IdPegawaiKN (the
 *     temu_janji table also has an id_pegawai_kn snapshot, but the case owns it).
 *
 *  2. tarikh_proses (nullable datetime): when processing began (PKN assigned).
 *
 *  3. id_forms (nullable unsignedBigInteger, NO FK): RESERVED for the KN->forms
 *     case bridge wired later by slice C (gated on a pending product decision).
 *     No FK is added now — just the column placeholder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->foreignId('id_pegawai_kn')->nullable()->after('id_pengguna')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('tarikh_proses')->nullable()->after('id_pegawai_kn');

            // Reserved for slice C (KN -> forms case bridge). No FK yet.
            $table->unsignedBigInteger('id_forms')->nullable()->after('id_temu_janji');
        });
    }

    public function down(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->dropForeign(['id_pegawai_kn']);
            $table->dropColumn(['id_pegawai_kn', 'tarikh_proses', 'id_forms']);
        });
    }
};
