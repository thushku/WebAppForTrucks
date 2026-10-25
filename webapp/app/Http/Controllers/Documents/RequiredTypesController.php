<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Modules\Documents\Models\DocumentRequiredType;
use App\Modules\Documents\Services\Compliance;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\MasterItem;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Which papers every truck (or every truck of a type), every person (or
 * every driver…) and the company must have. A required paper with no
 * document shows as "Missing"; a missing dispatch-blocking one stops
 * dispatch (13 §D #17).
 */
class RequiredTypesController extends Controller
{
    public function __construct(private CompanyContext $context, private Compliance $compliance) {}

    public function index(): View
    {
        return view('documents.required', [
            'rules'       => DocumentRequiredType::orderBy('applies_to')->orderBy('subtype')->orderBy('document_type')->get(),
            'types'       => $this->compliance->types(),
            'truckTypes'  => MasterItem::ofList('truck_type')->pluck('name', 'code'),
            'personTypes' => Person::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->context->id();
        $data = $request->validate([
            'applies_to'    => ['required', Rule::in(['truck', 'person', 'company'])],
            'subtype'       => ['nullable', 'string', 'max:40'],
            'document_type' => ['required', Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'document_type')],
        ]);

        $owner = $this->compliance->types()[$data['document_type']]['owner'] ?? null;
        if ($owner !== $data['applies_to']) {
            return back()->withErrors(['document_type' => __('That paper is for a :owner, not a :applies.', ['owner' => __($owner ?? '?'), 'applies' => __($data['applies_to'])])]);
        }
        $subtypes = $data['applies_to'] === 'truck' ? MasterItem::ofList('truck_type')->pluck('code')->all() : array_keys(Person::TYPES);
        if (! empty($data['subtype']) && ($data['applies_to'] === 'company' || ! in_array($data['subtype'], $subtypes, true))) {
            return back()->withErrors(['subtype' => __('Choose from the list.')]);
        }

        DocumentRequiredType::firstOrCreate(['applies_to' => $data['applies_to'], 'subtype' => $data['subtype'] ?: null, 'document_type' => $data['document_type']]);

        return back()->with('status', __('Rule added.'));
    }

    public function destroy(DocumentRequiredType $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', __('Rule removed.'));
    }
}
