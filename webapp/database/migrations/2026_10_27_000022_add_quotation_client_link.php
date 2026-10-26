<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M24: a sent quotation opens for the client by its secure link
 * (/d/{token}, like a bill) and the client accepts or declines it there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('public_token', 40)->nullable()->after('number');
            $table->timestamp('viewed_at')->nullable()->after('issued_at')->comment('First opened by the client');
            $table->string('answered_by_name', 120)->nullable()->after('decided_by')->comment('Who answered on the client link');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['public_token', 'viewed_at', 'answered_by_name']);
        });
    }
};
