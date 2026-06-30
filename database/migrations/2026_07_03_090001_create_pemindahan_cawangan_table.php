<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W7 + W3 — shared branch-transfer ledger.
 *
 * One polymorphic row per move of a case (forms) or advisory (khidmat_nasihat)
 * between branches. The record's own branch label moves at transfer time; this
 * table tracks who/why/when and the DIPINDAH -> DITERIMA/DITOLAK lifecycle so
 * the destination branch can accept or reject (reject reverses the label).
 *
 * No hard FKs: forms.id / khidmat_nasihat.id are legacy signed ints and
 * cawangan ids vary by record type, so id_rekod / cawangan_*_id are soft links.
 *
 * Also adds khidmat_nasihat.cawangan_asal_id (the KN counterpart of
 * forms.cawangan_asal) so the origin branch keeps the KN in its worklists after
 * transfer (D2 dual-branch visibility). khidmat_nasihat is a clean table — no
 * sql_mode relaxation needed (that gotcha is only for legacy `forms`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pemindahan_cawangan')) {
            Schema::create('pemindahan_cawangan', function (Blueprint $table) {
                $table->bigIncrements('id');

                // KES | KHIDMAT_NASIHAT + the moved record's id (soft link, no FK).
                $table->string('jenis_rekod', 20);
                $table->unsignedBigInteger('id_rekod');

                // Branch endpoints stored as BOTH name (forms.cawangan is a name)
                // and id (khidmat_nasihat.cawangan_id is an id) — populated per type.
                $table->string('cawangan_asal')->nullable();
                $table->unsignedBigInteger('cawangan_asal_id')->nullable();
                $table->string('cawangan_tujuan')->nullable();
                $table->unsignedBigInteger('cawangan_tujuan_id')->nullable();

                $table->text('sebab')->nullable();
                $table->text('sebab_tolak')->nullable();

                // DIPINDAH -> DITERIMA | DITOLAK.
                $table->string('status', 20)->default('DIPINDAH');

                $table->dateTime('tarikh_pindah');
                $table->dateTime('tarikh_terima')->nullable(); // accept OR reject decision date

                $table->string('dipindah_oleh')->nullable();
                $table->string('diterima_oleh')->nullable();

                $table->timestamps();

                $table->index(['jenis_rekod', 'id_rekod'], 'pc_rekod_idx');
                $table->index(['jenis_rekod', 'status'], 'pc_jenis_status_idx');
                $table->index('cawangan_tujuan', 'pc_tujuan_idx');
                $table->index('cawangan_tujuan_id', 'pc_tujuan_id_idx');
                $table->index('cawangan_asal', 'pc_asal_idx');
                $table->index('cawangan_asal_id', 'pc_asal_id_idx');
            });
        }

        if (! Schema::hasColumn('khidmat_nasihat', 'cawangan_asal_id')) {
            Schema::table('khidmat_nasihat', function (Blueprint $table) {
                // Origin branch retained after transfer (D2 dual-branch); soft link.
                $table->unsignedBigInteger('cawangan_asal_id')->nullable()->index()->after('cawangan_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('khidmat_nasihat', 'cawangan_asal_id')) {
            Schema::table('khidmat_nasihat', function (Blueprint $table) {
                $table->dropColumn('cawangan_asal_id');
            });
        }

        Schema::dropIfExists('pemindahan_cawangan');
    }
};
