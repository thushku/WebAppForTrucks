<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M4: trucks and people (blueprint 06 §5–6, §13; 09 §3, §6).
 *
 *  · trucks with their identity, build, capacities, ownership and loan;
 *  · complaints raised by the crew or the office, with a trail of updates;
 *  · service schedules (every N km or N days) and service jobs with parts
 *    and labour;
 *  · people (drivers, helpers, office staff), each with an optional phone-app
 *    login, and one-time activation codes for the crew app.
 *
 * ID numbers and bank details of people are encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('reg_no', 20)->comment('As written: KL-07-AB-1234');
            $table->string('reg_key', 20)->comment('Letters and digits only');
            $table->string('registration_state', 10)->nullable();
            $table->string('registered_rto', 80)->nullable();
            $table->date('registration_date')->nullable();
            $table->string('rc_owner_name', 150);
            $table->string('vehicle_class', 60)->nullable()->comment('Goods carrier, HGV…');
            $table->string('make', 60)->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedSmallInteger('manufacture_year')->nullable();
            $table->string('chassis_no', 30)->nullable();
            $table->string('engine_no', 30)->nullable();
            $table->string('emission_norm', 12)->nullable()->comment('BS4 | BS6 | Euro 5 …');
            $table->string('fuel_type', 12)->default('diesel')->comment('diesel | cng | lng | electric | petrol');
            $table->string('truck_type', 40)->nullable()->comment('Master list code');
            $table->string('body_type', 40)->nullable()->comment('Master list code');
            $table->unsignedTinyInteger('wheels')->nullable();
            $table->string('axle_config', 20)->nullable()->comment('6x2, 8x2, 10x2 …');
            $table->boolean('lift_axle')->default(false);
            $table->decimal('deck_length_ft', 6, 1)->nullable();
            $table->string('rate_card_label', 40)->nullable()->comment('"32 ft MXL"');
            $table->unsignedInteger('gvw_kg')->nullable();
            $table->unsignedInteger('unladen_kg')->nullable();
            $table->decimal('payload_mt', 8, 3)->nullable();
            $table->unsignedSmallInteger('fuel_tank_l')->nullable();
            $table->decimal('mileage_loaded_kmpl', 5, 2)->nullable();
            $table->decimal('mileage_empty_kmpl', 5, 2)->nullable();
            $table->string('ownership', 12)->default('own')->comment('own | attached | leased');
            $table->foreignId('owner_party_id')->nullable()->constrained('parties')->nullOnDelete()->comment('Attached truck: its owner');
            $table->string('financier', 120)->nullable();
            $table->decimal('emi_amount', 14, 2)->nullable();
            $table->unsignedTinyInteger('emi_day')->nullable();
            $table->date('loan_ends_on')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->string('status', 20)->default('idle')->comment('idle | on_trip | workshop | breakdown | out_of_service | sold');
            $table->unsignedInteger('odometer_km')->nullable();
            $table->timestamp('odometer_at')->nullable();
            $table->string('fastag_no', 40)->nullable()->comment('FASTag (India) / Salik tag (UAE)');
            $table->string('tracker_device_id', 60)->nullable()->comment('AIS-140 / SecurePath / Asateel');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('sold_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reg_key']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique()->comment('Made by the phone when raised offline');
            $table->string('number', 30);
            $table->foreignId('truck_id')->constrained();
            $table->unsignedBigInteger('trip_id')->nullable()->comment('Linked trip (M5)');
            $table->foreignId('raised_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('category', 40)->nullable()->comment('Master list code');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('normal')->comment('low | normal | high | urgent');
            $table->string('status', 20)->default('open')->comment('open | in_progress | waiting_parts | solved | closed');
            $table->foreignId('assigned_to')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('vendor_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->timestamp('solved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('service_job_id')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'priority']);
            $table->index(['truck_id', 'status']);
        });

        Schema::create('complaint_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->text('note')->nullable();
            $table->string('status_from', 20)->nullable();
            $table->string('status_to', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('service_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('truck_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80)->comment('Engine oil, general service, greasing…');
            $table->unsignedInteger('every_km')->nullable();
            $table->unsignedSmallInteger('every_days')->nullable();
            $table->unsignedInteger('last_done_km')->nullable();
            $table->date('last_done_on')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);
            $table->foreignId('truck_id')->constrained();
            $table->date('job_date');
            $table->unsignedInteger('odometer_km')->nullable();
            $table->foreignId('vendor_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('kind', 12)->default('scheduled')->comment('scheduled | breakdown | accident | other');
            $table->string('status', 12)->default('open')->comment('open | done | cancelled');
            $table->foreignId('service_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('parts_total', 14, 2)->default(0);
            $table->decimal('labour_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('bill_no', 40)->nullable()->comment("Vendor's bill number");
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['truck_id', 'job_date']);
        });

        Schema::create('service_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10)->default('part')->comment('part | labour');
            $table->string('description', 200);
            $table->decimal('qty', 12, 3)->default(1);
            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_user_id')->nullable()->unique()->constrained()->nullOnDelete()->comment('Phone-app or web login in this company');
            $table->string('name', 150);
            $table->string('type', 12)->default('driver')->comment('driver | helper | office | mechanic | other');
            $table->string('mobile', 20)->nullable();
            $table->string('alt_mobile', 20)->nullable();
            $table->string('employee_code', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('joining_date')->nullable();
            $table->date('leaving_date')->nullable();
            $table->string('status', 12)->default('active')->comment('active | on_leave | left');
            $table->string('photo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_name', 120)->nullable();
            $table->string('emergency_mobile', 20)->nullable();
            $table->string('blood_group', 4)->nullable();
            $table->string('licence_no', 30)->nullable();
            $table->string('licence_class', 40)->nullable();
            $table->date('licence_expiry')->nullable();
            $table->boolean('hazardous_endorsement')->default(false);
            $table->string('badge_no', 30)->nullable();
            $table->char('nationality', 2)->nullable();
            $table->string('id_type', 20)->nullable()->comment('aadhaar | emirates_id | passport | voter_id');
            $table->text('id_number')->nullable()->comment('encrypted');
            $table->string('id_last4', 4)->nullable();
            $table->string('bank_account_holder', 150)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->text('bank_account_number')->nullable()->comment('encrypted');
            $table->string('bank_last4', 4)->nullable();
            $table->string('bank_ifsc', 11)->nullable();
            $table->text('bank_iban')->nullable()->comment('encrypted');
            $table->text('upi_id')->nullable()->comment('encrypted');
            $table->string('pay_basis', 10)->default('monthly')->comment('monthly | per_trip | per_km');
            $table->decimal('monthly_salary', 14, 2)->nullable();
            $table->decimal('per_trip_rate', 14, 2)->nullable();
            $table->decimal('per_km_rate', 10, 2)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('default_truck_id')->nullable()->constrained('trucks')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'mobile']);
            $table->unique(['company_id', 'employee_code']);
            $table->index(['company_id', 'type', 'status']);
            $table->index(['company_id', 'licence_expiry']);
        });

        // A one-time code the office reads out or shows as a QR; the crew app
        // sends it with the mobile number to get its device token (M6).
        Schema::create('activation_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('used_device', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['person_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_codes');
        Schema::dropIfExists('people');
        Schema::dropIfExists('service_job_items');
        Schema::dropIfExists('service_jobs');
        Schema::dropIfExists('service_schedules');
        Schema::dropIfExists('complaint_updates');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('trucks');
    }
};
