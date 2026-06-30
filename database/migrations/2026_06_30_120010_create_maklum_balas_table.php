<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 12 slice 1 — Maklum Balas (post-appointment satisfaction feedback).
 *
 * One feedback per Khidmat Nasihat (DB-unique), submitted through a PUBLIC
 * throttled link after the advisory appointment is SELESAI. No login.
 *
 *   - khidmat_nasihat_id -> khidmat_nasihat.id : real FK, cascadeOnDelete,
 *     UNIQUE (one feedback per KN enforced at the DB level).
 *   - soalan_1a..1e : "how did you hear of JBG" multi-checkbox.
 *   - soalan_2a     : service satisfaction enum.
 *   - dihantar_dari_ip : request IP, light anti-abuse audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maklum_balas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('khidmat_nasihat_id')
                ->constrained('khidmat_nasihat')
                ->cascadeOnDelete()
                ->unique();

            // Soalan 1 — how the applicant heard of JBG (multi-select checkboxes).
            $table->boolean('soalan_1a')->default(false); // portal
            $table->boolean('soalan_1b')->default(false); // media sosial
            $table->boolean('soalan_1c')->default(false); // rujukan keluarga/rakan
            $table->boolean('soalan_1d')->default(false); // jabatan/agensi
            $table->boolean('soalan_1e')->default(false); // lain-lain
            $table->string('soalan_1_lain_lain')->nullable(); // free text when 1e checked

            // Soalan 2 — service satisfaction.
            $table->enum('soalan_2a', ['CEMERLANG', 'BAIK', 'KURANG_MEMUASKAN']);

            // Improvement suggestion (free text).
            $table->text('soalan_cadangan')->nullable();

            // Light anti-abuse audit.
            $table->string('dihantar_dari_ip')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maklum_balas');
    }
};
