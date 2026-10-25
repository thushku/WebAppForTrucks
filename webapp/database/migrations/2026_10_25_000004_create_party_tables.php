<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M3: parties (blueprint 06 §4, §11.2–11.3; 09 §2, §4, §5).
 *
 * One firm, many roles: the same company can be a client, a consignee and a
 * vendor at once, so roles live in their own table. A client billed plant by
 * plant has several GSTINs, each with its state and address. Hired trucks
 * (supplier vehicles) and rate contracts hang off the party.
 *
 * Money is DECIMAL(14,2); kinds and statuses are VARCHAR checked in code,
 * never ENUM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name', 150)->comment('Trade name, as people say it');
            $table->string('legal_name', 200)->nullable();
            $table->string('short_code', 6)->nullable()->comment('Shipper code in trip numbers (P1)');
            $table->foreignId('group_party_id')->nullable()->constrained('parties')->nullOnDelete()->comment('Parent, e.g. "UltraTech – all plants"');
            $table->string('mobile', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('address_line1', 200)->nullable();
            $table->string('address_line2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->char('country', 2)->default('IN');
            $table->string('postal_code', 20)->nullable();
            $table->string('pan', 10)->nullable()->comment('India PAN (also inside a GSTIN)');
            $table->string('tan', 10)->nullable();
            $table->string('udyam_number', 25)->nullable()->comment('MSME registration: payment within 45 days');
            $table->string('udyam_type', 10)->nullable()->comment('micro | small | medium');

            // Client: credit control and billing rules (09 §2.1).
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->unsignedSmallInteger('credit_days')->nullable();
            $table->string('due_basis', 12)->default('invoice')->comment('invoice | submission');
            $table->string('credit_action', 12)->default('warn')->comment('warn | block | override');
            $table->string('billing_cycle', 12)->default('per_lr')->comment('per_lr | weekly | fortnightly | monthly');
            $table->string('pod_required', 10)->default('none')->comment('none | original | epod');
            $table->json('required_refs')->nullable()->comment('References the client wants on bills: po, indent, shipment');
            $table->string('vendor_code_at_client', 40)->nullable();
            $table->string('portal_url', 255)->nullable();
            $table->decimal('late_interest_rate', 5, 2)->nullable()->comment('% per year after the due date');
            $table->string('tax_mode', 12)->nullable()->comment('India GTA: rcm | fcm5 | fcm18 | exempt (tax engine M8)');
            $table->boolean('tds_deducted')->default(false)->comment('Client deducts TDS from our bills');
            $table->string('tds_section', 10)->nullable();
            $table->decimal('tds_rate', 5, 2)->nullable();

            // Supplier (hired trucks) and vendor (09 §4.1, §5.1).
            $table->string('supplier_type', 20)->nullable()->comment('owner_operator | fleet_operator | broker');
            $table->string('vendor_category', 30)->nullable();
            $table->unsignedSmallInteger('payment_days')->nullable()->comment('Our credit days with a vendor/supplier');
            $table->decimal('lat', 10, 7)->nullable()->comment('Fuel pump location, to match fuel entries');
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('blacklisted')->default(false);
            $table->string('blacklist_reason', 255)->nullable();

            // Opening balance when moving from Tally, Excel or paper (posted to the books in M11).
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->string('opening_side', 10)->default('receivable')->comment('receivable (they owe us) | payable (we owe them)');
            $table->date('opening_as_of')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'short_code']);
            $table->index(['company_id', 'active', 'name']);
            $table->index(['company_id', 'mobile']);
        });

        Schema::create('party_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->comment('client | consignor | consignee | supplier | vendor | broker');
            $table->timestamps();

            $table->unique(['party_id', 'role']);
            $table->index(['company_id', 'role']);
        });

        // GSTINs / TRNs: plant-wise billing (one per state or branch of the client).
        Schema::create('party_tax_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->char('country', 2);
            $table->string('tax_id', 20)->comment('GSTIN (15) or UAE TRN (15 digits)');
            $table->string('state_code', 10)->nullable();
            $table->string('legal_name', 200)->nullable()->comment('Name on the registration');
            $table->text('address')->nullable()->comment('Billing address of this registration');
            $table->boolean('is_default')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'tax_id']);
            $table->index(['party_id', 'active']);
        });

        Schema::create('party_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('role', 30)->nullable()->comment('dispatch | accounts | bill_submission | owner | driver | other');
            $table->string('mobile', 20)->nullable();
            $table->string('alt_mobile', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['party_id', 'is_primary']);
        });

        // Where we pay suppliers and vendors. Account numbers and UPI ids are
        // encrypted at rest; only the last four digits are kept in clear.
        Schema::create('party_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('account_holder_name', 150);
            $table->string('bank_name', 120)->nullable();
            $table->string('branch', 120)->nullable();
            $table->text('account_number')->nullable()->comment('encrypted');
            $table->string('account_last4', 4)->nullable();
            $table->string('ifsc', 11)->nullable();
            $table->string('swift', 11)->nullable();
            $table->text('iban')->nullable()->comment('encrypted');
            $table->text('upi_id')->nullable()->comment('encrypted');
            $table->boolean('is_default')->default(false);
            $table->timestamp('verified_at')->nullable()->comment('Penny-drop check, phase 2');
            $table->timestamps();

            $table->index(['party_id', 'is_default']);
        });

        // Hired / market trucks of a supplier (09 §4.1).
        Schema::create('supplier_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('reg_no', 20)->comment('As written: KL-07-AB-1234');
            $table->string('reg_key', 20)->comment('Letters and digits only, for finding duplicates');
            $table->string('truck_type', 40)->nullable();
            $table->string('body_type', 40)->nullable();
            $table->decimal('capacity_mt', 8, 3)->nullable();
            $table->string('rc_owner_name', 150)->nullable();
            $table->string('driver_name', 120)->nullable();
            $table->string('driver_mobile', 20)->nullable();
            $table->string('driver_licence', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'reg_key']);
            $table->index(['party_id', 'active']);
        });

        // Agreed rates with a client (or a supplier's hire rates): versioned,
        // approved, and found automatically when a trip or LR is made.
        Schema::create('rate_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('version')->default(1);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('status', 12)->default('draft')->comment('draft | approved | cancelled');
            $table->foreignId('approved_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('diesel_base_price', 10, 2)->nullable()->comment('Escalation clause: price the rates assume');
            $table->decimal('escalation_percent_per_rupee', 6, 3)->nullable()->comment('Rate change % per ₹1 diesel change');
            $table->text('payment_terms')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'party_id', 'status', 'valid_from']);
        });

        Schema::create('rate_contract_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_place_id')->constrained('places');
            $table->foreignId('to_place_id')->constrained('places');
            $table->string('material', 40)->nullable()->comment('Master list code; empty = any material');
            $table->string('truck_type', 40)->nullable()->comment('Master list code; empty = any truck');
            $table->string('basis', 10)->default('trip')->comment('trip | tonne | kg | km | package');
            $table->decimal('rate', 14, 2);
            $table->decimal('min_weight_mt', 10, 3)->nullable()->comment('Minimum guaranteed weight');
            $table->unsignedSmallInteger('detention_free_hours')->nullable();
            $table->decimal('detention_rate_per_day', 14, 2)->nullable();
            $table->decimal('loading_charge', 14, 2)->nullable();
            $table->decimal('unloading_charge', 14, 2)->nullable();
            $table->decimal('extra_drop_charge', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'from_place_id', 'to_place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_contract_lines');
        Schema::dropIfExists('rate_contracts');
        Schema::dropIfExists('supplier_vehicles');
        Schema::dropIfExists('party_bank_accounts');
        Schema::dropIfExists('party_contacts');
        Schema::dropIfExists('party_tax_ids');
        Schema::dropIfExists('party_roles');
        Schema::dropIfExists('parties');
    }
};
