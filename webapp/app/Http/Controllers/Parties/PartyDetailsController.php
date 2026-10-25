<?php

namespace App\Http\Controllers\Parties;

use App\Http\Controllers\Controller;
use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Models\PartyBankAccount;
use App\Modules\Parties\Models\PartyContact;
use App\Modules\Parties\Models\PartyTaxId;
use App\Modules\Parties\Models\SupplierVehicle;
use App\Modules\Parties\Services\PartyService;
use App\Modules\Parties\Services\TaxIds;
use App\Platform\Auth\Identifier;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The lists on a party page: tax registrations, contacts, bank accounts and
 * (for suppliers) their trucks. Each row belongs to the party in the URL;
 * a row of another party is a 404.
 */
class PartyDetailsController extends Controller
{
    public function __construct(private CompanyContext $context, private PartyService $parties) {}

    // ---- Tax registrations -------------------------------------------------------

    public function storeTaxId(Request $request, Party $party): RedirectResponse
    {
        $data = $request->validate([
            'tax_id'     => ['required', 'string', 'max:20'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'address'    => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $country = $party->country;
        if (! TaxIds::isValid($country, $data['tax_id'])) {
            throw ValidationException::withMessages(['tax_id' => __('This :label is not valid. Check it for a typing mistake.', ['label' => TaxIds::label($country)])]);
        }

        $this->parties->addTaxId($party, $data + ['country' => $country]);

        return back()->with('status', __(':label added.', ['label' => TaxIds::label($country)]));
    }

    public function defaultTaxId(Party $party, PartyTaxId $taxId): RedirectResponse
    {
        $this->own($party, $taxId);
        $this->parties->makeDefault($taxId);

        return back()->with('status', __(':id is now the main registration.', ['id' => $taxId->tax_id]));
    }

    public function toggleTaxId(Party $party, PartyTaxId $taxId): RedirectResponse
    {
        $this->own($party, $taxId);
        if ($taxId->is_default && $taxId->active) {
            return back()->with('error', __('Make another registration the main one first.'));
        }
        $taxId->forceFill(['active' => ! $taxId->active])->save();

        return back()->with('status', $taxId->active ? __('Registration in use again.') : __('Registration no longer used.'));
    }

    // ---- Contacts ------------------------------------------------------------------

    public function storeContact(Request $request, Party $party): RedirectResponse
    {
        PartyContact::create($this->contactData($request, $party) + ['party_id' => $party->id]);

        return back()->with('status', __('Contact added.'));
    }

    public function updateContact(Request $request, Party $party, PartyContact $contact): RedirectResponse
    {
        $this->own($party, $contact);
        $contact->update($this->contactData($request, $party));

        return back()->with('status', __('Contact saved.'));
    }

    public function destroyContact(Party $party, PartyContact $contact): RedirectResponse
    {
        $this->own($party, $contact);
        $contact->delete();

        return back()->with('status', __('Contact removed.'));
    }

    // ---- Bank accounts (settings.bank) --------------------------------------------------

    public function storeBank(Request $request, Party $party): RedirectResponse
    {
        $india = $party->country === 'IN';
        $data  = $request->validate([
            'account_holder_name' => ['required', 'string', 'min:2', 'max:150'],
            'bank_name'      => ['nullable', 'string', 'max:120'],
            'branch'         => ['nullable', 'string', 'max:120'],
            'account_number' => [$india ? 'required_without:upi_id' : 'nullable', 'nullable', 'string', 'regex:/^[0-9]{6,18}$/'],
            'ifsc'           => ['nullable', 'required_with:account_number', 'string', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'iban'           => [$india ? 'nullable' : 'required_without:upi_id', 'nullable', 'string', 'regex:/^[A-Za-z]{2}[0-9]{2}[A-Za-z0-9]{11,30}$/'],
            'swift'          => ['nullable', 'string', 'regex:/^[A-Za-z]{6}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/'],
            'upi_id'         => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9.\-_]{2,}@[A-Za-z]{2,}$/'],
        ], [
            'account_number.regex' => __('Use 6 to 18 digits, without spaces.'),
            'ifsc.regex'  => __('An IFSC has 11 characters, like HDFC0001234.'),
            'iban.regex'  => __('This IBAN does not look right.'),
            'upi_id.regex' => __('A UPI id looks like name@bank.'),
        ]);
        if ($india && ! filled($data['ifsc'] ?? null) && filled($data['account_number'] ?? null)) {
            throw ValidationException::withMessages(['ifsc' => __('Give the IFSC with the account number.')]);
        }

        DB::transaction(function () use ($party, $data) {
            $first = ! PartyBankAccount::where('party_id', $party->id)->exists();
            PartyBankAccount::create([
                'party_id'            => $party->id,
                'account_holder_name' => trim($data['account_holder_name']),
                'bank_name'           => $data['bank_name'] ?? null,
                'branch'              => $data['branch'] ?? null,
                'account_number'      => $data['account_number'] ?? null,
                'account_last4'       => isset($data['account_number']) ? substr($data['account_number'], -4) : (isset($data['iban']) ? substr($data['iban'], -4) : null),
                'ifsc'                => isset($data['ifsc']) ? strtoupper($data['ifsc']) : null,
                'iban'                => isset($data['iban']) ? strtoupper(str_replace(' ', '', $data['iban'])) : null,
                'swift'               => isset($data['swift']) ? strtoupper($data['swift']) : null,
                'upi_id'              => $data['upi_id'] ?? null,
                'is_default'          => $first,
            ]);
        });

        return back()->with('status', __('Bank account added.'));
    }

    public function destroyBank(Party $party, PartyBankAccount $bank): RedirectResponse
    {
        $this->own($party, $bank);
        DB::transaction(function () use ($party, $bank) {
            $wasDefault = $bank->is_default;
            $bank->delete();
            if ($wasDefault) {
                PartyBankAccount::where('party_id', $party->id)->orderBy('id')->first()?->forceFill(['is_default' => true])->save();
            }
        });

        return back()->with('status', __('Bank account removed.'));
    }

    // ---- Supplier vehicles ------------------------------------------------------------

    public function storeVehicle(Request $request, Party $party): RedirectResponse
    {
        SupplierVehicle::create($this->vehicleData($request) + ['party_id' => $party->id]);

        return redirect()->route('parties.show', [$party, 'tab' => 'vehicles'])->with('status', __('Truck added.'));
    }

    public function updateVehicle(Request $request, Party $party, SupplierVehicle $vehicle): RedirectResponse
    {
        $this->own($party, $vehicle);
        $vehicle->update($this->vehicleData($request, $vehicle) + ['active' => $request->boolean('active')]);

        return redirect()->route('parties.show', [$party, 'tab' => 'vehicles'])->with('status', __('Truck saved.'));
    }

    // ---- Helpers ------------------------------------------------------------------------

    private function own(Party $party, $row): void
    {
        abort_unless((int) $row->party_id === $party->id, 404);
    }

    private function contactData(Request $request, Party $party): array
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'min:2', 'max:120'],
            'role'       => ['nullable', Rule::in(array_keys(PartyContact::ROLES))],
            'mobile'     => ['nullable', 'string', 'max:20'],
            'alt_mobile' => ['nullable', 'string', 'max:20'],
            'email'      => ['nullable', 'email:filter', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        foreach (['mobile', 'alt_mobile'] as $field) {
            if (filled($data[$field] ?? null)) {
                $data[$field] = Identifier::mobile($data[$field], $party->country)
                    ?? throw ValidationException::withMessages([$field => __('Enter a valid mobile number.')]);
            }
        }
        $data['is_primary'] = $request->boolean('is_primary');
        if ($data['is_primary']) {
            PartyContact::where('party_id', $party->id)->update(['is_primary' => false]);
        }

        return $data;
    }

    private function vehicleData(Request $request, ?SupplierVehicle $vehicle = null): array
    {
        $data = $request->validate([
            'reg_no'         => ['required', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9\s\-]+$/'],
            'truck_type'     => ['nullable', 'string', 'max:40'],
            'body_type'      => ['nullable', 'string', 'max:40'],
            'capacity_mt'    => ['nullable', 'numeric', 'min:0', 'max:200'],
            'rc_owner_name'  => ['nullable', 'string', 'max:150'],
            'driver_name'    => ['nullable', 'string', 'max:120'],
            'driver_mobile'  => ['nullable', 'string', 'max:20'],
            'driver_licence' => ['nullable', 'string', 'max:30'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $data['reg_no'] = strtoupper(trim(preg_replace('/\s+/', ' ', $data['reg_no'])));
        $key   = SupplierVehicle::key($data['reg_no']);
        $other = SupplierVehicle::with('party')->where('reg_key', $key)->where('id', '!=', $vehicle?->id ?? 0)->first();
        if ($other) {
            throw ValidationException::withMessages(['reg_no' => __(':reg is already listed under :party.', ['reg' => $other->reg_no, 'party' => $other->party?->name])]);
        }
        if (filled($data['driver_mobile'] ?? null)) {
            $data['driver_mobile'] = Identifier::mobile($data['driver_mobile'], $this->context->company()->country)
                ?? throw ValidationException::withMessages(['driver_mobile' => __('Enter a valid mobile number.')]);
        }

        return $data;
    }
}
