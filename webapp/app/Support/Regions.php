<?php

namespace App\Support;

/**
 * States and emirates, by the codes tax law uses.
 *
 * India: the two-digit GST state code (the first two digits of a GSTIN), so
 * place of supply and IGST-or-CGST/SGST can be worked out later from it.
 * UAE: the emirate, as printed on trade licences.
 */
final class Regions
{
    public const IN = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
        '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
        '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur',
        '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
        '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa',
        '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
        '97' => 'Other territory',
    ];

    public const AE = [
        'AZ' => 'Abu Dhabi', 'DU' => 'Dubai', 'SH' => 'Sharjah', 'AJ' => 'Ajman',
        'UQ' => 'Umm Al Quwain', 'RK' => 'Ras Al Khaimah', 'FU' => 'Fujairah',
    ];

    /** @return array<string, string> code => name for a country (empty when not listed yet) */
    public static function for(string $country): array
    {
        return match (strtoupper($country)) {
            'IN'    => self::IN,
            'AE'    => self::AE,
            default => [],
        };
    }

    public static function name(?string $country, ?string $code): ?string
    {
        return $code === null ? null : (self::for((string) $country)[$code] ?? $code);
    }

    /** "State" in India, "Emirate" in the UAE. */
    public static function label(string $country): string
    {
        return strtoupper($country) === 'AE' ? __('Emirate') : __('State');
    }
}
