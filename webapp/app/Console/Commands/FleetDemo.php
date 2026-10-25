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
            $this->tax($company);
            $this->fleet($owner->id);
            $this->trips($owner->id);
            $this->payroll($owner);
            $this->bankStatement($owner);

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

        $this->plans();
        // The demo is set up already: no getting-started card.
        $company->forceFill(['setup_done_at' => now()])->save();

        $this->info('Demo company ready. Sign in as ' . self::EMAIL . ' / ' . self::PASSWORD . ' (also anita@ and vikram@fleet.test, same password).');

        return self::SUCCESS;
    }

    /**
     * HDFC's statement brought in (M19), written from the book's own bank
     * entries a day later, as the bank shows them, with two lines the book
     * does not have yet: the SMS charges and the quarter's interest.
     */
    private function bankStatement(Account $owner): void
    {
        $bank = app(\App\Modules\Accounts\Services\ChartOfAccounts::class)->bank();
        $entries = \App\Modules\Accounts\Models\VoucherLine::query()->join('vouchers as v', 'v.id', '=', 'voucher_lines.voucher_id')
            ->where('voucher_lines.ledger_account_id', $bank->id)->where('v.status', 'posted')->where('v.voucher_date', '>', '2026-04-01')
            ->orderBy('v.voucher_date')->get(['voucher_lines.debit', 'voucher_lines.credit', 'v.voucher_date', 'v.reference', 'v.narration']);
        $lines = [];
        foreach ($entries as $e) {
            $day = \Carbon\CarbonImmutable::parse($e->voucher_date)->addDay();
            $day = $day->isAfter(now()) ? \Carbon\CarbonImmutable::parse($e->voucher_date) : $day;
            $lines[] = [$day, mb_strtoupper(mb_substr((string) $e->narration, 0, 40)), str_replace(' ', '', (string) $e->reference), (float) $e->credit, (float) $e->debit];
        }
        $lines[] = [now()->subDays(3)->startOfDay()->toImmutable(), 'SMS CHARGES FOR THE QUARTER', '', 17.70, 0.0];
        $lines[] = [now()->subDays(2)->startOfDay()->toImmutable(), 'CREDIT INTEREST CAPITALISED', '', 0.0, 1245.0];
        usort($lines, fn ($a, $b) => $a[0] <=> $b[0]);

        $balance = 500000.0;
        $rows = ['HDFC BANK LTD,Statement of account', 'Account No,50200012345678', '', 'Date,Narration,Chq./Ref.No.,Value Dt,Withdrawal Amt.,Deposit Amt.,Closing Balance'];
        foreach ($lines as [$day, $text, $ref, $out, $in]) {
            $balance = round($balance + $in - $out, 2);
            $rows[] = implode(',', [$day->format('d/m/y'), '"' . $text . '"', $ref, $day->format('d/m/y'), $out ? number_format($out, 2, '.', '') : '', $in ? number_format($in, 2, '.', '') : '', number_format($balance, 2, '.', '')]);
        }
        $path = tempnam(sys_get_temp_dir(), 'bank');
        file_put_contents($path, implode("\n", $rows) . "\n");
        app(\App\Modules\Accounts\Services\BankReconciliation::class)->import($bank, $path, 'HDFC-statement.csv', $owner);
        @unlink($path);
    }

    /** Last month's pay run, approved, with Ravi's payslip paid (M16). */
    private function payroll(Account $owner): void
    {
        $runs = app(\App\Modules\Payroll\Services\PayRuns::class);
        $last = now('Asia/Kolkata')->subMonthNoOverflow();
        ['run' => $run] = $runs->create(\Carbon\CarbonImmutable::parse($last->startOfMonth()->toDateString()), \Carbon\CarbonImmutable::parse($last->endOfMonth()->toDateString()), 'crew', $owner);
        $ravi = $run->payslips()->whereHas('person', fn ($q) => $q->where('name', 'Ravi Kumar'))->first();
        if ($ravi) {
            $runs->addLine($ravi, 'addition', 'bonus', 'Festival bonus', 2000);
        }
        $runs->approve($run->fresh(), $owner);
        if ($ravi) {
            $runs->pay($ravi->fresh(), 'bank', \Carbon\CarbonImmutable::parse($last->endOfMonth()->toDateString()), 'UTR 551209', false, $owner);
        }
    }

    /**
     * Sample plans and a coupon for the console and the billing page (the
     * prices are examples; the super-admin sets the real ones, decision D20).
     * Kept when the demo is rebuilt: plans belong to the platform.
     */
    private function plans(): void
    {
        foreach ([
            ['starter', 'Starter', 'For one to five trucks.', 'IN', 'INR', 'flat', 499, 4990, null, 5, 3, 2048, false, 10],
            ['fleet', 'Fleet', 'Every part of the app, priced per truck.', 'IN', 'INR', 'per_truck', 149, 1490, null, null, null, 20480, false, 20],
            ['lifetime', 'Lifetime', 'One payment, up to 25 trucks.', 'IN', 'INR', 'lifetime', null, null, 49999, 25, 10, 20480, false, 30],
            ['student', 'Student', 'Free for logistics students, two trucks.', 'IN', 'INR', 'flat', 0, 0, null, 2, 2, 512, true, 40],
            ['fleet-ae', 'Fleet', 'Every part of the app, priced per truck.', 'AE', 'AED', 'per_truck', 25, 250, null, null, null, 20480, false, 20],
        ] as [$code, $name, $about, $country, $currency, $pricing, $monthly, $annual, $lifetime, $trucks, $users, $mb, $student, $order]) {
            \App\Platform\Billing\Plan::updateOrCreate(['code' => $code], [
                'name' => $name, 'description' => $about, 'country' => $country, 'currency' => $currency, 'pricing' => $pricing,
                'price_monthly' => $monthly, 'price_annual' => $annual, 'price_lifetime' => $lifetime, 'max_trucks' => $trucks, 'max_users' => $users,
                'storage_mb' => $mb, 'is_student' => $student, 'is_public' => true, 'active' => true, 'sort_order' => $order,
            ]);
        }
        \App\Platform\Billing\Coupon::firstOrCreate(['code' => 'LAUNCH25'], ['kind' => 'percent', 'value' => 25, 'valid_until' => now()->addMonths(3)->toDateString(), 'max_uses' => 100]);
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

    /** A running full load, a part-load draft, a hired truck with its challan, and an ended trip waiting for settlement. */
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

        // 4. Kochi → Chennai last week: ended, its entries checked, waiting for settlement.
        $t4 = $trips->create(['trip_date' => now()->subDays(4)->toDateString(), 'truck_source' => 'own', 'truck_id' => $truck['MH-04-JK-3345'], 'from_place_id' => $place['COK'], 'to_place_id' => $place['MAA'], 'planned_km' => 690],
            [['person_id' => $person['Ravi Kumar'], 'role' => 'driver'], ['person_id' => $person['Shibu P'], 'role' => 'helper']],
            ['billing_party_id' => $party['UltraTech Cement'], 'consignor_party_id' => $party['UltraTech Cement'], 'material' => 'cement', 'packages' => 340, 'package_type' => 'bags', 'actual_weight_mt' => 17,
             'freight_basis' => 'trip', 'rate' => 38000, 'freight' => 38000, 'payment_type' => 'to_be_billed']);
        $trips->assign($t4, 'Demo trip: papers checked at the office');
        $trips->start($t4, 154000, now()->subDays(4)->setTime(1, 30));
        \App\Modules\Trips\Models\TripAdvance::create(['trip_id' => $t4->id, 'person_id' => $person['Ravi Kumar'], 'amount' => 15000, 'mode' => 'cash', 'given_on' => now()->subDays(4)->toDateString(), 'created_by' => $ownerId]);
        $this->finishedRoad($t4->fresh(), $ownerId);
        $trips->end($t4->fresh(), 154720, false, now()->subDays(3)->setTime(4, 10));

        $this->bills($t4->fresh(), $ownerId);
    }

    /**
     * Our bank account, and the Chennai trip billed: the bill issued (GTA under
     * reverse charge, UltraTech registered in Tamil Nadu) and part of it paid
     * with TDS deducted.
     */
    private function bills(\App\Modules\Trips\Models\Trip $trip, int $ownerId): void
    {
        $owner = \App\Models\Account::find($ownerId);
        \App\Modules\Settings\Models\CompanyBankAccount::create(['label' => 'HDFC current', 'bank_name' => 'HDFC Bank', 'holder_name' => 'Sharma Roadlines Private Limited',
            'account_number' => '50200012345678', 'account_last4' => '5678', 'ifsc' => 'HDFC0001234', 'branch' => 'Vyttila, Kochi', 'upi_id' => 'sharmaroadlines@hdfcbank',
            'currency' => 'INR', 'is_default' => true, 'show_on_invoice' => true]);

        $this->templates($ownerId);

        // The books open with the owner's capital in the office cash and the bank (a journal, as the accountant would type it).
        $chart = app(\App\Modules\Accounts\Services\ChartOfAccounts::class);
        $capital = \App\Modules\Accounts\Models\LedgerAccount::create(['name' => 'Capital — Rajesh Sharma', 'account_group_id' => $chart->group('capital')->id, 'kind' => 'general']);
        app(\App\Modules\Accounts\Services\Books::class)->post(['type' => 'journal', 'date' => '2026-04-01', 'narration' => 'Capital brought in: office cash and HDFC current', 'created_by' => $ownerId], [
            ['ledger' => $chart->ledger('cash'), 'debit' => 100000], ['ledger' => $chart->bank(), 'debit' => 500000], ['ledger' => $capital, 'credit' => 600000],
        ]);

        $load = \App\Modules\Trips\Models\Consignment::where('trip_id', $trip->id)->first();
        $invoices = app(\App\Modules\Invoicing\Services\InvoiceService::class);
        $invoice = $invoices->draftFromLoads($load->billingParty, [$load->id], $owner);
        $invoice->forceFill(['terms' => 'Payment within 30 days. Interest at 18% a year after the due date. Subject to Kochi jurisdiction.'])->save();
        $invoice = $invoices->finalise($invoice, $owner);

        app(\App\Modules\Invoicing\Services\ReceiptService::class)->record($load->billingParty, [
            'receipt_date' => now()->toDateString(), 'amount' => 20000, 'tds_amount' => 380, 'tds_section' => '393(1)', 'mode' => 'neft', 'reference' => 'UTIBN26102500412',
            'narration' => 'Part payment for ' . $invoice->number, 'allocations' => [$invoice->id => 20380],
        ], $owner);
    }

    /**
     * The templates (M10): the classic GST bill as the default with a footer,
     * a modern blue bill for Kerala Agro Foods, the classic LR, a compact A5
     * LR for the Mumbai branch and the A5 receipt.
     */
    private function templates(int $ownerId): void
    {
        $templates = app(\App\Modules\Invoicing\Services\DocumentTemplates::class);
        // Changes merge into the theme's design; a copy set given replaces the whole set.
        $design = function (string $family, string $theme, array $changes = []): array {
            $design = array_replace_recursive(\App\Modules\Invoicing\Design\DesignSchema::defaults($family, $theme), array_diff_key($changes, ['copies' => true]));

            return isset($changes['copies']) ? ['copies' => $changes['copies']] + $design : $design;
        };

        $templates->create('invoice', 'GST classic', $design('invoice', 'gst_classic', ['footer' => 'Subject to Kochi jurisdiction. E. & O.E.']), $ownerId);
        $blue = $templates->create('invoice', 'Modern blue', $design('invoice', 'modern', ['labels' => ['bill_to' => 'Billed to'], 'fields' => ['pan' => false]]), $ownerId);
        $agro = \App\Modules\Parties\Models\Party::where('name', 'Kerala Agro Foods')->value('id');
        if ($agro) {
            $templates->assign($blue, [$agro], []);
        }

        $templates->create('lr', 'Classic LR', $design('lr', 'classic_lr'), $ownerId);
        $small = $templates->create('lr', 'Compact A5 — Mumbai', $design('lr', 'compact_a5', ['copies' => [
            ['label' => 'Consignor copy', 'tint' => '#FFFFFF', 'hide_freight' => false],
            ['label' => 'Consignee copy', 'tint' => '#F5FAFF', 'hide_freight' => false],
            ['label' => 'Driver / lorry copy', 'tint' => '#FFFBEB', 'hide_freight' => true],
        ]]), $ownerId);
        $templates->assign($small, [], [Branch::where('code', 'BOM')->value('id')]);

        $templates->create('receipt', 'A5 receipt', $design('receipt', 'receipt_a5'), $ownerId);
    }

    /** A finished trip's entries, already checked by the office. */
    private function finishedRoad(\App\Modules\Trips\Models\Trip $trip, int $ownerId): void
    {
        $events    = app(\App\Modules\Road\Services\RoadEventService::class);
        $approvals = app(\App\Modules\Road\Services\Approvals::class);
        $owner     = \App\Models\Account::find($ownerId);
        $driver    = \App\Modules\People\Models\Person::where('name', 'Ravi Kumar')->first();
        $start     = $trip->started_at->toImmutable();

        \App\Modules\Road\Models\DrivingShift::create(['trip_id' => $trip->id, 'person_id' => $driver->id, 'started_at' => $start, 'ended_at' => $start->addHours(26)->addMinutes(40), 'start_odometer' => 154000, 'end_odometer' => 154720]);

        foreach ([
            ['fuel', 60, ['amount' => 14250, 'payment_mode' => 'cash', 'odometer' => 154050, 'data' => ['litres' => 150, 'rate' => 95, 'full_tank' => true, 'pump' => 'BPCL Angamaly']], 10.1960, 76.3860],
            ['toll', 200, ['amount' => 255, 'payment_mode' => 'cash', 'data' => ['plaza' => 'Paliyekkara']], 10.4100, 76.2800],
            ['toll', 420, ['amount' => 390, 'payment_mode' => 'fastag', 'data' => ['plaza' => 'Walayar']], 10.8470, 76.8390],
            ['food', 480, ['amount' => 320, 'payment_mode' => 'cash', 'data' => ['what' => 'Lunch, two people']], 11.0168, 76.9558],
            ['loading', 1500, ['amount' => 1200, 'payment_mode' => 'cash', 'data' => ['labour_count' => 6, 'paid_to' => 'Chennai depot labour']], 13.0827, 80.2707],
            ['money_received', 1560, ['amount' => 2000, 'payment_mode' => 'cash', 'data' => ['from' => 'party', 'reference' => 'Unloading charges back']], 13.0827, 80.2707],
        ] as [$type, $minutes, $fields, $lat, $lng]) {
            $answer = $events->push($driver, ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'trip' => $trip->uuid, 'type' => $type, 'event_at' => $start->addMinutes($minutes)->toIso8601String(), 'lat' => $lat, 'lng' => $lng, 'accuracy_m' => 15] + $fields, [], [$type]);
            $event = \App\Modules\Road\Models\RoadEvent::find($answer['id']);
            if ($approvals->actions($event)) {
                $approvals->decide($owner, $event, 'approve');
            }
        }

        // The whole track, a point every ten minutes, for the route replay: Kochi, Thrissur, Walayar, Coimbatore, the night at Salem, Vellore, Chennai.
        $path = [[0, 9.9312, 76.2673], [60, 10.1960, 76.3860], [200, 10.4100, 76.2800], [420, 10.8470, 76.8390], [480, 11.0168, 76.9558],
            [800, 11.6643, 78.1460], [1100, 11.6643, 78.1460], [1350, 12.9165, 79.1325], [1500, 13.0827, 80.2707], [1600, 13.0827, 80.2707]];
        $rows = [];
        for ($i = 0; $i < count($path) - 1; $i++) {
            [[$m1, $la1, $ln1], [$m2, $la2, $ln2]] = [$path[$i], $path[$i + 1]];
            for ($m = $m1; $m < $m2; $m += 10) {
                $f = ($m - $m1) / ($m2 - $m1);
                $rows[] = ['company_id' => $trip->company_id, 'person_id' => $driver->id, 'trip_id' => $trip->id, 'recorded_at' => $start->addMinutes($m)->utc(),
                    'lat' => round($la1 + ($la2 - $la1) * $f, 7), 'lng' => round($ln1 + ($ln2 - $ln1) * $f, 7), 'accuracy_m' => 12, 'speed_kmh' => $la1 === $la2 ? 0 : 52, 'received_at' => now()];
            }
        }
        DB::table('gps_points')->insert($rows);
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

    /** The company's GSTIN in Kerala, with the default GTA option (5% under reverse charge) for this year. */
    private function tax(Company $company): void
    {
        $gstin = '32AAACS1234F1Z' . TaxIds::gstinCheckCharacter('32AAACS1234F1Z');
        $registration = \App\Modules\Tax\Models\TaxRegistration::create([
            'country' => 'IN', 'regime' => 'in_gst', 'tax_id' => $gstin, 'state_code' => '32', 'is_default' => true,
            'legal_name' => 'Sharma Roadlines Private Limited', 'address' => '14/220, Container Road, Vallarpadam, Kochi 682504', 'registered_on' => '2019-04-01',
        ]);
        $fy = \App\Support\FiscalYear::containing(now('Asia/Kolkata'), 4)->key;
        \App\Modules\Tax\Models\TaxRegistrationYear::create(['tax_registration_id' => $registration->id, 'fy_key' => $fy, 'gta_option' => 'rcm_5']);
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
