<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M9: invoices, notes and receipts, ported from Clinic Ledger
 * (decision D14, blueprint 10) on the tax engine (M8).
 *
 *  · company_bank_accounts: where clients pay us (printed on bills, UPI QR);
 *  · invoices: tax invoice / bill of supply, supplementary invoice, credit
 *    and debit notes in one table (doc_type). A draft is a working document;
 *    finalised, it is evidence: number, lines, tax and the printed snapshot
 *    never change (enforced in the model), it is voided or credited instead;
 *  · invoice_lines: one per charge (freight of an LR, loading, detention…),
 *    with its class, tax code, rate and the amounts the engine worked out;
 *  · invoice_taxes: the tax parts by rate, charged or notional (reverse charge);
 *  · receipts + receipt_allocations: money received, TDS the client deducted
 *    and their deductions, spread over bills (oldest first or by hand);
 *  · document_links: the secure public link (/d/{token}) of a document;
 *  · document_deliveries: every send, open and download;
 *  · party_balances: each client's outstanding and advance, kept in step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60)->comment('"HDFC current"');
            $table->string('bank_name', 120);
            $table->string('holder_name', 150)->comment('Printed on invoices');
            $table->text('account_number')->comment('Encrypted');
            $table->string('account_last4', 4);
            $table->string('ifsc', 11)->nullable();
            $table->text('iban')->nullable()->comment('Encrypted (UAE)');
            $table->string('swift', 11)->nullable();
            $table->string('branch', 120)->nullable();
            $table->text('upi_id')->nullable()->comment('Encrypted; the invoice QR');
            $table->char('currency', 3)->default('INR');
            $table->boolean('is_default')->default(false);
            $table->boolean('show_on_invoice')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('doc_type', 14)->default('invoice')->comment('invoice | supplementary | credit_note | debit_note');
            $table->string('kind', 16)->nullable()->comment('From the tax engine: tax_invoice | bill_of_supply | invoice');
            $table->string('number', 40)->nullable()->comment('Taken at finalisation');
            $table->string('status', 10)->default('draft')->comment('draft | finalised | issued | sent | viewed | partial | paid | void');
            $table->foreignId('party_id')->constrained('parties')->comment('Billed to');
            $table->string('buyer_tax_id', 20)->nullable()->comment('The client GSTIN / TRN the bill goes to');
            $table->foreignId('tax_registration_id')->nullable()->constrained()->nullOnDelete()->comment('Ours; empty: not registered');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('related_invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->comment('The invoice a note or supplementary bill is against');
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('reference', 60)->nullable()->comment('Client PO / indent / shipment');
            $table->string('transport_type', 14)->nullable()->comment('UAE: domestic | export | import | international');
            $table->string('carrier_role', 4)->nullable()->comment('main | sub');
            $table->string('loading_state', 10)->nullable();
            $table->json('evidence')->nullable()->comment('Papers on file for zero-rated lines');
            $table->string('place_of_supply', 10)->nullable();
            $table->string('place_of_supply_name', 80)->nullable();
            $table->boolean('reverse_charge')->default(false);
            $table->boolean('pending_evidence')->default(false);
            $table->char('currency', 3);
            $table->boolean('inclusive')->default(false);
            $table->string('discount_type', 8)->default('none')->comment('none | percent | amount');
            $table->decimal('discount_value', 14, 3)->default(0);
            $table->decimal('subtotal', 16, 3)->default(0);
            $table->decimal('discount_amount', 16, 3)->default(0);
            $table->decimal('taxable_total', 16, 3)->default(0);
            $table->decimal('tax_total', 16, 3)->default(0)->comment('Tax charged on this document');
            $table->decimal('tax_notional', 16, 3)->default(0)->comment('Reverse charge: for the buyer to pay');
            $table->decimal('round_off', 10, 3)->default(0);
            $table->decimal('total', 16, 3)->default(0);
            $table->decimal('advance_less', 16, 3)->default(0)->comment('Less advance received (tax stays on the full value)');
            $table->decimal('amount_paid', 16, 3)->default(0)->comment('Cash + TDS + deductions applied by receipts');
            $table->decimal('amount_credited', 16, 3)->default(0)->comment('Taken off by credit notes');
            $table->decimal('credit_applied', 16, 3)->default(0)->comment('A credit note: what its invoice could take');
            $table->decimal('tds_expected', 16, 3)->default(0)->comment('What the client is expected to deduct');
            $table->decimal('balance_due', 16, 3)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('reason', 500)->nullable()->comment('Why a note or supplementary bill is issued');
            $table->text('snapshot')->nullable()->comment('Seller, buyer and bank as issued (encrypted)');
            $table->json('tax_trace')->nullable()->comment('Why each line got its tax');
            $table->json('withholding')->nullable();
            $table->json('design_snapshot')->nullable();
            $table->string('public_token', 40)->nullable()->unique();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->string('submitted_via', 20)->nullable()->comment('hand | courier | email | portal');
            $table->date('submitted_on')->nullable()->comment('Bill submission: due date may start from it');
            $table->string('submission_ref', 60)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'doc_type', 'number']);
            $table->index(['company_id', 'party_id', 'status']);
            $table->index(['company_id', 'status', 'due_date']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_class', 30);
            $table->string('description', 255);
            $table->string('detail', 255)->nullable();
            $table->string('sac_code', 10)->nullable();
            $table->string('unit', 12)->default('trip');
            $table->decimal('quantity', 14, 3)->default(1);
            $table->decimal('unit_rate', 16, 3)->default(0);
            $table->decimal('discount_percent', 6, 3)->default(0);
            $table->string('tax_code', 20)->nullable()->comment('Chosen by the engine, or by hand with a reason');
            $table->string('override_reason', 255)->nullable();
            $table->char('tax_category', 2)->nullable();
            $table->unsignedBigInteger('tax_rate_id')->nullable()->comment('The dated rate used (kept, never edited)');
            $table->unsignedBigInteger('tax_rule_id')->nullable();
            $table->decimal('rate_percent', 9, 6)->default(0);
            $table->decimal('line_subtotal', 16, 3)->default(0);
            $table->decimal('net', 16, 3)->default(0);
            $table->decimal('taxable', 16, 3)->default(0);
            $table->decimal('tax_amount', 16, 3)->default(0);
            $table->decimal('notional_tax', 16, 3)->default(0);
            $table->json('parts')->nullable()->comment('{"CGST": 450, "SGST": 450}');
            $table->decimal('line_total', 16, 3)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
            $table->index(['consignment_id']);
        });

        Schema::create('invoice_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('code', 12);
            $table->decimal('percent', 9, 6);
            $table->decimal('taxable', 16, 3);
            $table->decimal('amount', 16, 3);
            $table->boolean('charged')->default(true)->comment('False: reverse charge, the buyer pays it');
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('number', 40);
            $table->foreignId('party_id')->constrained('parties');
            $table->date('receipt_date');
            $table->char('currency', 3);
            $table->decimal('amount', 16, 3)->comment('Money received');
            $table->decimal('tds_amount', 16, 3)->default(0)->comment('TDS the client deducted');
            $table->string('tds_section', 10)->nullable();
            $table->decimal('deduction_amount', 16, 3)->default(0)->comment('Shortage, damage, late POD the client kept');
            $table->string('deduction_note', 255)->nullable();
            $table->decimal('unapplied', 16, 3)->default(0)->comment('Left over: the client\'s advance credit');
            $table->string('mode', 8)->comment('cash | cheque | neft | rtgs | upi | card | other');
            $table->string('reference', 60)->nullable()->comment('UTR, cheque no.');
            $table->date('cheque_date')->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained('company_bank_accounts')->nullOnDelete()->comment('Into which of our accounts');
            $table->string('narration', 255);
            $table->string('status', 8)->default('posted')->comment('posted | void');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->string('public_token', 40)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'party_id', 'receipt_date']);
        });

        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained();
            $table->decimal('amount', 16, 3)->comment('Cash applied');
            $table->decimal('tds', 16, 3)->default(0);
            $table->decimal('deduction', 16, 3)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['invoice_id']);
        });

        Schema::create('document_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 12)->comment('invoice | receipt | lr');
            $table->unsignedBigInteger('document_id');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('document_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 12);
            $table->unsignedBigInteger('document_id');
            $table->string('channel', 10)->comment('email | whatsapp | link | download | print');
            $table->string('recipient', 255)->nullable();
            $table->string('status', 10)->default('sent')->comment('sent | opened | failed');
            $table->string('error', 500)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_type', 'document_id']);
        });

        Schema::create('party_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->unique()->constrained('parties')->cascadeOnDelete();
            $table->decimal('outstanding', 16, 3)->default(0)->comment('Σ balance due of open bills');
            $table->decimal('overdue', 16, 3)->default(0);
            $table->decimal('advance', 16, 3)->default(0)->comment('Receipts not applied + credit not taken');
            $table->unsignedInteger('open_bills')->default(0);
            $table->date('oldest_due')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::table('consignments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('pod_received_at')->constrained()->nullOnDelete()->comment('The finalised bill it is on');
        });
    }

    public function down(): void
    {
        Schema::table('consignments', fn (Blueprint $table) => $table->dropConstrainedForeignId('invoice_id'));
        Schema::dropIfExists('party_balances');
        Schema::dropIfExists('document_deliveries');
        Schema::dropIfExists('document_links');
        Schema::dropIfExists('receipt_allocations');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('invoice_taxes');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('company_bank_accounts');
    }
};
