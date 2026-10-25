<?php

namespace App\Modules\Parties\Services;

use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Models\PartyRole;
use App\Modules\Parties\Models\PartyTaxId;
use App\Modules\Parties\Models\RateContract;
use App\Platform\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saving parties and the things that hang off them, each in one transaction.
 * Validation of single fields happens in the controller; the rules that look
 * at several records (a GSTIN used by another party, PAN against GSTIN, the
 * one default registration) live here.
 */
class PartyService
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param array<string, mixed> $fields party columns
     * @param string[]             $roles
     */
    public function save(?Party $party, array $fields, array $roles, ?string $firstTaxId = null): Party
    {
        return DB::transaction(function () use ($party, $fields, $roles, $firstTaxId) {
            $party ??= new Party();
            $country = $fields['country'] ?? $party->country ?? 'IN';

            if ($firstTaxId !== null && $firstTaxId !== '') {
                $firstTaxId = TaxIds::normalise($firstTaxId);
                $this->assertTaxIdFree($firstTaxId, $party->id);
                if ($country === 'IN') {
                    $pan = TaxIds::gstinPan($firstTaxId);
                    if (! empty($fields['pan']) && TaxIds::normalise($fields['pan']) !== $pan) {
                        throw ValidationException::withMessages(['pan' => __('This PAN does not match the GSTIN (:pan).', ['pan' => $pan])]);
                    }
                    $fields['pan'] ??= $pan;
                    $fields['state_code'] ??= TaxIds::gstinState($firstTaxId);
                }
            }

            $party->fill($fields)->save();
            $this->syncRoles($party, $roles);

            if ($firstTaxId) {
                $this->addTaxId($party, ['tax_id' => $firstTaxId, 'country' => $country, 'is_default' => true,
                    'legal_name' => $fields['legal_name'] ?? null]);
            }

            return $party;
        });
    }

    /** @param string[] $roles */
    public function syncRoles(Party $party, array $roles): void
    {
        $roles = array_values(array_unique(array_intersect($roles, array_keys(Party::ROLES))));
        if ($roles === []) {
            throw ValidationException::withMessages(['roles' => __('Choose at least one role.')]);
        }

        $current = $party->roles()->pluck('role')->all();
        $added   = array_diff($roles, $current);
        $removed = array_diff($current, $roles);

        PartyRole::where('party_id', $party->id)->whereIn('role', $removed)->delete();
        foreach ($added as $role) {
            PartyRole::create(['party_id' => $party->id, 'role' => $role]);
        }
        $party->unsetRelation('roles');

        if ($current !== [] && ($added || $removed)) {
            $this->audit->record('party.roles_changed', "Roles of {$party->name}: " . implode(', ', $roles), 'party', (string) $party->id, $party->name,
                changes: ['added' => array_values($added), 'removed' => array_values($removed)]);
        }
    }

    /** @param array{tax_id: string, country: string, state_code?: ?string, legal_name?: ?string, address?: ?string, is_default?: bool} $data */
    public function addTaxId(Party $party, array $data): PartyTaxId
    {
        return DB::transaction(function () use ($party, $data) {
            $taxId = TaxIds::normalise($data['tax_id']);
            $this->assertTaxIdFree($taxId, $party->id);

            if ($data['country'] === 'IN') {
                $data['state_code'] = TaxIds::gstinState($taxId);
                if ($party->pan && TaxIds::gstinPan($taxId) !== $party->pan) {
                    throw ValidationException::withMessages(['tax_id' => __('This GSTIN belongs to PAN :pan, not to this party’s PAN :mine.', ['pan' => TaxIds::gstinPan($taxId), 'mine' => $party->pan])]);
                }
            }

            $first = ! PartyTaxId::where('party_id', $party->id)->exists();
            $row   = PartyTaxId::create([
                'party_id'   => $party->id,
                'country'    => $data['country'],
                'tax_id'     => $taxId,
                'state_code' => $data['state_code'] ?? null,
                'legal_name' => $data['legal_name'] ?? null,
                'address'    => $data['address'] ?? null,
                'is_default' => $first,
            ]);

            if (! $first && ! empty($data['is_default'])) {
                $this->makeDefault($row);
            }
            if ($first && ! $party->pan && $data['country'] === 'IN') {
                $party->forceFill(['pan' => TaxIds::gstinPan($taxId)])->save();
            }

            return $row;
        });
    }

    public function makeDefault(PartyTaxId $row): void
    {
        DB::transaction(function () use ($row) {
            PartyTaxId::where('party_id', $row->party_id)->where('id', '!=', $row->id)->where('is_default', true)->update(['is_default' => false]);
            $row->forceFill(['is_default' => true, 'active' => true])->save();
        });
    }

    /** A new draft version of an approved contract, with the same lines. */
    public function newVersion(RateContract $contract, string $validFrom): RateContract
    {
        return DB::transaction(function () use ($contract, $validFrom) {
            $next = $contract->replicate(['status', 'approved_by', 'approved_at', 'version']);
            $next->forceFill([
                'version'    => (int) RateContract::where('party_id', $contract->party_id)->where('name', $contract->name)->max('version') + 1,
                'status'     => 'draft',
                'valid_from' => $validFrom,
                'valid_to'   => null,
                'created_by' => auth()->id(),
            ])->save();

            foreach ($contract->lines as $line) {
                $next->lines()->create($line->only([
                    'from_place_id', 'to_place_id', 'material', 'truck_type', 'basis', 'rate', 'min_weight_mt',
                    'detention_free_hours', 'detention_rate_per_day', 'loading_charge', 'unloading_charge', 'extra_drop_charge',
                ]));
            }

            return $next;
        });
    }

    /**
     * Approve a draft. The previous approved version of the same contract
     * ends the day before this one starts, so two versions never overlap.
     */
    public function approve(RateContract $contract): void
    {
        if ($contract->status !== 'draft') {
            throw ValidationException::withMessages(['contract' => __('Only a draft can be approved.')]);
        }
        if (! $contract->lines()->exists()) {
            throw ValidationException::withMessages(['contract' => __('Add at least one route rate first.')]);
        }

        DB::transaction(function () use ($contract) {
            RateContract::where('party_id', $contract->party_id)->where('name', $contract->name)
                ->where('status', 'approved')->where('id', '!=', $contract->id)
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $contract->valid_from->toDateString()))
                ->get()
                ->each(function (RateContract $old) use ($contract) {
                    $old->forceFill(['valid_to' => $contract->valid_from->copy()->subDay()->toDateString()])->save();
                });

            $contract->forceFill(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()])->save();
        });

        $this->audit->record('rate_contract.approved', "Approved rates {$contract->label} for {$contract->party->name}", 'rate_contract', (string) $contract->id, $contract->label, severity: 'notable');
    }

    private function assertTaxIdFree(string $taxId, ?int $partyId): void
    {
        $other = PartyTaxId::with('party')->where('tax_id', $taxId)->first();
        if ($other && $other->party_id !== $partyId) {
            throw ValidationException::withMessages(['tax_id' => __(':id is already on :party.', ['id' => $taxId, 'party' => $other->party?->name])]);
        }
        if ($other && $other->party_id === $partyId) {
            throw ValidationException::withMessages(['tax_id' => __(':id is already on this party.', ['id' => $taxId])]);
        }
    }
}
