<?php

namespace App\Platform\Tenancy;

use App\Modules\Settings\Models\Branch;
use App\Platform\Authorization\PermissionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Which branch the lists show (M22, blueprint 04 §1 "branch filter"), and
 * which branches a person may work in at all.
 *
 * A role given for one branch only (Settings → Team) limits the person to
 * it: their lists show that branch, and its trips, bills and quotations are
 * the only ones they can open (trucks and people with no branch are shared).
 * Everyone else sees the whole company and may narrow the lists to one
 * branch with the filter in the header — a view, not a permission. Services
 * and the books never look here: a truck busy in another branch is still busy.
 */
class BranchContext
{
    private const SESSION_KEY = 'branch_filter';

    /** @var array{member: ?int, allowed: ?array, choices: ?Collection} */
    private array $memo = ['member' => -1, 'allowed' => null, 'choices' => null];

    public function __construct(private CompanyContext $context, private PermissionResolver $permissions) {}

    /** The branches this person may work in; null = the whole company. */
    public function allowed(): ?array
    {
        $this->remember();

        return $this->memo['allowed'];
    }

    public function limited(): bool
    {
        return $this->allowed() !== null;
    }

    /** The branches to pick from in the filter and on forms (id => name). */
    public function choices(): Collection
    {
        $this->remember();
        if ($this->memo['choices'] === null) {
            $allowed = $this->memo['allowed'];
            $this->memo['choices'] = $this->context->has()
                ? Branch::where('active', true)->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))->orderByDesc('is_head_office')->orderBy('name')->pluck('name', 'id')
                : collect();
        }

        return $this->memo['choices'];
    }

    /** The filter shows only where there is a choice: two branches or more. */
    public function shown(): bool
    {
        return $this->choices()->count() > 1;
    }

    /** The branch the lists show now; null = every branch the person may see. */
    public function selected(): ?int
    {
        $choice = $this->context->has() ? session()->get(self::SESSION_KEY . '.' . $this->context->id()) : null;
        if ($choice !== null && $this->choices()->has((int) $choice)) {
            return (int) $choice;
        }
        $allowed = $this->allowed();

        return $allowed !== null && count($allowed) === 1 ? $allowed[0] : null;
    }

    public function select(?int $branchId): void
    {
        $key = self::SESSION_KEY . '.' . $this->context->id();
        $branchId !== null && $this->choices()->has($branchId) ? session()->put($key, $branchId) : session()->forget($key);
    }

    /** The branch ids lists are limited to; null = no limit. */
    public function ids(): ?array
    {
        $selected = $this->selected();

        return $selected !== null ? [$selected] : $this->allowed();
    }

    /** Narrow a list to the branch(es) shown; records with no branch stay when they are shared (trucks, people). */
    public function apply(Builder $query, string $column = 'branch_id', bool $includeUnassigned = false): Builder
    {
        $ids = $this->ids();

        return $ids === null ? $query : $query->where(fn ($q) => $q->whereIn($column, $ids)->when($includeUnassigned, fn ($w) => $w->orWhereNull($column)));
    }

    /** May this person open a record of this branch? Only a branch-limited person is ever refused. */
    public function canSee(?int $branchId, bool $sharedWhenUnassigned = false): bool
    {
        $allowed = $this->allowed();
        if ($allowed === null) {
            return true;
        }

        return $branchId === null ? $sharedWhenUnassigned : in_array($branchId, $allowed, true);
    }

    /** The branch a new trip, bill or quotation starts in: the one shown, else a limited person's first. */
    public function defaultBranch(): ?int
    {
        return $this->selected() ?? ($this->allowed()[0] ?? null);
    }

    /** Memo per request and member: tests and long processes serve several people with one instance. */
    private function remember(): void
    {
        $member = $this->context->has() ? $this->context->member() : null;
        $key = spl_object_id(request()) . ':' . ($member?->id ?? 0) . ':' . ($this->context->has() ? $this->context->id() : 0);
        if ($this->memo['member'] === $key) {
            return;
        }
        $this->memo = ['member' => $key, 'allowed' => $member ? $this->permissions->branchScope($member) : null, 'choices' => null];
    }
}
