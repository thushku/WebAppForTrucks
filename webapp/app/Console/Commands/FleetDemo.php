<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\CompanyUser;
use App\Modules\Parties\Models\PartyContact;
use App\Modules\Parties\Models\SupplierVehicle;
use App\Modules\Parties\Services\PartyService;
use App\Modules\Parties\Services\TaxIds;
use App\Modules\Settings\Models\Branch;
use App\Modules\Settings\Models\Place;
use App\Platform\Authorization\RoleProvisioner;
use App\Platform\Signup\SignupService;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * A demo company to look around in: Sharma Roadlines, Kochi, with a Mumbai
 * branch, the places its trucks go to, and a small office team. Each later
 * milestone adds its own demo data here (trucks, crew, trips, bills).
 *
 * Refuses to run in production. Running it again rebuilds the demo company.
 */
class FleetDemo extends Command
{
    protected $signature = 'fleet:demo {--fresh : Delete the demo company first}';

    protected $description = 'Build the demo company (demo@fleet.test / Demo-Pass-2026)';

    public const EMAIL    = 'demo@fleet.test';
    public const PASSWORD = 'Demo-Pass-2026';

    public function handle(SignupService $signup, RoleProvisioner $roles, CompanyContext $context, PartyService $parties): int
    {
        if (app()->isProduction()) {
            $this->error('Not in production.');

            return self::FAILURE;
        }

        $existing = Account::where('email', self::EMAIL)->first();
        if ($existing && ! $this->option('fresh')) {
            $this->info('The demo company exists. Use --fresh to build it again.');

            return self::SUCCESS;
        }
        if ($existing) {
            $this->forget($existing);
        }

        ['account' => $owner, 'company' => $company] = $signup->register([
            'company' => 'Sharma Roadlines', 'country' => 'IN', 'name' => 'Rajesh Sharma',
            'email' => self::EMAIL, 'mobile' => '+919847000001', 'password' => self::PASSWORD,
        ]);
        $owner->forceFill(['email_verified_at' => now()])->save();
        $company->forceFill([
            'legal_name' => 'Sharma Roadlines Private Limited', 'short_code' => 'SRL',
            'address_line1' => '14/220, Container Road', 'address_line2' => 'Vallarpadam', 'city' => 'Kochi',
            'state_code' => '32', 'postal_code' => '682504', 'phone' => '+91 484 2400100', 'email' => 'office@sharmaroadlines.test',
        ])->save();

        $context->runAs($company, function () use ($company, $roles, $parties, $owner) {
            Branch::where('code', 'HO')->update(['name' => 'Kochi head office', 'city' => 'Kochi', 'state_code' => '32']);
            Branch::create(['name' => 'Mumbai branch', 'code' => 'BOM', 'city' => 'Bhiwandi', 'state_code' => '27']);

            foreach ([
                ['Kochi', 'COK', 'city', '32', 9.9312, 76.2673], ['Mumbai', 'BOM', 'city', '27', 19.0760, 72.8777],
                ['Bhiwandi', 'BHW', 'godown', '27', 19.2813, 73.0483], ['Chennai', 'MAA', 'city', '33', 13.0827, 80.2707],
                ['Bengaluru', 'BLR', 'city', '29', 12.9716, 77.5946], ['Coimbatore', 'CJB', 'city', '33', 11.0168, 76.9558],
                ['Hyderabad', 'HYD', 'city', '36', 17.3850, 78.4867], ['Pune', 'PNQ', 'city', '27', 18.5204, 73.8567],
                ['Walayar checkpost', 'WLR', 'border', '32', 10.8470, 76.8390], ['Kumbalam toll plaza', 'KBM', 'toll', '32', 9.9080, 76.3160],
            ] as [$name, $code, $type, $state, $lat, $lng]) {
                Place::create(['name' => $name, 'short_code' => $code, 'type' => $type, 'state_code' => $state, 'city' => $type === 'city' ? $name : null, 'lat' => $lat, 'lng' => $lng]);
            }

            $this->parties($parties, $owner->id);
            $this->fleet($owner->id);
            $this->trips($owner->id);

            foreach ([['Anita Menon', 'anita@fleet.test', 'accountant', 'Accounts'], ['Vikram Rao', 'vikram@fleet.test', 'manager', 'Operations manager']] as [$name, $email, $role, $title]) {
                $account = Account::firstOrNew(['email' => $email]);
                $account->name = $name;
                $account->password_hash = Hash::make(self::PASSWORD);
                $account->email_verified_at = now();
                $account->save();
                $member = CompanyUser::create(['company_id' => $company->id, 'account_id' => $account->id, 'status' => 'active', 'joined_at' => now(), 'title' => $title]);
                $roles->grant($member, $role);
            }
        });

        $this->info('Demo company ready. Sign in as ' . self::EMAIL . ' / ' . self::PASSWORD . ' (also anita@ and vikram@fleet.test, same password).');

        return self::SUCCESS;
    }

