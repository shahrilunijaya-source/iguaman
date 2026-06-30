<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 8 — ref_jawatan (staff job titles). Currently a free-text column on
 * pegawai_jbg.jawatan; normalised here as a reference master for staff profiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_jawatan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_jawatan');
    }
};
