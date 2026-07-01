<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 DB backstops (audit DB-09 / DB-10 / DB-12). Pre-checked before writing:
 *   - slot_temu_janji has 0 duplicate (branch, room, date, start) tuples;
 *   - sejarah_ppuu has 0 orphan / null id_kes rows;
 *   - butiran_peguam_panel_6.modifiedDate has 0 unparseable non-empty values.
 */
return new class extends Migration
{
    public function up(): void
    {
        // DB-10: stop duplicate slots for the same (branch, room, date, start time). Room-level
        // uniqueness is enforced here; branch-level slots (bilik_id NULL) stay guarded by the
        // SlotGenerator idempotency check (MySQL treats NULLs as distinct in a UNIQUE index).
        Schema::table('slot_temu_janji', function (Blueprint $table) {
            $table->unique(['cawangan_id', 'bilik_id', 'tarikh_slot', 'masa_mula'], 'slot_unik_idx');
        });

        // DB-09: sejarah_ppuu.id_kes -> forms.id. id_kes was created `int unsigned` while forms.id
        // is signed `int` (a legacy inconsistency), so align it first — all ids are positive, so a
        // signed int holds every value. Column is NOT NULL + already indexed; restrict on delete so
        // a case with PPUU history can't be hard-deleted out from under its trail.
        Schema::table('sejarah_ppuu', function (Blueprint $table) {
            $table->integer('id_kes')->nullable(false)->change();
        });
        Schema::table('sejarah_ppuu', function (Blueprint $table) {
            $table->foreign('id_kes', 'sppuu_id_kes_fk')->references('id')->on('forms')->restrictOnDelete();
        });

        // DB-12: modifiedDate was stored as varchar. Normalise empty strings to NULL, then make it
        // a real datetime so date comparisons/sorting are correct.
        DB::table('butiran_peguam_panel_6')->where('modifiedDate', '')->update(['modifiedDate' => null]);
        Schema::table('butiran_peguam_panel_6', function (Blueprint $table) {
            $table->dateTime('modifiedDate')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('butiran_peguam_panel_6', function (Blueprint $table) {
            $table->string('modifiedDate')->nullable()->change();
        });

        Schema::table('sejarah_ppuu', function (Blueprint $table) {
            $table->dropForeign('sppuu_id_kes_fk');
        });
        Schema::table('sejarah_ppuu', function (Blueprint $table) {
            $table->unsignedInteger('id_kes')->nullable(false)->change();
        });

        Schema::table('slot_temu_janji', function (Blueprint $table) {
            $table->dropUnique('slot_unik_idx');
        });
    }
};
