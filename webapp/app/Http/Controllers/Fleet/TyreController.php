<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Models\Tyre;
use App\Modules\Fleet\Models\TyreFitment;
use App\Modules\Fleet\Services\Tyres;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Models\Branch;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Trucks → Tyres (M38): the tyre store and register — every tyre with its
 * status, km run and cost per km, the ones near the end of their expected
 * km, what each brand has given — and a tyre's own page with its history.
 * Fitting, taking off and moving are done from the truck's Tyres tab, by
 * position. Seen with truck.view, changed with complaint.manage (workshop
 * work, like service jobs).
 */
class TyreController extends Controller
{
    public function __construct(private CompanyContext $context, private Tyres $tyres) {}

    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), Tyre::STATUSES) ? $request->query('status') : '';
        $q = trim((string) $request->query('q'));
        $list = Tyre::inBranch()->with(['current', 'truck:id,reg_no,odometer_km'])
            ->when($status, fn ($query) => $query->where('status', $status), fn ($query) => $query->where('status', '!=', 'scrapped'))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('serial_no', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%")->orWhere('size', 'like', "%{$q}%")))
            ->orderByRaw("field(status, 'fitted', 'in_store', 'retreading', 'scrapped')")->orderBy('serial_no')->paginate(50)->withQueryString();

        return view('trucks.tyres', [
            'status' => $status, 'q' => $q, 'list' => $list, 'service' => $this->tyres,
            'counts' => Tyre::inBranch()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'near' => $this->tyres->nearEnd(),
            'brands' => $this->tyres->brands(),
            'vendors' => Party::active()->withRole('vendor')->orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'today' => $this->today(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $added = $this->tyres->add($request->validate($this->tyres->rules()), $request->user());

        return redirect()->route('trucks.tyres', ['status' => 'in_store'])->with('status', trans_choice(':count tyre added to the store.|:count tyres added to the store.', $added->count()));
    }

    public function show(Tyre $tyre): View
    {
        $tyre->load(['vendor:id,name', 'truck:id,reg_no,odometer_km', 'current', 'fitments.truck:id,reg_no', 'retreadings.vendor:id,name']);

        return view('trucks.tyre', [
            'tyre' => $tyre, 'service' => $this->tyres, 'km' => $this->tyres->kmRun($tyre), 'used' => $this->tyres->used($tyre),
            'vendors' => Party::active()->withRole('vendor')->orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'today' => $this->today(),
        ]);
    }

    public function update(Request $request, Tyre $tyre): RedirectResponse
    {
        $this->tyres->update($tyre, $request->validate($this->tyres->rules($tyre)));

        return redirect()->route('trucks.tyres.show', $tyre)->with('status', __('Saved.'));
    }

    public function retread(Request $request, Tyre $tyre): RedirectResponse
    {
        $data = $request->validate(['sent_on' => ['required', 'date_format:Y-m-d'], 'vendor_party_id' => $this->vendorRule()]);
        $this->tyres->sendForRetread($tyre, $data['sent_on'], $data['vendor_party_id'] ?? null, $request->user());

        return redirect()->route('trucks.tyres.show', $tyre)->with('status', __('Sent for retreading.'));
    }

    public function returned(Request $request, Tyre $tyre): RedirectResponse
    {
        $data = $request->validate(['returned_on' => ['required', 'date_format:Y-m-d'], 'cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'expected_km' => ['nullable', 'integer', 'min:1000', 'max:1000000'], 'vendor_party_id' => $this->vendorRule()]);
        $this->tyres->returned($tyre, $data['returned_on'], (float) $data['cost'], isset($data['expected_km']) ? (int) $data['expected_km'] : null, $data['vendor_party_id'] ?? null);

        return redirect()->route('trucks.tyres.show', $tyre)->with('status', __('Back in the store, retreaded.'));
    }

    public function scrap(Request $request, Tyre $tyre): RedirectResponse
    {
        $data = $request->validate(['scrapped_on' => ['required', 'date_format:Y-m-d'], 'scrap_value' => ['nullable', 'numeric', 'min:0', 'max:9999999']]);
        $this->tyres->scrap($tyre, $data['scrapped_on'], isset($data['scrap_value']) ? (float) $data['scrap_value'] : null);

        return redirect()->route('trucks.tyres.show', $tyre)->with('status', __('Scrapped.'));
    }

    // ---- On a truck (its Tyres tab) ---------------------------------------------------

    public function fit(Request $request, Truck $truck): RedirectResponse
    {
        $data = $request->validate([
            'tyre_id' => ['required', 'integer', Rule::exists('tyres', 'id')->where('company_id', $this->context->id())],
            'position' => ['required', 'string', 'max:10'], 'fitted_on' => ['required', 'date_format:Y-m-d'], 'odometer' => ['required', 'integer', 'min:0', 'max:9999999'],
        ], ['tyre_id.required' => __('Choose a tyre from the store.')]);
        $tyre = Tyre::inBranch()->findOrFail($data['tyre_id']);
        $this->tyres->fit($tyre, $truck, $data['position'], $data['fitted_on'], (int) $data['odometer'], $request->user());

        return $this->backToTruck($truck, __('Tyre :serial fitted at :position.', ['serial' => $tyre->serial_no, 'position' => $this->tyres->positionName($data['position'])]));
    }

    public function off(Request $request, Truck $truck, Tyre $tyre): RedirectResponse
    {
        abort_unless((int) $tyre->truck_id === (int) $truck->id, 404);
        $data = $request->validate([
            'removed_on' => ['required', 'date_format:Y-m-d'], 'odometer' => ['required', 'integer', 'min:0', 'max:9999999'],
            'reason' => ['required', Rule::in(array_keys(TyreFitment::REASONS))], 'scrap_value' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'vendor_party_id' => $this->vendorRule(),
        ]);
        $this->tyres->remove($tyre, $data['removed_on'], (int) $data['odometer'], $data['reason'], $request->user(),
            isset($data['scrap_value']) ? (float) $data['scrap_value'] : null, $data['vendor_party_id'] ?? null);

        return $this->backToTruck($truck, __('Tyre :serial taken off: :reason.', ['serial' => $tyre->serial_no, 'reason' => __(TyreFitment::REASONS[$data['reason']][0])]));
    }

    public function move(Request $request, Truck $truck, Tyre $tyre): RedirectResponse
    {
        abort_unless((int) $tyre->truck_id === (int) $truck->id, 404);
        $data = $request->validate([
            'to_truck_id' => ['nullable', 'integer', Rule::exists('trucks', 'id')->where('company_id', $this->context->id())],
            'position' => ['required', 'string', 'max:10'], 'moved_on' => ['required', 'date_format:Y-m-d'],
            'odometer' => ['required', 'integer', 'min:0', 'max:9999999'], 'new_odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ]);
        $to = ! empty($data['to_truck_id']) ? Truck::inBranch()->findOrFail($data['to_truck_id']) : $truck;
        $this->tyres->move($tyre, $to, $data['position'], $data['moved_on'], (int) $data['odometer'], isset($data['new_odometer']) ? (int) $data['new_odometer'] : null, $request->user());

        return $this->backToTruck($truck, __('Tyre :serial moved to :position.', ['serial' => $tyre->serial_no, 'position' => ($to->id !== $truck->id ? $to->reg_no . ' ' : '') . $this->tyres->positionName($data['position'])]));
    }

    private function backToTruck(Truck $truck, string $message): RedirectResponse
    {
        return redirect()->route('trucks.show', [$truck, 'tab' => 'tyres'])->with('status', $message);
    }

    private function vendorRule(): array
    {
        return ['nullable', 'integer', Rule::exists('parties', 'id')->where('company_id', $this->context->id())];
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }
}
