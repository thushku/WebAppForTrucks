<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M2: how a company is set up.
 *
 *  · the company's identity and address (printed on documents later);
 *  · its rules (dual approval on/off and the rest, blueprint 03 / 02 §3);
 *  · branches, places with short codes (trip numbers, P1), master lists;
 *  · number series for every document type (ported from Clinic Ledger);
 *  · QR sign-in to the web app from the owner app (P7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('address_line1', 200)->nullable()->after('legal_name');
            $table->string('address_line2', 200)->nullable()->after('address_line1');
            $table->string('city', 100)->nullable()->after('address_line2');
            $table->string('state_code', 10)->nullable()->after('city')->comment('India GST state code, e.g. 32; UAE emirate, e.g. DU');
            $table->string('postal_code', 20)->nullable()->after('state_code');
            $table->string('phone', 20)->nullable()->after('postal_code');
            $table->string('email', 255)->nullable()->after('phone');
            $table->string('website', 255)->nullable()->after('email');
            $table->string('logo_path')->nullable()->after('website');
        });

        // Typed company rules with defaults in code (CompanySettings::DEFAULTS).
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('code', 10)->comment('Short code used in number formats, e.g. COK');
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_head_office')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        // Places with short codes: a trip from Kochi (COK) to Mumbai (BOM).
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('short_code', 6);
            $table->string('type', 30)->default('city')->comment('city | customer_site | pump | toll | weighbridge | godown | border | other');
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->char('country', 2)->default('IN');
            $table->string('postal_code', 20)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'short_code']);
            $table->index(['company_id', 'active', 'name']);
        });

        // Small company-editable lists (materials, expense categories, truck
        // types, document types, leave types…). One table, many lists:
        // each list's extra fields live in `meta`.
        Schema::create('master_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('list', 40);
            $table->string('code', 40);
            $table->string('name', 120);
            $table->json('meta')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_system')->default(false)->comment('Came with the country preset; can be renamed or hidden, not deleted');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'list', 'code']);
            $table->index(['company_id', 'list', 'active', 'sort']);
        });

        // How each document type is numbered (Clinic Ledger's DocumentNumbering).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('doc_type', 40);
            $table->string('scope_type', 20)->default('company')->comment('company | branch | registration');
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->string('prefix', 20)->default('');
            $table->string('format', 60)->default('{PREFIX}{FY2}/{0001}');
            $table->unsignedInteger('start_number')->default(1)->comment('Where every new period starts');
            $table->string('reset_policy', 20)->default('fiscal_year')->comment('fiscal_year | monthly | daily | never');
            $table->unsignedTinyInteger('max_length')->nullable()->comment('India tax invoice: 16');
            $table->timestamps();

            $table->unique(['company_id', 'doc_type', 'scope_type', 'scope_id'], 'document_sequences_scope_unique');
        });

        // One counter per sequence and period ("2026-27", "2026-10", "2026-10-25"
        // or "all"), so a document dated in an earlier period continues that
        // period's own series and never moves the current one.
        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_sequence_id')->constrained()->cascadeOnDelete();
            $table->string('period_key', 12);
            $table->unsignedInteger('next_number');
            $table->timestamp('last_assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['document_sequence_id', 'period_key']);
        });

        // "Use on desktop": the web login page shows a QR code; the owner app
        // scans it and signs the browser in (P7). Only the token's hash is kept.
        Schema::create('web_login_qr', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('poll_hash', 64)->unique()->comment('Second secret held only by the waiting browser');
            $table->timestamp('expires_at');
            $table->foreignId('approved_account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('approved_company_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_login_qr');
        Schema::dropIfExists('document_counters');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('master_items');
        Schema::dropIfExists('places');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('company_settings');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['address_line1', 'address_line2', 'city', 'state_code', 'postal_code', 'phone', 'email', 'website', 'logo_path']);
        });
    }
};
