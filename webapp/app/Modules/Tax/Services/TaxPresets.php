<?php

namespace App\Modules\Tax\Services;

use App\Modules\Tax\Models\TaxCode;
use App\Modules\Tax\Models\TaxRate;
use App\Modules\Tax\Models\TaxRule;
use App\Modules\Tax\Models\TaxType;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;

/**
 * The country presets (blueprint 11 §6): India GST and UAE VAT as rows a
 * company owns and can adjust. Applying a preset again adds what is missing
 * and never changes what the company has (or an invoice has used).
 *
 * Every rate and rule here is our reading of the law in October 2026 and is
 * marked "(verify)" until the company's tax adviser has checked it — the
 * Settings → Tax page says so.
 */
class TaxPresets
{
    public const REGIME_FOR = ['IN' => 'in_gst', 'AE' => 'ae_vat'];

    /** India: union territories without a legislature charge CGST + UTGST instead of SGST. */
    public const IN_UT_WITHOUT_LEGISLATURE = ['04', '26', '31', '35', '38'];

    public function __construct(private CompanyContext $context) {}

    public function apply(Company $company): void
    {
        $regime = self::REGIME_FOR[$company->country] ?? null;
        if (! $regime) {
            return;
        }
        $preset = $this->preset($regime);

        $this->context->runAs($company, function () use ($regime, $preset) {
            DB::transaction(function () use ($regime, $preset) {
                foreach ($preset['types'] as $code => $name) {
                    TaxType::firstOrCreate(['regime' => $regime, 'code' => $code], ['name' => $name]);
                }

                foreach ($preset['codes'] as $code => $c) {
                    $taxCode = TaxCode::firstOrCreate(['code' => $code], [
                        'regime' => $regime, 'name' => $c['name'], 'category' => $c['category'], 'reverse_charge' => $c['reverse_charge'] ?? false,
                        'legal_text' => $c['legal_text'] ?? null, 'exemption_reason' => $c['exemption_reason'] ?? null, 'evidence' => $c['evidence'] ?? null,
                        'is_system' => true,
                    ]);
                    if (! $taxCode->rates()->exists()) {
                        foreach ($c['rates'] as [$from, $to, $rate, $note]) {
                            TaxRate::create(['tax_code_id' => $taxCode->id, 'valid_from' => $from, 'valid_to' => $to, 'rate' => $rate,
                                'parts' => $this->parts($regime, (float) $rate), 'note' => $note]);
                        }
                    }
                }

                if (! TaxRule::where('regime', $regime)->where('is_system', true)->exists()) {
                    foreach ($preset['rules'] as $rule) {
                        TaxRule::create($rule + ['regime' => $regime, 'is_system' => true]);
                    }
                }
            });
        });
    }

    /** How a rate splits: India by place of supply, the UAE in one VAT line. */
    public function parts(string $regime, float $rate): ?array
    {
        if ($rate <= 0) {
            return null;
        }

        return $regime === 'in_gst'
            ? ['intra' => ['CGST' => $rate / 2, 'SGST' => $rate / 2], 'inter' => ['IGST' => $rate], 'ut' => ['CGST' => $rate / 2, 'UTGST' => $rate / 2]]
            : ['fixed' => ['VAT' => $rate]];
    }

    /** @return array{types: array, codes: array, rules: array} */
    public function preset(string $regime): array
    {
        return $regime === 'in_gst' ? $this->india() : $this->uae();
    }

