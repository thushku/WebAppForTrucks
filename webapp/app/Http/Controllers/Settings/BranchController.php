<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\Branch;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Regions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Branches (offices). A role can be given for one branch only, and a branch
 * code can appear in number formats ({BR}). Branches are switched off, never
 * deleted: trips and bills keep pointing at them.
 */
class BranchController extends Controller
{
    public function __construct(private CompanyContext $context) {}

    public function index(): View
    {
        $company = $this->context->company();

        return view('settings.branches', [
            'branches' => Branch::orderByDesc('is_head_office')->orderBy('name')->get(),
            'regions'  => Regions::for($company->country),
            'country'  => $company->country,
            'editing'  => null,
        ]);
    }

    public function edit(Branch $branch): View
    {
        $company = $this->context->company();

        return view('settings.branches', [
            'branches' => Branch::orderByDesc('is_head_office')->orderBy('name')->get(),
            'regions'  => Regions::for($company->country),
            'country'  => $company->country,
            'editing'  => $branch,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Branch::create($this->validated($request));

        return redirect()->route('settings.branches')->with('status', __('Branch added.'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $this->validated($request, $branch);

        if ($branch->is_head_office && ! $data['active']) {
            return back()->withErrors(['active' => __('The head office cannot be switched off. Make another branch the head office first.')]);
        }

        DB::transaction(function () use ($branch, $data) {
            if ($data['is_head_office'] && ! $branch->is_head_office) {
                Branch::where('is_head_office', true)->update(['is_head_office' => false]);
            }
            $branch->update($data);
        });

        return redirect()->route('settings.branches')->with('status', __('Branch saved.'));
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $company = $this->context->company();

        $data = $request->validate([
            'name'          => ['required', 'string', 'min:2', 'max:120'],
            'code'          => ['required', 'string', 'regex:/^[A-Za-z0-9]{2,6}$/',
                Rule::unique('branches', 'code')->where('company_id', $company->id)->ignore($branch?->id)],
            'address_line1' => ['nullable', 'string', 'max:200'],
            'address_line2' => ['nullable', 'string', 'max:200'],
            'city'          => ['nullable', 'string', 'max:100'],
            'state_code'    => ['nullable', Rule::in(array_keys(Regions::for($company->country)))],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'phone'         => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'is_head_office' => ['nullable', 'boolean'],
            'active'        => ['nullable', 'boolean'],
        ], ['code.regex' => __('2 to 6 letters or digits.'), 'code.unique' => __('Another branch has this code.')]);

        $data['code']           = strtoupper($data['code']);
        $data['is_head_office'] = $request->boolean('is_head_office');
        $data['active']         = $branch ? $request->boolean('active') : true;

        return $data;
    }
}
