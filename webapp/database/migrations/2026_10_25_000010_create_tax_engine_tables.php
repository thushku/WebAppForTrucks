<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M8: the tax engine (blueprint 11, decision D15).
 *
 * Rates and rules are data with start and end dates; the code only knows
 * how to calculate. A company gets its country's preset (India GST, UAE VAT)
 * as rows it can adjust, and a new rate is a new dated row — a rate that an
 * invoice used is never edited.
 *
 *  · tax_registrations: our GSTINs / TRNs (one company may have several);
 *    India: the GTA option chosen for each financial year (Annexure V);
 *  · tax_types: the parts a tax is made of (CGST, SGST, UTGST, IGST, VAT);
 *  · tax_codes: what a line is taxed as — category (S standard, Z zero, E
 *    exempt, AE reverse charge, O outside scope), the printed legal text,
 *    the evidence a zero rate needs;
 *  · tax_rates: each code's dated rate and how it splits (same state / other
 *    state / union territory, or fixed parts);
 *  · tax_rules: freight facts → tax code (service class, GTA option,
 *    transport type, our role as main carrier or subcontractor, the buyer).
 *    The most specific matching rule wins; no match stops the invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->char('country', 2);
            $table->string('regime', 10)->comment('in_gst | ae_vat');
            $table->string('tax_id', 20)->comment('GSTIN or TRN');
            $table->string('state_code', 10)->nullable()->comment('India: from the GSTIN; UAE: emirate');
            $table->string('legal_name', 200)->nullable();
            $table->text('address')->nullable()->comment('Principal place of business on the registration');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->date('registered_on')->nullable();
            $table->date('cancelled_on')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'tax_id']);
        });

        Schema::create('tax_registration_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_registration_id')->constrained()->cascadeOnDelete();
            $table->string('fy_key', 9)->comment('2026-27');
            $table->string('gta_option', 8)->comment('rcm_5 | fcm_5 | fcm_18 | non_gta');
            $table->string('annexure_ref', 60)->nullable()->comment('Annexure V acknowledgement');
            $table->date('declared_on')->nullable();
            $table->timestamps();

            $table->unique(['tax_registration_id', 'fy_key']);
        });

        Schema::create('tax_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('regime', 10);
            $table->string('code', 12)->comment('CGST, SGST, UTGST, IGST, VAT');
            $table->string('name', 80);
            $table->string('ledger_code', 20)->nullable()->comment('Output / input tax accounts (M11)');
            $table->timestamps();

            $table->unique(['company_id', 'regime', 'code']);
        });

        Schema::create('tax_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('regime', 10);
            $table->string('code', 20);
            $table->string('name', 120);
            $table->char('category', 2)->comment('S standard | Z zero | E exempt | AE reverse charge | O outside scope');
            $table->boolean('reverse_charge')->default(false)->comment('The buyer pays the tax; the invoice shows it as notional');
            $table->string('legal_text', 255)->nullable()->comment('Printed on the invoice');
            $table->string('exemption_reason', 120)->nullable()->comment('Law reference for exempt / zero / outside scope');
            $table->json('evidence')->nullable()->comment('Papers a zero rate needs: transport_document, exit_or_customs…');
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_code_id')->constrained()->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->decimal('rate', 9, 6)->comment('Total rate, e.g. 18.000000');
            $table->json('parts')->nullable()->comment('{"intra":{"CGST":9,"SGST":9},"inter":{"IGST":18},"ut":{"CGST":9,"UTGST":9}} or {"fixed":{"VAT":5}}');
            $table->string('note', 255)->nullable()->comment('Source: notification, circular (verify before go-live)');
            $table->timestamps();

            $table->index(['tax_code_id', 'valid_from']);
        });

        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('regime', 10);
            // Conditions: empty means "any".
            $table->string('service_class', 30)->nullable();
            $table->string('gta_option', 8)->nullable();
            $table->string('transport_type', 14)->nullable()->comment('domestic | export | import | international');
            $table->string('carrier_role', 4)->nullable()->comment('main | sub');
            $table->boolean('buyer_registered')->nullable();
            $table->string('buyer_category', 14)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->smallInteger('priority')->default(0);
            // Outcome.
            $table->string('tax_code', 20);
            $table->string('sac_code', 10)->nullable()->comment('India SAC / HSN printed on the line');
            $table->string('note', 255)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'regime', 'active']);
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->string('tax_category', 14)->nullable()->after('tax_mode')
                ->comment('regular | unregistered | sez | government | gta | composition | overseas; empty: from the tax id');
        });
    }

    public function down(): void
    {
        Schema::table('parties', fn (Blueprint $table) => $table->dropColumn('tax_category'));
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_codes');
        Schema::dropIfExists('tax_types');
        Schema::dropIfExists('tax_registration_years');
        Schema::dropIfExists('tax_registrations');
    }
};
