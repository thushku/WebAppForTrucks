<?php

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentRequiredType;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Parties\Models\Party;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\MasterItem;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Collection;

/**
 * What is expired, due, or missing — for the dashboard, the truck and person
 * pages, reminders, and the dispatch check (blueprint 03 §4: "an expired
 * document blocks dispatch unless a manager overrides it with a reason").
 *
 * Document types and their rules (reminder days, blocks dispatch, personal
 * ID) come from Settings → Lists → Document types.
 */
class Compliance
{
    /** @var array<int, Collection> per company */
    private array $types = [];

    public function __construct(private CompanyContext $context) {}

    /** @return Collection<string, array{name: string, owner: string, remind_days: int, blocks_dispatch: bool, sensitive: bool}> */
    public function types(): Collection
    {
        return $this->types[$this->context->id()] ??= MasterItem::ofList('document_type')->get()->mapWithKeys(fn (MasterItem $t) => [$t->code => [
            'name'            => $t->name,
            'owner'           => $t->meta['owner'] ?? 'truck',
            'remind_days'     => (int) ($t->meta['remind_days'] ?? 30),
            'blocks_dispatch' => (bool) ($t->meta['blocks_dispatch'] ?? false),
            'sensitive'       => (bool) ($t->meta['sensitive'] ?? false),
            'active'          => $t->active,
        ]]);
    }

    public function typeName(string $code): string
    {
        return $this->types()[$code]['name'] ?? ucfirst(str_replace('_', ' ', $code));
    }

    public function isSensitive(string $code): bool
    {
        return (bool) ($this->types()[$code]['sensitive'] ?? false);
    }

    public function state(Document $document): string
    {
        return $document->state($this->types()[$document->document_type]['remind_days'] ?? 30);
    }

    /**
     * The papers that stop this truck or driver going out today: required or
     * dispatch-blocking types that are expired, and required ones that are
     * missing. A driver's licence on the person record counts too.
     *
     * @return array<int, array{type: string, name: string, reason: string}>
     */
    public function blockers(Truck|Person $owner): array
    {
        $type   = $owner instanceof Truck ? 'truck' : 'person';
        $docs   = Document::where('owner_type', $type)->where('owner_id', $owner->id)->get();
        $types  = $this->types();
        $out    = [];

        foreach ($docs as $doc) {
            if (($types[$doc->document_type]['blocks_dispatch'] ?? false) && $doc->daysLeft() !== null && $doc->daysLeft() < 0) {
                $out[$doc->document_type] = ['type' => $doc->document_type, 'name' => $this->typeName($doc->document_type), 'reason' => 'expired'];
            }
        }
        foreach ($this->missing($owner, $docs) as $code) {
            if ($types[$code]['blocks_dispatch'] ?? false) {
                $out[$code] ??= ['type' => $code, 'name' => $this->typeName($code), 'reason' => 'missing'];
            }
        }
        if ($owner instanceof Person && $owner->type === 'driver' && ! isset($out['licence'])) {
            $state = $owner->licenceState();
            if (in_array($state, ['expired', 'missing'], true)) {
                $out['licence'] = ['type' => 'licence', 'name' => $this->typeName('licence'), 'reason' => $state];
            }
        }

        return array_values($out);
    }

    /**
     * Required types this owner has no document for.
     *
     * @return string[]
     */
    public function missing(Truck|Person|null $owner, ?Collection $docs = null): array
    {
        [$appliesTo, $subtype, $ownerId] = match (true) {
            $owner instanceof Truck  => ['truck', $owner->truck_type, $owner->id],
            $owner instanceof Person => ['person', $owner->type, $owner->id],
            default                  => ['company', null, 0],
        };

        $required = DocumentRequiredType::where('applies_to', $appliesTo)
            ->where(fn ($q) => $q->whereNull('subtype')->when($subtype, fn ($w) => $w->orWhere('subtype', $subtype)))
            ->pluck('document_type')->unique();

        $docs ??= Document::where('owner_type', $appliesTo)->where('owner_id', $ownerId)->get();
        $have = $docs->pluck('document_type')->all();

        // A driver whose licence is on the person record has it, even without a document.
        if ($owner instanceof Person && $owner->licence_no) {
            $have[] = 'licence';
        }

        return $required->reject(fn ($code) => in_array($code, $have, true))->values()->all();
    }

