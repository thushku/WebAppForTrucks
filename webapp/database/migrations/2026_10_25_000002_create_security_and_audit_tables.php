<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-in safety and the audit trail.
 *
 * A one-time code, a trusted browser and a reset/verification link all
 * belong to a "subject": a person's account or a console admin. The plain
 * code or token is never stored — only its hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 255)->unique();
            $table->string('password_hash');
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->unsignedTinyInteger('lock_level')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('locked_hard_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('status', 20)->default('active');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20)->comment('account | admin');
            $table->unsignedBigInteger('subject_id');
            $table->string('purpose', 30)->comment('login | step_up | mobile_login | mobile_verify | device_enrol');
            $table->string('channel', 20)->default('email')->comment('email | sms | whatsapp');
            $table->string('destination_hint', 120)->nullable();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'purpose', 'created_at'], 'otp_subject_purpose_index');
        });

        Schema::create('trusted_browsers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('user_agent')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // Email verification, password reset and invitations: one table of
        // single-use links.
        Schema::create('password_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->string('purpose', 20)->comment('verify | reset | invite');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'purpose']);
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 20)->comment('account | admin | unknown');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('identifier', 255)->comment('The email or mobile that was typed');
            $table->boolean('succeeded');
            $table->string('failure_reason', 40)->nullable();
            $table->string('method', 20)->default('password');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('attempted_at');

            $table->index(['subject_type', 'subject_id', 'attempted_at']);
            $table->index(['ip', 'attempted_at']);
        });

        // Append-only. Nothing updates or deletes a row here, and the app has
        // no screen or switch to turn it off (India's audit-trail rule).
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->comment('Null = platform-level event');
            $table->string('actor_type', 20)->comment('account | admin | system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label', 150)->nullable()->comment('Kept so the row reads right after the person leaves');
            $table->string('action', 80);
            $table->string('entity_type', 60)->nullable();
            $table->string('entity_id', 40)->nullable();
            $table->string('entity_label', 200)->nullable();
            $table->json('change_set')->nullable();
            $table->string('summary', 500);
            $table->string('reason', 500)->nullable();
            $table->string('severity', 12)->default('info')->comment('info | notable | sensitive');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['company_id', 'occurred_at']);
            $table->index(['company_id', 'entity_type', 'entity_id']);
        });

        // Console-wide switches: captcha mode, trial days, branding and so on.
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Laravel Sanctum (approved, D18): one token per phone.
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('password_tokens');
        Schema::dropIfExists('trusted_browsers');
        Schema::dropIfExists('otp_challenges');
        Schema::dropIfExists('admins');
    }
};
