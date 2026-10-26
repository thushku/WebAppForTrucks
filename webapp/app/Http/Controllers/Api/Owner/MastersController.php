<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Api\Owner\Concerns\RunsOnce;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\PlaceController;
use App\Modules\Documents\Services\Compliance;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Services\PartyService;
use App\Modules\Parties\Services\TaxIds;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\Place;
use App\Platform\Auth\Identifier;
use App\Platform\Billing\Limits;
use App\Platform\Tenancy\BranchContext;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Regions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The owner app's masters (M29, 13-parity #9): clients, places, trucks and
 * crew — a card list, open one, add, edit, deactivate (never delete). The
 * web's permissions and checks; the phone sends the everyday fields, the
 * rest stays on the web's full forms.
 *
 * Adding runs once per Idempotency-Key (§12). Editing changes only the fields
 * sent, so sending it again is harmless; deactivating likewise. Every change
 * is in the audit log, as from the web (the models record it).
 */
class MastersController extends Controller
{
    use RunsOnce;

    public function __construct(protected CompanyContext $context, private BranchContext $branches) {}

    // ---- Clients ------------------------------------------------------------------------

    public function clients(Request $request): JsonResponse
    {
        [$q, $status] = $this->filters($request);
        $page = Party::withRole('client')->with('taxIds')
            ->when($status !== 'all', fn ($w) => $w->where('active', $status === 'active'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $this->like($q))->orWhere('short_code', $q)->orWhere('mobile', 'like', $this->like(preg_replace('/\D/', '', $q) ?: $q))))
            ->orderBy('name')->simplePaginate(50);
        $due = Invoice::inBranch()->whereIn('party_id', $page->getCollection()->pluck('id'))->whereIn('status', Invoice::OPEN_STATUSES)
            ->groupBy('party_id')->selectRaw('party_id, sum(balance_due) as due')->pluck('due', 'party_id');