    /** green | amber | red for an owner (truck page, people list). */
    public function health(Truck|Person $owner): string
    {
        $type = $owner instanceof Truck ? 'truck' : 'person';
        $docs = Document::where('owner_type', $type)->where('owner_id', $owner->id)->get();

        if ($this->missing($owner, $docs) || $docs->contains(fn ($d) => $this->state($d) === 'expired')) {
            return 'red';
        }

        return $docs->contains(fn ($d) => $this->state($d) === 'due') ? 'amber' : 'green';
    }

    /**
     * Counts for the dashboard tiles: expired, due within 7 / 15 / 30 days,
     * and missing required papers across every truck and person.
     *
     * @return array{expired: int, d7: int, d15: int, d30: int, missing: int}
     */
    public function counts(): array
    {
        $today = now()->startOfDay();
        $docs  = Document::whereNotNull('expires_on')->where('expires_on', '<=', $today->copy()->addDays(30))->pluck('expires_on');

        $counts = ['expired' => 0, 'd7' => 0, 'd15' => 0, 'd30' => 0, 'missing' => 0];
        foreach ($docs as $date) {
            $days = (int) $today->diffInDays($date, false);
            $key  = $days < 0 ? 'expired' : ($days <= 7 ? 'd7' : ($days <= 15 ? 'd15' : 'd30'));
            $counts[$key]++;
        }
        $counts['missing'] = count($this->missingAll());

        return $counts;
    }

    /**
     * Every missing required paper: [owner_type, owner_id, owner label, type].
     *
     * @return array<int, array{owner_type: string, owner_id: int, owner: string, type: string}>
     */
    public function missingAll(): array
    {
        $rules = DocumentRequiredType::all();
        if ($rules->isEmpty()) {
            return [];
        }

        $have = Document::query()->get(['owner_type', 'owner_id', 'document_type'])
            ->groupBy(fn ($d) => $d->owner_type . ':' . $d->owner_id)
            ->map(fn ($g) => $g->pluck('document_type')->all());

        $out = [];
        $check = function (string $appliesTo, ?string $subtype, int $id, string $label, array $extra = []) use ($rules, $have, &$out) {
            $owned = array_merge($have[$appliesTo . ':' . $id] ?? [], $extra);
            foreach ($rules->where('applies_to', $appliesTo) as $rule) {
                if (($rule->subtype === null || $rule->subtype === $subtype) && ! in_array($rule->document_type, $owned, true)) {
                    $out[$appliesTo . ':' . $id . ':' . $rule->document_type] = ['owner_type' => $appliesTo, 'owner_id' => $id, 'owner' => $label, 'type' => $rule->document_type];
                }
            }
        };

        if ($rules->contains('applies_to', 'truck')) {
            foreach (Truck::inService()->get(['id', 'reg_no', 'truck_type']) as $t) {
                $check('truck', $t->truck_type, $t->id, $t->reg_no);
            }
        }
        if ($rules->contains('applies_to', 'person')) {
            foreach (Person::working()->get(['id', 'name', 'type', 'licence_no']) as $p) {
                $check('person', $p->type, $p->id, $p->name, $p->licence_no ? ['licence'] : []);
            }
        }
        if ($rules->contains('applies_to', 'company')) {
            $check('company', null, 0, $this->context->company()->name);
        }

        return array_values($out);
    }

    /**
     * Names for a list of documents' owners, in a few queries.
     *
     * @param iterable<Document> $documents
     * @return array<string, string> "truck:5" => "KL-07-AB-1234"
     */
    public function ownerLabels(iterable $documents): array
    {
        $ids = [];
        foreach ($documents as $d) {
            $ids[$d->owner_type][] = $d->owner_id;
        }

        $labels = [];
        if (! empty($ids['truck'])) {
            foreach (Truck::whereIn('id', array_unique($ids['truck']))->pluck('reg_no', 'id') as $id => $reg) {
                $labels["truck:{$id}"] = $reg;
            }
        }
        if (! empty($ids['person'])) {
            foreach (Person::whereIn('id', array_unique($ids['person']))->pluck('name', 'id') as $id => $name) {
                $labels["person:{$id}"] = $name;
            }
        }
        if (! empty($ids['party'])) {
            foreach (Party::whereIn('id', array_unique($ids['party']))->pluck('name', 'id') as $id => $name) {
                $labels["party:{$id}"] = $name;
            }
        }
        if (! empty($ids['company'])) {
            $labels['company:0'] = $this->context->company()->name;
        }

        return $labels;
    }
}
