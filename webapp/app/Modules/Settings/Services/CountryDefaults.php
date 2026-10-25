<?php

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\Branch;
use App\Modules\Settings\Models\DocumentSequence;
use App\Modules\Settings\Models\MasterItem;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;

/**
 * What a new company starts with, by country: its head office, the master
 * lists (materials, expense categories, truck types, document types, leave
 * types…) and a number series for every document type.
 *
 * Everything here can be renamed, hidden or added to by the company; items
 * that came from the preset are marked `is_system` so they cannot be deleted
 * (older trips and bills point at them).
 *
 * Only India and the UAE can sign up today (SignupService::COUNTRIES); a
 * new country adds its own lists here as part of its country pack.
 */
class CountryDefaults
{
    /** Lists every country shares. code => name or [name, meta] */
    private const SHARED = [
        'material' => [
            'general' => 'General goods', 'fmcg' => 'FMCG / packaged goods', 'cement' => 'Cement',
            'steel' => 'Steel / iron', 'building' => 'Building material', 'agri' => 'Agricultural produce',
            'food_grains' => 'Food grains', 'fruits_veg' => 'Fruits and vegetables', 'chemicals' => 'Chemicals (non-hazardous)',
            'hazardous' => 'Hazardous goods', 'machinery' => 'Machinery', 'furniture' => 'Furniture',
            'electronics' => 'Electronics', 'textiles' => 'Textiles', 'auto_parts' => 'Auto parts',
            'container' => 'Container', 'household' => 'Household goods (shifting)', 'empty' => 'Empty return',
        ],
        'expense_category' => [
            'fuel'         => ['Fuel (diesel)', ['group' => 'running', 'needs_receipt' => true]],
            'adblue'       => ['AdBlue / DEF', ['group' => 'running', 'needs_receipt' => true]],
            'toll'         => ['Toll', ['group' => 'running', 'needs_receipt' => false]],
            'parking'      => ['Parking', ['group' => 'running', 'needs_receipt' => false]],
            'loading'      => ['Loading / unloading charges', ['group' => 'running', 'needs_receipt' => false]],
            'weighbridge'  => ['Weighbridge', ['group' => 'running', 'needs_receipt' => true]],
            'fine_receipt' => ['Official fine / fee (with receipt)', ['group' => 'running', 'needs_receipt' => true]],
            'no_receipt'   => ['Payment without receipt', ['group' => 'running', 'needs_receipt' => false]],
            'food'         => ['Food', ['group' => 'running', 'needs_receipt' => false]],
            'puncture'     => ['Tyre puncture on road', ['group' => 'running', 'needs_receipt' => false]],
            'road_repair'  => ['Repair on road', ['group' => 'maintenance', 'needs_receipt' => true]],
            'service'      => ['Service', ['group' => 'maintenance', 'needs_receipt' => true]],
            'tyres'        => ['Tyres', ['group' => 'maintenance', 'needs_receipt' => true]],
            'spares'       => ['Spare parts', ['group' => 'maintenance', 'needs_receipt' => true]],
            'oil'          => ['Engine oil and lubricants', ['group' => 'maintenance', 'needs_receipt' => true]],
            'washing'      => ['Washing / greasing', ['group' => 'maintenance', 'needs_receipt' => false]],
            'insurance'    => ['Insurance', ['group' => 'office', 'needs_receipt' => true]],
            'permit_tax'   => ['Permit and road tax', ['group' => 'office', 'needs_receipt' => true]],
            'commission'   => ['Commission / brokerage', ['group' => 'office', 'needs_receipt' => false]],
            'office'       => ['Office expenses', ['group' => 'office', 'needs_receipt' => false]],
            'other'        => ['Other', ['group' => 'running', 'needs_receipt' => false]],
        ],
        'body_type' => [
            'open' => 'Open body', 'container' => 'Closed container', 'flatbed' => 'Flatbed trailer',
            'low_bed' => 'Low-bed trailer', 'tanker' => 'Tanker', 'tipper' => 'Tipper', 'reefer' => 'Refrigerated (reefer)',
            'car_carrier' => 'Car carrier', 'curtain' => 'Curtain side', 'bulker' => 'Bulker',
        ],
        'complaint_category' => [
            'engine' => 'Engine', 'brakes' => 'Brakes', 'tyres' => 'Tyres', 'electrical' => 'Electrical / lights',
            'clutch' => 'Clutch / gearbox', 'suspension' => 'Suspension', 'body' => 'Body / tarpaulin', 'ac' => 'Cabin / AC',
            'accident' => 'Accident damage', 'other' => 'Other',
        ],
    ];

