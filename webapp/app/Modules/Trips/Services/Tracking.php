<?php

namespace App\Modules\Trips\Services;

use App\Modules\Invoicing\Services\DocumentLinks;
use App\Modules\Road\Models\ConsignmentDelivery;
use App\Modules\Road\Models\GpsPoint;
use App\Modules\Settings\Models\Place;
use App\Modules\Trips\Models\Consignment;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A client's tracking link for one LR (M45, blueprint 08 phase 4): no
 * sign-in, the secure token names the company and the load. It shows the
 * steps — booked, loaded, on the way, reached, delivered — with their times,
 * and while on the road the nearest known place and the time of the last
 * GPS fix, the position rounded to about a kilometre. Never the freight,
 * the crew or the truck's exact track. It ends 30 days after delivery, or
 * when the LR is cancelled.
 */
class Tracking
{
    public const DAYS_AFTER_DELIVERY = 30;

    /** A place within this many km is "near" it. */
    public const NEAR_KM = 60;

    public function __construct(private DocumentLinks $links, private CompanyContext $context) {}

    /** The LR's link: the one it has, or a new one. */
    public function link(Consignment $load): string
    {
        $token = DB::table('document_links')->where('company_id', $this->context->id())->where('document_type', 'tracking')->where('document_id', $load->id)->value('token')
            ?? $this->links->create('tracking', $load->id);

        return route('track', $token);
    }

    /** The words to send with it: "Track LR … Kochi → Mumbai: link". */
    public function message(Consignment $load, string $url): string
    {
        $load->loadMissing(['pickupStop.place', 'dropStop.place']);

        return __('Track :lr (:route) from :company: :url', ['lr' => $load->lr_number ?? '—', 'company' => $this->context->company()->name,
            'route' => collect([$load->pickupStop?->place?->name, $load->dropStop?->place?->name])->filter()->join(' → '), 'url' => $url]);
    }

    /**
     * What the client sees, or null when the link is unknown or has ended.
     *
     * @return ?array<string, mixed>
     */
    public function view(string $token): ?array
    {
        $link = $this->links->find($token);
        if (! $link || $link->document_type !== 'tracking') {
            return null;
        }
        $company = Company::find($link->company_id);
        if (! $company?->isUsable()) {
            return null;
        }

        return $this->context->runAs($company, function () use ($company, $link) {
            $load = Consignment::with(['trip', 'consignor:id,name', 'consignee:id,name', 'pickupStop.place', 'dropStop.place'])->find($link->document_id);
            if (! $load || $load->status === 'cancelled' || ! $load->trip || $load->trip->status === 'cancelled') {
                return ['ended' => true, 'company' => $company];
            }
            $tz = $company->timezone ?: 'UTC';
            $delivery = ConsignmentDelivery::where('consignment_id', $load->id)->latest('delivered_at')->first();
            $deliveredAt = $delivery?->delivered_at ?? $load->delivered_at;
            if ($deliveredAt && CarbonImmutable::parse($deliveredAt)->addDays(self::DAYS_AFTER_DELIVERY)->isPast()) {
                return ['ended' => true, 'company' => $company];
            }
            $trip = $load->trip;
            $local = fn ($at) => $at ? CarbonImmutable::parse($at)->setTimezone($tz) : null;
            $loaded = in_array($load->status, ['picked_up', 'in_transit', 'delivered', 'pod_received', 'billed'], true);
            $steps = [
                // Booked on the LR's date (no time of day), else when it was made — never after the trip started.
                ['key' => 'booked', 'label' => __('Booked'), 'at' => $load->lr_date ? null : $local(collect([$load->created_at, $trip->started_at])->filter()->min()), 'day' => $load->lr_date, 'done' => true],
                ['key' => 'loaded', 'label' => __('Loaded at :place', ['place' => $load->pickupStop?->place?->name ?? '—']), 'at' => $local($load->pickupStop?->departed_at ?? $load->pickupStop?->arrived_at), 'done' => $loaded],
                ['key' => 'moving', 'label' => __('On the way'), 'at' => $local($trip->started_at), 'done' => (bool) $trip->started_at && $loaded],
                ['key' => 'reached', 'label' => __('Reached :place', ['place' => $load->dropStop?->place?->name ?? '—']), 'at' => $local($load->dropStop?->arrived_at), 'done' => (bool) $load->dropStop?->arrived_at || (bool) $deliveredAt],
                ['key' => 'delivered', 'label' => __('Delivered'), 'at' => $local($deliveredAt), 'done' => (bool) $deliveredAt],
            ];

            return [
                'ended' => false, 'company' => $company, 'load' => $load, 'steps' => $steps,
                'from' => $load->pickupStop?->place?->name, 'to' => $load->dropStop?->place?->name,
                'position' => ! $deliveredAt && $trip->status === 'running' ? $this->position($trip->id, $tz) : null,
                'delivery' => $deliveredAt ? ['at' => $local($deliveredAt), 'receiver' => $delivery?->receiver_name, 'short' => (int) ($delivery?->short_packages ?? 0),
                    'damaged' => (int) ($delivery?->damaged_packages ?? 0), 'remarks' => $delivery?->remarks] : null,
            ];
        });
    }

    /** The last fix, rounded to about a kilometre, and the nearest known place. */
    private function position(int $tripId, string $tz): ?array
    {
        $point = GpsPoint::where('trip_id', $tripId)->orderByDesc('recorded_at')->first(['lat', 'lng', 'recorded_at']);
        if (! $point) {
            return null;
        }
        $lat = round((float) $point->lat, 2);
        $lng = round((float) $point->lng, 2);
        $near = Place::whereNotNull('lat')->whereNotNull('lng')->get(['name', 'lat', 'lng'])
            ->map(fn ($p) => ['name' => $p->name, 'km' => $this->km($lat, $lng, (float) $p->lat, (float) $p->lng)])
            ->filter(fn ($p) => $p['km'] <= self::NEAR_KM)->sortBy('km')->first();

        return ['lat' => $lat, 'lng' => $lng, 'at' => CarbonImmutable::parse($point->recorded_at)->setTimezone($tz), 'near' => $near['name'] ?? null, 'km' => isset($near) ? (int) round($near['km']) : null];
    }

    private function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
