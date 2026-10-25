<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M11: the accounting core (blueprint 07 §1, 09 §0). One book of
 * vouchers; the five ledgers, the trip and truck profit and the statements
 * are views of it.
 *
 *  · account_groups: the chart's tree (Tally-style in India, IFRS-style in
 *    the UAE) — each group is an asset, liability, equity, income or expense;
 *  · ledger_accounts: the accounts entries post to. Each client, supplier,
 *    vendor, crew member and bank account gets its own, made the first time
 *    it is needed; the system's own (cash, freight income, output GST, TDS
 *    receivable…) carry a system key;
 *  · vouchers: one balanced entry, made by the system from a document or an
 *    operation (its source), or typed by the accountant (a journal). Never
 *    deleted or edited: a cancel is an exact reversal, dated in the open
 *    period;
 *  · voucher_lines: debit or credit on one ledger, with the cost centres
 *    (trip, truck, branch, LR) that give profit per trip and per truck, and
 *    for a crew member the bucket (trip cash / earnings);
 *  · book_locks: the books are locked up to a date; every lock and unlock
 *    is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('account_groups')->nullOnDelete();
            $table->string('code', 30)->comment('System groups: sundry_debtors, direct_expenses…');
            $table->string('name', 120);
            $table->string('nature', 10)->comment('asset | liability | equity | income | expense');
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_group_id')->constrained();
            $table->string('name', 150);
            $table->string('code', 30)->nullable()->comment('Short code typed by the accountant');
            $table->string('system_key', 60)->nullable()->comment('cash, freight_income, output_tax:CGST, expense:fuel, party:12…');
            $table->string('kind', 10)->default('general')->comment('general | party | person | bank | cash | tax');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('company_bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('opening_balance', 16, 3)->default(0)->comment('Positive = debit');
            $table->date('opening_date')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'system_key']);
            $table->index(['company_id', 'account_group_id']);
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('voucher_type', 12)->comment('sales | credit_note | receipt | payment | journal | contra | expense | purchase | reversal');
            $table->string('number', 40);
            $table->date('voucher_date');
            $table->date('document_date')->nullable()->comment('The source\'s own date when the books were locked on it');
            $table->string('reference', 60)->nullable()->comment('The document\'s number: INV/26-27/00001, LR, UTR');
            $table->string('narration', 500);
            $table->string('source_type', 30)->nullable()->comment('invoice | receipt | trip_advance | road_event | settlement | office_entry | hire_challan | hire_line | broker_commission | service_job');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_event', 20)->nullable()->comment('posted | reversed');
            $table->decimal('total', 16, 3)->comment('Sum of the debits (= the credits)');
            $table->char('currency', 3);
            $table->string('status', 10)->default('posted')->comment('posted | cancelled (reversed)');
            $table->foreignId('reverses_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('reversed_by_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            // A document posts once per event: a retry or a rebuild finds the voucher already there.
            $table->unique(['company_id', 'source_type', 'source_id', 'source_event'], 'vouchers_source_unique');
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'voucher_date']);
        });

        Schema::create('voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained();
            $table->decimal('debit', 16, 3)->default(0);
            $table->decimal('credit', 16, 3)->default(0);
            $table->string('bucket', 10)->nullable()->comment('Crew ledgers: trip_cash | earnings | loan');
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->index(['company_id', 'ledger_account_id', 'voucher_id']);
            $table->index(['company_id', 'trip_id']);
            $table->index(['company_id', 'truck_id']);
        });

        Schema::create('book_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('locked_until')->nullable()->comment('Null: unlocked');
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'id']);
        });

        Schema::table('trip_settlements', function (Blueprint $table) {
            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_settlements', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
        });
        Schema::dropIfExists('book_locks');
        Schema::dropIfExists('voucher_lines');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('account_groups');
    }
};
