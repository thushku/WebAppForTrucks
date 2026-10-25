<?php

namespace App\Modules\Road\Services;

use Illuminate\Validation\Rule;

/**
 * Every kind of road event the crew app can send (blueprint 03 §5.2), with:
 *  · its expense category (Settings → Lists), when it is money;
 *  · how it is approved: none ("info"), the manager checks it ("check"), or
 *    a second driver first ("dual": payment without receipt, decision D5);
 *  · the photo slots the app takes for it;
 *  · the extra fields it carries (validated; stored in `data`).
 *
 * The phone sends a type code; anything else is refused.
 */
final class EventTypes
{
    /** type => [label, category, approval, photo slots, amount required] */
    public const TYPES = [
        'fuel'          => ['Fuel', 'fuel', 'check', ['odometer', 'pump_meter', 'receipt'], true],
        'toll'          => ['Toll', 'toll', 'check', ['receipt'], true],
        'weighbridge'   => ['Weighbridge', 'weighbridge', 'check', ['slip'], false],
        'fine_receipt'  => ['Official fine / fee (with receipt)', 'fine_receipt', 'check', ['receipt'], true],
        'no_receipt'    => ['Payment without receipt', 'no_receipt', 'dual', [], true],
        'loading'       => ['Loading / unloading charges', 'loading', 'check', ['receipt'], true],
        'parking'       => ['Parking / entry fee', 'parking', 'check', ['receipt'], true],
        'repair'        => ['Repair / puncture', 'road_repair', 'check', ['receipt', 'damage'], true],
        'food'          => ['Food', 'food', 'check', ['receipt'], true],
        'other_expense' => ['Other expense', 'other', 'check', ['receipt'], true],
        'money_received' => ['Money received', null, 'check', ['receipt'], true],
        'reached_stop'  => ['Reached a stop', null, 'info', [], false],
        'left_stop'     => ['Left a stop', null, 'info', [], false],
        'loaded'        => ['Loaded', null, 'info', ['loaded', 'slip'], false],
        'delivered'     => ['Unloaded / delivered', null, 'check', ['pod'], false],
        'driver_change' => ['Driver change', null, 'info', ['odometer'], false],
        'rest_start'    => ['Rest started', null, 'info', [], false],
        'rest_end'      => ['Rest ended', null, 'info', [], false],
        'incident'      => ['Incident', null, 'info', ['damage', 'location'], false],
        'note'          => ['Note', null, 'info', [], false],
        'start_trip'    => ['Trip started', null, 'info', ['odometer', 'fuel_gauge'], false],
        'end_trip'      => ['Trip ended', null, 'info', ['odometer', 'fuel_gauge'], false],
    ];

    /** Payment modes, and whether the money left the crew member's own pocket (it counts in their trip cash). */
    public const PAYMENT_MODES = [
        'cash'         => ['Cash', true],
        'upi'          => ['UPI (own phone)', true],
        'fuel_card'    => ['Company fuel card', false],
        'fastag'       => ['FASTag / Salik / Darb', false],
        'credit'       => ['On credit with the pump / vendor', false],
        'party_paid'   => ['Paid by the party', false],
        'company_paid' => ['Paid by the office', false],
    ];

