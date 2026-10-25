<?php

namespace App\Http\Controllers\Parties;

use App\Http\Controllers\Controller;
use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Models\RateContract;
use App\Modules\Parties\Models\RateContractLine;
use App\Modules\Parties\Services\PartyService;
use App\Modules\Settings\Models\MasterItem;
use App\Modules\Settings\Models\Place;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Rate contracts on a party page: a draft is edited freely; approving it
 * makes it count for new trips; changing an approved contract means a new
 * version from a date (old trips keep their rate).
 */
class RateContractController extends Controller
{
    public function __construct(private CompanyContext $context, private PartyService $parties, private AuditRecorder $audit) {}

    public function create(Party $party): View
    {
        $contract = new RateContract(['name' => __('Rates :year', ['year' => now()->year]), 'valid_from' => now()->toDateString()]);

        return view('parties.rate-contract', $this->formData($party, $contract));
    }

    public function store(Request $request, Party $party): RedirectResponse
    {
        $contract = DB::transaction(function () use ($request, $party) {
            [$fields, $lines] = $this->validated($request);
            $contract = RateContract::create($fields + ['party_id' => $party->id, 'created_by' => $request->user()->id, 'status' => 'draft', 'version' => 1]);
            $this->replaceLines($contract, $lines);

            return $contract;
        });

        return redirect()->route('parties.rates.edit', [$party, $contract])->with('status', __('Draft saved. Approve it when the rates are agreed.'));
    }

    public function edit(Party $party, RateContract $contract): View
    {
        $this->own($party, $contract);

        return view('parties.rate-contract', $this->formData($party, $contract->load('lines')));
    }

    public function update(Request $request, Party $party, RateContract $contract): RedirectResponse
    {
        $this->own($party, $contract);
        if (! $contract->isEditable()) {
            return back()->with('error', __('An approved contract is changed by making a new version.'));
        }

        DB::transaction(function () use ($request, $contract) {
            [$fields, $lines] = $this->validated($request);
            $contract->update($fields);
            $this->replaceLines($contract, $lines);
        });

        return back()->with('status', __('Draft saved.'));
    }

    public function approve(Party $party, RateContract $contract): RedirectResponse
    {
        $this->own($party, $contract);
        $this->parties->approve($contract);

        return redirect()->route('parties.show', [$party, 'tab' => 'rates'])->with('status', __('Rates approved. New trips for :party use them.', ['party' => $party->name]));
    }

    public function newVersion(Request $request, Party $party, RateContract $contract): RedirectResponse
    {
        $this->own($party, $contract);
        $data = $request->validate(['valid_from' => ['required', 'date_format:Y-m-d', 'after:' . $contract->valid_from->toDateString()]],
            ['valid_from.after' => __('The new version must start after :date.', ['date' => $contract->valid_from->format('d M Y')])]);

        $next = $this->parties->newVersion($contract->load('lines'), $data['valid_from']);

        return redirect()->route('parties.rates.edit', [$party, $next])->with('status', __('Version :v made as a draft. Change the rates, then approve.', ['v' => $next->version]));
    }

    public function cancel(Party $party, RateContract $contract): RedirectResponse
    {
        $this->own($party, $contract);
        $contract->forceFill(['status' => 'cancelled'])->save();

        return redirect()->route('parties.show', [$party, 'tab' => 'rates'])->with('status', __('Contract cancelled.'));
    }

    private function own(Party $party, RateContract $contract): void
    {
        abort_unless($contract->party_id === $party->id, 404);
    }

    private function formData(Party $party, RateContract $contract): array
    {
        return [
            'party'      => $party,
            'contract'   => $contract,
            'places'     => Place::active()->orderBy('name')->get(['id', 'name', 'short_code']),
            'materials'  => MasterItem::ofList('material')->where('active', true)->get(['code', 'name']),
            'truckTypes' => MasterItem::ofList('truck_type')->where('active', true)->get(['code', 'name']),
        ];
    }

    /** @return array{0: array<string, mixed>, 1: array<int, array<string, mixed>>} */
    private function validated(Request $request): array
    {
        $companyId = $this->context->id();
        $place     = Rule::exists('places', 'id')->where('company_id', $companyId);
        $material  = Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'material');
        $truckType = Rule::exists('master_items', 'code')->where('company_id', $companyId)->where('list', 'truck_type');

        $data = $request->validate([
            'name'              => ['required', 'string', 'min:2', 'max:120'],
            'valid_from'        => ['required', 'date_format:Y-m-d'],
            'valid_to'          => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'diesel_base_price' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'escalation_percent_per_rupee' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_terms'     => ['nullable', 'string', 'max:1000'],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'lines'             => ['nullable', 'array', 'max:500'],
            'lines.*.from_place_id' => ['nullable', 'integer', $place],
            'lines.*.to_place_id'   => ['nullable', 'integer', $place],
            'lines.*.material'      => ['nullable', 'string', $material],
            'lines.*.truck_type'    => ['nullable', 'string', $truckType],
            'lines.*.basis'         => ['nullable', Rule::in(array_keys(RateContractLine::BASES))],
            'lines.*.rate'          => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.min_weight_mt' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'lines.*.detention_free_hours'   => ['nullable', 'integer', 'min:0', 'max:720'],
            'lines.*.detention_rate_per_day' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.loading_charge'         => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.unloading_charge'       => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'lines.*.extra_drop_charge'      => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $lines = [];
        $seen  = [];
        foreach ($data['lines'] ?? [] as $i => $line) {
            // A row left completely empty (the spare row on the form) is ignored.
            if (! filled($line['from_place_id'] ?? null) && ! filled($line['to_place_id'] ?? null) && ! filled($line['rate'] ?? null)) {
                continue;
            }
            foreach (['from_place_id', 'to_place_id', 'rate'] as $required) {
                if (! filled($line[$required] ?? null)) {
                    throw ValidationException::withMessages(["lines.{$i}.{$required}" => __('Row :n: give from, to and the rate.', ['n' => $i + 1])]);
                }
            }
            $key = implode('|', [$line['from_place_id'], $line['to_place_id'], $line['material'] ?? '', $line['truck_type'] ?? '']);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["lines.{$i}.from_place_id" => __('Row :n repeats row :m (same route, material and truck type).', ['n' => $i + 1, 'm' => $seen[$key] + 1])]);
            }
            $seen[$key] = $i;
            $lines[] = array_merge(array_map(fn ($v) => $v === '' ? null : $v, $line), ['basis' => $line['basis'] ?? 'trip']);
        }

        return [collect($data)->except('lines')->all(), $lines];
    }

    private function replaceLines(RateContract $contract, array $lines): void
    {
        $contract->lines()->delete();
        foreach ($lines as $line) {
            $contract->lines()->create(collect($line)->only([
                'from_place_id', 'to_place_id', 'material', 'truck_type', 'basis', 'rate', 'min_weight_mt',
                'detention_free_hours', 'detention_rate_per_day', 'loading_charge', 'unloading_charge', 'extra_drop_charge',
            ])->all());
        }
        $this->audit->record('rate_contract.lines_saved', "Saved " . count($lines) . " route rates in {$contract->label}", 'rate_contract', (string) $contract->id, $contract->label);
    }
}
