<?php

namespace App\Http\Controllers\Parties;

use App\Http\Controllers\Controller;
use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Services\PartyService;
use App\Modules\Parties\Services\TaxIds;
use App\Platform\Auth\Identifier;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Regions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Parties: the list (by role), the add/edit form and the party page.
 * Parties are switched off, never deleted — trips, bills and the books
 * point at them.
 */
class PartyController extends Controller
{
    public function __construct(private CompanyContext $context, private PartyService $parties) {}

    public function index(Request $request): View
    {
        $role   = (string) $request->query('role', '');
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'active');

        $parties = Party::query()
            ->with(['roles', 'taxIds'])
            ->when(isset(Party::ROLES[$role]), fn ($q) => $q->withRole($role))
            ->when($status === 'active', fn ($q) => $q->where('active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('active', false))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('legal_name', 'like', $like)
                    ->orWhere('short_code', $search)->orWhere('mobile', 'like', $like)
                    ->orWhereHas('taxIds', fn ($t) => $t->where('tax_id', 'like', TaxIds::normalise($search) . '%')));
            })
            ->orderBy('name')
            ->paginate(50)->withQueryString();

        $counts = \App\Modules\Parties\Models\PartyRole::query()
            ->whereHas('party', fn ($q) => $q->where('active', true))
            ->selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role');

        return view('parties.index', compact('parties', 'role', 'search', 'status', 'counts'));
    }

    public function create(Request $request): View
    {
        $party = new Party(['country' => $this->context->company()->country]);
        $role  = (string) $request->query('role', 'client');

        return view('parties.form', $this->formData($party, [isset(Party::ROLES[$role]) ? $role : 'client']));
    }

    public function store(Request $request): RedirectResponse
    {
        [$fields, $roles, $taxId] = $this->validated($request);
        $fields['created_by'] = $request->user()->id;

        $party = $this->parties->save(null, $fields, $roles, $taxId);

        return redirect()->route('parties.show', $party)->with('status', __(':name added.', ['name' => $party->name]));
    }

    public function show(Request $request, Party $party): View
    {
        $party->load(['roles', 'taxIds', 'contacts', 'bankAccounts', 'vehicles', 'group',
            'rateContracts' => fn ($q) => $q->withCount('lines')]);

        $tab = in_array($request->query('tab'), ['overview', 'rates', 'vehicles', 'billing'], true) ? $request->query('tab') : 'overview';

        return view('parties.show', [
            'party'   => $party,
            'tab'     => $tab,
            'country' => $this->context->company()->country,
            'members' => Party::where('group_party_id', $party->id)->orderBy('name')->get(['id', 'name']),
            'billing' => $tab === 'billing' && $request->user()->can('invoice.view') ? $this->billing($party) : null,
        ]);
    }

    /**
     * The client's billing view (M24, 13-parity #20): what is not billed yet,
     * what is billed and unpaid, what came in this financial year; the LRs
     * to bill, the bills with Settle, the payments and the quotations.
     */
    private function billing(Party $party): array
    {
        $company = $this->context->company();
        $year = \App\Support\FiscalYear::containing(\Carbon\CarbonImmutable::now($company->timezone ?: 'UTC'), (int) ($company->fy_start_month ?: 4));
        $unbilled = \App\Modules\Trips\Models\Consignment::with('trip:id,trip_number,trip_date')->waitingToBill()->where('billing_party_id', $party->id)
            ->orderBy('lr_date')->orderBy('id')->get(['id', 'trip_id', 'lr_number', 'lr_date', 'status', 'freight', 'loading_charge', 'unloading_charge', 'other_charges', 'pod_received_at']);
        $open = \App\Modules\Invoicing\Models\Invoice::inBranch()->where('party_id', $party->id)->whereIn('status', \App\Modules\Invoicing\Models\Invoice::OPEN_STATUSES);

        return [
            'client_page' => ($url = app(\App\Modules\Parties\Services\ClientPage::class)->current($party))
                ? ['url' => $url, 'message' => app(\App\Modules\Parties\Services\ClientPage::class)->message($party, $url)] : null,
            'year' => $year,
            'unbilled' => $unbilled,
            'unbilled_total' => round((float) $unbilled->sum(fn ($c) => (float) $c->freight + (float) $c->loading_charge + (float) $c->unloading_charge + (float) $c->other_charges), 2),
            'due' => round((float) (clone $open)->where('doc_type', '!=', 'credit_note')->sum('balance_due'), 2),
            'overdue' => round((float) (clone $open)->where('doc_type', '!=', 'credit_note')->where('due_date', '<', now($company->timezone ?: 'UTC')->toDateString())->sum('balance_due'), 2),
            'received' => round((float) \App\Modules\Invoicing\Models\Receipt::where('party_id', $party->id)->where('status', '!=', 'void')
                ->whereBetween('receipt_date', [$year->start->toDateString(), $year->end->toDateString()])->sum('amount'), 2),
            'invoices' => \App\Modules\Invoicing\Models\Invoice::inBranch()->where('party_id', $party->id)->orderByDesc('issue_date')->orderByDesc('id')->limit(50)->get(),
            'receipts' => \App\Modules\Invoicing\Models\Receipt::with('allocations.invoice:id,number')->where('party_id', $party->id)->orderByDesc('receipt_date')->orderByDesc('id')->limit(30)->get(),
            'quotations' => \App\Modules\Invoicing\Models\Quotation::inBranch()->where('party_id', $party->id)->orderByDesc('quote_date')->orderByDesc('id')->limit(10)->get(),
        ];
    }

    public function edit(Party $party): View
    {
        return view('parties.form', $this->formData($party, $party->roleCodes()));
    }

    public function update(Request $request, Party $party): RedirectResponse
    {
        [$fields, $roles] = $this->validated($request, $party);
        $this->parties->save($party, $fields, $roles);

        return redirect()->route('parties.show', $party)->with('status', __('Saved.'));
    }

    /** A free short code for a name (the form offers it as you type). */
    public function suggest(Request $request): JsonResponse
    {
        $name = (string) $request->validate(['name' => ['required', 'string', 'max:150']])['name'];
        $base = Party::suggestCode($name);
        $code = $base;
        for ($i = 2; Party::where('short_code', $code)->exists() && $i < 100; $i++) {
            $code = substr($base, 0, 5) . $i;
        }

        return response()->json(['code' => $code]);
    }

    /** Search for pickers elsewhere (trip form, LR): name, code, GSTIN. */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::in(array_keys(Party::ROLES))]]);
        $q    = trim((string) ($data['q'] ?? ''));

        $rows = Party::active()->with('roles')
            ->when($data['role'] ?? null, fn ($w, $role) => $w->withRole($role))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', '%' . addcslashes($q, '%_\\') . '%')->orWhere('short_code', $q)))
            ->orderBy('name')->limit(20)->get();

        return response()->json($rows->map(fn (Party $p) => [
            'id' => $p->id, 'name' => $p->name, 'code' => $p->short_code, 'city' => $p->city, 'roles' => $p->roleCodes(),
        ]));
    }

    private function formData(Party $party, array $roles): array
    {
        $country = $party->country ?: $this->context->company()->country;

        return [
            'party'   => $party,
            'roles'   => $roles,
            'country' => $country,
            'regions' => Regions::for($country),
            'groups'  => Party::active()->where('id', '!=', $party->id ?? 0)->whereNull('group_party_id')->orderBy('name')->get(['id', 'name']),
        ];
    }

    /** @return array{0: array<string, mixed>, 1: string[], 2: ?string} */
    private function validated(Request $request, ?Party $party = null): array
    {
        $companyId = $this->context->id();
        $country   = $party->country ?? $this->context->company()->country;

        $data = $request->validate([
            'name'          => ['required', 'string', 'min:2', 'max:150'],
            'legal_name'    => ['nullable', 'string', 'max:200'],
            'short_code'    => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{2,6}$/', Rule::unique('parties', 'short_code')->where('company_id', $companyId)->ignore($party?->id)],
            'roles'         => ['required', 'array', 'min:1'],
            'roles.*'       => [Rule::in(array_keys(Party::ROLES))],
            'group_party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where('company_id', $companyId)],
            'mobile'        => ['nullable', 'string', 'max:20'],
            'email'         => ['nullable', 'email:filter', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state_code'    => ['nullable', Rule::in(array_keys(Regions::for($country)))],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'tax_id'        => [$party ? 'prohibited' : 'nullable', 'string', 'max:20'],
            'pan'           => ['nullable', 'string', 'max:12'],
            'tan'           => ['nullable', 'string', 'regex:/^[A-Za-z]{4}[0-9]{5}[A-Za-z]$/'],
            'udyam_number'  => ['nullable', 'string', 'regex:/^UDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}$/i'],
            'udyam_type'    => ['nullable', Rule::in(['micro', 'small', 'medium'])],
            'credit_limit'  => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'credit_days'   => ['nullable', 'integer', 'min:0', 'max:365'],
            'due_basis'     => ['nullable', Rule::in(['invoice', 'submission'])],
            'credit_action' => ['nullable', Rule::in(['warn', 'block', 'override'])],
            'billing_cycle' => ['nullable', Rule::in(['per_lr', 'weekly', 'fortnightly', 'monthly'])],
            'pod_required'  => ['nullable', Rule::in(['none', 'original', 'epod'])],
            'required_refs' => ['nullable', 'array'],
            'required_refs.*' => [Rule::in(['po', 'indent', 'shipment'])],
            'vendor_code_at_client' => ['nullable', 'string', 'max:40'],
            'portal_url'    => ['nullable', 'url:http,https', 'max:255'],
            'late_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'tds_deducted'  => ['nullable', 'boolean'],
            'tds_section'   => ['nullable', 'string', 'max:10'],
            'tds_rate'      => ['nullable', 'numeric', 'min:0', 'max:30'],
            'supplier_type' => ['nullable', Rule::in(array_keys(Party::SUPPLIER_TYPES))],
            'vendor_category' => ['nullable', Rule::in(array_keys(Party::VENDOR_CATEGORIES))],
            'payment_days'  => ['nullable', 'integer', 'min:0', 'max:365'],
            'lat'           => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng'           => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'rating'        => ['nullable', 'integer', 'min:1', 'max:5'],
            'blacklisted'   => ['nullable', 'boolean'],
            'send_reminders' => ['nullable', 'boolean'],
            'blacklist_reason' => ['nullable', 'required_if:blacklisted,1', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'opening_side'  => ['nullable', Rule::in(['receivable', 'payable'])],
            'opening_as_of' => ['nullable', 'date_format:Y-m-d', 'required_unless:opening_balance,null,0,0.00'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'active'        => ['nullable', 'boolean'],
        ], [
            'short_code.regex'   => __('2 to 6 letters or digits.'),
            'short_code.unique'  => __('Another party has this code.'),
            'tan.regex'          => __('A TAN has 4 letters, 5 digits and a letter.'),
            'udyam_number.regex' => __('Write it as UDYAM-KL-01-1234567.'),
            'blacklist_reason.required_if' => __('Say why the party is blacklisted.'),
            'opening_as_of.required_unless' => __('Give the date of the opening balance.'),
        ]);

        $mobile = null;
        if (filled($data['mobile'] ?? null)) {
            $mobile = Identifier::mobile($data['mobile'], $country);
            if (! $mobile) {
                throw \Illuminate\Validation\ValidationException::withMessages(['mobile' => __('Enter a valid mobile number.')]);
            }
        }

        $pan = filled($data['pan'] ?? null) ? TaxIds::normalise($data['pan']) : null;
        if ($pan && ! TaxIds::isPan($pan)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['pan' => __('A PAN has 5 letters, 4 digits and a letter, like AAACA1234F.')]);
        }

        $taxId = filled($data['tax_id'] ?? null) ? TaxIds::normalise($data['tax_id']) : null;
        if ($taxId && ! TaxIds::isValid($country, $taxId)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['tax_id' => __('This :label is not valid. Check it for a typing mistake.', ['label' => TaxIds::label($country)])]);
        }

        $fields = collect($data)->except(['roles', 'tax_id'])->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all();
        $fields['mobile']       = $mobile;
        $fields['pan']          = $pan;
        $fields['tan']          = isset($fields['tan']) ? strtoupper($fields['tan']) : null;
        $fields['udyam_number'] = isset($fields['udyam_number']) ? strtoupper($fields['udyam_number']) : null;
        $fields['short_code']   = isset($fields['short_code']) ? strtoupper($fields['short_code']) : null;
        $fields['country']      = $country;
        $fields['tds_deducted'] = $request->boolean('tds_deducted');
        $fields['blacklisted']  = $request->boolean('blacklisted');
        // Unticked (or not on the form): no automatic reminders for this client.
        $fields['send_reminders'] = $request->has('send_reminders_shown') ? $request->boolean('send_reminders') : true;
        $fields['blacklist_reason'] = $fields['blacklisted'] ? $fields['blacklist_reason'] : null;
        $fields['active']       = $party ? $request->boolean('active') : true;
        $fields['opening_balance'] = (float) ($fields['opening_balance'] ?? 0);
        $fields['required_refs'] = $data['required_refs'] ?? null;
        foreach (['due_basis' => 'invoice', 'credit_action' => 'warn', 'billing_cycle' => 'per_lr', 'pod_required' => 'none', 'opening_side' => 'receivable'] as $key => $default) {
            $fields[$key] ??= $default;
        }

        return [$fields, $data['roles'], $taxId];
    }
}