    /** Lists that differ by country. */
    private const BY_COUNTRY = [
        'IN' => [
            'truck_type' => [
                'lcv'      => ['LCV (4 wheels, up to 7.5 T)', ['wheels' => 4, 'payload_mt' => 3]],
                'w6'       => ['6 wheels (9 T)', ['wheels' => 6, 'payload_mt' => 9]],
                'w10'      => ['10 wheels (16 T)', ['wheels' => 10, 'payload_mt' => 16]],
                'w12'      => ['12 wheels (21 T)', ['wheels' => 12, 'payload_mt' => 21]],
                'w14'      => ['14 wheels (25 T)', ['wheels' => 14, 'payload_mt' => 25]],
                'w16'      => ['16 wheels (30 T)', ['wheels' => 16, 'payload_mt' => 30]],
                'trailer'  => ['Trailer (18–22 wheels)', ['wheels' => 18, 'payload_mt' => 35]],
                'container_20' => ['20 ft container truck', ['wheels' => 10, 'payload_mt' => 20]],
                'container_32' => ['32 ft container truck (SXL/MXL)', ['wheels' => 10, 'payload_mt' => 15]],
            ],
            'document_type' => [
                'rc'          => ['Registration certificate (RC)', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'insurance'   => ['Insurance', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'fitness'     => ['Fitness certificate', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'permit_np'   => ['National permit', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'permit_st'   => ['State permit', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'road_tax'    => ['Road tax', ['owner' => 'truck', 'remind_days' => 15, 'blocks_dispatch' => false]],
                'puc'         => ['Pollution certificate (PUC)', ['owner' => 'truck', 'remind_days' => 15, 'blocks_dispatch' => true]],
                'tracker'     => ['AIS-140 tracker certificate', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => false]],
                'speed_gov'   => ['Speed governor certificate', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => false]],
                'fastag'      => ['FASTag', ['owner' => 'truck', 'remind_days' => 0]],
                'loan_noc'    => ['Loan papers / NOC', ['owner' => 'truck', 'remind_days' => 0]],
                'purchase'    => ['Purchase invoice', ['owner' => 'truck', 'remind_days' => 0]],
                'licence'     => ['Driving licence', ['owner' => 'person', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'badge'       => ['Driver badge', ['owner' => 'person', 'remind_days' => 30]],
                'aadhaar'     => ['Aadhaar', ['owner' => 'person', 'remind_days' => 0, 'sensitive' => true]],
                'pan'         => ['PAN card', ['owner' => 'person', 'remind_days' => 0, 'sensitive' => true]],
                'bank_proof'  => ['Bank proof / cancelled cheque', ['owner' => 'person', 'remind_days' => 0, 'sensitive' => true]],
                'photo'       => ['Photo', ['owner' => 'person', 'remind_days' => 0]],
                'address'     => ['Address proof', ['owner' => 'person', 'remind_days' => 0, 'sensitive' => true]],
                'medical'     => ['Medical / eye test', ['owner' => 'person', 'remind_days' => 30]],
                'police'      => ['Police verification', ['owner' => 'person', 'remind_days' => 30]],
                'appointment' => ['Appointment letter', ['owner' => 'person', 'remind_days' => 0]],
                'gst_reg'     => ['GST registration certificate', ['owner' => 'company', 'remind_days' => 0]],
                'pan_company' => ['Company PAN', ['owner' => 'company', 'remind_days' => 0]],
                'tan'         => ['TAN', ['owner' => 'company', 'remind_days' => 0]],
                'udyam'       => ['Udyam (MSME) certificate', ['owner' => 'company', 'remind_days' => 0]],
                'trade_lic'   => ['Trade / shop licence', ['owner' => 'company', 'remind_days' => 30]],
                'carriers_act' => ['Carriage by Road Act registration', ['owner' => 'company', 'remind_days' => 30]],
                'gta_annex'   => ['GTA Annexure V (forward charge option)', ['owner' => 'company', 'remind_days' => 30]],
                'agreement'   => ['Agreement / contract', ['owner' => 'party', 'remind_days' => 30]],
                'rate_agreement' => ['Rate agreement', ['owner' => 'party', 'remind_days' => 30]],
                'party_gst'   => ['Party GST certificate', ['owner' => 'party', 'remind_days' => 0]],
                'party_pan'   => ['Party PAN', ['owner' => 'party', 'remind_days' => 0]],
                'form16a'     => ['Form 16A (TDS certificate)', ['owner' => 'party', 'remind_days' => 0]],
                'no_tds_decl' => ['Declaration: 10 trucks or fewer (no TDS)', ['owner' => 'party', 'remind_days' => 30]],
                'cheque'      => ['Cancelled cheque', ['owner' => 'party', 'remind_days' => 0]],
            ],
            'leave_type' => [
                'casual' => ['Casual leave', ['paid' => true, 'days_per_year' => 12]],
                'sick'   => ['Sick leave', ['paid' => true, 'days_per_year' => 12]],
                'earned' => ['Earned leave', ['paid' => true, 'days_per_year' => 15]],
                'unpaid' => ['Leave without pay', ['paid' => false, 'days_per_year' => 0]],
            ],
        ],
        'AE' => [
            'truck_type' => [
                'pickup'   => ['Pickup (1 T)', ['wheels' => 4, 'payload_mt' => 1]],
                't3'       => ['3-ton truck', ['wheels' => 4, 'payload_mt' => 3]],
                't7'       => ['7-ton truck', ['wheels' => 6, 'payload_mt' => 7]],
                't10'      => ['10-ton truck', ['wheels' => 6, 'payload_mt' => 10]],
                'flatbed'  => ['Trailer 40 ft flatbed', ['wheels' => 18, 'payload_mt' => 25]],
                'low_bed'  => ['Low-bed trailer', ['wheels' => 18, 'payload_mt' => 40]],
                'tanker'   => ['Tanker', ['wheels' => 10, 'payload_mt' => 20]],
            ],
            'document_type' => [
                'mulkiya'     => ['Vehicle registration (Mulkiya)', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'insurance'   => ['Insurance', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'rta_permit'  => ['RTA / ITC permit', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'tracker'     => ['Approved tracking device certificate', ['owner' => 'truck', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'salik'       => ['Salik / Darb tag', ['owner' => 'truck', 'remind_days' => 0]],
                'gcc_permit'  => ['GCC transit permit', ['owner' => 'truck', 'remind_days' => 30]],
                'licence'     => ['UAE driving licence', ['owner' => 'person', 'remind_days' => 30, 'blocks_dispatch' => true]],
                'emirates_id' => ['Emirates ID', ['owner' => 'person', 'remind_days' => 30, 'sensitive' => true]],
                'passport'    => ['Passport', ['owner' => 'person', 'remind_days' => 60, 'sensitive' => true]],
                'visa'        => ['Residence visa', ['owner' => 'person', 'remind_days' => 60, 'sensitive' => true]],
                'labour'      => ['Work permit (labour card)', ['owner' => 'person', 'remind_days' => 60]],
                'medical'     => ['Medical fitness', ['owner' => 'person', 'remind_days' => 30]],
                'iloe'        => ['ILOE insurance', ['owner' => 'person', 'remind_days' => 30]],
                'trade_lic'   => ['Trade licence', ['owner' => 'company', 'remind_days' => 30]],
                'trn_cert'    => ['VAT registration (TRN) certificate', ['owner' => 'company', 'remind_days' => 0]],
                'company_ins' => ['Company insurance', ['owner' => 'company', 'remind_days' => 30]],
                'agreement'   => ['Agreement / contract', ['owner' => 'party', 'remind_days' => 30]],
                'party_trn'   => ['Party TRN certificate', ['owner' => 'party', 'remind_days' => 0]],
                'party_trade' => ['Party trade licence', ['owner' => 'party', 'remind_days' => 30]],
            ],
            'leave_type' => [
                'annual' => ['Annual leave', ['paid' => true, 'days_per_year' => 30]],
                'sick'   => ['Sick leave', ['paid' => true, 'days_per_year' => 15]],
                'unpaid' => ['Unpaid leave', ['paid' => false, 'days_per_year' => 0]],
            ],
        ],
    ];

    public function __construct(private CompanyContext $context, private DocumentNumbering $numbering, private \App\Modules\Tax\Services\TaxPresets $tax) {}

    /** Give a new company its starting set-up. Safe to run twice: what exists is left alone. */
    public function apply(Company $company): void
    {
        $this->context->runAs($company, function () use ($company) {
            if (! Branch::exists()) {
                Branch::create(['name' => 'Head office', 'code' => 'HO', 'is_head_office' => true, 'state_code' => $company->state_code, 'city' => $company->city]);
            }

            foreach ($this->listsFor($company->country) as $list => $items) {
                $sort = 0;
                foreach ($items as $code => $item) {
                    [$name, $meta] = is_array($item) ? $item : [$item, null];
                    MasterItem::firstOrCreate(['list' => $list, 'code' => $code], [
                        'name' => $name, 'meta' => $meta, 'sort' => $sort += 10, 'is_system' => true,
                    ]);
                }
            }

            foreach (array_keys(DocumentSequence::DOC_TYPES) as $docType) {
                $this->numbering->sequence($docType);
            }
        });

        // The country's GST / VAT codes, dated rates and rules (blueprint 11).
        $this->tax->apply($company);
    }

    /** @return array<string, array<string, mixed>> */
    public function listsFor(string $country): array
    {
        return self::SHARED + (self::BY_COUNTRY[$country] ?? self::BY_COUNTRY['IN']);
    }
}
