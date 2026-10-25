<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M18: sending documents and reminders (blueprint 10).
 *
 *  · message_templates: the company's own wording per purpose (sending a
 *    bill, a receipt, an overdue reminder) and channel (email, WhatsApp);
 *    without a row the shipped wording is used;
 *  · reminder_log: one row per bill and reminder step, so a reminder is never
 *    sent twice for the same step;
 *  · parties.send_reminders: a client may be kept out of automatic reminders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 12)->comment('invoice | receipt | reminder');
            $table->string('channel', 10)->comment('email | whatsapp');
            $table->string('subject', 200)->nullable();
            $table->text('body');
            $table->timestamps();

            $table->unique(['company_id', 'purpose', 'channel']);
        });

        Schema::create('reminder_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_days')->comment('Days after the due date');
            $table->string('recipient', 255)->nullable();
            $table->string('status', 10)->comment('sent | failed | skipped');
            $table->string('error', 255)->nullable();
            $table->timestamp('sent_at');

            $table->unique(['invoice_id', 'step_days']);
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->boolean('send_reminders')->default(true)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('parties', fn (Blueprint $table) => $table->dropColumn('send_reminders'));
        Schema::dropIfExists('reminder_log');
        Schema::dropIfExists('message_templates');
    }
};
