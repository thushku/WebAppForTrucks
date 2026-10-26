<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M21 (the rest of M9): quotations / rate confirmations and
 * pre-printed LR books (blueprint 10 §1, §3, §6.3).
 *
 * A quotation's lines are what a rate contract's lines are (route, material,
 * truck type, basis, rate, detention, charges), so an accepted quotation
 * becomes the client's rate contract without retyping. An LR book is a range
 * of leaves handed to a branch, agent or driver; a typed LR number must be a
 * leaf of an issued book, not used and not cancelled or lost — only those
 * leaves are kept as rows, the used ones are the consignments themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40)->nullable()->comment('Given when issued');
            $table->foreignId('party_id')->constrained('parties');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->string('status', 12)->default('draft')->comment('draft | issued | accepted | declined | cancelled');
            $table->string('subject', 150)->nullable();
            $table->string('currency', 3);
            $table->text('terms')->nullable()->comment('Payment and detention terms, as printed');
            $table->text('notes')->nullable()->comment('Office only, not printed');
            $table->string('decision_note', 255)->nullable()->comment('How the client accepted or why they declined');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('rate_contract_id')->nullable()->constrained()->nullOnDelete()->comment('Made from it once accepted');
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'quote_date']);
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('from_place_id')->constrained('places');
            $table->foreignId('to_place_id')->constrained('places');
            $table->string('material', 40)->nullable();
            $table->string('truck_type', 40)->nullable();
            $table->string('basis', 10)->default('trip')->comment('trip | tonne | kg | km | package');
            $table->decimal('rate', 14, 2);
            $table->decimal('min_weight_mt', 10, 3)->nullable();
            $table->unsignedSmallInteger('detention_free_hours')->nullable();
            $table->decimal('detention_rate_per_day', 14, 2)->nullable();
            $table->decimal('loading_charge', 14, 2)->nullable();
            $table->decimal('unloading_charge', 14, 2)->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('lr_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('book_no', 30);
            $table->string('prefix', 10)->default('')->comment('Printed before each leaf number, e.g. "A-"');
            $table->unsignedInteger('first_no');
            $table->unsignedInteger('last_no');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('issued_to', 120)->nullable()->comment('Agent, driver or desk holding the book');
            $table->date('issued_on');
            $table->string('status', 10)->default('active')->comment('active | closed');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'book_no']);
            $table->index(['company_id', 'prefix', 'first_no', 'last_no']);
        });

        Schema::create('lr_book_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lr_book_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('leaf_no');
            $table->string('status', 10)->comment('cancelled | lost');
            $table->string('reason', 255);
            $table->foreignId('marked_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lr_book_id', 'leaf_no']);
        });

        Schema::table('consignments', function (Blueprint $table) {
            $table->foreignId('lr_book_id')->nullable()->after('lr_date')->constrained()->nullOnDelete()->comment('The printed book the typed LR number is from');
            $table->foreignId('quotation_id')->nullable()->after('rate_contract_line_id')->constrained()->nullOnDelete()->comment('The quotation the rate came from');
        });
    }

    public function down(): void
    {
        Schema::table('consignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quotation_id');
            $table->dropConstrainedForeignId('lr_book_id');
        });
        Schema::dropIfExists('lr_book_leaves');
        Schema::dropIfExists('lr_books');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
    }
};
