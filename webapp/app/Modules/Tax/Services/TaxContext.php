<?php

namespace App\Modules\Tax\Services;

use App\Modules\Parties\Models\Party;
use App\Modules\Tax\Models\TaxRegistration;
use Carbon\CarbonImmutable;

/**
 * The facts a tax decision needs (blueprint 11 §3 step 1): the date, our
 * registration, the buyer, where the goods were handed over and delivered,
 * the kind of transport and our role in it, and the papers on file.
 */
final class TaxContext
{
    /**
     * @param ?string  $buyerTaxId     the buyer registration the invoice is addressed to (default: theirs for the country)
     * @param ?string  $loadingState   state / emirate where the goods were handed over for transport
     * @param ?string  $transportType  domestic | export | import | international
     * @param ?string  $carrierRole    main | sub (subcontractor of another carrier)
     * @param string[] $evidence       papers on file: transport_document, exit_or_customs, lut, sez_authorisation
     */
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly ?TaxRegistration $registration,
        public readonly ?Party $buyer,
        public readonly ?string $buyerTaxId = null,
        public readonly ?string $loadingState = null,
        public readonly ?string $unloadingState = null,
        public readonly ?string $transportType = null,
        public readonly ?string $carrierRole = null,
        public readonly array $evidence = [],
        public readonly bool $inclusive = false,
        public readonly ?bool $roundTotal = null,
        public readonly ?string $currency = null,
    ) {}
}
