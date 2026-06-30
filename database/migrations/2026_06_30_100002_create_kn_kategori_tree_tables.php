<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 8 — Khidmat Nasihat category tree (3 levels, matches the Nuxt FE):
 *   ref_kategori_kn      (L1, "Jenis Khidmat": SIVIL / SYARIAH / PENDAMPING JENAYAH / PENDAMPING GUAMAN)
 *     -> ref_kategori_kes_kn   (L2, .NET "JenisKes")
 *          -> ref_subkategori_kn (L3, FE-only level)
 *
 * Kept separate from rekod-kes `ref_kes` (flat, case-records-owned) per locked decision.
 * Children cascade-delete with their parent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_kategori_kn', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_kategori');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('ref_kategori_kes_kn', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->constrained('ref_kategori_kn')->cascadeOnDelete();
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('ref_subkategori_kn', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_kes_id')->constrained('ref_kategori_kes_kn')->cascadeOnDelete();
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_subkategori_kn');
        Schema::dropIfExists('ref_kategori_kes_kn');
        Schema::dropIfExists('ref_kategori_kn');
    }
};
