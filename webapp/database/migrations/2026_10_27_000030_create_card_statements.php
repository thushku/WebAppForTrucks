<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M44: FASTag and fuel-card statements. The provider's CSV of
 * charges brought in; each charge matched to the crew's entry paid by that
 * card, booked when the crew recorded nothing, or set aside with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->comment('fastag | fuel_card');
            $table->string('file_name', 190);
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedInteger('lines')->default(0);
            $table->unsignedInteger('skipped')->default(0)->comment('Already brought in, or not a charge');
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('card_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_statement_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->timestamp('txn_at');
            $table->string('vehicle', 20)->nullable()->comment('As on the statement');
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete()->comment('The truck’s trip running then');
            $table->string('place', 150)->nullable()->comment('Plaza or pump');
            $table->decimal('amount', 12, 2);
            $table->string('reference', 80)->comment('The provider’s transaction id (or one made from the row)');
            $table->string('status', 10)->default('open')->comment('open | matched | booked | ignored');
            $table->foreignId('road_event_id')->nullable()->constrained('road_events')->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->string('match_how', 8)->nullable()->comment('auto | hand');
            $table->string('note', 255)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'kind', 'reference'], 'card_lines_once');
            $table->unique('road_event_id', 'card_lines_one_entry');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_statement_lines');
        Schema::dropIfExists('card_statements');
    }
};
