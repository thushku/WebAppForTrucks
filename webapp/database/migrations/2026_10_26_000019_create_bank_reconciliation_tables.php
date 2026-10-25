<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M19: bank reconciliation (blueprint 07 §1). A bank statement
 * (CSV from the bank) is brought in against one bank ledger; each of its
 * lines is matched to the book's entry on that ledger, left out on purpose
 * (ignored), or booked on the spot (bank charges, interest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->comment('The bank ledger');
            $table->string('file_name', 200)->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('closing_balance', 16, 3)->nullable()->comment('The bank\'s balance on the last line, when the file has one');
            $table->unsignedInteger('lines')->default(0);
            $table->unsignedInteger('duplicates')->default(0)->comment('Lines already brought in before, left out');
            $table->foreignId('imported_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained();
            $table->date('line_date');
            $table->string('description', 255)->nullable();
            $table->string('reference', 80)->nullable();
            $table->decimal('amount', 16, 3)->comment('Into the bank +, out of it −');
            $table->decimal('balance', 16, 3)->nullable();
            $table->string('fingerprint', 64)->comment('Date, amount, description and reference: the same line is not brought in twice');
            $table->string('status', 10)->default('open')->comment('open | matched | ignored');
            $table->foreignId('voucher_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_how', 10)->nullable()->comment('auto | hand | booked');
            $table->string('note', 255)->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->unique(['ledger_account_id', 'fingerprint']);
            $table->unique('voucher_line_id');
            $table->index(['company_id', 'ledger_account_id', 'status', 'line_date'], 'bank_lines_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');
    }
};