    public static function exists(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    public static function label(string $type): string
    {
        return __(self::TYPES[$type][0] ?? ucfirst(str_replace('_', ' ', $type)));
    }

    public static function approval(string $type): string
    {
        return self::TYPES[$type][2] ?? 'info';
    }

    public static function category(string $type): ?string
    {
        return self::TYPES[$type][1] ?? null;
    }

    /** @return string[] */
    public static function slots(string $type): array
    {
        return self::TYPES[$type][3] ?? [];
    }

    /**
     * Only the fields an event type defines are kept: whatever else a phone
     * puts in `data` is dropped, never stored.
     */
    public static function onlyKnownData(string $type, ?array $data): ?array
    {
        if (! $data) {
            return null;
        }
        $known = array_map(fn ($rule) => substr($rule, 5), array_keys(self::dataRules($type)));

        return array_intersect_key($data, array_flip($known)) ?: null;
    }

    /** Validation rules for an event's extra fields (prefixed "data."). */
    public static function dataRules(string $type): array
    {
        return match ($type) {
            'fuel' => [
                'data.litres'    => ['required', 'numeric', 'min:0.1', 'max:2000'],
                'data.rate'      => ['nullable', 'numeric', 'min:1', 'max:1000'],
                'data.full_tank' => ['nullable', 'boolean'],
                'data.adblue_litres' => ['nullable', 'numeric', 'min:0', 'max:200'],
                'data.pump'      => ['nullable', 'string', 'max:150'],
                'data.vendor_party_id' => ['nullable', 'integer'],
                'data.vat_amount' => ['nullable', 'numeric', 'min:0'],
                'data.supplier_trn' => ['nullable', 'string', 'max:20'],
            ],
            'toll' => ['data.plaza' => ['nullable', 'string', 'max:150']],
            'weighbridge' => [
                'data.gross_kg' => ['required', 'integer', 'min:0', 'max:200000'],
                'data.tare_kg'  => ['nullable', 'integer', 'min:0', 'max:200000'],
                'data.net_kg'   => ['nullable', 'integer', 'min:0', 'max:200000'],
                'data.slip_no'  => ['nullable', 'string', 'max:40'],
            ],
            'fine_receipt' => [
                'data.authority'  => ['required', Rule::in(['police', 'rto', 'checkpost', 'municipality', 'other'])],
                'data.receipt_no' => ['required', 'string', 'max:60'],
                'data.reason'     => ['nullable', 'string', 'max:200'],
            ],
            'no_receipt' => [
                'data.authority' => ['required', Rule::in(['police', 'rto', 'checkpost', 'municipality', 'other'])],
                'data.reason'    => ['required', 'string', 'min:3', 'max:200'],
                'data.place'     => ['nullable', 'string', 'max:150'],
            ],
            'loading' => ['data.labour_count' => ['nullable', 'integer', 'min:0', 'max:200'], 'data.paid_to' => ['nullable', 'string', 'max:120']],
            'repair' => ['data.what' => ['required', 'string', 'min:3', 'max:200'], 'data.workshop' => ['nullable', 'string', 'max:150']],
            'food', 'other_expense', 'parking' => ['data.what' => ['nullable', 'string', 'max:200']],
            'money_received' => [
                'data.from'      => ['required', Rule::in(['office', 'party', 'other'])],
                'data.reference' => ['nullable', 'string', 'max:60'],
                'data.consignment_id' => ['nullable', 'integer'],
            ],
            'reached_stop', 'left_stop' => ['data.stop_id' => ['required', 'integer']],
            'loaded' => [
                'data.consignment_id' => ['required', 'integer'],
                'data.packages'       => ['nullable', 'integer', 'min:0'],
                'data.weight_mt'      => ['nullable', 'numeric', 'min:0', 'max:500'],
                'data.seal_no'        => ['nullable', 'string', 'max:40'],
            ],
            'delivered' => [
                'data.consignment_id'   => ['required', 'integer'],
                'data.stop_id'          => ['nullable', 'integer'],
                'data.packages'         => ['nullable', 'integer', 'min:0'],
                'data.weight_mt'        => ['nullable', 'numeric', 'min:0', 'max:500'],
                'data.short_packages'   => ['nullable', 'integer', 'min:0'],
                'data.damaged_packages' => ['nullable', 'integer', 'min:0'],
                'data.remarks'          => ['nullable', 'string', 'max:500'],
                'data.receiver_name'    => ['required', 'string', 'min:2', 'max:120'],
                'data.receiver_mobile'  => ['nullable', 'string', 'max:20'],
                'data.otp_verified'     => ['nullable', 'boolean'],
                'data.unloading_started_at' => ['nullable', 'date'],
                'data.unloading_ended_at'   => ['nullable', 'date'],
                'data.partial'              => ['nullable', 'boolean'],
            ],
            // The PIN of the person taking the seat travels beside the event, never inside it.
            'driver_change' => ['data.incoming_person_id' => ['required', 'integer']],
            'incident' => [
                'data.kind'        => ['required', Rule::in(['breakdown', 'accident', 'theft', 'police_case', 'other'])],
                'data.description' => ['required', 'string', 'min:3', 'max:1000'],
            ],
            'start_trip', 'end_trip' => [
                'data.fuel_litres' => ['nullable', 'numeric', 'min:0', 'max:2000'],
                'data.fuel_gauge'  => ['nullable', Rule::in(['empty', '1/4', '1/2', '3/4', 'full'])],
                'data.checklist'   => ['nullable', 'array'],
            ],
            default => [],
        };
    }
}
