<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who people are, which companies exist, and who works where.
 *
 *  · accounts — one row per real person (a login identity). A driver who
 *    works for two owners has ONE account and two memberships (P7).
 *  · companies — one row per customer ("tenant"). Every company-owned table
 *    elsewhere carries company_id, and that column leads its indexes.
 *  · company_users — the membership: this person, in this company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            // Either may be the sign-in name; both are unique when present.
            $table->string('email', 255)->nullable()->unique();
            $table->string('mobile', 20)->nullable()->unique()->comment('E.164, e.g. +919876543210');
            $table->string('password_hash')->nullable();
            $table->string('pin_hash')->nullable()->comment('Phone-app PIN, never the plain value');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->string('locale', 10)->default('en');
            $table->string('photo_path')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->boolean('mfa_enabled')->default(false);
            // The lock ladder: 15 minutes, then an hour, then until unlocked by hand.
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->unsignedTinyInteger('lock_level')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('locked_hard_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('status', 20)->default('active')->comment('active | suspended');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Public id; never expose the number');
            $table->string('name', 150);
            $table->string('legal_name', 200)->nullable();
            $table->string('short_code', 10)->nullable();
            $table->char('country', 2)->default('IN');
            $table->char('currency', 3)->default('INR');
            $table->string('timezone', 40)->default('Asia/Kolkata');
            $table->unsignedTinyInteger('fy_start_month')->default(4)->comment('India 4 (April), UAE 1');
            $table->string('status', 20)->default('trial')->comment('trial | active | lapsed | suspended | closing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('mfa_policy', 10)->default('optional')->comment('off | optional | always');
            $table->foreignId('owner_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            // Account deletion waits 72 hours and can be cancelled (09 §8).
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamp('read_only_since')->nullable();
            $table->timestamps();

            $table->index(['status', 'trial_ends_at']);
        });

        Schema::create('company_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active')->comment('invited | active | suspended | left');
            $table->string('title', 100)->nullable()->comment('Shown on screens, e.g. "Dispatch"');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'account_id']);
            $table->index(['account_id', 'status']);
        });

        // Roles are company data: each company gets the standard ladder and
        // may rename it or change what each role may do.
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 80);
            $table->unsignedTinyInteger('level')->default(5)->comment('0 = owner; lower is more senior');
            $table->boolean('is_system')->default(false);
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission_code', 60);
            // An explicit "deny" beats a grant from any other role.
            $table->boolean('allow')->default(true);
            $table->timestamps();

            $table->unique(['role_id', 'permission_code']);
        });

        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->comment('Limit to one branch; null = whole company');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['company_user_id', 'role_id', 'branch_id']);
            $table->index(['company_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('company_users');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('accounts');
    }
};