        return response()->json(['clients' => $page->getCollection()->map(fn (Party $p) => $this->clientCard($p) + ['due' => round((float) ($due[$p->id] ?? 0), 2)])->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null]);
    }

    public function client(Party $party): JsonResponse
    {
        abort_unless(in_array('client', $party->roleCodes(), true), 404);

        return response()->json(['client' => $this->clientFull($party->load('taxIds'))]);
    }

    public function storeClient(Request $request, PartyService $parties): JsonResponse
    {
        return $this->once($request, 'client', function () use ($request, $parties) {
            [$fields, $taxId] = $this->clientFields($request, null);
            $fields['short_code'] ??= $this->freePartyCode($fields['name']);
            $party = $parties->save(null, $fields + ['country' => $this->context->company()->country, 'active' => true, 'created_by' => $request->user()->id], ['client'], $taxId);

            return ['client' => $this->clientFull($party->load('taxIds'))];
        });
    }

    public function updateClient(Request $request, Party $party, PartyService $parties): JsonResponse
    {
        abort_unless(in_array('client', $party->roleCodes(), true), 404);
        [$fields] = $this->clientFields($request, $party);
        $parties->save($party, $fields, $party->roleCodes());

        return response()->json(['client' => $this->clientFull($party->fresh('taxIds'))]);
    }

    public function activeClient(Request $request, Party $party): JsonResponse
    {
        abort_unless(in_array('client', $party->roleCodes(), true), 404);
        $party->forceFill(['active' => $this->activeFlag($request)])->save();

        return response()->json(['client' => $this->clientFull($party->fresh('taxIds'))]);
    }

    // ---- Places -------------------------------------------------------------------------

    public function places(Request $request): JsonResponse
    {
        [$q, $status] = $this->filters($request);
        $page = Place::query()
            ->when($status !== 'all', fn ($w) => $w->where('active', $status === 'active'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $this->like($q))->orWhere('short_code', $q)->orWhere('city', 'like', $this->like($q))))
            ->orderBy('name')->simplePaginate(100);

        return response()->json(['places' => $page->getCollection()->map(fn (Place $p) => $this->placeCard($p))->values(), 'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null]);
    }

    public function storePlace(Request $request, PlaceController $codes): JsonResponse
    {
        return $this->once($request, 'place', function () use ($request, $codes) {
            $data = $this->placeFields($request, null);
            $data['short_code'] ??= $codes->freeCode($data['name']);
            $data['type'] ??= filled($data['address'] ?? null) ? 'customer_site' : 'city';
            $place = Place::create($data + ['country' => $this->context->company()->country, 'active' => true]);

            return ['place' => $this->placeCard($place)];
        });
    }

    public function updatePlace(Request $request, Place $place): JsonResponse
    {
        $place->update($this->placeFields($request, $place));

        return response()->json(['place' => $this->placeCard($place->fresh())]);
    }

    public function activePlace(Request $request, Place $place): JsonResponse
    {
        $place->forceFill(['active' => $this->activeFlag($request)])->save();

        return response()->json(['place' => $this->placeCard($place->fresh())]);
    }

    // ---- Trucks (the list is GET /owner/trucks) -------------------------------------------

    public function truck(Truck $truck): JsonResponse
    {
        return response()->json(['truck' => $this->truckFull($truck)]);
    }

    public function storeTruck(Request $request): JsonResponse
    {
        return $this->once($request, 'truck', function () use ($request) {
            app(Limits::class)->assertRoom($this->context->company(), 'trucks');
            $data = $this->truckFields($request, null);
            $data['branch_id'] = $this->branchFor($data['branch_id'] ?? null);
            $truck = Truck::create($data + ['status' => 'idle', 'fuel_type' => 'diesel', 'ownership' => 'own', 'created_by' => $request->user()->id,
                'odometer_at' => isset($data['odometer_km']) ? now() : null]);

            return ['truck' => $this->truckFull($truck->fresh())];
        });
    }

    public function updateTruck(Request $request, Truck $truck): JsonResponse
    {
        $data = $this->truckFields($request, $truck);
        if (array_key_exists('branch_id', $data)) {
            $data['branch_id'] = $this->branchFor($data['branch_id']);
        }
        $truck->update($data);

        return response()->json(['truck' => $this->truckFull($truck->fresh())]);
    }

    /** Free, in the workshop, broken down, out of service or sold — as the truck page's status buttons. */
    public function truckStatus(Request $request, Truck $truck): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['idle', 'workshop', 'breakdown', 'out_of_service', 'sold'])]]);
        if ($truck->status === 'on_trip') {
            throw ValidationException::withMessages(['status' => __('The truck is on a trip. Its status changes when the trip ends.')]);
        }
        $truck->forceFill(['status' => $data['status'], 'sold_on' => $data['status'] === 'sold' ? ($truck->sold_on ?? now()) : null])->save();

        return response()->json(['truck' => $this->truckFull($truck->fresh())]);
    }

    // ---- Crew ---------------------------------------------------------------------------

    public function crew(Request $request, Compliance $compliance): JsonResponse
    {
        [$q, $status] = $this->filters($request, 'working');
        $type = (string) $request->query('type', '');
        $page = Person::inBranch()->with('defaultTruck:id,reg_no')
            ->when(isset(Person::TYPES[$type]), fn ($w) => $w->where('type', $type))
            ->when($status === 'working' || $status === 'active', fn ($w) => $w->where('status', '!=', 'left'))
            ->when($status === 'left' || $status === 'inactive', fn ($w) => $w->where('status', 'left'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', $this->like($q))->orWhere('mobile', 'like', $this->like(preg_replace('/\D/', '', $q) ?: $q))->orWhere('licence_no', $q)))
            ->orderBy('name')->simplePaginate(50);
        $papers = $request->user()->can('document.view') ? $compliance->healthMany($page->getCollection()) : [];

        return response()->json(['crew' => $page->getCollection()->map(fn (Person $p) => $this->personCard($p) + ['papers' => $papers[$p->id] ?? null])->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null]);
    }

    public function person(Person $person): JsonResponse
    {
        return response()->json(['person' => $this->personFull($person)]);
    }

    public function storePerson(Request $request): JsonResponse
    {
        return $this->once($request, 'person', function () use ($request) {
            $data = $this->personFields($request, null);
            $data['branch_id'] = $this->branchFor($data['branch_id'] ?? null);
            $data['pay_basis'] ??= 'monthly';
            $person = Person::create($data + ['status' => 'active', 'nationality' => $this->context->company()->country, 'created_by' => $request->user()->id]);

            return ['person' => $this->personFull($person->fresh())];
        });
    }

    public function updatePerson(Request $request, Person $person): JsonResponse
    {
        $data = $this->personFields($request, $person);
        if (array_key_exists('branch_id', $data)) {
            $data['branch_id'] = $this->branchFor($data['branch_id']);
        }
        $person->update($data);

        return response()->json(['person' => $this->personFull($person->fresh())]);
    }

    /** Off: they have left (from today, unless a date is given); on: working again. */
    public function activePerson(Request $request, Person $person): JsonResponse
    {
        $active = $this->activeFlag($request);
        $on = $request->validate(['leaving_date' => ['nullable', 'date_format:Y-m-d']])['leaving_date'] ?? null;
        if (! $active && \App\Modules\Trips\Models\Trip::open()->whereHas('crew', fn ($q) => $q->where('person_id', $person->id))->exists()) {
            throw ValidationException::withMessages(['active' => __(':name is on a trip that is not ended.', ['name' => $person->name])]);
        }
        $person->update($active ? ['status' => 'active', 'leaving_date' => null] : ['status' => 'left', 'leaving_date' => $on ?? now()->toDateString()]);

        return response()->json(['person' => $this->personFull($person->fresh())]);
    }

    // ---- Fields -------------------------------------------------------------------------

    /** @return array{0: array<string, mixed>, 1: ?string} the fields sent (all on create) and, on create, the GSTIN / TRN */
    private function clientFields(Request $request, ?Party $party): array
    {
        $companyId = $this->context->id();
        $country = $party->country ?? $this->context->company()->country;
        $data = $request->validate($this->partial([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'short_code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{2,6}$/', Rule::unique('parties', 'short_code')->where('company_id', $companyId)->ignore($party?->id)],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email:filter', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:200'], 'address_line2' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:100'],
            'state_code' => ['nullable', Rule::in(array_keys(Regions::for($country)))],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'tax_id' => [$party ? 'prohibited' : 'nullable', 'string', 'max:20'],
        ], $party !== null), ['short_code.regex' => __('2 to 6 letters or digits.'), 'short_code.unique' => __('Another party has this code.'),
            'tax_id.prohibited' => __('Add or change tax numbers on the web (the client’s page → Tax IDs).')]);

        if (filled($data['mobile'] ?? null)) {
            $data['mobile'] = Identifier::mobile($data['mobile'], $country) ?? throw ValidationException::withMessages(['mobile' => __('Enter a valid mobile number.')]);
        }
        $taxId = filled($data['tax_id'] ?? null) ? TaxIds::normalise($data['tax_id']) : null;
        if ($taxId && ! TaxIds::isValid($country, $taxId)) {
            throw ValidationException::withMessages(['tax_id' => __('This :label is not valid. Check it for a typing mistake.', ['label' => TaxIds::label($country)])]);
        }
        unset($data['tax_id']);
        if (isset($data['short_code'])) {
            $data['short_code'] = strtoupper($data['short_code']);
        }

        return [$this->trimmed($data), $taxId];
    }

    private function placeFields(Request $request, ?Place $place): array
    {
        $company = $this->context->company();
        $data = $request->validate($this->partial([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'short_code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{2,6}$/', Rule::unique('places', 'short_code')->where('company_id', $company->id)->ignore($place?->id)],
            'type' => ['nullable', Rule::in(array_keys(Place::TYPES))],
            'address' => ['nullable', 'string', 'max:500'], 'city' => ['nullable', 'string', 'max:100'],
            'state_code' => ['nullable', Rule::in(array_keys(Regions::for($company->country)))], 'postal_code' => ['nullable', 'string', 'max:20'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'], 'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
        ], $place !== null), ['short_code.regex' => __('2 to 6 letters or digits.'), 'short_code.unique' => __('Another place has this code.')]);
        // The pin on the map is both numbers or none, on an edit too.
        if (array_key_exists('lat', $data) !== array_key_exists('lng', $data)) {
            $missing = array_key_exists('lat', $data) ? 'lng' : 'lat';
            throw ValidationException::withMessages([$missing => __('Send the latitude and the longitude together.')]);
        }
        if (isset($data['short_code'])) {
            $data['short_code'] = strtoupper($data['short_code']);
        }
        if ($place && array_key_exists('type', $data) && $data['type'] === null) {
            unset($data['type']);
        }

        return $this->trimmed($data);
    }

    private function truckFields(Request $request, ?Truck $truck): array
    {
        $companyId = $this->context->id();
        $country = $this->context->company()->country;
        $data = $request->validate($this->partial([
            'reg_no' => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9\s\-]+$/'],
            'rc_owner_name' => ['required', 'string', 'min:2', 'max:150'],
            'truck_type' => ['nullable', Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'truck_type')],
            'body_type' => ['nullable', Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'body_type')],
            'vehicle_class' => ['nullable', 'string', 'max:60'], 'make' => ['nullable', 'string', 'max:60'], 'model' => ['nullable', 'string', 'max:80'],
            'manufacture_year' => ['nullable', 'integer', 'min:1980', 'max:' . (now()->year + 1)],
            'registration_state' => ['nullable', Rule::in(array_keys(Regions::for($country)))],
            'registration_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fuel_type' => ['nullable', Rule::in(array_keys(Truck::FUELS))],
            'ownership' => ['nullable', Rule::in(array_keys(Truck::OWNERSHIP))],
            'owner_party_id' => ['nullable', 'required_if:ownership,attached', 'integer', Rule::exists('parties', 'id')->where('company_id', $companyId)],
            'payload_mt' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], $truck !== null), ['reg_no.regex' => __('Use letters, digits, spaces and dashes only.'),
            'owner_party_id.required_if' => __('Choose the owner of an attached truck (add them as a supplier first).')]);

        if (isset($data['reg_no'])) {
            $data['reg_no'] = strtoupper(trim(preg_replace('/\s+/', ' ', $data['reg_no'])));
            if ($other = Truck::where('reg_key', Truck::key($data['reg_no']))->where('id', '!=', $truck?->id ?? 0)->first()) {
                throw ValidationException::withMessages(['reg_no' => __(':reg is already in your fleet.', ['reg' => $other->reg_no])]);
            }
        }
        if (($data['ownership'] ?? $truck?->ownership) !== 'attached' && array_key_exists('ownership', $data)) {
            $data['owner_party_id'] = null;
        }
        foreach (['fuel_type', 'ownership'] as $f) {
            if (array_key_exists($f, $data) && $data[$f] === null) {
                unset($data[$f]);
            }
        }
        // The odometer is typed once; after that trips and jobs move it forward.
        if ($truck && $truck->odometer_km !== null) {
            unset($data['odometer_km']);
        } elseif ($truck && isset($data['odometer_km'])) {
            $data['odometer_at'] = now();
        }

        return $this->trimmed($data);
    }

    private function personFields(Request $request, ?Person $person): array
    {
        $companyId = $this->context->id();
        $country = $this->context->company()->country;
        $data = $request->validate($this->partial([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'type' => ['required', Rule::in(array_keys(Person::TYPES))],
            'mobile' => ['nullable', 'string', 'max:20'], 'alt_mobile' => ['nullable', 'string', 'max:20'],
            'employee_code' => ['nullable', 'string', 'max:20', Rule::unique('people', 'employee_code')->where('company_id', $companyId)->ignore($person?->id)],
            'joining_date' => ['nullable', 'date_format:Y-m-d'],
            'licence_no' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\s\/]+$/'], 'licence_class' => ['nullable', 'string', 'max:40'],
            'licence_expiry' => ['nullable', 'date_format:Y-m-d'], 'badge_no' => ['nullable', 'string', 'max:30'],
            'pay_basis' => ['nullable', Rule::in(array_keys(Person::PAY_BASES))],
            'monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999'], 'per_trip_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'per_km_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'default_truck_id' => ['nullable', 'integer', Rule::exists('trucks', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'address' => ['nullable', 'string', 'max:500'], 'emergency_name' => ['nullable', 'string', 'max:120'], 'emergency_mobile' => ['nullable', 'string', 'max:20'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], $person !== null));

        foreach (['mobile', 'alt_mobile', 'emergency_mobile'] as $field) {
            if (filled($data[$field] ?? null)) {
                $data[$field] = Identifier::mobile($data[$field], $country) ?? throw ValidationException::withMessages([$field => __('Enter a valid mobile number.')]);
            }
        }
        if (! empty($data['mobile']) && Person::where('mobile', $data['mobile'])->where('id', '!=', $person?->id ?? 0)->exists()) {
            throw ValidationException::withMessages(['mobile' => __('Another person has this mobile number.')]);
        }
        // The crew app signs in with the mobile number: a new one is changed on the web, which ends the old login.
        if ($person && $person->company_user_id && array_key_exists('mobile', $data) && $data['mobile'] !== $person->mobile) {
            throw ValidationException::withMessages(['mobile' => __(':name uses the crew app with this number. Change it on the web, which ends the old login.', ['name' => $person->name])]);
        }
        // With a licence document on file, its number and expiry come from there (renew it in Documents).
        if ($person && \App\Modules\Documents\Models\Document::where('owner_type', 'person')->where('owner_id', $person->id)->where('document_type', 'licence')->exists()) {
            unset($data['licence_no'], $data['licence_expiry']);
        }
        if (isset($data['licence_no'])) {
            $data['licence_no'] = strtoupper(preg_replace('/\s+/', '', $data['licence_no']));
        }
        if ($person && array_key_exists('pay_basis', $data) && $data['pay_basis'] === null) {
            unset($data['pay_basis']);
        }

        return $this->trimmed($data);
    }

    // ---- Answers ------------------------------------------------------------------------

    private function clientCard(Party $p): array
    {
        $gstin = $p->taxIds->sortByDesc('is_default')->first();

        return ['id' => $p->id, 'name' => $p->name, 'short_code' => $p->short_code, 'mobile' => $p->mobile, 'email' => $p->email, 'city' => $p->city,
            'state' => Regions::name($p->country, $p->state_code), 'tax_id' => $gstin?->tax_id, 'active' => (bool) $p->active];
    }

    private function clientFull(Party $p): array
    {
        return $this->clientCard($p) + $p->only(['legal_name', 'address_line1', 'address_line2', 'state_code', 'postal_code', 'credit_days', 'notes'])
            + ['tax_ids' => $p->taxIds->map(fn ($t) => ['number' => $t->tax_id, 'default' => (bool) $t->is_default])->values(), 'roles' => $p->roleCodes()];
    }

    private function placeCard(Place $p): array
    {
        return ['id' => $p->id, 'name' => $p->name, 'short_code' => $p->short_code, 'type' => $p->type, 'type_label' => __(Place::TYPES[$p->type] ?? $p->type),
            'address' => $p->address, 'city' => $p->city, 'state_code' => $p->state_code, 'postal_code' => $p->postal_code,
            'lat' => $p->lat !== null ? (float) $p->lat : null, 'lng' => $p->lng !== null ? (float) $p->lng : null, 'active' => (bool) $p->active];
    }

    private function truckFull(Truck $t): array
    {
        return ['id' => $t->id, 'status' => $t->status, 'status_label' => __(Truck::STATUSES[$t->status] ?? $t->status)]
            + $t->only(['reg_no', 'rc_owner_name', 'truck_type', 'body_type', 'vehicle_class', 'make', 'model', 'manufacture_year', 'registration_state',
                'fuel_type', 'ownership', 'owner_party_id', 'odometer_km', 'branch_id', 'notes'])
            + ['registration_date' => $t->registration_date?->toDateString(), 'payload_mt' => $t->payload_mt !== null ? (float) $t->payload_mt : null,
                'odometer_locked' => $t->odometer_km !== null];
    }

    private function personCard(Person $p): array
    {
        return ['id' => $p->id, 'name' => $p->name, 'type' => $p->type, 'type_label' => __(Person::TYPES[$p->type] ?? $p->type), 'mobile' => $p->mobile,
            'status' => $p->status, 'licence_no' => $p->licence_no, 'licence_expiry' => $p->licence_expiry?->toDateString(), 'licence_state' => $p->licenceState(),
            'truck' => $p->defaultTruck?->reg_no, 'app_linked' => $p->company_user_id !== null];
    }

    private function personFull(Person $p): array
    {
        $p->loadMissing('defaultTruck:id,reg_no');
        $pay = auth()->user()->can('payroll.view') || auth()->user()->can('people.manage');

        return $this->personCard($p) + $p->only(['alt_mobile', 'employee_code', 'licence_class', 'badge_no', 'default_truck_id', 'branch_id', 'address', 'emergency_name', 'emergency_mobile', 'blood_group', 'notes'])
            + ['joining_date' => $p->joining_date?->toDateString(), 'leaving_date' => $p->leaving_date?->toDateString()]
            + ($pay ? ['pay_basis' => $p->pay_basis, 'monthly_salary' => $p->monthly_salary !== null ? (float) $p->monthly_salary : null,
                'per_trip_rate' => $p->per_trip_rate !== null ? (float) $p->per_trip_rate : null, 'per_km_rate' => $p->per_km_rate !== null ? (float) $p->per_km_rate : null] : []);
    }

    // ---- Helpers ------------------------------------------------------------------------

    /** On an edit every rule becomes "only when sent". */
    private function partial(array $rules, bool $edit): array
    {
        return $edit ? array_map(fn ($r) => array_merge(['sometimes'], $r), $rules) : $rules;
    }

    private function trimmed(array $data): array
    {
        return array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $data);
    }

    /** @return array{0: string, 1: string} the search text and active / inactive / all */
    private function filters(Request $request, string $default = 'active'): array
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:60'], 'status' => ['nullable', Rule::in(['active', 'inactive', 'all', 'working', 'left'])]]);

        return [trim((string) ($f['q'] ?? '')), $f['status'] ?? $default];
    }

    private function like(string $q): string
    {
        return '%' . addcslashes($q, '%_\\') . '%';
    }

    private function activeFlag(Request $request): bool
    {
        return (bool) $request->validate(['active' => ['required', 'boolean']])['active'];
    }

    /** A person limited to a branch adds and moves things only within it. */
    private function branchFor(?int $branchId): ?int
    {
        return $this->branches->limited() ? $this->branches->defaultBranch() : ($branchId ?? $this->branches->defaultBranch());
    }

    private function freePartyCode(string $name): string
    {
        $base = Party::suggestCode($name);
        $code = $base;
        for ($i = 2; Party::where('short_code', $code)->exists() && $i < 100; $i++) {
            $code = substr($base, 0, 5) . $i;
        }

        return $code;
    }
}
