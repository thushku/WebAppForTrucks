<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Models\CompanyUser;
use App\Modules\Fleet\Models\Complaint;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Services\FleetService;
use App\Modules\Parties\Models\Party;
use App\Modules\Settings\Models\MasterItem;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Complaints: the board across all trucks (open → in progress → waiting
 * for parts → solved), raising one from a truck page, and working on one.
 */
class ComplaintController extends Controller
{
    public function __construct(private CompanyContext $context, private FleetService $fleet) {}

    public function index(Request $request): View
    {
        $show = $request->query('show') === 'all' ? 'all' : 'open';

        $complaints = Complaint::with(['truck', 'raisedBy', 'assignee'])
            ->when($show === 'open', fn ($q) => $q->whereNotIn('status', ['closed']))
            ->orderByRaw("field(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderByDesc('id')
            ->limit(400)->get();

        return view('complaints.index', [
            'columns'    => $complaints->groupBy('status'),
            'show'       => $show,
            'categories' => MasterItem::ofList('complaint_category')->pluck('name', 'code'),
        ]);
    }

    public function store(Request $request, Truck $truck): RedirectResponse
    {
        $companyId = $this->context->id();
        $data = $request->validate([
            'title'       => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'category'    => ['nullable', Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'complaint_category')],
            'priority'    => ['required', Rule::in(array_keys(Complaint::PRIORITIES))],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ]);

        $complaint = $this->fleet->raiseComplaint($truck, $data);

        return redirect()->route('complaints.show', $complaint)->with('status', __('Complaint :no raised.', ['no' => $complaint->number]));
    }

    public function show(Complaint $complaint): View
    {
        $complaint->load(['truck', 'raisedBy', 'assignee', 'vendor', 'updates.account']);

        return view('complaints.show', [
            'complaint'  => $complaint,
            'categories' => MasterItem::ofList('complaint_category')->pluck('name', 'code'),
            'staff'      => CompanyUser::with('account')->where('company_id', $this->context->id())->where('status', 'active')->get()->sortBy('label')->pluck('label', 'account_id'),
            'vendors'    => Party::active()->withRole('vendor')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        $companyId = $this->context->id();
        $data = $request->validate([
            'status'          => ['nullable', Rule::in(array_keys(Complaint::STATUSES))],
            'note'            => ['nullable', 'string', 'max:3000'],
            'assigned_to'     => ['nullable', 'integer', Rule::exists('company_users', 'account_id')->where('company_id', $companyId)->where('status', 'active')],
            'vendor_party_id' => ['nullable', 'integer', Rule::exists('parties', 'id')->where('company_id', $companyId)],
        ]);

        $this->fleet->updateComplaint(
            $complaint,
            $data['status'] ?? null,
            $data['note'] ?? null,
            isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            isset($data['vendor_party_id']) ? (int) $data['vendor_party_id'] : null,
        );

        return back()->with('status', __('Complaint updated.'));
    }
}