    /** Clients, a supplier with hired trucks, vendors, and approved rates. */
    private function parties(PartyService $parties, int $ownerId): void
    {
        $gstin = fn (string $state, string $pan) => $state . $pan . '1Z' . TaxIds::gstinCheckCharacter($state . $pan . '1Z');

        $acc = $parties->save(null, ['name' => 'ACC Limited', 'legal_name' => 'ACC Limited', 'short_code' => 'ACC', 'country' => 'IN',
            'city' => 'Mumbai', 'mobile' => '+919820000101', 'email' => 'logistics@acc.test', 'credit_days' => 45, 'credit_limit' => 2500000,
            'due_basis' => 'submission', 'tds_deducted' => true, 'tds_section' => '194C', 'tds_rate' => 2, 'billing_cycle' => 'monthly',
            'pod_required' => 'original', 'created_by' => $ownerId], ['client', 'consignor', 'consignee'], $gstin('27', 'AAACA5678K'));
        $parties->addTaxId($acc, ['tax_id' => $gstin('32', 'AAACA5678K'), 'country' => 'IN', 'address' => 'Cement terminal, Vallarpadam, Kochi']);
        PartyContact::create(['party_id' => $acc->id, 'name' => 'Suresh Nair', 'role' => 'dispatch', 'mobile' => '+919847000202', 'is_primary' => true]);
        PartyContact::create(['party_id' => $acc->id, 'name' => 'Meena Iyer', 'role' => 'accounts', 'email' => 'payables@acc.test']);

        $parties->save(null, ['name' => 'UltraTech Cement', 'short_code' => 'ULT', 'country' => 'IN', 'city' => 'Chennai', 'credit_days' => 30,
            'created_by' => $ownerId], ['client'], $gstin('33', 'AAACU9876M'));
        $parties->save(null, ['name' => 'Kerala Agro Foods', 'short_code' => 'KAF', 'country' => 'IN', 'city' => 'Thrissur', 'state_code' => '32',
            'created_by' => $ownerId], ['client', 'consignor'], null);

        $raju = $parties->save(null, ['name' => 'Raju Transport', 'country' => 'IN', 'city' => 'Bhiwandi', 'state_code' => '27', 'mobile' => '+919890000303',
            'supplier_type' => 'owner_operator', 'pan' => 'ABCPR1234K', 'rating' => 4, 'payment_days' => 15, 'created_by' => $ownerId], ['supplier'], null);
        SupplierVehicle::create(['party_id' => $raju->id, 'reg_no' => 'MH-04-KL-7788', 'truck_type' => '12 wheels', 'capacity_mt' => 21, 'rc_owner_name' => 'Raju Patil',
            'driver_name' => 'Ganesh', 'driver_mobile' => '+919890000404']);

        $parties->save(null, ['name' => 'Indian Oil — Aluva Highway', 'country' => 'IN', 'city' => 'Aluva', 'state_code' => '32', 'vendor_category' => 'fuel_pump',
            'lat' => 10.1004, 'lng' => 76.3570, 'payment_days' => 7, 'created_by' => $ownerId], ['vendor'], null);
        $parties->save(null, ['name' => 'Malabar Truck Works', 'country' => 'IN', 'city' => 'Kochi', 'state_code' => '32', 'vendor_category' => 'workshop',
            'udyam_number' => 'UDYAM-KL-07-0012345', 'udyam_type' => 'micro', 'payment_days' => 30, 'created_by' => $ownerId], ['vendor'], null);

        $places = \App\Modules\Settings\Models\Place::pluck('id', 'short_code');
        $contract = \App\Modules\Parties\Models\RateContract::create(['party_id' => $acc->id, 'name' => 'Cement 2026', 'valid_from' => now()->startOfMonth()->toDateString(), 'created_by' => $ownerId]);
        foreach ([['COK', 'BOM', 'tonne', 3400, 21], ['COK', 'BHW', 'tonne', 3300, 21], ['COK', 'MAA', 'tonne', 1900, 21], ['COK', 'BLR', 'trip', 52000, null]] as [$from, $to, $basis, $rate, $min]) {
            $contract->lines()->create(['from_place_id' => $places[$from], 'to_place_id' => $places[$to], 'material' => 'cement', 'basis' => $basis, 'rate' => $rate,
                'min_weight_mt' => $min, 'detention_free_hours' => 24, 'detention_rate_per_day' => 2500]);
        }
        auth()->setUser(\App\Models\Account::find($ownerId));
        $parties->approve($contract);
    }

