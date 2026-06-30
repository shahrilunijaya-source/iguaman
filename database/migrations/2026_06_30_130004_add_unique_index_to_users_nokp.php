<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One account per IC. The awam register FormRequest already enforces unique:users,nokp
 * at the app layer, but two concurrent registrations with the same IC can race past it;
 * this DB-level unique index closes that window. MySQL treats NULLs as distinct, so the
 * many no-IC rows (if any) are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('nokp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nokp']);
        });
    }
};
