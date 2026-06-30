<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 10 slice 1 — Janji Temu (appointments) + slot/calendar engine schema.
 *
 *   slot_temu_janji   — generated open/booked time slots per branch (+room) per date.
 *                       is_temujanji = the "booked" flag (false = available).
 *   temu_janji        — an appointment; links to a slot + (later) khidmat_nasihat.
 *                       id_khidmat_nasihat / id_pegawai_kn are wired at integration
 *                       (khidmat_nasihat is built in parallel Batch 9) — no DB FK yet.
 *   penutupan_operasi — per-branch/room operational closures (date ranges) the
 *                       SlotAvailabilityService excludes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_temu_janji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cawangan_id')->constrained('cawangan')->cascadeOnDelete();
            $table->foreignId('bilik_id')->nullable()->constrained('bilik')->nullOnDelete();
            $table->date('tarikh_slot');
            $table->time('masa_mula');
            $table->time('masa_akhir');
            $table->boolean('is_temujanji')->default(false); // booked flag
            $table->boolean('status_aktif')->default(true);
            $table->timestamps();

            $table->index(['cawangan_id', 'tarikh_slot']);
        });

        Schema::create('temu_janji', function (Blueprint $table) {
            $table->id();
            // khidmat_nasihat is built in parallel Batch 9 — wired at integration (no FK).
            $table->unsignedBigInteger('id_khidmat_nasihat')->nullable();
            $table->foreignId('slot_temu_janji_id')->nullable()->constrained('slot_temu_janji')->nullOnDelete();
            $table->foreignId('cawangan_id')->constrained('cawangan')->cascadeOnDelete();
            $table->date('tarikh_temu_janji');
            $table->time('masa_mula');
            $table->time('masa_akhir');
            $table->string('tempat')->nullable();
            $table->unsignedBigInteger('id_pegawai_kn')->nullable(); // -> users.id (no FK)
            $table->enum('status', ['MENUNGGU', 'DISAHKAN', 'HADIR', 'TIDAK_HADIR', 'SELESAI', 'BATAL'])->default('MENUNGGU');
            $table->string('cipta_oleh')->nullable();
            $table->string('kemaskini_oleh')->nullable();
            $table->timestamps();

            $table->index(['cawangan_id', 'tarikh_temu_janji']);
        });

        Schema::create('penutupan_operasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cawangan_id')->constrained('cawangan')->cascadeOnDelete();
            $table->foreignId('bilik_id')->nullable()->constrained('bilik')->nullOnDelete();
            $table->date('tarikh_mula');
            $table->date('tarikh_tamat');
            $table->string('sebab')->nullable();
            $table->timestamps();

            $table->index(['cawangan_id', 'tarikh_mula']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penutupan_operasi');
        Schema::dropIfExists('temu_janji');
        Schema::dropIfExists('slot_temu_janji');
    }
};
