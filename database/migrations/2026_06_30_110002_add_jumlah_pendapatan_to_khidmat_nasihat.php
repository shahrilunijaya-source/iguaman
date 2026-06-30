<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 9 slice 2 — declared income for the KhidmatBayaran threshold.
 *
 * The create wizard captures jumlah_pendapatan (RM) to drive the "Laluan
 * Sumbangan" rule: Sivil/Syariah applicants above RM50,000 pay RM260 instead of
 * the RM10 base fee. Additive (the slice-1 table is already committed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->decimal('jumlah_pendapatan', 12, 2)->nullable()->after('jenis_kes');
        });
    }

    public function down(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->dropColumn('jumlah_pendapatan');
        });
    }
};
