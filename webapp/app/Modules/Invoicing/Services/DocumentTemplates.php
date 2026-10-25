<?php

namespace App\Modules\Invoicing\Services;

use App\Modules\Invoicing\Design\DesignSchema;
use App\Modules\Invoicing\Models\DocumentTemplate;
use App\Modules\Invoicing\Models\DocumentTemplateVersion;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Which design a document prints with, and the life of a template (blueprint
 * 10 §9.3, ported from Clinic Ledger).
 *
 * A document looks for its design in this order: the template the bill was
 * given in the builder, the client's template, the branch's, the company's
 * default, and last the built-in design of the family's first theme — so a
 * company that never opens the editor still prints a proper GST invoice and
 * LR. An issued bill prints from the design frozen on it at issue.
 *
 * Every save is a new version; a rollback copies an old version forward, so
 * the history only grows. A client or branch belongs to one template per
 * family: giving it to one template takes it off the others.
 */
class DocumentTemplates
{
    // No cache on purpose: a controller (and this service inside it) can outlive one request,
    // and a print must never show a design older than the last save. A document reads one family: one query.

    public function __construct(private CompanyContext $context) {}

    /**
     * The design a document prints with.
     *
     * @return array{design: array, template: ?DocumentTemplate}
     */
    public function resolve(string $family, ?int $partyId = null, ?int $branchId = null, ?int $templateId = null): array
    {
        $templates = $this->templates($family);

        $template = ($templateId ? $templates->firstWhere('id', $templateId) : null)
            ?? ($partyId ? $templates->first(fn ($t) => in_array($partyId, array_map('intval', $t->party_ids ?? []), true)) : null)
            ?? ($branchId ? $templates->first(fn ($t) => in_array($branchId, array_map('intval', $t->branch_ids ?? []), true)) : null)
            ?? $templates->firstWhere('is_default', true);

        return ['design' => $template ? $template->designNormalized() : $this->builtIn($family), 'template' => $template];
    }

    /** The design frozen on a bill at issue: the design itself, and which template and version it came from. */
    public function freeze(string $family, ?int $partyId, ?int $branchId, ?int $templateId): array
    {
        ['design' => $design, 'template' => $template] = $this->resolve($family, $partyId, $branchId, $templateId);

        return $design + ['template' => $template ? ['id' => $template->id, 'name' => $template->name, 'version' => $template->version] : null];
    }

    /** The built-in design: the family's first theme, with the company's country in mind. */
    public function builtIn(string $family): array
    {
        $company = $this->context->company();
        $design  = DesignSchema::defaults($family);
        if ($family === 'invoice' && $company->country !== 'IN') {
            $design = DesignSchema::defaults('invoice', 'modern');
            $design['copies'] = [['label' => '', 'tint' => '#FFFFFF', 'hide_freight' => false]];
        }
        if ($company->country !== 'IN') {
            $design['paper'] = $family === 'receipt' ? 'A5' : ($company->country === 'US' ? 'Letter' : 'A4');
        }

        return $design;
    }

    /** @return Collection<int, DocumentTemplate> */
    public function templates(string $family): Collection
    {
        return DocumentTemplate::family($family)->orderByDesc('is_default')->orderBy('name')->get();
    }

    // ---- Changes ------------------------------------------------------------------

    /** A new template from a theme (or an imported design). The first of its family becomes the default. */
    public function create(string $family, string $name, array $input = [], ?int $accountId = null, ?array &$dropped = null): DocumentTemplate
    {
        $design = DesignSchema::normalize($family, $input ?: $this->builtIn($family), $dropped);

        return DB::transaction(function () use ($family, $name, $design, $accountId) {
            $template = DocumentTemplate::create([
                'family' => $family, 'name' => $this->uniqueName($family, $name), 'design' => $design, 'version' => 1,
                'is_default' => ! DocumentTemplate::family($family)->exists(), 'created_by' => $accountId,
            ]);
            $this->addVersion($template, $accountId);

            return $template;
        });
    }

