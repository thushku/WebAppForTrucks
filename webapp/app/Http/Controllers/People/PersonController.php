<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Services\CrewCash;
use App\Modules\Fleet\Models\Truck;
use App\Modules\People\Models\ActivationCode;
use App\Modules\People\Models\Person;
use App\Modules\People\Services\ActivationService;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Auth\Identifier;
use App\Modules\Settings\Models\Branch;
use App\Platform\Authorization\Models\RoleAssignment;
use App\Platform\Authorization\PermissionResolver;
use App\Platform\Tenancy\CompanyContext;
use App\Support\QrCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * People: drivers, helpers and staff — the list with the licence chip, the
 * profile, and the crew-app activation code.
 *
 * ID numbers and bank details are entered and seen only with
 * `people.id_documents`; everyone else sees the last four characters.
 */
class PersonController extends Controller
{
    public function __construct(private CompanyContext $context, private ActivationService $activation, private AuditRecorder $audit) {}

    public function index(Request $request): View
    {
        $type   = (string) $request->query('type', '');
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'working');

        $people = Person::query()->inBranch()
            ->with('defaultTruck')
            ->when(isset(Person::TYPES[$type]), fn ($q) => $q->where('type', $type))
            ->when($status === 'working', fn ($q) => $q->where('status', '!=', 'left'))
            ->when($status === 'left', fn ($q) => $q->where('status', 'left'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orWhere('mobile', 'like', '%' . preg_replace('/\D/', '', $search) . '%')->orWhere('licence_no', $search)->orWhere('employee_code', $search)))
            ->orderBy('name')
            ->paginate(50)->withQueryString();

