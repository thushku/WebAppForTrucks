<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M32: the morning summary by email. One row per person, company
 * and period sent (or failed), so the hourly run never sends one twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digest_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('period', 8)->comment('daily | weekly');
            $table->date('period_key')->comment('The day (daily) or the Monday (weekly) it was for');
            $table->string('status', 8)->comment('sent | failed');
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['company_id', 'account_id', 'period', 'period_key'], 'digest_deliveries_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digest_deliveries');
    }
};
