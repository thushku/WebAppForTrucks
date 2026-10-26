<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\Owner\Concerns\RunsOnce;
use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Models\Tyre;
use App\Modules\Fleet\Models\TyreFitment;
use App\Modules\Fleet\Services\Tyres;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The owner app's tyres (M38): the store and register, a truck's tyres by
 * position, and the workshop's moves — add tyres, fit, take off, move or
 * swap, retread, scrap — each once per Idempotency-Key (§12). The web's
 * rules (`Fleet/Services/Tyres`); seen with truck.view, changed with
 * complaint.manage.
 */
class TyresController extends Controller
{
    use RunsOnce;

    public function __construct(protected CompanyContext $context, private Tyres $tyres) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['status' => ['nullable', Rule::in(array_keys(Tyre::STATUSES))], 'q' => ['nullable', 'string', 'max:40']]);
        $list = Tyre::inBranch()->with(['current', 'truck:id,reg_no,odometer_km'])
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'scrapped'))
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('serial_no', 'like', "%{$s}%")->orWhere('brand', 'like', "%{$s}%")))
            ->orderBy('serial_no')->limit(300)->get();

        return response()->json([
            'tyres' => $list->map(fn (Tyre $t) => $this->row($t))->values(),
            'near_end' => $this->tyres->nearEnd()->map(fn ($r) => $this->row($r['tyre']))->values(),
        ]);
    }

    public function show(Tyre $tyre): JsonResponse
    {
        $tyre->load(['current', 'truck:id,reg_no,odometer_km', 'vendor:id,name', 'fitments.truck:id,reg_no', 'retreadings.vendor:id,name']);

        return response()->json(['tyre' => $this->row($tyre) + [
            'model' => $tyre->model, 'purchased_on' => $tyre->purchased_on?->toDateString(), 'vendor' => $tyre->vendor?->name, 'notes' => $tyre->notes,
            'scrapped_on' => $tyre->scrapped_on?->toDateString(), 'scrap_value' => $tyre->scrap_value !== null ? (float) $tyre->scrap_value : null,
            'fitments' => $tyre->fitments->map(fn (TyreFitment $f) => $this->fitment($f) + ['truck' => $f->truck?->reg_no])->values(),
            'retreads' => $tyre->retreadings->map(fn ($r) => ['sent_on' => $r->sent_on->toDateString(), 'returned_on' => $r->returned_on?->toDateString(),
                'vendor' => $r->vendor?->name, 'cost' => $r->cost !== null ? (float) $r->cost : null, 'expected_km' => $r->expected_km])->values(),
        ]]);
    }

    /** A truck's tyres by axle and position, the tyres in the store to fit, and its last fitments. */
    public function truck(Truck $truck): JsonResponse
    {
        $fitted = $this->tyres->onTruck($truck);

        return response()->json([
            'truck' => ['id' => $truck->id, 'reg_no' => $truck->reg_no, 'odometer_km' => $truck->odometer_km, 'wheels' => $this->tyres->wheels($truck)],
            'axles' => collect($this->tyres->axles($truck))->map(fn ($a) => ['axle' => $a['axle'], 'name' => $a['name'],
                'positions' => collect($a['positions'])->map(fn ($p) => ['position' => $p, 'name' => $this->tyres->positionName($p),
                    'tyre' => isset($fitted[$p]) ? $this->row($fitted[$p]) : null])->values()])->values(),
            'store' => Tyre::inBranch()->with(['current', 'truck:id,reg_no,odometer_km'])->where('status', 'in_store')->orderBy('serial_no')->get()->map(fn (Tyre $t) => $this->row($t))->values(),
            'history' => TyreFitment::with('tyre:id,serial_no,brand')->where('truck_id', $truck->id)->orderByDesc('fitted_on')->orderByDesc('id')->limit(20)->get()
                ->map(fn (TyreFitment $f) => $this->fitment($f) + ['serial_no' => $f->tyre?->serial_no, 'brand' => $f->tyre?->brand])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->once($request, 'tyres', function () use ($request) {
            // The app may send the serial numbers as a list.
            if (is_array($request->input('serials'))) {
                $request->merge(['serials' => implode("\n", array_map('strval', $request->input('serials')))]);
            }
            $added = $this->tyres->add($request->validate($this->tyres->rules()), $request->user());

            return ['tyres' => $added->map(fn (Tyre $t) => $this->row($t))->values()->all()];
        });
    }

    public function fit(Request $request, Truck $truck): JsonResponse
    {
        return $this->once($request, 'tyre.fit', function () use ($request, $truck) {
            $data = $request->validate([
                'tyre_id' => ['required', 'integer', Rule::exists('tyres', 'id')->where('company_id', $this->context->id())],
                'position' => ['required', 'string', 'max:10'], 'fitted_on' => ['nullable', 'date_format:Y-m-d'], 'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            ]);
            $odometer = $data['odometer'] ?? $truck->odometer_km;
            if ($odometer === null) {
                throw \Illuminate\Validation\ValidationException::withMessages(['odometer' => __('Give the odometer reading.')]);
            }
            $tyre = Tyre::inBranch()->findOrFail($data['tyre_id']);
            $this->tyres->fit($tyre, $truck, $data['position'], $data['fitted_on'] ?? $this->today(), (int) $odometer, $request->user());

            return ['tyre' => $this->row($tyre->fresh(['current', 'truck']))];
        });
    }

    public function off(Request $request, Truck $truck, Tyre $tyre): JsonResponse
    {
        abort_unless((int) $tyre->truck_id === (int) $truck->id, 404);

        return $this->once($request, 'tyre.off', function () use ($request, $truck, $tyre) {
            $data = $request->validate([
                'removed_on' => ['nullable', 'date_format:Y-m-d'], 'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
                'reason' => ['required', Rule::in(array_keys(TyreFitment::REASONS))], 'scrap_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
                'vendor_party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where('company_id', $this->context->id())],
            ]);
            $tyre = $this->tyres->remove($tyre, $data['removed_on'] ?? $this->today(), (int) ($data['odometer'] ?? $truck->odometer_km), $data['reason'], $request->user(),
                isset($data['scrap_value']) ? (float) $data['scrap_value'] : null, $data['vendor_party_id'] ?? null);

            return ['tyre' => $this->row($tyre->fresh(['current', 'truck']))];
        });
    }

    public function move(Request $request, Truck $truck, Tyre $tyre): JsonResponse
    {
        abort_unless((int) $tyre->truck_id === (int) $truck->id, 404);

        return $this->once($request, 'tyre.move', function () use ($request, $truck, $tyre) {
            $data = $request->validate([
                'to_truck_id' => ['nullable', 'integer', Rule::exists('trucks', 'id')->where('company_id', $this->context->id())],
                'position' => ['required', 'string', 'max:10'], 'moved_on' => ['nullable', 'date_format:Y-m-d'],
                'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'], 'new_odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            ]);
            $to = ! empty($data['to_truck_id']) ? Truck::inBranch()->findOrFail($data['to_truck_id']) : $truck;
            $tyre = $this->tyres->move($tyre, $to, $data['position'], $data['moved_on'] ?? $this->today(), (int) ($data['odometer'] ?? $truck->odometer_km),
                isset($data['new_odometer']) ? (int) $data['new_odometer'] : null, $request->user());

            return ['tyre' => $this->row($tyre->load(['current', 'truck']))];
        });
    }

    public function retread(Request $request, Tyre $tyre): JsonResponse
    {
        return $this->once($request, 'tyre.retread', function () use ($request, $tyre) {
            $data = $request->validate(['sent_on' => ['nullable', 'date_format:Y-m-d'], 'vendor_party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where('company_id', $this->context->id())]]);
            $this->tyres->sendForRetread($tyre, $data['sent_on'] ?? $this->today(), $data['vendor_party_id'] ?? null, $request->user());

            return ['tyre' => $this->row($tyre->fresh(['current', 'truck']))];
        });
    }

    public function returned(Request $request, Tyre $tyre): JsonResponse
    {
        return $this->once($request, 'tyre.returned', function () use ($request, $tyre) {
            $data = $request->validate(['returned_on' => ['nullable', 'date_format:Y-m-d'], 'cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
                'expected_km' => ['nullable', 'integer', 'min:1000', 'max:1000000'], 'vendor_party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where('company_id', $this->context->id())]]);
            $tyre = $this->tyres->returned($tyre, $data['returned_on'] ?? $this->today(), (float) $data['cost'], isset($data['expected_km']) ? (int) $data['expected_km'] : null, $data['vendor_party_id'] ?? null);

            return ['tyre' => $this->row($tyre->load(['current', 'truck']))];
        });
    }

    public function scrap(Request $request, Tyre $tyre): JsonResponse
    {
        return $this->once($request, 'tyre.scrap', function () use ($request, $tyre) {
            $data = $request->validate(['scrapped_on' => ['nullable', 'date_format:Y-m-d'], 'scrap_value' => ['nullable', 'numeric', 'min:0', 'max:9999999']]);
            $tyre = $this->tyres->scrap($tyre, $data['scrapped_on'] ?? $this->today(), isset($data['scrap_value']) ? (float) $data['scrap_value'] : null);

            return ['tyre' => $this->row($tyre->load(['current', 'truck']))];
        });
    }

    private function row(Tyre $t): array
    {
        $used = $this->tyres->used($t);

        return [
            'id' => $t->id, 'serial_no' => $t->serial_no, 'brand' => $t->brand, 'size' => $t->size, 'kind' => $t->kind, 'retreads' => (int) $t->retreads,
            'status' => $t->status, 'status_label' => __(Tyre::STATUSES[$t->status] ?? $t->status),
            'truck' => $t->status === 'fitted' && $t->truck ? ['id' => $t->truck->id, 'reg_no' => $t->truck->reg_no] : null,
            'position' => $t->position, 'position_name' => $t->position ? $this->tyres->positionName($t->position) : null,
            'fitted_on' => $t->current?->fitted_on?->toDateString(), 'fitted_odo' => $t->current?->fitted_odo,
            'km_run' => $this->tyres->kmRun($t), 'expected_km' => $t->expected_km, 'used' => $used, 'near_end' => $used !== null && $used >= Tyres::NEAR_END,
            'cost' => (float) $t->cost, 'cost_per_km' => $t->costPerKm(),
        ];
    }

    private function fitment(TyreFitment $f): array
    {
        return ['position' => $f->position, 'fitted_on' => $f->fitted_on->toDateString(), 'fitted_odo' => $f->fitted_odo, 'removed_on' => $f->removed_on?->toDateString(),
            'removed_odo' => $f->removed_odo, 'km' => $f->km, 'reason' => $f->reason, 'reason_label' => $f->reason ? __(TyreFitment::REASONS[$f->reason][0] ?? $f->reason) : null];
    }

    private function today(): string
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->toDateString();
    }
}