        return view('people.index', [
            'people' => $people, 'type' => $type, 'search' => $search, 'status' => $status,
            // Each person's papers as one chip (M27, 13-parity #19), for those who may see papers.
            'papers' => $request->user()->can('document.view') ? app(\App\Modules\Documents\Services\Compliance::class)->healthMany($people->getCollection()) : [],
            'counts' => Person::inBranch()->where('status', '!=', 'left')->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type'),
            // Who is on approved leave today, and how many requests wait (M40).
            'onLeave' => app(\App\Modules\People\Services\Leave::class)->onLeave($people->getCollection()->pluck('id'), $this->today(), $this->today()),
            'leaveWaiting' => $request->user()->can('leave.approve') ? \App\Modules\People\Models\LeaveRequest::where('status', 'waiting')->whereIn('person_id', Person::inBranch()->select('id'))->count() : 0,
        ]);
    }

    public function create(Request $request): View
    {
        $type = (string) $request->query('type', 'driver');

        return view('people.form', $this->formData(new Person(['type' => isset(Person::TYPES[$type]) ? $type : 'driver', 'nationality' => $this->context->company()->country])));
    }

    public function store(Request $request): RedirectResponse
    {
        $person = Person::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('people.show', $person)->with('status', __(':name added.', ['name' => $person->name]));
    }

    public function show(Person $person, ?string $revealed = null): View
    {
        $person->load(['defaultTruck', 'branch', 'member.account']);

        return view('people.show', [
            'person'  => $person,
            'pending' => ActivationCode::where('person_id', $person->id)->whereNull('used_at')->where('expires_at', '>', now())->latest()->first(),
            'used'    => ActivationCode::where('person_id', $person->id)->whereNotNull('used_at')->where('used_device', '!=', 'replaced')->latest('used_at')->first(),
            'code'    => session('activation_code'),
            'qr'      => session('activation_code') ? QrCode::encode('fleet-activate:' . $person->mobile . ':' . session('activation_code'))->svg(2, '#0F1A2B', __('Activation code')) : null,
            'revealed' => $revealed,
            // Pay history (M16), for those who may see salaries.
            'payslips' => auth()->user()->can('payroll.view') ? \App\Modules\Payroll\Models\Payslip::with('run')->where('person_id', $person->id)->where('status', '!=', 'cancelled')->orderByDesc('id')->limit(12)->get() : collect(),
            // The khata in short and the quick cash form, and the papers as one chip (M27).
            'khata' => $this->khata($person),
            'papers' => auth()->user()->can('document.view') ? app(\App\Modules\Documents\Services\Compliance::class)->healthMany([$person])[$person->id] : null,
            // Leave this year by type and the latest requests (M40).
            'leave' => app(\App\Modules\People\Services\Leave::class)->balances($person),
            'leaveRequests' => \App\Modules\People\Models\LeaveRequest::with('decider:id,name')->where('person_id', $person->id)->orderByDesc('from_on')->orderByDesc('id')->limit(8)->get(),
        ]);
    }

    private function today(): \Carbon\CarbonImmutable
    {
        return \Carbon\CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay();
    }

    /** The person's buckets for those who see ledgers or give cash, and the form's choices for those who give cash. */
    private function khata(Person $person): ?array
    {
        $user = auth()->user();
        $gives = $user->can('trip.settle');
        if (! $gives && ! $user->canAny(['ledger.view', 'accounts.view'])) {
            return null;
        }
        $ledger = \App\Modules\Accounts\Models\LedgerAccount::where('system_key', 'person:' . $person->id)->first();

        return [
            'ledger' => $ledger,
            'buckets' => $ledger ? app(\App\Modules\Accounts\Services\Statements::class)->crewBuckets($ledger) : ['trip_cash' => 0, 'earnings' => 0, 'loan' => 0, 'net' => 0],
            'gives' => $gives && $person->status !== 'left',
            'trips' => $gives ? app(CrewCash::class)->trips($person) : collect(),
            'categories' => $gives ? \App\Modules\Settings\Models\MasterItem::ofList('expense_category')->where('active', true)->pluck('name', 'code') : collect(),
        ];
    }

    public function edit(Person $person): View
    {
        return view('people.form', $this->formData($person));
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $data    = $this->validated($request, $person);
        $relogin = $person->company_user_id && ($data['mobile'] ?? null) !== $person->mobile;

        DB::transaction(function () use ($person, $data, $relogin) {
            // The crew app signs in with the mobile number. A new number ends
            // the old login in this company; the office makes a new code.
            if ($relogin) {
                $member = \App\Models\CompanyUser::findOrFail($person->company_user_id);
                RoleAssignment::where('company_user_id', $member->id)->delete();
                $member->forceFill(['status' => 'left'])->save();
                app(PermissionResolver::class)->forget($member);
                $data['company_user_id'] = null;
                $this->audit->record('person.app_login_ended', "Mobile number of {$person->name} changed; the old crew-app login was ended", 'person', (string) $person->id, $person->name, severity: 'notable');
            }
            $person->update($data);
        });

        return redirect()->route('people.show', $person)->with('status', $relogin
            ? __('Saved. The crew app login for the old number has ended — make a new activation code.')
            : __('Saved.'));
    }

    /** Quick cash given to or spent by the person (M27): one voucher in their khata. */
    public function cash(Request $request, Person $person, CrewCash $cash): RedirectResponse
    {
        $voucher = $cash->record($person, $request->validate($cash->rules(), $cash->messages()), $request->user());

        return redirect()->to(route('people.show', $person) . '#cash')->with('status', __('Recorded in :name’s khata as :number.', ['name' => $person->name, 'number' => $voucher->number]));
    }

    /** A new activation code, shown once on the next page (never stored in clear). */
    public function activate(Person $person): RedirectResponse
    {
        $code = $this->activation->issue($person);

        return redirect()->route('people.show', $person)->with('activation_code', $code);
    }

    /**
     * Show the full ID number once, on this response only (never put in the
     * session), and log who looked.
     */
    public function revealId(Person $person): View
    {
        $this->audit->record('person.id_viewed', "Viewed the ID number of {$person->name}", 'person', (string) $person->id, $person->name, severity: 'sensitive');

        return $this->show($person, $person->id_number);
    }

    private function formData(Person $person): array
    {
        return [
            'person'   => $person,
            'licenceDoc' => $person->exists ? \App\Modules\Documents\Models\Document::where('owner_type', 'person')->where('owner_id', $person->id)->where('document_type', 'licence')->first() : null,
            'country'  => $this->context->company()->country,
            'trucks'   => Truck::inService()->orderBy('reg_no')->pluck('reg_no', 'id'),
            'branches' => Branch::where('active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validated(Request $request, ?Person $person = null): array
    {
        $companyId = $this->context->id();
        $country   = $this->context->company()->country;
        $sensitive = $request->user()->can('people.id_documents');

        $data = $request->validate([
            'name'           => ['required', 'string', 'min:2', 'max:150'],
            'type'           => ['required', Rule::in(array_keys(Person::TYPES))],
            'mobile'         => ['nullable', 'string', 'max:20'],
            'alt_mobile'     => ['nullable', 'string', 'max:20'],
            'employee_code'  => ['nullable', 'string', 'max:20', Rule::unique('people', 'employee_code')->where('company_id', $companyId)->ignore($person?->id)],
            'date_of_birth'  => ['nullable', 'date_format:Y-m-d', 'before:-16 years'],
            'joining_date'   => ['nullable', 'date_format:Y-m-d'],
            'leaving_date'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joining_date'],
            'status'         => ['nullable', Rule::in(array_keys(Person::STATUSES))],
            'address'        => ['nullable', 'string', 'max:500'],
            'emergency_name' => ['nullable', 'string', 'max:120'],
            'emergency_mobile' => ['nullable', 'string', 'max:20'],
            'blood_group'    => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'licence_no'     => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\s\/]+$/'],
            'licence_class'  => ['nullable', 'string', 'max:40'],
            'licence_expiry' => ['nullable', 'date_format:Y-m-d'],
            'hazardous_endorsement' => ['nullable', 'boolean'],
            'badge_no'       => ['nullable', 'string', 'max:30'],
            'nationality'    => ['nullable', 'string', 'size:2'],
            'pay_basis'      => ['required', Rule::in(array_keys(Person::PAY_BASES))],
            'monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'per_trip_rate'  => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'per_km_rate'    => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'branch_id'      => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'default_truck_id' => ['nullable', 'integer', Rule::exists('trucks', 'id')->where('company_id', $companyId)],
            'notes'          => ['nullable', 'string', 'max:2000'],
            // Sensitive: only with people.id_documents (ignored otherwise).
            'id_type'        => ['nullable', Rule::in(array_keys(Person::ID_TYPES))],
            'id_number'      => ['nullable', 'string', 'max:30'],
            'bank_account_holder' => ['nullable', 'string', 'max:150'],
            'bank_name'      => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'regex:/^[0-9]{6,18}$/'],
            'bank_ifsc'      => ['nullable', 'string', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'],
            'bank_iban'      => ['nullable', 'string', 'regex:/^[A-Za-z]{2}[0-9]{2}[A-Za-z0-9]{11,30}$/'],
            'upi_id'         => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9.\-_]{2,}@[A-Za-z]{2,}$/'],
        ], [
            'date_of_birth.before' => __('A person must be at least 16.'),
            'bank_account_number.regex' => __('Use 6 to 18 digits, without spaces.'),
            'bank_ifsc.regex' => __('An IFSC has 11 characters, like HDFC0001234.'),
        ]);

        foreach (['mobile', 'alt_mobile', 'emergency_mobile'] as $field) {
            if (filled($data[$field] ?? null)) {
                $data[$field] = Identifier::mobile($data[$field], $country) ?? throw ValidationException::withMessages([$field => __('Enter a valid mobile number.')]);
            }
        }
        if (! empty($data['mobile']) && Person::where('mobile', $data['mobile'])->where('id', '!=', $person?->id ?? 0)->exists()) {
            throw ValidationException::withMessages(['mobile' => __('Another person has this mobile number.')]);
        }
        // With a licence document on file, its number and expiry come from there (renew it in Documents).
        if ($person && \App\Modules\Documents\Models\Document::where('owner_type', 'person')->where('owner_id', $person->id)->where('document_type', 'licence')->exists()) {
            unset($data['licence_no'], $data['licence_expiry']);
        }

        $data['hazardous_endorsement'] = $request->boolean('hazardous_endorsement');
        if (array_key_exists('licence_no', $data)) {
            $data['licence_no'] = isset($data['licence_no']) ? strtoupper(preg_replace('/\s+/', '', $data['licence_no'])) : null;
        }
        $data['status'] ??= $person->status ?? 'active';
        if ($data['status'] === 'left') {
            $data['leaving_date'] ??= now()->toDateString();
        }

        $secret = ['id_type', 'id_number', 'bank_account_holder', 'bank_name', 'bank_account_number', 'bank_ifsc', 'bank_iban', 'upi_id'];
        if (! $sensitive) {
            // Without the permission these fields are neither shown nor changed.
            foreach ($secret as $field) {
                unset($data[$field]);
            }
        } else {
            if (filled($data['id_number'] ?? null)) {
                $id = strtoupper(preg_replace('/[\s\-]/', '', $data['id_number']));
                if (($data['id_type'] ?? null) === 'aadhaar' && ! preg_match('/^[2-9][0-9]{11}$/', $id)) {
                    throw ValidationException::withMessages(['id_number' => __('An Aadhaar number has 12 digits.')]);
                }
                if (($data['id_type'] ?? null) === 'emirates_id' && ! preg_match('/^784[0-9]{12}$/', $id)) {
                    throw ValidationException::withMessages(['id_number' => __('An Emirates ID has 15 digits starting with 784.')]);
                }
                $data['id_number'] = $id;
                $data['id_last4']  = substr($id, -4);
            } elseif ($request->has('id_number') && $person) {
                // Left empty on an existing person: keep what is stored.
                unset($data['id_number']);
            }
            if (filled($data['bank_account_number'] ?? null)) {
                $data['bank_last4'] = substr($data['bank_account_number'], -4);
            } elseif ($person) {
                unset($data['bank_account_number']);
            }
            foreach (['bank_iban', 'upi_id'] as $field) {
                if (! filled($data[$field] ?? null) && $person) {
                    unset($data[$field]);
                }
            }
            if (isset($data['bank_ifsc'])) {
                $data['bank_ifsc'] = strtoupper($data['bank_ifsc']);
            }
        }

        return $data;
    }
}
