<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M7: checking the crew's entries, settling their trip cash and
 * closing the trip (blueprint 03 §3, 6, 8; decisions D5–D8).
 *
 *  · event_approvals: every decision on an entry — the second driver's yes
 *    or no, the office's approve / query / reject, the crew's answer to a
 *    query — in order, never changed;
 *  · trip_office_entries: income and expenses the office adds to a trip,
 *    also after it is closed (D8), each change audited;
 *  · trip_settlements: each crew member's trip cash account when the trip is
 *    settled — what they received, what they spent from their own pocket,
 *    the balance and how it was settled, and their bata. A reopened trip
 *    voids its settlements (kept, marked void) and is settled again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('road_event_id')->constrained()->cascadeOnDelete();
            $table->string('step', 14)->comment('second_driver | manager | crew');
            $table->string('decision', 10)->comment('approve | reject | query | answer');
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete()->comment('Office user');
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete()->comment('Crew member');
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['road_event_id', 'id']);
        });

        Schema::create('trip_office_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 8)->comment('income | expense');
            $table->string('category', 40)->comment('Expense: master list code; income: TripOfficeEntry::INCOME');
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete()->comment('Paid to / received from');
            $table->decimal('amount', 14, 2);
            $table->date('entry_date');
            $table->string('reference', 60)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'kind']);
        });

        Schema::create('trip_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people');
            $table->decimal('advances', 14, 2)->default(0)->comment('Given by the office');
            $table->decimal('collected', 14, 2)->default(0)->comment('Received on the road (party, other)');
            $table->decimal('spent', 14, 2)->default(0)->comment('Approved spending from their own pocket');
            $table->decimal('balance', 14, 2)->comment('Positive: the crew member returns it; negative: the company pays it');
            $table->string('mode', 16)->comment('nil | cash_returned | paid_to_crew | carried_forward | ledger');
            $table->string('reference', 60)->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedSmallInteger('bata_days')->nullable();
            $table->decimal('bata_amount', 14, 2)->default(0)->comment('To the personal ledger, not this cash account');
            $table->unsignedBigInteger('voucher_id')->nullable()->comment('Accounting voucher (M11)');
            $table->foreignId('settled_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('settled_at');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'person_id']);
            $table->index(['company_id', 'person_id', 'settled_at']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('settled_by')->nullable()->after('settled_at')->constrained('accounts')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('accounts')->nullOnDelete();
            $table->unsignedSmallInteger('reopen_count')->default(0)->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('settled_by');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn('reopen_count');
        });
        Schema::dropIfExists('trip_settlements');
        Schema::dropIfExists('trip_office_entries');
        Schema::dropIfExists('event_approvals');
    }
};
