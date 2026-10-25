<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M20: notifications (blueprint 01 "Queue worker … push"; decision
 * D18). One row per person told something, in their company: the inbox the
 * web bell and the phone apps read, and the record of the push sent to their
 * phones. Like news collapses into one row (collapse_key) instead of piling up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40)->comment('trip.sent, entry.queried, approvals.waiting …');
            $table->string('title', 150);
            $table->string('body', 500)->nullable();
            $table->string('url', 255)->nullable()->comment('Where the web bell opens');
            $table->json('data')->nullable()->comment('What the phone app opens: type and ids');
            $table->string('collapse_key', 80)->nullable()->comment('Like news updates the unread row instead of adding one');
            $table->unsignedInteger('times')->default(1);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->string('push_result', 20)->nullable()->comment('sent | no_device | failed | waiting | held');
            $table->timestamps();

            $table->index(['company_id', 'account_id', 'read_at'], 'user_notifications_inbox');
            $table->index(['company_id', 'account_id', 'kind', 'collapse_key'], 'user_notifications_collapse');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
