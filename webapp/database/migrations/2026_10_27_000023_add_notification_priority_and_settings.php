<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M26 (13-parity #15, #29): a notice's priority (high / medium /
 * low — the inbox's order and colour, the push's urgency), and each
 * person's choices in each company: which kinds reach their phone, and
 * quiet hours when only high ones do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('priority', 6)->default('medium')->after('kind')->comment('high | medium | low');
        });
        Schema::table('company_users', function (Blueprint $table) {
            $table->json('notification_settings')->nullable()->comment('{"muted": [kind groups], "quiet_from": "22:00", "quiet_to": "06:00"}');
        });
    }

    public function down(): void
    {
        Schema::table('company_users', fn (Blueprint $table) => $table->dropColumn('notification_settings'));
        Schema::table('user_notifications', fn (Blueprint $table) => $table->dropColumn('priority'));
    }
};
