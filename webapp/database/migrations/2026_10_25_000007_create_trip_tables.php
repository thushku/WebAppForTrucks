<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M5: trips (blueprint 03, 12, 10 §4, §6.1; 13 §A).
 *
 *  · a trip is one journey of one truck — ours or hired — with its crew,
 *    ordered stops and one or more consignments (each one LR);
 *  · full load, part load (each consignment picks up and drops at its own
 *    stops) or mixed;
 *  · a hired truck gets a lorry hire challan: hire, advances, deductions,
 *    commission, TDS, balance;
 *  · broker commissions in both directions, and cash advances to the crew.
 *
 * Money DECIMAL(14,2), weights DECIMAL(12,3); kinds and statuses are VARCHAR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('trip_number', 40)->nullable()->comment('Fixed when sent to the crew (P1)');
            $table->date('trip_date');
            $table->string('load_type', 6)->default('ftl')->comment('ftl | ptl | mixed');
            $table->string('trip_kind', 10)->default('loaded')->comment('loaded | empty | round');
            $table->string('truck_source', 6)->default('own')->comment('own | hired');
            $table->foreignId('truck_id')->nullable()->constrained();
            $table->foreignId('supplier_party_id')->nullable()->constrained('parties');
            $table->foreignId('supplier_vehicle_id')->nullable()->constrained();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('return_of_trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('from_place_id')->constrained('places');
            $table->foreignId('to_place_id')->constrained('places');
            $table->unsignedInteger('planned_km')->nullable();
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('expected_delivery_at')->nullable();
            $table->decimal('budget_fuel', 14, 2)->nullable();
            $table->decimal('budget_toll', 14, 2)->nullable();
            $table->decimal('budget_bata', 14, 2)->nullable();
            $table->decimal('other_charges', 14, 2)->default(0)->comment('Billed to the client on top of freight (13 §A #1)');
            $table->string('cost_allocation_method', 10)->nullable()->comment('freight | weight | tonne_km; empty = company rule');
            $table->text('instructions')->nullable()->comment('For the crew');
            $table->text('office_note')->nullable()->comment('Office only; the crew never see it (13 §A #2)');
            $table->string('status', 10)->default('draft')->comment('draft | assigned | accepted | running | ended | settled | closed | cancelled');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('start_odometer')->nullable();
            $table->unsignedInteger('end_odometer')->nullable();
            $table->boolean('ended_early')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->string('dispatch_override_reason', 255)->nullable()->comment('Sent despite an expired paper (logged)');
            $table->foreignId('dispatch_override_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'trip_number']);
            $table->index(['company_id', 'status', 'trip_date']);
            $table->index(['company_id', 'trip_date']);
            $table->index(['truck_id', 'status']);
        });

        Schema::create('trip_crew', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people');
            $table->string('role', 10)->comment('driver | co_driver | helper');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'person_id']);
            $table->index(['person_id', 'trip_id']);
        });

        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seq');
            $table->foreignId('place_id')->constrained('places');
            $table->string('kind', 10)->default('other')->comment('load | unload | both | fuel | border | other');
            $table->dateTime('planned_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('departed_at')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'seq']);
        });

        // A consignment is one booking = one LR (lorry receipt / bilty).
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lr_number', 40)->nullable();
            $table->date('lr_date')->nullable();
            $table->string('status', 14)->default('booked')->comment('booked | picked_up | in_transit | delivered | pod_received | billed | cancelled');
            $table->foreignId('billing_party_id')->constrained('parties');
            $table->foreignId('consignor_party_id')->nullable()->constrained('parties');
            $table->foreignId('consignee_party_id')->nullable()->constrained('parties');
            $table->foreignId('pickup_stop_id')->nullable()->constrained('trip_stops')->nullOnDelete();
            $table->foreignId('drop_stop_id')->nullable()->constrained('trip_stops')->nullOnDelete();
            $table->string('material', 40)->nullable()->comment('Master list code');
            $table->string('description', 200)->nullable()->comment('"Said to contain"');
            $table->unsignedInteger('packages')->nullable();
            $table->string('package_type', 20)->nullable()->comment('bags | boxes | drums | pallets | loose …');
            $table->decimal('actual_weight_mt', 12, 3)->nullable();
            $table->decimal('charged_weight_mt', 12, 3)->nullable();
            $table->string('freight_basis', 10)->default('trip')->comment('trip | tonne | kg | km | package');
            $table->decimal('rate', 14, 2)->nullable();
            $table->decimal('freight', 14, 2)->default(0);
            $table->decimal('loading_charge', 14, 2)->default(0);
            $table->decimal('unloading_charge', 14, 2)->default(0);
            $table->decimal('other_charges', 14, 2)->default(0);
            $table->unsignedSmallInteger('detention_free_hours')->nullable();
            $table->decimal('detention_rate_per_day', 14, 2)->nullable();
            $table->decimal('advance_from_party', 14, 2)->default(0);
            $table->string('payment_type', 14)->default('to_be_billed')->comment('paid | to_pay | to_be_billed | foc');
            $table->string('gst_payable_by', 12)->nullable()->comment('consignor | consignee | transporter');
            $table->string('consignor_invoice_no', 40)->nullable();
            $table->date('consignor_invoice_date')->nullable();
            $table->decimal('consignor_invoice_value', 14, 2)->nullable();
            $table->decimal('declared_value', 14, 2)->nullable();
            $table->string('risk', 8)->default('owner')->comment('owner | carrier');
            $table->boolean('hazardous')->default(false);
            $table->boolean('over_dimension')->default(false);
            $table->string('seal_no', 40)->nullable();
            $table->string('container_no', 20)->nullable();
            $table->string('client_ref', 60)->nullable()->comment('PO / indent / shipment no.');
            $table->string('uae_transport_type', 12)->nullable()->comment('domestic | export | import | international');
            $table->string('uae_role', 6)->nullable()->comment('main | sub (carrier)');
            $table->foreignId('rate_contract_line_id')->nullable()->constrained()->nullOnDelete()->comment('Where the rate came from');
            $table->timestamp('delivered_at')->nullable();
            $table->string('pod_status', 10)->default('none')->comment('none | received | verified');
            $table->timestamp('pod_received_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'lr_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'billing_party_id', 'status']);
            $table->index(['trip_id']);
        });

        Schema::create('consignment_ewaybills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->string('number', 12);
            $table->dateTime('valid_until')->nullable();
            $table->string('invoice_no', 40)->nullable();
            $table->decimal('value', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });

        Schema::create('trip_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people');
            $table->decimal('amount', 14, 2);
            $table->string('mode', 6)->default('cash')->comment('cash | upi | bank');
            $table->date('given_on');
            $table->string('reference', 60)->nullable()->comment('UTR / UPI ref');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();
        });

        // Lorry hire challan for a hired (market) truck (10 §6.1).
        Schema::create('hire_challans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40)->nullable();
            $table->date('challan_date');
            $table->foreignId('trip_id')->constrained();
            $table->foreignId('supplier_party_id')->constrained('parties');
            $table->foreignId('supplier_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vehicle_reg', 20);
            $table->string('driver_name', 120)->nullable();
            $table->string('driver_mobile', 20)->nullable();
            $table->string('driver_licence', 30)->nullable();
            $table->string('hire_basis', 10)->default('fixed')->comment('fixed | tonne');
            $table->decimal('hire_rate', 14, 2)->nullable();
            $table->decimal('hire_weight_mt', 12, 3)->nullable();
            $table->decimal('hire_amount', 14, 2);
            $table->foreignId('broker_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->string('commission_mode', 12)->default('deducted')->comment('deducted (from the supplier) | paid (by us)');
            $table->decimal('office_charge', 14, 2)->default(0)->comment('Munshiana');
            $table->string('tds_section', 10)->nullable();
            $table->decimal('tds_rate', 5, 2)->default(0);
            $table->decimal('tds_amount', 14, 2)->default(0);
            $table->string('tds_nil_reason', 150)->nullable()->comment('"Declaration of 10 trucks or fewer for 2026-27"');
            $table->unsignedSmallInteger('balance_days_after_pod')->nullable();
            $table->text('terms')->nullable();
            $table->string('status', 10)->default('draft')->comment('draft | issued | settled | cancelled');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'supplier_party_id', 'status']);
        });

        Schema::create('hire_challan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hire_challan_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->comment('advance | fuel | detention | extra | deduction | payment');
            $table->decimal('amount', 14, 2);
            $table->date('line_date');
            $table->string('mode', 6)->nullable()->comment('cash | upi | bank (money paid)');
            $table->string('paid_to', 10)->nullable()->comment('owner | broker | driver');
            $table->string('reference', 60)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();
        });

        // Broker commission, either way (12 §5.1).
        Schema::create('broker_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broker_party_id')->constrained('parties');
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hire_challan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 10)->comment('payable (we pay) | receivable (we earn) | deducted (from the supplier)');
            $table->string('basis', 8)->default('fixed')->comment('fixed | percent');
            $table->decimal('percent', 6, 3)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broker_commissions');
        Schema::dropIfExists('hire_challan_lines');
        Schema::dropIfExists('hire_challans');
        Schema::dropIfExists('trip_advances');
        Schema::dropIfExists('consignment_ewaybills');
        Schema::dropIfExists('consignments');
        Schema::dropIfExists('trip_stops');
        Schema::dropIfExists('trip_crew');
        Schema::dropIfExists('trips');
    }
};
