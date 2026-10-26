<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M34: a trip's distance by GPS beside the odometer's, and how
 * much of the trip the GPS covered — a gap between the two is a tampered
 * odometer or a run nobody entered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('gps_km', 8, 1)->nullable()->after('end_odometer')->comment('Distance by the crew phone\'s GPS (M34)');
            $table->unsignedTinyInteger('gps_coverage')->nullable()->after('gps_km')->comment('% of the trip time with GPS points');
            $table->string('km_check', 8)->nullable()->after('gps_coverage')->comment('ok | differs | thin (too little GPS) | none');
        });
    }

    public function down(): void
    {
        Schema::table('trips', fn (Blueprint $table) => $table->dropColumn(['gps_km', 'gps_coverage', 'km_check']));
    }
};
