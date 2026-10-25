<?php

namespace App\Modules\Tax\Services;

/**
 * The kinds of charge on a freight invoice (blueprint 11 §2). Tax rules look
 * at the class, never at the line's free text. Add-on charges billed with
 * the freight normally take the freight line's tax (the presets say "@main"
 * for them); a rule can say otherwise.
 */
final class ServiceClasses
{
    /** code => label */
    public const CLASSES = [
        'FREIGHT_ROAD'                => 'Road freight',
        'VEHICLE_HIRE_WITH_DRIVER'    => 'Vehicle hire with driver',
        'VEHICLE_HIRE_WITHOUT_DRIVER' => 'Vehicle hire without driver',
        'LOADING_UNLOADING'           => 'Loading / unloading',
        'DETENTION'                   => 'Detention / halting',
        'DEMURRAGE'                   => 'Demurrage',
        'FUEL_SURCHARGE'              => 'Fuel surcharge',
        'PACKING'                     => 'Packing',
        'TOLL_RECHARGE'               => 'Toll recovered',
        'OTHER_CHARGES'               => 'Other charges billed with the freight',
        'DISBURSEMENT'                => 'Paid on your behalf (pure agent)',
        'DAMAGE_CLAIM'                => 'Damage claim',
        'PENALTY'                     => 'Penalty',
        'LATE_PAYMENT_INTEREST'       => 'Interest on late payment',
        'WAREHOUSING'                 => 'Warehousing',
        'CUSTOMS_CLEARANCE'           => 'Customs clearance',
        'ASSET_SALE'                  => 'Sale of a vehicle or asset',
        'SCRAP_SALE'                  => 'Scrap sale',
    ];

    /** The classes an add-on can follow: the invoice's main supply. */
    public const MAIN = ['FREIGHT_ROAD', 'VEHICLE_HIRE_WITH_DRIVER', 'VEHICLE_HIRE_WITHOUT_DRIVER'];

    /** Add-ons billed with the freight. */
    public const ADD_ONS = ['LOADING_UNLOADING', 'DETENTION', 'DEMURRAGE', 'FUEL_SURCHARGE', 'PACKING', 'TOLL_RECHARGE', 'OTHER_CHARGES'];

    /** Classes where the goods move: place of supply looks at where they were handed over. */
    public const TRANSPORT = ['FREIGHT_ROAD', 'VEHICLE_HIRE_WITH_DRIVER', 'LOADING_UNLOADING', 'DETENTION', 'DEMURRAGE', 'FUEL_SURCHARGE', 'PACKING', 'TOLL_RECHARGE', 'OTHER_CHARGES'];

    public static function exists(string $class): bool
    {
        return isset(self::CLASSES[$class]);
    }

    public static function label(string $class): string
    {
        return __(self::CLASSES[$class] ?? $class);
    }
}