    /** Four trucks, their crew, a schedule, a service job and two complaints. */
    private function fleet(int $ownerId): void
    {
        auth()->setUser(\App\Models\Account::find($ownerId));
        $fleet = app(\App\Modules\Fleet\Services\FleetService::class);
        $trucks = [];
        foreach ([
            ['KL-07-CU-4521', 'Tata', 'Signa 4825.TK', 'w12', 'tipper', 2021, 25, 186400, '2021-03-18'],
            ['KL-07-CV-1180', 'Ashok Leyland', 'AVTR 3120', 'w10', 'open', 2019, 16, 412300, '2019-08-02'],
            ['KL-43-D-7702', 'BharatBenz', '3523R', 'w12', 'container', 2023, 21, 98700, '2023-01-11'],
            ['MH-04-JK-3345', 'Eicher', 'Pro 6028', 'w10', 'open', 2022, 18, 154000, '2022-06-25'],
        ] as [$reg, $make, $model, $type, $body, $year, $payload, $odo, $regDate]) {
            $trucks[$reg] = \App\Modules\Fleet\Models\Truck::create([
                'reg_no' => $reg, 'rc_owner_name' => 'Sharma Roadlines Pvt Ltd', 'make' => $make, 'model' => $model, 'truck_type' => $type, 'body_type' => $body,
                'manufacture_year' => $year, 'payload_mt' => $payload, 'odometer_km' => $odo, 'odometer_at' => now(), 'registration_date' => $regDate,
                'emission_norm' => $year >= 2020 ? 'BS6' : 'BS4', 'fuel_tank_l' => 365, 'mileage_loaded_kmpl' => 3.8, 'mileage_empty_kmpl' => 4.6,
                'registration_state' => substr($reg, 0, 2) === 'MH' ? '27' : '32', 'created_by' => $ownerId,
            ]);
        }

        foreach ([
            ['Ravi Kumar', 'driver', '+919847100001', 'KL-07-CU-4521', '2027-04-30'], ['Suresh Babu', 'driver', '+919847100002', 'KL-07-CU-4521', '2026-11-15'],
            ['Manoj Thomas', 'driver', '+919847100003', 'KL-07-CV-1180', '2026-10-10'], ['Abdul Rasheed', 'driver', '+919847100004', 'KL-43-D-7702', '2028-01-20'],
            ['Binu Joseph', 'helper', '+919847100005', 'KL-07-CU-4521', null], ['Shibu P', 'helper', '+919847100006', 'KL-07-CV-1180', null],
        ] as [$name, $type, $mobile, $reg, $expiry]) {
            \App\Modules\People\Models\Person::create([
                'name' => $name, 'type' => $type, 'mobile' => $mobile, 'default_truck_id' => $trucks[$reg]->id, 'joining_date' => '2024-04-01',
                'licence_no' => $expiry ? 'KL07' . substr($mobile, -7) . '2015' : null, 'licence_expiry' => $expiry, 'licence_class' => $expiry ? 'Transport (HGMV)' : null,
                'pay_basis' => $type === 'driver' ? 'monthly' : 'per_trip', 'monthly_salary' => $type === 'driver' ? 22000 : null, 'per_trip_rate' => $type === 'helper' ? 1500 : null,
                'created_by' => $ownerId,
            ]);
        }

        $truck = $trucks['KL-07-CU-4521'];
        $schedule = $truck->serviceSchedules()->create(['name' => 'Engine oil and filters', 'every_km' => 30000, 'every_days' => 180, 'last_done_km' => 160000, 'last_done_on' => now()->subMonths(5)->toDateString()]);
        $workshop = \App\Modules\Parties\Models\Party::where('name', 'Malabar Truck Works')->first();
        $job = $fleet->saveJob(null, $truck, ['job_date' => now()->subMonths(5)->toDateString(), 'kind' => 'scheduled', 'odometer_km' => 160000, 'vendor_party_id' => $workshop?->id, 'service_schedule_id' => $schedule->id, 'bill_no' => 'MTW/1182'], [
            ['kind' => 'part', 'description' => 'Engine oil 15W40', 'qty' => 28, 'rate' => 310], ['kind' => 'part', 'description' => 'Oil filter', 'qty' => 2, 'rate' => 650],
            ['kind' => 'labour', 'description' => 'Labour', 'qty' => 1, 'rate' => 1200],
        ]);
        $fleet->completeJob($job);
        // Papers with a spread of expiry dates, and the papers every truck and driver must have.
        $docs = app(\App\Modules\Documents\Services\DocumentService::class);
        foreach (['insurance', 'permit_np', 'road_tax'] as $code) {
            \App\Modules\Documents\Models\DocumentRequiredType::create(['applies_to' => 'truck', 'subtype' => null, 'document_type' => $code]);
        }
        \App\Modules\Documents\Models\DocumentRequiredType::create(['applies_to' => 'person', 'subtype' => 'driver', 'document_type' => 'licence']);
        $offsets = ['KL-07-CU-4521' => [200, 26, 90, 4], 'KL-07-CV-1180' => [-6, 140, 12, 300], 'KL-43-D-7702' => [330, 60, 180, 45], 'MH-04-JK-3345' => [18, 250, null, 9]];
        foreach ($offsets as $reg => [$insurance, $fitness, $permit, $puc]) {
            $id = $trucks[$reg]->id;
            $docs->create('truck', $id, ['document_type' => 'rc', 'number' => 'RC/' . str_replace('-', '', $reg), 'authority' => 'RTO']);
            $docs->create('truck', $id, ['document_type' => 'insurance', 'number' => 'POL-' . random_int(100000, 999999), 'authority' => 'New India Assurance', 'expires_on' => now()->addDays($insurance)->toDateString(), 'amount' => 48500]);
            $docs->create('truck', $id, ['document_type' => 'fitness', 'number' => 'FC-' . random_int(1000, 9999), 'expires_on' => now()->addDays($fitness)->toDateString(), 'amount' => 1500]);
            if ($permit !== null) {
                $docs->create('truck', $id, ['document_type' => 'permit_np', 'number' => 'NP-' . random_int(10000, 99999), 'expires_on' => now()->addDays($permit)->toDateString(), 'amount' => 16500]);
            }
            $docs->create('truck', $id, ['document_type' => 'puc', 'number' => 'PUC-' . random_int(1000, 9999), 'expires_on' => now()->addDays($puc)->toDateString(), 'amount' => 400]);
        }
        $docs->create('company', 0, ['document_type' => 'gst_reg', 'number' => '32AAACS1234F1Z' . TaxIds::gstinCheckCharacter('32AAACS1234F1Z')]);

        $fleet->raiseComplaint($truck, ['title' => 'Brake noise, front left', 'priority' => 'high', 'category' => 'brakes', 'description' => 'Squeals when braking downhill.']);
        $fleet->raiseComplaint($trucks['KL-07-CV-1180'], ['title' => 'Cabin AC not cooling', 'priority' => 'normal', 'category' => 'ac']);
    }