    private function india(): array
    {
        $gst2 = 'GST 2.0 rates from 22 Sep 2025 (verify)';

        return [
            'types' => ['CGST' => 'Central GST', 'SGST' => 'State GST', 'UTGST' => 'Union territory GST', 'IGST' => 'Integrated GST'],
            'codes' => [
                'GTA_RCM5' => ['name' => 'GTA freight, 5% under reverse charge', 'category' => 'AE', 'reverse_charge' => true,
                    'legal_text' => 'Tax payable on reverse charge: Yes. GST on this freight is to be paid by the recipient.',
                    'rates' => [['2017-07-01', null, 5, 'Notification 13/2017-CT(R) (verify)']]],
                'GTA_FCM5' => ['name' => 'GTA freight, 5% forward charge (no input credit)', 'category' => 'S',
                    'legal_text' => 'Tax payable on reverse charge: No. GST at 5%; input tax credit not taken.',
                    'rates' => [['2017-07-01', null, 5, 'Notification 11/2017-CT(R) (verify)']]],
                'GTA_FCM12' => ['name' => 'GTA freight, 12% forward charge (before GST 2.0)', 'category' => 'S',
                    'legal_text' => 'Tax payable on reverse charge: No.',
                    'rates' => [['2017-10-13', '2025-09-21', 12, 'Replaced by the 18% option from 22 Sep 2025 (verify)']]],
                'GTA_FCM18' => ['name' => 'GTA freight, 18% forward charge (with input credit)', 'category' => 'S',
                    'legal_text' => 'Tax payable on reverse charge: No.',
                    'rates' => [['2025-09-22', null, 18, $gst2]]],
                'GST_EXEMPT' => ['name' => 'Exempt (bill of supply)', 'category' => 'E',
                    'legal_text' => 'Exempt supply: no GST charged.', 'exemption_reason' => 'Notification 12/2017-CT(R) (verify entry)',
                    'rates' => [['2017-07-01', null, 0, null]]],
                'GST_ZERO_SEZ' => ['name' => 'Zero-rated supply to an SEZ (under LUT)', 'category' => 'Z',
                    'legal_text' => 'Supply meant for SEZ under LUT without payment of IGST.', 'exemption_reason' => 'Section 16, IGST Act (verify)',
                    'evidence' => ['lut', 'sez_authorisation'],
                    'rates' => [['2017-07-01', null, 0, null]]],
                'GST_5' => ['name' => 'GST 5%', 'category' => 'S', 'rates' => [['2017-07-01', null, 5, $gst2]]],
                'GST_18' => ['name' => 'GST 18%', 'category' => 'S', 'rates' => [['2017-07-01', null, 18, $gst2]]],
                'OUT_SCOPE' => ['name' => 'Outside the scope of GST', 'category' => 'O',
                    'legal_text' => 'Not a supply: no GST.', 'exemption_reason' => 'Reimbursement as pure agent / not a supply (verify)',
                    'rates' => [['2017-07-01', null, 0, null]]],
            ],
            'rules' => [
                ['service_class' => 'FREIGHT_ROAD', 'gta_option' => 'rcm_5', 'buyer_registered' => true, 'tax_code' => 'GTA_RCM5', 'sac_code' => '996791', 'note' => 'GTA under reverse charge: the registered recipient pays (verify)'],
                ['service_class' => 'FREIGHT_ROAD', 'gta_option' => 'fcm_5', 'tax_code' => 'GTA_FCM5', 'sac_code' => '996791'],
                ['service_class' => 'FREIGHT_ROAD', 'gta_option' => 'fcm_18', 'valid_from' => '2025-09-22', 'tax_code' => 'GTA_FCM18', 'sac_code' => '996791'],
                ['service_class' => 'FREIGHT_ROAD', 'gta_option' => 'fcm_18', 'valid_to' => '2025-09-21', 'tax_code' => 'GTA_FCM12', 'sac_code' => '996791', 'note' => 'Before GST 2.0 the higher option was 12%'],
                ['service_class' => 'FREIGHT_ROAD', 'buyer_registered' => false, 'priority' => 10, 'tax_code' => 'GST_EXEMPT', 'sac_code' => '996791', 'note' => 'GTA to an unregistered person is exempt (verify specified recipients)'],
                ['service_class' => 'FREIGHT_ROAD', 'buyer_category' => 'sez', 'priority' => 20, 'tax_code' => 'GST_ZERO_SEZ', 'sac_code' => '996791', 'note' => '(verify)'],
                ['service_class' => 'FREIGHT_ROAD', 'gta_option' => 'non_gta', 'priority' => 30, 'tax_code' => 'GST_EXEMPT', 'sac_code' => '996511', 'note' => 'No consignment note: not a GTA (verify)'],
                ['service_class' => 'VEHICLE_HIRE_WITH_DRIVER', 'buyer_category' => 'gta', 'tax_code' => 'GST_EXEMPT', 'sac_code' => '996601', 'note' => 'Hiring a goods carriage to a GTA is exempt (verify)'],
                ['service_class' => 'VEHICLE_HIRE_WITH_DRIVER', 'tax_code' => 'GST_18', 'sac_code' => '996601', 'note' => '(verify)'],
                ['service_class' => 'VEHICLE_HIRE_WITHOUT_DRIVER', 'tax_code' => 'GST_18', 'sac_code' => '997311', 'note' => '(verify)'],
                ['service_class' => 'WAREHOUSING', 'tax_code' => 'GST_18', 'sac_code' => '996729'],
                ['service_class' => 'CUSTOMS_CLEARANCE', 'tax_code' => 'GST_18', 'sac_code' => '996713'],
                ['service_class' => 'ASSET_SALE', 'tax_code' => 'GST_18', 'sac_code' => '8704', 'note' => 'Used vehicles may use the margin scheme (verify)'],
                ['service_class' => 'SCRAP_SALE', 'tax_code' => 'GST_18', 'sac_code' => '7204', 'note' => '(verify)'],
                // Add-ons and interest take the freight's tax: interest for late payment is part of the value (section 15(2)(d)).
                ...array_map(fn ($c) => ['service_class' => $c, 'tax_code' => '@main'], [...ServiceClasses::ADD_ONS, 'LATE_PAYMENT_INTEREST', 'PENALTY']),
                ['service_class' => 'DISBURSEMENT', 'tax_code' => 'OUT_SCOPE', 'note' => 'Only as a pure agent, with the third party’s bill (verify)'],
                ['service_class' => 'DAMAGE_CLAIM', 'tax_code' => 'OUT_SCOPE', 'note' => '(verify)'],
            ],
        ];
    }

