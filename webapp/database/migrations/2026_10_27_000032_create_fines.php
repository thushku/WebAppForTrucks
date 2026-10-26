<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M48: fines and e-challans. A traffic fine against a truck (or a
 * driver) — the challan number, the authority, the offence, the amount and,
 * in the UAE, its black points —, the trip and driver of that moment, who
 * bears it (the company, or the driver from their pay), and its payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete()->comment('The driver of that moment');
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->string('challan_no', 40)->nullable();
            $table->string('authority', 80)->nullable()->comment('Police, RTO, Dubai Police…');
            $table->string('offence', 150);
            $table->string('place', 150)->nullable();
            $table->dateTime('issued_at');
            $table->decimal('amount', 14, 2);
            $table->unsignedTinyInteger('points')->default(0)->comment('Black points (UAE)');
            $table->date('due_on')->nullable();
            $table->string('status', 10)->default('unpaid')->comment('unpaid | paid | disputed | cancelled');
            $table->string('status_note', 255)->nullable();
            $table->string('bears', 8)->default('company')->comment('company | driver (from their pay)');
            $table->foreignId('payslip_id')->nullable()->constrained()->nullOnDelete()->comment('The payslip that takes it back from the driver');
            $table->date('paid_on')->nullable();
            $table->string('paid_mode', 8)->nullable()->comment('cash | bank | road (the crew paid it from trip cash)');
            $table->foreignId('bank_account_id')->nullable()->constrained('company_bank_accounts')->nullOnDelete();
            $table->string('paid_reference', 60)->nullable();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'challan_no']);
            $table->index(['company_id', 'status']);
            $table->index(['person_id', 'issued_at']);
            $table->index(['truck_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};
