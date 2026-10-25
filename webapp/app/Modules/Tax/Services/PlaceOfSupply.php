<?php

namespace App\Modules\Tax\Services;

use App\Modules\Parties\Models\PartyTaxId;
use App\Support\Regions;

/**
 * Where a supply is taxed (blueprint 11 §3 step 3), worked out per country
 * and never guessed: missing facts stop the invoice with a clear message.
 *
 * India (IGST Act section 12): for transport of goods, a registered buyer's
 * state (from the GSTIN the bill goes to); for an unregistered buyer, the
 * state where the goods were handed over for transport. Other services: the
 * registered buyer's state, else the buyer's address. Same state as our
 * GSTIN → CGST + SGST (CGST + UTGST in a union territory without a
 * legislature); another state → IGST.
 *
 * UAE: one country; the emirate of our registration is kept for the return.
 */
class PlaceOfSupply
{
    /**
     * @return array{code: string, name: string, where: 'intra'|'inter'|'ut'|'national', reason: string}
     */
    public function resolve(TaxContext $ctx, ?PartyTaxId $buyerTaxId, string $serviceClass): array
    {
        $registration = $ctx->registration;

        if ($registration->regime === 'ae_vat') {
            $emirate = $registration->state_code ?: 'DU';

            return ['code' => $emirate, 'name' => Regions::AE[$emirate] ?? $emirate, 'where' => 'national', 'reason' => __('UAE supply, reported under the emirate of our registration.')];
        }

        $ours = (string) $registration->state_code;
        if ($buyerTaxId?->state_code) {
            $state  = $buyerTaxId->state_code;
            $reason = __('The buyer is registered in :state (GSTIN :gstin).', ['state' => Regions::IN[$state] ?? $state, 'gstin' => $buyerTaxId->tax_id]);
        } elseif (in_array($serviceClass, ServiceClasses::TRANSPORT, true) && $ctx->loadingState) {
            $state  = $ctx->loadingState;
            $reason = __('Unregistered buyer: where the goods were handed over for transport (:state).', ['state' => Regions::IN[$state] ?? $state]);
        } elseif ($ctx->buyer?->state_code) {
            $state  = $ctx->buyer->state_code;
            $reason = __('Unregistered buyer: the buyer’s address (:state).', ['state' => Regions::IN[$state] ?? $state]);
        } else {
            throw new TaxException(in_array($serviceClass, ServiceClasses::TRANSPORT, true)
                ? __('Place of supply unknown: add the buyer’s GSTIN, or the state of the loading place.')
                : __('Place of supply unknown: add the buyer’s GSTIN or the state in their address.'), 'place_of_supply');
        }

        if (! isset(Regions::IN[$state])) {
            throw new TaxException(__('Place of supply: “:state” is not an Indian state code.', ['state' => $state]), 'place_of_supply');
        }

        $where = $state !== $ours ? 'inter' : (in_array($state, TaxPresets::IN_UT_WITHOUT_LEGISLATURE, true) ? 'ut' : 'intra');

        return ['code' => $state, 'name' => Regions::IN[$state], 'where' => $where, 'reason' => $reason];
    }
}
