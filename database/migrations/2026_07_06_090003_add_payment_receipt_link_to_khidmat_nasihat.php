<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W2 — manual iPayment: link the uploaded receipt (resit) of a counter-paid KN
 * intake fee. The receipt detail (nombor_resit / tarikh_resit / kaedah_bayaran /
 * rujukan_bayaran) is recorded on the central ledger row (it already carries those
 * columns, G-M3); the KN keeps only the resit-document link + its status_bayaran flag.
 * Soft link — uploaded_files.id is a legacy int (matches the W1 waiver pattern).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->unsignedBigInteger('id_lampiran_resit')->nullable()->after('id_lampiran_waiver');
        });
    }

    public function down(): void
    {
        Schema::table('khidmat_nasihat', function (Blueprint $table) {
            $table->dropColumn('id_lampiran_resit');
        });
    }
};
