<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M16: the basic driver pay run (13-parity-additions #16, #24).
 *
 *  · pay_runs: one run for any date range — draft, approved (each payslip's
 *    pay posted to the person's earnings), paid when every payslip is paid,
 *    or cancelled (its postings reversed, never edited);
 *  · payslips: one per person and run — the pay basis and how many days,
 *    trips or km at what rate, the base pay, extra lines (bonus, advance
 *    recovered…), the net, and how and when it was paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('scope', 10)->default('crew')->comment('crew | everyone');
            $table->string('status', 10)->default('draft')->comment('draft | approved | paid | cancelled');
            $table->unsignedInteger('people')->default(0);
            $table->decimal('gross_total', 16, 2)->default(0);
            $table->decimal('deductions_total', 16, 2)->default(0);
            $table->decimal('net_total', 16, 2)->default(0);
            $table->decimal('paid_total', 16, 2)->default(0);
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'period_to']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pay_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people');
            $table->string('pay_basis', 10)->comment('monthly | per_trip | per_km');
            $table->decimal('units', 12, 2)->default(0)->comment('Days, trips or km in the period');
            $table->decimal('rate', 14, 2)->default(0)->comment('Monthly salary, or per trip, or per km');
            $table->decimal('base_pay', 14, 2)->default(0);
            $table->json('lines')->nullable()->comment('Extra lines: [{kind: addition|deduction, type, label, amount}]');
            $table->decimal('additions', 14, 2)->default(0);
            $table->decimal('deductions', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->string('status', 10)->default('pending')->comment('pending | paid | cancelled');
            $table->decimal('paid_amount', 14, 2)->nullable()->comment('Net, or more when other dues (bata) were paid with it');
            $table->string('paid_mode', 10)->nullable()->comment('cash | upi | bank');
            $table->string('paid_reference', 60)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pay_run_id', 'person_id']);
            $table->index(['company_id', 'person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('pay_runs');
    }
};
