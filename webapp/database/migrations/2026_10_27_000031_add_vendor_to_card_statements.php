<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M46: a pump's credit statement is a card statement too — the
 * pump (a vendor) on the statement, its charges matched to the crew's fuel
 * entries "on credit with the pump", and a charge nobody entered owed to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_statements', function (Blueprint $table) {
            $table->foreignId('vendor_party_id')->nullable()->after('kind')->constrained('parties')->nullOnDelete()->comment('The pump, for a pump statement');
        });
    }

    public function down(): void
    {
        Schema::table('card_statements', fn (Blueprint $table) => $table->dropConstrainedForeignId('vendor_party_id'));
    }
};