    /** A running full load, a delivered one, a part-load draft and a hired truck with its challan. */
    private function trips(int $ownerId): void
    {
        auth()->setUser(\App\Models\Account::find($ownerId));
        $trips  = app(\App\Modules\Trips\Services\TripService::class);
        $loads  = app(\App\Modules\Trips\Services\ConsignmentService::class);
        $hire   = app(\App\Modules\Trips\Services\HireService::class);
        $place  = \App\Modules\Settings\Models\Place::pluck('id', 'short_code');
        $party  = \App\Modules\Parties\Models\Party::pluck('id', 'name');
        $truck  = \App\Modules\Fleet\Models\Truck::pluck('id', 'reg_no');
        $person = \App\Modules\People\Models\Person::pluck('id', 'name');
        $line   = \App\Modules\Parties\Models\RateContractLine::whereHas('contract', fn ($q) => $q->where('status', 'approved'))->where('to_place_id', $place['BOM'])->first();

        // 1. Kochi → Mumbai, cement for ACC, running (its insurance is fine).
        $t1 = $trips->create(['trip_date' => now()->subDay()->toDateString(), 'truck_source' => 'own', 'truck_id' => $truck['KL-43-D-7702'], 'from_place_id' => $place['COK'], 'to_place_id' => $place['BOM'],
            'planned_km' => 1380, 'budget_fuel' => 36000, 'budget_toll' => 9500, 'instructions' => 'Unload at ACC depot gate 2. Call Suresh 30 min before.'],
            [['person_id' => $person['Abdul Rasheed'], 'role' => 'driver'], ['person_id' => $person['Binu Joseph'], 'role' => 'helper']],
            ['billing_party_id' => $party['ACC Limited'], 'consignor_party_id' => $party['ACC Limited'], 'consignee_party_id' => $party['ACC Limited'], 'material' => 'cement',
             'description' => 'OPC 53 grade, 50 kg bags', 'packages' => 380, 'package_type' => 'bags', 'actual_weight_mt' => 19, 'freight_basis' => 'tonne', 'rate' => 3400,
             'rate_contract_line_id' => $line?->id, 'freight' => 71400, 'payment_type' => 'to_be_billed', 'gst_payable_by' => 'consignor']);
        $trips->assign($t1);
        $trips->start($t1, 98700, now()->subHours(20));
        \App\Modules\Trips\Models\TripAdvance::create(['trip_id' => $t1->id, 'person_id' => $person['Abdul Rasheed'], 'amount' => 20000, 'mode' => 'cash', 'given_on' => now()->subDay()->toDateString(), 'created_by' => $ownerId]);

        // 2. A part-load draft: Kochi → Thrissur… → Coimbatore with two clients.
        $t2 = $trips->create(['trip_date' => now()->toDateString(), 'load_type' => 'ptl', 'truck_source' => 'own', 'truck_id' => $truck['KL-07-CU-4521'], 'from_place_id' => $place['COK'], 'to_place_id' => $place['CJB'], 'planned_km' => 195],
            [['person_id' => $person['Ravi Kumar'], 'role' => 'driver'], ['person_id' => $person['Suresh Babu'], 'role' => 'co_driver']],
            ['billing_party_id' => $party['Kerala Agro Foods'], 'consignor_party_id' => $party['Kerala Agro Foods'], 'material' => 'food_grains', 'packages' => 200, 'package_type' => 'bags', 'actual_weight_mt' => 10,
             'freight_basis' => 'tonne', 'rate' => 1100, 'freight' => 11000, 'payment_type' => 'to_pay']);
        $stop = $trips->addStop($t2, $place['WLR'], 'both', 1);
        $stops = $t2->stops()->get();
        $loads->save($t2, null, ['billing_party_id' => $party['UltraTech Cement'], 'material' => 'cement', 'packages' => 160, 'package_type' => 'bags', 'actual_weight_mt' => 8,
            'freight_basis' => 'trip', 'rate' => 9000, 'payment_type' => 'to_be_billed', 'pickup_stop_id' => $stop->id, 'drop_stop_id' => $stops->last()->id]);

        // 3. A hired truck for the Bhiwandi run, with its hire challan and an advance.
        $raju = \App\Modules\Parties\Models\Party::where('name', 'Raju Transport')->first();
        $vehicle = \App\Modules\Parties\Models\SupplierVehicle::where('party_id', $raju->id)->first();
        $t3 = $trips->create(['trip_date' => now()->toDateString(), 'truck_source' => 'hired', 'supplier_party_id' => $raju->id, 'supplier_vehicle_id' => $vehicle->id,
            'from_place_id' => $place['BHW'], 'to_place_id' => $place['PNQ'], 'planned_km' => 150], [],
            ['billing_party_id' => $party['ACC Limited'], 'material' => 'cement', 'actual_weight_mt' => 20, 'freight_basis' => 'trip', 'rate' => 18500, 'freight' => 18500, 'payment_type' => 'to_be_billed']);
        $trips->assign($t3);
        $tds = $hire->tdsFor($raju);
        $challan = $hire->save($t3, null, ['challan_date' => now()->toDateString(), 'vehicle_reg' => $vehicle->reg_no, 'driver_name' => $vehicle->driver_name, 'driver_mobile' => $vehicle->driver_mobile,
            'hire_basis' => 'fixed', 'hire_amount' => 15500, 'commission_mode' => 'deducted', 'commission_amount' => 0, 'office_charge' => 100,
            'tds_section' => $tds['section'], 'tds_rate' => $tds['rate'], 'tds_nil_reason' => $tds['nil_reason'], 'balance_days_after_pod' => 15]);
        $hire->issue($challan);
        $hire->addLine($challan->fresh(), ['kind' => 'advance', 'amount' => 8000, 'line_date' => now()->toDateString(), 'mode' => 'upi', 'paid_to' => 'owner', 'reference' => 'UPI-4471']);

        $this->road($t1->fresh());
    }

