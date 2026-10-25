<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\Place;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Regions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Places with short codes (decision P1): where trips load, unload and stop.
 * The code goes into trip numbers, so it is checked to be unique and is
 * offered automatically from the name.
 */
class PlaceController extends Controller
{
    public function __construct(private CompanyContext $context) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $type   = (string) $request->query('type', '');

        $places = Place::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%' . addcslashes($search, '%_\\') . '%')->orWhere('short_code', 'like', addcslashes($search, '%_\\') . '%')))
            ->when(isset(Place::TYPES[$type]), fn ($q) => $q->where('type', $type))
            ->orderByDesc('active')->orderBy('name')
            ->paginate(50)->withQueryString();

        return view('settings.places', [
            'places'  => $places,
            'search'  => $search,
            'type'    => $type,
            'editing' => null,
            'regions' => Regions::for($this->context->company()->country),
            'country' => $this->context->company()->country,
        ]);
    }

    public function edit(Place $place): View
    {
        return view('settings.places', [
            'places'  => Place::orderByDesc('active')->orderBy('name')->paginate(50),
            'search'  => '',
            'type'    => '',
            'editing' => $place,
            'regions' => Regions::for($this->context->company()->country),
            'country' => $this->context->company()->country,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Place::create($this->validated($request));

        return redirect()->route('settings.places')->with('status', __('Place added.'));
    }

    public function update(Request $request, Place $place): RedirectResponse
    {
        $place->update($this->validated($request, $place));

        return redirect()->route('settings.places')->with('status', __('Place saved.'));
    }

    /** A free short code for a name, for the form to offer as you type. */
    public function suggest(Request $request): JsonResponse
    {
        $name = (string) $request->validate(['name' => ['required', 'string', 'max:150']])['name'];

        return response()->json(['code' => $this->freeCode($name)]);
    }

    /** "BHI", then "BHI2"… until one is not used yet. */
    public function freeCode(string $name): string
    {
        $base = Place::suggestCode($name);
        $code = $base;
        for ($i = 2; Place::where('short_code', $code)->exists() && $i < 100; $i++) {
            $code = $base . $i;
        }

        return $code;
    }

    private function validated(Request $request, ?Place $place = null): array
    {
        $company = $this->context->company();

        $data = $request->validate([
            'name'        => ['required', 'string', 'min:2', 'max:150'],
            'short_code'  => ['required', 'string', 'regex:/^[A-Za-z0-9]{2,6}$/',
                Rule::unique('places', 'short_code')->where('company_id', $company->id)->ignore($place?->id)],
            'type'        => ['required', Rule::in(array_keys(Place::TYPES))],
            'address'     => ['nullable', 'string', 'max:500'],
            'city'        => ['nullable', 'string', 'max:100'],
            'state_code'  => ['nullable', Rule::in(array_keys(Regions::for($company->country)))],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'lat'         => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng'         => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'active'      => ['nullable', 'boolean'],
        ], ['short_code.regex' => __('2 to 6 letters or digits.'), 'short_code.unique' => __('Another place has this code.')]);

        $data['short_code'] = strtoupper($data['short_code']);
        $data['country']    = $company->country;
        $data['active']     = $place ? $request->boolean('active') : true;

        return $data;
    }
}
