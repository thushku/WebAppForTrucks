<?php

namespace App\Http\Controllers\Trips;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\PlaceController;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Services\PartyService;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\Place;
use App\Platform\Billing\Limits;
use App\Platform\Tenancy\BranchContext;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "+ New" inside the trip form (M25, 13-parity #3): a client, a driver or
 * helper, a truck or a place with just the fields needed to plan the trip;
 * the rest is filled in later on its own page. Answers the new record for
 * the form to select at once.
 */
class QuickAddController extends Controller
{
    public function __construct(private CompanyContext $context) {}

    public function client(Request $request, PartyService $parties): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:150'], 'mobile' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,20}$/']]);
        $code = Party::suggestCode($data['name']);
        for ($i = 2, $base = $code; Party::where('short_code', $code)->exists() && $i < 100; $i++) {
            $code = substr($base, 0, 5) . $i;
        }
        $party = $parties->save(null, ['name' => trim($data['name']), 'mobile' => $data['mobile'] ?? null, 'short_code' => $code, 'country' => $this->context->company()->country], ['client']);

        return response()->json(['id' => $party->id, 'label' => $party->name . ' (' . $party->short_code . ')'], 201);
    }

    public function person(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'], 'mobile' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,20}$/'],
            'type' => ['required', Rule::in(['driver', 'helper'])],
        ]);
        $person = Person::create(['name' => trim($data['name']), 'mobile' => $data['mobile'] ?? null, 'type' => $data['type'], 'status' => 'active',
            'branch_id' => app(BranchContext::class)->defaultBranch()]);

        return response()->json(['id' => $person->id, 'label' => $person->name, 'type' => $person->type], 201);
    }

    public function truck(Request $request): JsonResponse
    {
        app(Limits::class)->assertRoom($this->context->company(), 'trucks');
        $data = $request->validate([
            'reg_no' => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9\s\-]+$/'],
            'rc_owner_name' => ['required', 'string', 'min:2', 'max:150'],
        ], ['reg_no.regex' => __('Use letters, digits, spaces and dashes only.')]);
        $reg = strtoupper(trim(preg_replace('/\s+/', ' ', $data['reg_no'])));
        if ($other = Truck::where('reg_key', Truck::key($reg))->first()) {
            throw ValidationException::withMessages(['reg_no' => __(':reg is already in your fleet.', ['reg' => $other->reg_no])]);
        }
        $truck = Truck::create(['reg_no' => $reg, 'rc_owner_name' => trim($data['rc_owner_name']), 'fuel_type' => 'diesel', 'ownership' => 'own', 'status' => 'idle',
            'branch_id' => app(BranchContext::class)->defaultBranch(), 'created_by' => $request->user()->id]);

        return response()->json(['id' => $truck->id, 'label' => $truck->reg_no], 201);
    }

    public function place(Request $request, PlaceController $places): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:120'], 'address' => ['nullable', 'string', 'max:500']]);
        $place = Place::create(['name' => trim($data['name']), 'short_code' => $places->freeCode($data['name']), 'address' => $data['address'] ?? null,
            'type' => filled($data['address'] ?? null) ? 'customer_site' : 'city', 'country' => $this->context->company()->country, 'active' => true]);

        return response()->json(['id' => $place->id, 'label' => $place->name . ' (' . $place->short_code . ')'], 201);
    }
}
