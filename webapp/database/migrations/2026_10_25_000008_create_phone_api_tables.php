<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Milestone M6: what the phone apps send and the server keeps (blueprint
 * 01 §4, 03 §5–7, 05 §4).
 *
 *  · devices: each signed-in phone, its token and when it last synced;
 *  · road events: every entry the crew make (fuel, toll, payment without
 *    receipt, driver change, delivered…), saved once per phone-made UUID,
 *    with three clocks (phone, uptime, GPS) and the server's flags;
 *  · event photos with their fingerprints (exact and look-alike);
 *  · GPS points in batches; driving shifts and duty sessions;
 *  · api_commands: a command sent twice (no network, retry) runs once.
 *
 * gps_points is the biggest table. It has no foreign keys on purpose (the
 * blueprint's monthly partitions cannot have them); integrity is checked in
 * code. Partitioning is added on the production server (see PROJECT-STATE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 100)->comment('Made by the app on first run');
            $table->string('platform', 10)->comment('android | ios');
            $table->string('model', 80)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('app_kind', 6)->default('crew')->comment('crew | owner');
            $table->text('push_token')->nullable();
            $table->unsignedBigInteger('token_id')->nullable()->comment('Sanctum token in use');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_gps_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'device_id']);
        });

        Schema::create('road_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique()->comment('Made by the phone: the same entry is saved once');
            $table->foreignId('trip_id')->constrained();
            $table->foreignId('person_id')->constrained('people')->comment('Who entered it');
            $table->string('type', 20);
            $table->string('category', 40)->nullable()->comment('Expense category code');
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('payment_mode', 12)->nullable()->comment('cash | upi | fuel_card | fastag | credit | party_paid | company_paid');
            $table->boolean('from_own_pocket')->default(false)->comment('Counts in the crew member\'s trip cash (settlement)');
            $table->unsignedInteger('odometer')->nullable();
            $table->json('data')->nullable()->comment('Fields of this event type (litres, plaza, slip no…)');
            $table->string('note', 500)->nullable();
            $table->dateTime('event_at')->comment('Phone clock at the moment of the event');
            $table->dateTime('recorded_at')->nullable()->comment('Phone clock when it was typed');
            $table->dateTime('trusted_at')->comment('Rebuilt by the server from uptime / GPS time');
            $table->unsignedBigInteger('uptime_ms')->nullable();
            $table->dateTime('gps_time')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->boolean('mock_location')->default(false);
            $table->string('device_id', 100)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('status', 14)->comment('info | waiting_2nd | waiting_check | approved | queried | rejected | disputed | cancelled');
            $table->foreignId('second_by')->nullable()->constrained('people')->nullOnDelete()->comment('Second approver (crew)');
            $table->foreignId('second_by_account')->nullable()->constrained('accounts')->nullOnDelete()->comment('Second approver (manager, one-driver trip)');
            $table->timestamp('second_at')->nullable();
            $table->string('second_reject_reason', 255)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('decision_note', 255)->nullable()->comment('Reject reason or query');
            $table->json('flags')->nullable()->comment('Automatic checks for the manager');
            $table->unsignedTinyInteger('photos_expected')->default(0);
            $table->unsignedTinyInteger('photos_received')->default(0);
            $table->unsignedBigInteger('complaint_id')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['trip_id', 'event_at']);
            $table->index(['person_id', 'event_at']);
        });

        // The old values each time a crew member edits their own entry before it is checked.
        Schema::create('road_event_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('road_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people');
            $table->json('before');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('road_event_id')->constrained()->cascadeOnDelete();
            $table->string('slot', 20)->comment('odometer | pump_meter | receipt | fuel_gauge | slip | pod | damage | location | other');
            $table->string('path');
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64);
            $table->char('ahash', 16)->comment('Look-alike fingerprint (average hash)');
            $table->foreignId('duplicate_of')->nullable()->constrained('event_photos')->nullOnDelete();
            $table->dateTime('taken_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['road_event_id', 'slot']);
            $table->index(['company_id', 'sha256']);
            $table->index(['company_id', 'ahash']);
        });

        Schema::create('gps_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('person_id');
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->dateTime('recorded_at');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->decimal('speed_kmh', 6, 1)->nullable();
            $table->unsignedSmallInteger('heading')->nullable();
            $table->boolean('mock')->default(false);
            $table->timestamp('received_at')->useCurrent();

            $table->index(['company_id', 'person_id', 'recorded_at']);
            $table->index(['trip_id', 'recorded_at']);
        });

        Schema::create('driving_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('start_odometer')->nullable();
            $table->unsignedInteger('end_odometer')->nullable();
            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['person_id', 'started_at']);
            $table->index(['trip_id', 'started_at']);
        });

        Schema::create('duty_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'started_at']);
        });

        Schema::create('api_commands', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('account_id');
            $table->string('name', 30);
            $table->smallInteger('http_status');
            $table->json('result');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
        });

        // A POD delivered on the phone (e-POD) for one load at one stop.
        Schema::create('consignment_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('road_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('trip_stop_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('packages')->nullable();
            $table->decimal('weight_mt', 12, 3)->nullable();
            $table->unsignedInteger('short_packages')->default(0);
            $table->unsignedInteger('damaged_packages')->default(0);
            $table->string('remarks', 500)->nullable();
            $table->string('receiver_name', 120)->nullable();
            $table->string('receiver_mobile', 20)->nullable();
            $table->boolean('otp_verified')->default(false);
            $table->dateTime('unloading_started_at')->nullable();
            $table->dateTime('unloading_ended_at')->nullable();
            $table->dateTime('delivered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_deliveries');
        Schema::dropIfExists('api_commands');
        Schema::dropIfExists('duty_sessions');
        Schema::dropIfExists('driving_shifts');
        Schema::dropIfExists('gps_points');
        Schema::dropIfExists('event_photos');
        Schema::dropIfExists('road_event_edits');
        Schema::dropIfExists('road_events');
        Schema::dropIfExists('devices');
    }
};
