<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M13: the SaaS side, all set by the super-admin and nothing fixed
 * in code (decision D20).
 *
 *  · plans: what a company can buy — per truck, flat or lifetime; monthly,
 *    annual or one price; the limits (trucks, users, storage) and the
 *    modules included; a student tier; per country and currency;
 *  · subscriptions: a company's plan from a date to a date, with what was
 *    paid, the coupon and the reference — recorded by the console (online
 *    payment comes when a payment gateway is approved);
 *  · coupons: codes for a percentage or an amount off, with dates, a use
 *    limit and the plans they apply to;
 *  · companies.plan_id: the plan the company is on now (limits read it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->char('country', 2)->nullable()->comment('Null: every country');
            $table->char('currency', 3);
            $table->string('pricing', 10)->comment('per_truck | flat | lifetime');
            $table->decimal('price_monthly', 12, 2)->nullable();
            $table->decimal('price_annual', 12, 2)->nullable();
            $table->decimal('price_lifetime', 12, 2)->nullable();
            $table->unsignedInteger('max_trucks')->nullable()->comment('Null: no limit');
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('storage_mb')->nullable();
            $table->json('modules')->nullable()->comment('Null: every module');
            $table->boolean('is_student')->default(false);
            $table->boolean('is_public')->default(true)->comment('Shown on the website and the billing page');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('kind', 8)->comment('percent | fixed');
            $table->decimal('value', 12, 2);
            $table->char('currency', 3)->nullable()->comment('For a fixed amount');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->json('plan_ids')->nullable()->comment('Null: every plan');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('period', 10)->comment('monthly | annual | lifetime');
            $table->string('status', 10)->default('active')->comment('active | ended | cancelled');
            $table->date('starts_on');
            $table->date('ends_on')->nullable()->comment('Null: lifetime');
            $table->unsignedInteger('trucks')->nullable()->comment('Trucks billed (per-truck plans)');
            $table->decimal('amount', 12, 2)->comment('Charged, after the coupon');
            $table->decimal('discount', 12, 2)->default(0);
            $table->char('currency', 3);
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 80)->nullable()->comment('Payment reference (UTR, invoice)');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by_admin')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status', 'ends_on']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('status')->constrained()->nullOnDelete();
            $table->string('suspended_reason', 255)->nullable()->after('read_only_since');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('suspended_reason');
        });
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('plans');
    }
};
