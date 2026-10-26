<?php

namespace App\Modules\Fleet\Services;

use App\Models\Account;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Models\Tyre;
use App\Modules\Fleet\Models\TyreFitment;
use App\Modules\Fleet\Models\TyreRetread;
use App\Modules\Settings\Models\MasterItem;
use App\Modules\Trips\Models\Trip;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The tyre register (M38). A tyre is bought into the store, fitted to a
 * truck at a wheel position from an odometer reading, taken off at another
 * (moved, repaired, sent for retreading, worn out, burst, sold), and in the
 * end scrapped. Every fitment's km add to the tyre's km run; a retread adds
 * its cost and the km it is good for again. Cost ÷ expected km is what each
 * km of the tyre costs — its wear, which a trip's km are charged (an
 * estimate, beside the truck's fixed costs of M37).
 *
 * The register keeps no books: the tyre dealer's bill is paid or booked as
 * any other (a payment voucher, the vendor's ledger).
 */
class Tyres
{
    /** A tyre fitted past this share of its expected km is shown as near its end. */
    public const NEAR_END = 0.9;

    public const SPARE = 'SP';

    public function __construct(private CompanyContext $context, private AuditRecorder $audit) {}

    // ---- Wheel positions ----------------------------------------------------------

    /** The truck's wheel count: its own, else its type's, else ten. */
    public function wheels(Truck $truck): int
    {
        $wheels = $truck->wheels ?: (int) (MasterItem::ofList('truck_type')->where('code', $truck->truck_type)->first()?->meta['wheels'] ?? 0);

        return $wheels >= 4 ? min($wheels, 26) : 10;
    }

    /**
     * The truck's axles and the positions on each, front to back, and the
     * spare: a 10-wheeler is F1-L, F1-R; R1-LO, R1-LI, R1-RI, R1-RO; R2-…; SP.
     * Twelve and sixteen wheels have two steering axles; rear axles carry
     * four (twin) tyres, and two left over make a single rear axle.
     *
     * @return list<array{axle: string, name: string, positions: list<string>}>
     */
    public function axles(Truck $truck): array
    {
        $wheels = $this->wheels($truck);
        $front = in_array($wheels, [12, 16], true) ? 2 : 1;
        $axles = [];
        for ($n = 1; $n <= $front; $n++) {
            $axles[] = ['axle' => "F{$n}", 'name' => $n === 1 ? __('Front') : __('Second front'), 'positions' => ["F{$n}-L", "F{$n}-R"]];
        }
        $rest = $wheels - 2 * $front;
        $n = 0;
        for ($i = 0; $i < intdiv($rest, 4); $i++) {
            $n++;
            $axles[] = ['axle' => "R{$n}", 'name' => __('Rear :n', ['n' => $n]), 'positions' => ["R{$n}-LO", "R{$n}-LI", "R{$n}-RI", "R{$n}-RO"]];
        }
        if ($rest % 4 >= 2) {
            $n++;
            $axles[] = ['axle' => "R{$n}", 'name' => __('Rear :n', ['n' => $n]), 'positions' => ["R{$n}-L", "R{$n}-R"]];
        }
        $axles[] = ['axle' => self::SPARE, 'name' => __('Spare'), 'positions' => [self::SPARE]];

        return $axles;
    }

    /** @return list<string> */
    public function positions(Truck $truck): array
    {
        return array_merge(...array_column($this->axles($truck), 'positions'));
    }

    /** "Rear 1, left outer" for R1-LO. */
    public function positionName(string $code): string
    {
        if ($code === self::SPARE) {
            return __('Spare');
        }
        [$axle, $side] = array_pad(explode('-', $code, 2), 2, '');
        $sides = ['L' => __('left wheel'), 'R' => __('right wheel'), 'LO' => __('left outer'), 'LI' => __('left inner'), 'RI' => __('right inner'), 'RO' => __('right outer')];
        $n = (int) substr($axle, 1);
        $where = str_starts_with($axle, 'F') ? ($n === 1 ? __('Front') : __('Second front')) : __('Rear :n', ['n' => $n]);

        return __(':axle, :side', ['axle' => $where, 'side' => $sides[$side] ?? $side]);
    }

    // ---- Reading -----------------------------------------------------------------

    /** The km a tyre has run: its ended fitments, and on a fitted one the truck's km since it went on (not as the spare). */
    public function kmRun(Tyre $tyre): int
    {
        if ($tyre->status !== 'fitted' || ! $tyre->truck || $tyre->position === self::SPARE) {
            return (int) $tyre->km_run;
        }
        $fitted = $tyre->relationLoaded('current') ? $tyre->current?->fitted_odo
            : TyreFitment::where('tyre_id', $tyre->id)->whereNull('removed_on')->value('fitted_odo');

        return (int) $tyre->km_run + max(0, (int) $tyre->truck->odometer_km - (int) $fitted);
    }

    /** How far through its expected km a tyre is, 0–1+ (null without an expected km). */
    public function used(Tyre $tyre): ?float
    {
        return $tyre->expected_km ? round($this->kmRun($tyre) / $tyre->expected_km, 3) : null;
    }

    /**
     * The tyres fitted to a truck, by position.
     *
     * @return Collection<string, Tyre>
     */
    public function onTruck(Truck $truck): Collection
    {
        return Tyre::with(['current', 'truck:id,reg_no,odometer_km'])->where('truck_id', $truck->id)->where('status', 'fitted')->get()->keyBy('position');
    }

    /**
     * Fitted tyres past NEAR_END of their expected km, the most worn first.
     *
     * @return Collection<int, array{tyre: Tyre, km: int, used: float}>
     */
    public function nearEnd(): Collection
    {
        return Tyre::inBranch()->with(['current', 'truck:id,reg_no,odometer_km'])->where('status', 'fitted')->whereNotNull('expected_km')->get()
            ->map(fn (Tyre $t) => ['tyre' => $t, 'km' => $this->kmRun($t), 'used' => (float) $this->used($t)])
            ->filter(fn ($r) => $r['used'] >= self::NEAR_END)->sortByDesc('used')->values();
    }

    /**
     * What each brand and size has given: tyres bought, still running, and for
     * those finished (scrapped) the km they ran and what each km cost after
     * the scrap money.
     *
     * @return Collection<int, object>
     */
    public function brands(): Collection
    {
        return Tyre::inBranch()->get(['brand', 'size', 'status', 'cost', 'km_run', 'scrap_value', 'expected_km'])->groupBy(fn ($t) => mb_strtolower($t->brand) . '|' . $t->size)
            ->map(function (Collection $set) {
                $done = $set->where('status', 'scrapped')->filter(fn ($t) => $t->km_run > 0);

                return (object) [
                    'brand' => $set->first()->brand, 'size' => $set->first()->size, 'tyres' => $set->count(),
                    'running' => $set->whereIn('status', ['fitted', 'in_store', 'retreading'])->count(), 'finished' => $done->count(),
                    'km' => $done->isNotEmpty() ? (int) round($done->avg('km_run')) : null,
                    'per_km' => $done->sum('km_run') > 0 ? round($done->sum(fn ($t) => (float) $t->cost - (float) $t->scrap_value) / $done->sum('km_run'), 2) : null,
                    'expected_per_km' => $set->sum('expected_km') > 0 ? round($set->sum(fn ($t) => (float) $t->cost) / $set->sum('expected_km'), 2) : null,
                ];
            })->sortByDesc('tyres')->values();
    }

    /**
     * Tyre wear of trips: for each trip with its odometer readings, the km
     * every tyre (not the spare) was on its truck within them, at the tyre's
     * cost per km. Trips of hired trucks or without readings have none.
     *
     * @param iterable<Trip> $trips
     * @return array<int, array{km: int, tyres: int, amount: float}>
     */
    public function wearForTrips(iterable $trips): array
    {
        $trips = collect($trips)->filter(fn (Trip $t) => $t->truck_id && $t->start_odometer && $t->end_odometer && $t->end_odometer > $t->start_odometer);
        if ($trips->isEmpty()) {
            return [];
        }
        $fitments = TyreFitment::with('tyre:id,cost,expected_km')->whereIn('truck_id', $trips->pluck('truck_id')->unique())->where('position', '!=', self::SPARE)
            ->get(['id', 'tyre_id', 'truck_id', 'position', 'fitted_odo', 'removed_odo'])->groupBy('truck_id');

        $wear = [];
        foreach ($trips as $trip) {
            $amount = 0.0;
            $tyres = 0;
            foreach ($fitments[$trip->truck_id] ?? [] as $f) {
                $from = max((int) $trip->start_odometer, (int) $f->fitted_odo);
                $to = min((int) $trip->end_odometer, $f->removed_odo !== null ? (int) $f->removed_odo : (int) $trip->end_odometer);
                $perKm = $f->tyre?->costPerKm();
                if ($to > $from && $perKm) {
                    $amount += ($to - $from) * $perKm;
                    $tyres++;
                }
            }
            if ($tyres) {
                $wear[$trip->id] = ['km' => (int) ($trip->end_odometer - $trip->start_odometer), 'tyres' => $tyres, 'amount' => round($amount, 2)];
            }
        }

        return $wear;
    }

    /** @return ?array{km: int, tyres: int, amount: float} */
    public function wearFor(Trip $trip): ?array
    {
        return $this->wearForTrips([$trip])[$trip->id] ?? null;
    }

    // ---- Writing -----------------------------------------------------------------

    /** Adding tyres: one or more serial numbers (one per line) with the same details. */
    public function rules(?Tyre $tyre = null): array
    {
        $company = $this->context->id();
        $details = [
            'brand' => ['required', 'string', 'max:40'],
            'model' => ['nullable', 'string', 'max:40'],
            'size' => ['required', 'string', 'max:30'],
            'kind' => ['required', Rule::in(array_keys(Tyre::KINDS))],
            'purchased_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:' . $this->today()->toDateString()],
            'vendor_party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where('company_id', $company)],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'expected_km' => ['nullable', 'integer', 'min:1000', 'max:1000000'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $company)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];

        return $tyre
            ? ['serial_no' => ['required', 'string', 'max:40', Rule::unique('tyres', 'serial_no')->where('company_id', $company)->ignore($tyre->id)]] + $details
            : ['serials' => ['required', 'string', 'max:2000']] + $details;
    }

    /** The serial numbers typed, one per line or comma, without repeats. @return list<string> */
    public function serials(string $typed): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($s) => mb_strtoupper(trim($s)), preg_split('/[\r\n,]+/', $typed)), fn ($s) => $s !== '')));
    }

    /** @return Collection<int, Tyre> */
    public function add(array $data, Account $by): Collection
    {
        $serials = $this->serials($data['serials'] ?? '');
        if (! $serials) {
            throw ValidationException::withMessages(['serials' => __('Type the serial number of each tyre.')]);
        }
        if (count($serials) > 40) {
            throw ValidationException::withMessages(['serials' => __('Add at most 40 tyres at a time.')]);
        }
        if ($long = collect($serials)->first(fn ($s) => mb_strlen($s) > 40)) {
            throw ValidationException::withMessages(['serials' => __('“:serial” is longer than 40 characters.', ['serial' => $long])]);
        }
        if ($taken = Tyre::whereIn('serial_no', $serials)->pluck('serial_no')->all()) {
            throw ValidationException::withMessages(['serials' => __('Already in the register: :serials.', ['serials' => implode(', ', $taken)])]);
        }
        $details = collect($data)->only(['brand', 'model', 'size', 'kind', 'purchased_on', 'vendor_party_id', 'cost', 'expected_km', 'branch_id', 'notes'])->all();

        return DB::transaction(fn () => collect($serials)->map(fn ($serial) => Tyre::create($details + ['serial_no' => $serial, 'status' => 'in_store', 'created_by' => $by->id])));
    }

    public function update(Tyre $tyre, array $data): Tyre
    {
        $tyre->update(collect($data)->only(['serial_no', 'brand', 'model', 'size', 'kind', 'purchased_on', 'vendor_party_id', 'cost', 'expected_km', 'branch_id', 'notes'])->all());

        return $tyre;
    }

    /** Fit a tyre from the store to a free position of a truck, at an odometer reading. */
    public function fit(Tyre $tyre, Truck $truck, string $position, string $on, int $odometer, Account $by): TyreFitment
    {
        if ($truck->status === 'sold') {
            throw ValidationException::withMessages(['truck' => __('The truck is sold.')]);
        }
        if (! in_array($position, $this->positions($truck), true)) {
            throw ValidationException::withMessages(['position' => __('The truck has no position :position.', ['position' => $position])]);
        }
        $this->checkDate($on);

        return DB::transaction(function () use ($tyre, $truck, $position, $on, $odometer, $by) {
            $tyre = Tyre::lockForUpdate()->findOrFail($tyre->id);
            if ($tyre->status !== 'in_store') {
                throw ValidationException::withMessages(['tyre_id' => __('Tyre :serial is not in the store (:status).', ['serial' => $tyre->serial_no, 'status' => __(Tyre::STATUSES[$tyre->status])])]);
            }
            if ($there = Tyre::where('truck_id', $truck->id)->where('position', $position)->first()) {
                throw ValidationException::withMessages(['position' => __(':position has tyre :serial on it — take it off or move it first.', ['position' => $this->positionName($position), 'serial' => $there->serial_no])]);
            }
            $fitment = TyreFitment::create(['tyre_id' => $tyre->id, 'truck_id' => $truck->id, 'position' => $position, 'fitted_on' => $on, 'fitted_odo' => $odometer, 'created_by' => $by->id]);
            $tyre->update(['status' => 'fitted', 'truck_id' => $truck->id, 'position' => $position]);
            $this->audit->record('tyre.fitted', "Tyre {$tyre->serial_no} fitted to {$truck->reg_no} at {$position} ({$odometer} km)", 'truck', (string) $truck->id, $truck->reg_no);

            return $fitment;
        });
    }

    /**
     * Take a fitted tyre off at an odometer reading. The reason says where it
     * goes: back to the store (moved, repaired), to the retreader, or scrapped.
     */
    public function remove(Tyre $tyre, string $on, int $odometer, string $reason, Account $by, ?float $scrapValue = null, ?int $vendorId = null): Tyre
    {
        if (! isset(TyreFitment::REASONS[$reason])) {
            throw ValidationException::withMessages(['reason' => __('Choose why it comes off.')]);
        }
        $this->checkDate($on);

        return DB::transaction(function () use ($tyre, $on, $odometer, $reason, $by, $scrapValue, $vendorId) {
            $tyre = Tyre::lockForUpdate()->findOrFail($tyre->id);
            $fitment = $this->end($tyre, $on, $odometer, $reason, $by);
            $goes = TyreFitment::REASONS[$reason][1];
            $tyre->update(['status' => $goes, 'truck_id' => null, 'position' => null, 'km_run' => $tyre->km_run + $fitment->km]
                + ($goes === 'scrapped' ? ['scrapped_on' => $on, 'scrap_value' => $scrapValue] : []));
            if ($goes === 'retreading') {
                TyreRetread::create(['tyre_id' => $tyre->id, 'sent_on' => $on, 'vendor_party_id' => $vendorId, 'created_by' => $by->id]);
            }
            $truck = Truck::find($fitment->truck_id);
            $this->audit->record('tyre.removed', "Tyre {$tyre->serial_no} off {$truck?->reg_no} {$fitment->position} at {$odometer} km after {$fitment->km} km: " . TyreFitment::REASONS[$reason][0],
                'truck', (string) $fitment->truck_id, $truck?->reg_no);

            return $tyre;
        });
    }

    /**
     * Move a fitted tyre to another position, on the same truck or another,
     * in one step: off here (rotation) and on there. On the same truck a
     * position taken by another tyre is a swap — the two change places.
     */
    public function move(Tyre $tyre, Truck $to, string $position, string $on, int $offOdometer, ?int $onOdometer, Account $by): Tyre
    {
        if (! in_array($position, $this->positions($to), true)) {
            throw ValidationException::withMessages(['position' => __('The truck has no position :position.', ['position' => $position])]);
        }
        $this->checkDate($on);

        return DB::transaction(function () use ($tyre, $to, $position, $on, $offOdometer, $onOdometer, $by) {
            $tyre = Tyre::lockForUpdate()->findOrFail($tyre->id);
            if ($tyre->status !== 'fitted') {
                throw ValidationException::withMessages(['tyre' => __('Tyre :serial is not fitted.', ['serial' => $tyre->serial_no])]);
            }
            $from = Truck::findOrFail($tyre->truck_id);
            $was = $tyre->position;
            if ($from->id === $to->id && $was === $position) {
                throw ValidationException::withMessages(['position' => __('The tyre is at :position already.', ['position' => $this->positionName($position)])]);
            }
            if ($from->id !== $to->id && $onOdometer === null && $to->odometer_km === null) {
                throw ValidationException::withMessages(['new_odometer' => __('Give :truck’s odometer reading.', ['truck' => $to->reg_no])]);
            }
            $onOdometer = $from->id === $to->id ? $offOdometer : ($onOdometer ?? (int) $to->odometer_km);
            $other = Tyre::lockForUpdate()->where('truck_id', $to->id)->where('position', $position)->first();
            if ($other && $from->id !== $to->id) {
                throw ValidationException::withMessages(['position' => __(':position has tyre :serial on it — take it off or move it first.', ['position' => $this->positionName($position), 'serial' => $other->serial_no])]);
            }

            $fitment = $this->end($tyre, $on, $offOdometer, 'rotation', $by);
            $tyre->update(['km_run' => $tyre->km_run + $fitment->km, 'truck_id' => null, 'position' => null]);
            if ($other) {
                $theirs = $this->end($other, $on, $offOdometer, 'rotation', $by);
                $other->update(['km_run' => $other->km_run + $theirs->km, 'position' => $was]);
                TyreFitment::create(['tyre_id' => $other->id, 'truck_id' => $to->id, 'position' => $was, 'fitted_on' => $on, 'fitted_odo' => $offOdometer, 'created_by' => $by->id]);
            }
            $tyre->update(['truck_id' => $to->id, 'position' => $position, 'status' => 'fitted']);
            TyreFitment::create(['tyre_id' => $tyre->id, 'truck_id' => $to->id, 'position' => $position, 'fitted_on' => $on, 'fitted_odo' => $onOdometer, 'created_by' => $by->id]);
            $this->audit->record('tyre.moved', "Tyre {$tyre->serial_no} moved from {$from->reg_no} {$was} to {$to->reg_no} {$position}" . ($other ? " (swapped with {$other->serial_no})" : ''),
                'truck', (string) $to->id, $to->reg_no);

            return $tyre->fresh();
        });
    }

    /** Send a tyre from the store to the retreader. */
    public function sendForRetread(Tyre $tyre, string $on, ?int $vendorId, Account $by): TyreRetread
    {
        $this->checkDate($on);

        return DB::transaction(function () use ($tyre, $on, $vendorId, $by) {
            $tyre = Tyre::lockForUpdate()->findOrFail($tyre->id);
            if ($tyre->status !== 'in_store') {
                throw ValidationException::withMessages(['tyre' => __('Only a tyre in the store can be sent; take it off the truck first.')]);
            }
            $tyre->update(['status' => 'retreading']);

            return TyreRetread::create(['tyre_id' => $tyre->id, 'sent_on' => $on, 'vendor_party_id' => $vendorId, 'created_by' => $by->id]);
        });
    }

    /** Back from the retreader: what it cost and the km it is good for again add to the tyre's. */
    public function returned(Tyre $tyre, string $on, float $cost, ?int $expectedKm, ?int $vendorId): Tyre
    {
        $this->checkDate($on);

        return DB::transaction(function () use ($tyre, $on, $cost, $expectedKm, $vendorId) {
            $tyre = Tyre::lockForUpdate()->findOrFail($tyre->id);
            $retread = TyreRetread::where('tyre_id', $tyre->id)->whereNull('returned_on')->latest('id')->first();
            if ($tyre->status !== 'retreading' || ! $retread) {
                throw ValidationException::withMessages(['tyre' => __('Tyre :serial is not at the retreader.', ['serial' => $tyre->serial_no])]);
            }
            if (CarbonImmutable::parse($on)->toDateString() < $retread->sent_on->toDateString()) {
                throw ValidationException::withMessages(['returned_on' => __('It was sent on :date.', ['date' => $retread->sent_on->format('d M Y')])]);
            }
            $retread->update(['returned_on' => $on, 'cost' => $cost, 'expected_km' => $expectedKm, 'vendor_party_id' => $vendorId ?? $retread->vendor_party_id]);
            $tyre->update([
                'status' => 'in_store', 'kind' => 'retread', 'retreads' => $tyre->retreads + 1, 'cost' => round((float) $tyre->cost + $cost, 2),
                // Without an expected km before, the km it has run stand for its first life.
                'expected_km' => $expectedKm ? ($tyre->expected_km ?? $tyre->km_run) + $expectedKm : $tyre->expected_km,
            ]);

            return $tyre;
        });
    }

    /** Scrap a tyre that is not on a truck (one on a truck comes off as worn out, burst or sold). */
    public function scrap(Tyre $tyre, string $on, ?float $value): Tyre
    {
        $this->checkDate($on);

        return DB::transaction(function () use ($tyre, $on, $value) {
            $tyre = Tyre::lockForUpdate()->findOrFail($tyre->id);
            if (! in_array($tyre->status, ['in_store', 'retreading'], true)) {
                throw ValidationException::withMessages(['tyre' => __('Take the tyre off the truck first (worn out, burst or sold scraps it).')]);
            }
            TyreRetread::where('tyre_id', $tyre->id)->whereNull('returned_on')->update(['returned_on' => $on]);
            $tyre->update(['status' => 'scrapped', 'scrapped_on' => $on, 'scrap_value' => $value]);

            return $tyre;
        });
    }

    // ---- Helpers -----------------------------------------------------------------

    /** End the tyre's open fitment at the odometer reading given. */
    private function end(Tyre $tyre, string $on, int $odometer, string $reason, Account $by): TyreFitment
    {
        $fitment = TyreFitment::where('tyre_id', $tyre->id)->whereNull('removed_on')->latest('id')->first();
        if ($tyre->status !== 'fitted' || ! $fitment) {
            throw ValidationException::withMessages(['tyre' => __('Tyre :serial is not fitted.', ['serial' => $tyre->serial_no])]);
        }
        if ($odometer < $fitment->fitted_odo) {
            throw ValidationException::withMessages(['odometer' => __('It was fitted at :km km; the reading cannot be lower.', ['km' => number_format($fitment->fitted_odo)])]);
        }
        if (CarbonImmutable::parse($on)->toDateString() < $fitment->fitted_on->toDateString()) {
            throw ValidationException::withMessages(['removed_on' => __('It was fitted on :date.', ['date' => $fitment->fitted_on->format('d M Y')])]);
        }
        // A spare rides along: it runs no km.
        $km = $fitment->position === self::SPARE ? 0 : $odometer - $fitment->fitted_odo;
        $fitment->update(['removed_on' => $on, 'removed_odo' => $odometer, 'km' => $km, 'reason' => $reason, 'removed_by' => $by->id]);

        return $fitment;
    }

    private function checkDate(string $on): void
    {
        if (CarbonImmutable::parse($on)->toDateString() > $this->today()->toDateString()) {
            throw ValidationException::withMessages(['on' => __('The date cannot be ahead of today.')]);
        }
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
