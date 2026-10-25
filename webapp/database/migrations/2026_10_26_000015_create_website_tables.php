<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M14: the public website.
 *
 *  · contact_messages: what people send from the Contact page, read and
 *    marked handled in the console (platform-level, not company-owned);
 *  · companies.requested_plan_id / requested_coupon: the plan and coupon
 *    chosen on the pricing page before signing up, so the console can apply
 *    them when the payment arrives;
 *  · companies.setup_done_at: when the owner finished (or skipped) the
 *    getting-started steps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('mobile', 20)->nullable();
            $table->string('company', 150)->nullable();
            $table->string('topic', 20)->comment('sales | support | partner | other');
            $table->text('message');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by_admin')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['handled_at', 'created_at']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('requested_plan_id')->nullable()->after('plan_id')->constrained('plans')->nullOnDelete();
            $table->string('requested_coupon', 30)->nullable()->after('requested_plan_id');
            $table->timestamp('setup_done_at')->nullable()->after('suspended_reason');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_plan_id');
            $table->dropColumn(['requested_coupon', 'setup_done_at']);
        });
        Schema::dropIfExists('contact_messages');
    }
};