    private function uae(): array
    {
        $intl = fn (string $type, string $role, string $code) => ['service_class' => 'FREIGHT_ROAD', 'transport_type' => $type, 'carrier_role' => $role, 'tax_code' => $code,
            'note' => $role === 'sub' ? 'A subcontractor doing only the UAE leg charges 5% (FTA clarification 2025, verify)' : 'Zero-rated with evidence (verify)'];

        return [
            'types' => ['VAT' => 'Value added tax'],
            'codes' => [
                'VAT_5' => ['name' => 'VAT 5%', 'category' => 'S', 'rates' => [['2018-01-01', null, 5, 'Federal Decree-Law 8 of 2017']]],
                'VAT_0_INTL' => ['name' => 'Zero-rated international transport', 'category' => 'Z',
                    'legal_text' => 'Zero-rated supply: international transport of goods.', 'exemption_reason' => 'Article 45, Federal Decree-Law 8 of 2017 (verify)',
                    'evidence' => ['transport_document', 'exit_or_customs'],
                    'rates' => [['2018-01-01', null, 0, null]]],
                'VAT_EXEMPT' => ['name' => 'Exempt', 'category' => 'E', 'legal_text' => 'Exempt supply.', 'rates' => [['2018-01-01', null, 0, null]]],
                'VAT_RC' => ['name' => 'Reverse charge (imported services)', 'category' => 'AE', 'reverse_charge' => true,
                    'legal_text' => 'Reverse charge: VAT to be accounted for by the recipient.', 'rates' => [['2018-01-01', null, 5, null]]],
                'OUT_SCOPE' => ['name' => 'Outside the scope of VAT', 'category' => 'O', 'legal_text' => 'Outside the scope of UAE VAT.',
                    'rates' => [['2018-01-01', null, 0, null]]],
            ],
            'rules' => [
                ['service_class' => 'FREIGHT_ROAD', 'transport_type' => 'domestic', 'tax_code' => 'VAT_5'],
                $intl('export', 'main', 'VAT_0_INTL'), $intl('import', 'main', 'VAT_0_INTL'), $intl('international', 'main', 'VAT_0_INTL'),
                $intl('export', 'sub', 'VAT_5'), $intl('import', 'sub', 'VAT_5'), $intl('international', 'sub', 'VAT_5'),
                ['service_class' => 'VEHICLE_HIRE_WITH_DRIVER', 'tax_code' => 'VAT_5'],
                ['service_class' => 'VEHICLE_HIRE_WITHOUT_DRIVER', 'tax_code' => 'VAT_5'],
                ['service_class' => 'WAREHOUSING', 'tax_code' => 'VAT_5'],
                ['service_class' => 'CUSTOMS_CLEARANCE', 'tax_code' => 'VAT_5'],
                ['service_class' => 'ASSET_SALE', 'tax_code' => 'VAT_5'],
                ['service_class' => 'SCRAP_SALE', 'tax_code' => 'VAT_5', 'note' => 'Domestic scrap may be under reverse charge for registered buyers (verify)'],
                ...array_map(fn ($c) => ['service_class' => $c, 'tax_code' => '@main'], ServiceClasses::ADD_ONS),
                ['service_class' => 'DISBURSEMENT', 'tax_code' => 'OUT_SCOPE', 'note' => 'Disbursement in the client’s name (verify)'],
                ['service_class' => 'DAMAGE_CLAIM', 'tax_code' => 'OUT_SCOPE', 'note' => '(verify)'],
                ['service_class' => 'PENALTY', 'tax_code' => 'OUT_SCOPE', 'note' => '(verify)'],
                ['service_class' => 'LATE_PAYMENT_INTEREST', 'tax_code' => 'OUT_SCOPE', 'note' => '(verify)'],
            ],
        ];
    }
}
