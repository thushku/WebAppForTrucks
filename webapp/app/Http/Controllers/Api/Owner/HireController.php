<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\Owner\Concerns\RunsOnce;
use App\Http\Controllers\Controller;
use App\Modules\Trips\Models\HireChallan;
use App\Modules\Trips\Models\HireChallanLine;
use App\Modules\Trips\Models\Trip;
use App\Modules\Trips\Services\HireService;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A hired truck's lorry hire challan from the owner app (M42): the draft
 * (filled from the trip, with the suggested TDS), edits while it is a
 * draft, issue (its HC/ number), then the money on it — advance, fuel
 * given, detention, deductions, the balance paid. The web's rules
 * (`HireService`); every write once per Idempotency-Key (§12). M49: an
 * attached truck's trip has the owner's challan the same way — the hire
 * is the loads' freight, our commission kept back — and what we spent on
 * the trip is listed to charge back as a deduction.
 */
class HireController extends Controller
{
    use RunsOnce;

    public function __construct(protected CompanyContext $context, private HireService $hire) {}

    /** The trip's challans, and what a new one would start with. */
    public function index(string $uuid): JsonResponse
    {
        $trip = $this->trip($uuid);

        $challans = HireChallan::with('lines')->where('trip_id', $trip->id)->orderBy('id')->get();
        $attached = $trip->isAttached();

        return response()->json([
            'attached' => $attached,
            'supplier' => ($party = $trip->hireParty()) ? ['id' => $party->id, 'name' => $party->name] : null,
            // On an attached truck, our spending on the trip (the owner's) and what is charged back so far.
            'spent' => $attached ? $this->hire->spentByUs($trip)->map(fn ($r) => ['ledger' => $r->name, 'amount' => $r->amount])->values() : [],
            'charged' => round((float) $challans->where('status', '!=', 'cancelled')->flatMap->lines->where('kind', 'deduction')->sum('amount'), 2),
            'challans' => $challans->map(fn ($c) => $this->row($c))->values(),
            'new' => $this->hire->draftFor($trip) + ['hire_basis' => 'fixed', 'commission_mode' => 'deducted'],
            'line_kinds' => collect(HireChallan::LINE_KINDS)->map(fn ($k, $code) => ['code' => $code, 'label' => __($k[0]), 'adds' => $k[1] > 0])->values(),
        ]);
    }

    public function store(Request $request, string $uuid): JsonResponse
    {
        $trip = $this->trip($uuid);

        return $this->once($request, 'hire.draft', function () use ($request, $trip) {
            $data = $this->hire->prepare($request->validate($this->hire->rules()));

            return ['challan' => $this->row($this->hire->save($trip, null, $data + ['supplier_vehicle_id' => $trip->supplier_vehicle_id])->load('lines'))];
        });
    }

    /** Change a draft: send it whole, as the web's form does. */
    public function update(Request $request, string $uuid, HireChallan $challan): JsonResponse
    {
        $trip = $this->trip($uuid);
        abort_unless((int) $challan->trip_id === (int) $trip->id, 404);
        $data = $this->hire->prepare($request->validate($this->hire->rules()));

        return response()->json(['challan' => $this->row($this->hire->save($trip, $challan, $data)->load('lines'))]);
    }

    public function issue(Request $request, string $uuid, HireChallan $challan): JsonResponse
    {
        $trip = $this->trip($uuid);
        abort_unless((int) $challan->trip_id === (int) $trip->id, 404);

        return $this->once($request, 'hire.issue', function () use ($challan) {
            $this->hire->issue($challan);

            return ['challan' => $this->row($challan->fresh('lines'))];
        });
    }

    public function line(Request $request, string $uuid, HireChallan $challan): JsonResponse
    {
        $trip = $this->trip($uuid);
        abort_unless((int) $challan->trip_id === (int) $trip->id, 404);

        return $this->once($request, 'hire.line', function () use ($request, $challan) {
            $this->hire->addLine($challan, $request->validate($this->hire->lineRules(), $this->hire->lineMessages()));

            return ['challan' => $this->row($challan->fresh('lines'))];
        });
    }

    private function trip(string $uuid): Trip
    {
        $trip = Trip::inBranch()->where('uuid', $uuid)->firstOrFail();
        abort_unless($trip->hasHireChallan(), 404);

        return $trip;
    }

    private function row(HireChallan $c): array
    {
        return [
            'id' => $c->id, 'number' => $c->number, 'status' => $c->status, 'status_label' => __(HireChallan::STATUSES[$c->status] ?? $c->status),
            'challan_date' => $c->challan_date?->toDateString(), 'vehicle_reg' => $c->vehicle_reg, 'driver_name' => $c->driver_name, 'driver_mobile' => $c->driver_mobile,
            'hire_basis' => $c->hire_basis, 'hire_rate' => $c->hire_rate !== null ? (float) $c->hire_rate : null, 'hire_weight_mt' => $c->hire_weight_mt !== null ? (float) $c->hire_weight_mt : null,
            'hire_amount' => (float) $c->hire_amount, 'office_charge' => (float) $c->office_charge, 'commission_amount' => (float) $c->commission_amount, 'commission_mode' => $c->commission_mode,
            'tds_section' => $c->tds_section, 'tds_rate' => (float) $c->tds_rate, 'tds_amount' => (float) $c->tds_amount, 'balance' => round($c->balance(), 2),
            'lines' => $c->lines->map(fn (HireChallanLine $l) => ['kind' => $l->kind, 'label' => __(HireChallan::LINE_KINDS[$l->kind][0] ?? $l->kind), 'amount' => (float) $l->amount,
                'date' => $l->line_date?->toDateString(), 'mode' => $l->mode, 'paid_to' => $l->paid_to, 'reference' => $l->reference, 'note' => $l->note])->values(),
        ];
    }
}