    /**
     * What the crew app would have sent on the running trip: fuel, tolls, a
     * payment without receipt waiting for the manager (one driver), food,
     * a rest, and the GPS track so far.
     */
    private function road(\App\Modules\Trips\Models\Trip $trip): void
    {
        $events = app(\App\Modules\Road\Services\RoadEventService::class);
        $driver = \App\Modules\People\Models\Person::where('name', 'Abdul Rasheed')->first();
        $start  = $trip->started_at->toImmutable();
        $at     = fn (int $minutes) => $start->addMinutes($minutes)->toIso8601String();

        \App\Modules\Road\Models\DrivingShift::create(['trip_id' => $trip->id, 'person_id' => $driver->id, 'started_at' => $start, 'start_odometer' => 98700, 'start_lat' => 9.9312, 'start_lng' => 76.2673]);

        foreach ([
            ['start_trip', 0, ['odometer' => 98700, 'data' => ['fuel_gauge' => '1/2']], 9.9312, 76.2673],
            ['toll', 35, ['amount' => 155, 'payment_mode' => 'fastag', 'data' => ['plaza' => 'Kumbalam']], 9.9080, 76.3160],
            ['fuel', 95, ['amount' => 23750, 'payment_mode' => 'fuel_card', 'odometer' => 98790, 'data' => ['litres' => 250, 'rate' => 95, 'full_tank' => true, 'pump' => 'HP Aluva']], 10.1076, 76.3516],
            ['toll', 180, ['amount' => 255, 'payment_mode' => 'fastag', 'data' => ['plaza' => 'Paliyekkara']], 10.3940, 76.2870],
            ['no_receipt', 300, ['amount' => 500, 'payment_mode' => 'cash', 'data' => ['authority' => 'police', 'reason' => 'Overload check', 'place' => 'Walayar']], 10.8470, 76.8390],
            ['food', 420, ['amount' => 240, 'payment_mode' => 'cash', 'data' => ['what' => 'Lunch, two people']], 11.0168, 76.9558],
            ['rest_start', 600, ['note' => 'Night halt at Salem bypass'], 11.6643, 78.1460],
            ['rest_end', 1020, [], 11.6643, 78.1460],
            ['toll', 1100, ['amount' => 310, 'payment_mode' => 'cash', 'data' => ['plaza' => 'Krishnagiri']], 12.5186, 78.2137],
        ] as [$type, $minutes, $fields, $lat, $lng]) {
            $payload = ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'trip' => $trip->uuid, 'type' => $type, 'event_at' => $at($minutes), 'lat' => $lat, 'lng' => $lng, 'accuracy_m' => 15] + $fields;
            $type === 'start_trip'
                ? $events->build($driver, $trip, $payload)->save()
                : $events->push($driver, $payload, [], [$type]);
        }

