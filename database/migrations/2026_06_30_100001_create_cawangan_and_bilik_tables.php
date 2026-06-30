<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 8 — Cawangan master (JBG / JKM / Penjara) + bilik (rooms).
 *
 * Single typed master per the locked decision. Mahkamah is NOT included here —
 * reuse the existing mahkamah_sivil / mahkamah_syariah tables (rekod-kes domain).
 *
 * `nama` mirrors the legacy branch string (e.g. "JBG PUTRAJAYA") so CawanganScope —
 * which filters records by the cawangan string — keeps working unchanged. New KN
 * tables (batches 9-13) reference cawangan.id directly.
 *
 * negeri_id is a plain indexed unsignedInteger (ref_negeri.id is legacy `int`, not
 * bigint) — no DB-level FK to the legacy reference table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cawangan', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['JBG', 'JKM', 'PENJARA'])->default('JBG')->index();
            $table->string('kod', 20)->nullable();
            $table->string('nama')->unique(); // = legacy cawangan string (CawanganScope match)
            $table->unsignedInteger('negeri_id')->nullable()->index(); // -> ref_negeri.id (legacy int, no FK)
            $table->string('alamat1')->nullable();
            $table->string('alamat2')->nullable();
            $table->string('alamat3')->nullable();
            $table->string('poskod', 10)->nullable();
            $table->string('no_tel', 30)->nullable();
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('bilik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cawangan_id')->constrained('cawangan')->cascadeOnDelete();
            $table->string('nama_bilik');
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bilik');
        Schema::dropIfExists('cawangan');
    }
};
