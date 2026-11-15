<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M49: attached trucks. Our commission on an attached truck's
 * freight — the owner's challan for each of its trips is drafted with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->decimal('attached_commission_pct', 5, 2)->nullable()->after('owner_party_id')->comment('Our commission, % of the freight (attached trucks)');
        });
    }

    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->dropColumn('attached_commission_pct');
        });
    }
};
