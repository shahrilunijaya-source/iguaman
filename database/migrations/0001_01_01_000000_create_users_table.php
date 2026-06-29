<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Unified auth — collapses legacy users + users_peguam_panel_2/_3 into one table.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // legacy: nama
            $table->string('email')->unique();               // legacy: emel
            $table->string('username')->nullable();          // staff login id
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');                      // bcrypt (legacy kata_laluan was plaintext)
            $table->enum('user_type', ['staff', 'lawyer'])->default('staff');
            $table->string('role')->default('pegawai');      // admin|pengarah|koordinator|pegawai|peguam
            $table->string('cawangan')->nullable();          // branch (staff)
            $table->string('nokp', 20)->nullable();          // IC number
            $table->string('id_peguam_panel')->nullable();   // links lawyer login -> peguam_panel
            $table->boolean('is_active')->default(true);     // legacy: status_aktif
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['user_type', 'role']);
            $table->index('username');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
