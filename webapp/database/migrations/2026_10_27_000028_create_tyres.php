<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M38: the tyre register. Each tyre (by its serial number) with
 * what it cost and the km it is expected to run; where it is fitted (a truck
 * and a wheel position) from which odometer to which; its trips to the
 * retreader. A tyre's km run and its cost per km come from these.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tyres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('serial_no', 40);
            $table->string('brand', 40);
            $table->string('model', 40)->nullable()->comment('Pattern, e.g. XMZ+');
            $table->string('size', 30)->comment('e.g. 295/80 R22.5, 10.00 R20');
            $table->string('kind', 10)->default('new')->comment('new | retread');
            $table->date('purchased_on')->nullable();
            $table->foreignId('vendor_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->decimal('cost', 12, 2)->default(0)->comment('What the tyre cost, with every retread');
            $table->unsignedInteger('expected_km')->nullable()->comment('Expected over its life, with every retread');
            $table->unsignedInteger('km_run')->default(0)->comment('From fitments ended; a fitted tyre adds the truck’s km since');
            $table->unsignedTinyInteger('retreads')->default(0);
            $table->string('status', 12)->default('in_store')->comment('in_store | fitted | retreading | scrapped');
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete()->comment('Where it is fitted now');
            $table->string('position', 10)->nullable();
            $table->date('scrapped_on')->nullable();
            $table->decimal('scrap_value', 12, 2)->nullable();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'serial_no'], 'tyres_serial_once');
            $table->unique(['truck_id', 'position'], 'tyres_one_per_position');
            $table->index(['company_id', 'status']);
        });

        Schema::create('tyre_fitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tyre_id')->constrained()->cascadeOnDelete();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->string('position', 10);
            $table->date('fitted_on');
            $table->unsignedInteger('fitted_odo');
            $table->date('removed_on')->nullable();
            $table->unsignedInteger('removed_odo')->nullable();
            $table->unsignedInteger('km')->nullable();
            $table->string('reason', 12)->nullable()->comment('rotation | repair | retread | worn | burst | sold | other');
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['truck_id', 'fitted_odo']);
            $table->index(['tyre_id', 'fitted_on']);
        });

        Schema::create('tyre_retreads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tyre_id')->constrained()->cascadeOnDelete();
            $table->date('sent_on');
            $table->foreignId('vendor_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->date('returned_on')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->unsignedInteger('expected_km')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['tyre_id', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tyre_retreads');
        Schema::dropIfExists('tyre_fitments');
        Schema::dropIfExists('tyres');
    }
};
