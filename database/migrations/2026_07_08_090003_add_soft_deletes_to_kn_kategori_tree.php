<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-06 — soft deletes for the Khidmat Nasihat category tree (L1/L2/L3). Deleting
 * reference data is now recoverable; the controller cascade-soft-deletes children with
 * a per-child audit row (the DB cascadeOnDelete only fires on a hard delete, which no
 * longer happens). User deletions are handled separately by deactivation (is_active).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ref_kategori_kn', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('ref_kategori_kes_kn', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('ref_subkategori_kn', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('ref_subkategori_kn', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('ref_kategori_kes_kn', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('ref_kategori_kn', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
