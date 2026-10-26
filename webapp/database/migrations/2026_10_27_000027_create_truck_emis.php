<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M35: truck loans and their EMIs. The truck keeps its loan's
 * terms (the financier, EMI and day are there already; now the rate and
 * the first EMI); each EMI paid is a row with its voucher — principal off
 * the truck's loan ledger, interest an expense of the truck.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucks', function (Blueprint $table) {
            $table->decimal('loan_rate', 5, 2)->nullable()->after('emi_day')->comment('% a year, for the interest part of each EMI');
            $table->date('loan_starts_on')->nullable()->after('loan_rate')->comment('First EMI month');
        });

        Schema::create('truck_emis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->date('due_on');
            $table->date('paid_on');
            $table->decimal('amount', 14, 2);
            $table->decimal('principal', 14, 2);
            $table->decimal('interest', 14, 2)->default(0);
            $table->string('mode', 6)->comment('cash | bank');
            $table->foreignId('bank_account_id')->nullable()->constrained('company_bank_accounts')->nullOnDelete();
            $table->string('reference', 60)->nullable();
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['truck_id', 'due_on'], 'truck_emis_once');
            $table->index(['company_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_emis');
        Schema::table('trucks', fn (Blueprint $table) => $table->dropColumn(['loan_rate', 'loan_starts_on']));
    }
};