        // A point every ten minutes along the way (straight lines between the stops above are enough for a demo).
        $path = [[0, 9.9312, 76.2673], [95, 10.1076, 76.3516], [300, 10.8470, 76.8390], [600, 11.6643, 78.1460], [1020, 11.6643, 78.1460], [1180, 12.6, 78.0]];
        $rows = [];
        for ($i = 0; $i < count($path) - 1; $i++) {
            [[$m1, $la1, $ln1], [$m2, $la2, $ln2]] = [$path[$i], $path[$i + 1]];
            for ($m = $m1; $m < $m2; $m += 10) {
                $f = ($m - $m1) / ($m2 - $m1);
                $rows[] = ['company_id' => $trip->company_id, 'person_id' => $driver->id, 'trip_id' => $trip->id, 'recorded_at' => $start->addMinutes($m)->utc(),
                    'lat' => round($la1 + ($la2 - $la1) * $f, 7), 'lng' => round($ln1 + ($ln2 - $ln1) * $f, 7), 'accuracy_m' => 12, 'speed_kmh' => $la1 === $la2 ? 0 : 48, 'received_at' => now()];
            }
        }
        DB::table('gps_points')->insert($rows);
    }

    /**
     * Remove the old demo company and its people (only rows the demo made).
     * Every table with a company_id is emptied of the demo company's rows
     * first: the company's own links (trips → hired trucks, entries → people)
     * cannot be untied in one cascading delete.
     */
    private function forget(Account $owner): void
    {
        $tables = collect(DB::select("select table_name as t from information_schema.columns where table_schema = database() and column_name = 'company_id'"))
            ->pluck('t')->reject(fn ($t) => in_array($t, ['audit_log'], true));

        DB::transaction(function () use ($owner, $tables) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach (Company::where('owner_account_id', $owner->id)->get() as $company) {
                    DB::table('role_permissions')->whereIn('role_id', DB::table('roles')->where('company_id', $company->id)->pluck('id'))->delete();
                    foreach ($tables as $table) {
                        DB::table($table)->where('company_id', $company->id)->delete();
                    }
                    $company->delete();
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
            Account::whereIn('email', [self::EMAIL, 'anita@fleet.test', 'vikram@fleet.test'])->delete();
        });
    }
}
