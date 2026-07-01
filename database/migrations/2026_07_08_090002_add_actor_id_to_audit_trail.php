<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOG-06 — store the acting user's id on every audit row (the legacy table only kept a
 * display name in modified_by, which is not a stable forensic key). Nullable: scheduler /
 * console writes have no authenticated user. No FK — the legacy users table type/lifecycle
 * shouldn't be able to block an audit write.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trail', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_id')->nullable()->after('modified_by')->index('audit_actor_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_trail', function (Blueprint $table) {
            $table->dropIndex('audit_actor_id_idx');
            $table->dropColumn('actor_id');
        });
    }
};