    /**
     * Saves a design. Nothing changes when the design is the same; otherwise
     * the version goes up and the old one stays in the history.
     */
    public function save(DocumentTemplate $template, array $input, ?string $name = null, ?int $accountId = null, ?array &$dropped = null): DocumentTemplate
    {
        $design = DesignSchema::normalize($template->family, $input, $dropped);

        return DB::transaction(function () use ($template, $design, $name, $accountId) {
            $template = DocumentTemplate::lockForUpdate()->findOrFail($template->id);
            if ($name !== null && trim($name) !== '' && trim($name) !== $template->name) {
                $template->name = $this->uniqueName($template->family, trim($name), $template->id);
            }
            if ($design != $template->designNormalized()) {
                $template->design  = $design;
                $template->version = $template->version + 1;
                $template->save();
                $this->addVersion($template, $accountId);
            } elseif ($template->isDirty()) {
                $template->save();
            }

            return $template;
        });
    }

    public function duplicate(DocumentTemplate $template, ?int $accountId = null): DocumentTemplate
    {
        return $this->create($template->family, __(':name (copy)', ['name' => $template->name]), $template->designNormalized(), $accountId);
    }

    /** Copies an old version forward as the newest one. */
    public function rollback(DocumentTemplate $template, int $version, ?int $accountId = null): DocumentTemplate
    {
        $old = $template->versions()->where('version', $version)->first();
        if (! $old) {
            throw ValidationException::withMessages(['version' => __('That version is not in this template’s history.')]);
        }

        return $this->save($template, $old->design, null, $accountId);
    }

    public function makeDefault(DocumentTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            DocumentTemplate::family($template->family)->where('id', '!=', $template->id)->where('is_default', true)->get()->each->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });
    }

    /**
     * Gives the template to these clients and branches. A client or branch
     * prints with one template per family, so it leaves any other.
     */
    public function assign(DocumentTemplate $template, array $partyIds, array $branchIds): void
    {
        $partyIds  = array_values(array_unique(array_map('intval', array_filter($partyIds))));
        $branchIds = array_values(array_unique(array_map('intval', array_filter($branchIds))));

        DB::transaction(function () use ($template, $partyIds, $branchIds) {
            foreach (DocumentTemplate::family($template->family)->where('id', '!=', $template->id)->lockForUpdate()->get() as $other) {
                $parties  = array_values(array_diff(array_map('intval', $other->party_ids ?? []), $partyIds));
                $branches = array_values(array_diff(array_map('intval', $other->branch_ids ?? []), $branchIds));
                if ($parties !== array_map('intval', $other->party_ids ?? []) || $branches !== array_map('intval', $other->branch_ids ?? [])) {
                    $other->update(['party_ids' => $parties ?: null, 'branch_ids' => $branches ?: null]);
                }
            }
            $template->update(['party_ids' => $partyIds ?: null, 'branch_ids' => $branchIds ?: null]);
        });
    }

    /** A template goes only when it is not the default; bills already issued keep their frozen design. */
    public function delete(DocumentTemplate $template): void
    {
        if ($template->is_default) {
            throw ValidationException::withMessages(['template' => __('This is the default template. Make another one the default first.')]);
        }
        $template->delete();
    }

    /** The design as a file to keep or move to another company. */
    public function export(DocumentTemplate $template): array
    {
        return ['name' => $template->name] + $template->designNormalized();
    }

    // ---- Helpers -------------------------------------------------------------------

    private function addVersion(DocumentTemplate $template, ?int $accountId): void
    {
        DocumentTemplateVersion::create([
            'document_template_id' => $template->id, 'version' => $template->version, 'design' => $template->design, 'created_by' => $accountId,
        ]);
    }

    private function uniqueName(string $family, string $name, ?int $except = null): string
    {
        $name  = mb_substr(trim($name) ?: __('Template'), 0, 70);
        $taken = DocumentTemplate::family($family)->when($except, fn ($q) => $q->where('id', '!=', $except))->pluck('name')->all();
        $try   = $name;
        for ($n = 2; in_array($try, $taken, true); $n++) {
            $try = $name . ' ' . $n;
        }

        return $try;
    }
}
