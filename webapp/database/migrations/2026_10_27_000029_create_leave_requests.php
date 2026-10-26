<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M40: leave. A request for days off of one of the company's leave
 * types, asked for from the crew app or recorded by the office, approved or
 * turned down. Balances are worked out from the type's days a year and the
 * approved requests — nothing stored to drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique()->comment('The phone command that asked, or made here');
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('leave_type', 30)->comment('A leave_type list code, or unpaid');
            $table->date('from_on');
            $table->date('to_on');
            $table->unsignedSmallInteger('days');
            $table->string('reason', 255)->nullable();
            $table->string('status', 12)->default('waiting')->comment('waiting | approved | rejected | cancelled');
            $table->string('asked_via', 8)->default('office')->comment('app | office');
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 255)->nullable();
            $table->timestamps();

            $table->index(['person_id', 'from_on']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
