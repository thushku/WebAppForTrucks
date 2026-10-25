<?php

namespace App\Platform\Tenancy;

use App\Models\CompanyUser;
use RuntimeException;

/**
 * The company this request (or queued job) works in.
 *
 * Registered as a singleton. Every company-owned model reads it through the
 * BelongsToCompany scope, so no query can forget `where company_id = ?`.
 * A query with no company in context throws instead of quietly reading
 * every company's rows.
 */
class CompanyContext
{
    private ?Company $company = null;

    /** The signed-in person's membership in this company, when there is one. */
    private ?CompanyUser $member = null;

    public function set(Company $company, ?CompanyUser $member = null): void
    {
        $this->company = $company;
        $this->member  = $member;
    }

    public function forget(): void
    {
        $this->company = null;
        $this->member  = null;
    }

    public function has(): bool
    {
        return $this->company !== null;
    }

    public function company(): Company
    {
        if ($this->company === null) {
            throw new RuntimeException(
                'No company in context. Every company-scoped query needs one — '
                . 'if this is a queued job, it must carry and restore the company id.'
            );
        }

        return $this->company;
    }

    public function id(): int
    {
        return $this->company()->id;
    }

    public function member(): ?CompanyUser
    {
        return $this->member;
    }

    /**
     * Run a closure as another company, restoring the previous one afterwards
     * even if the closure throws. For console commands, jobs and tests that
     * legitimately span companies; never for request-handling code.
     */
    public function runAs(Company $company, callable $callback, ?CompanyUser $member = null): mixed
    {
        [$previous, $previousMember] = [$this->company, $this->member];
        [$this->company, $this->member] = [$company, $member];

        try {
            return $callback();
        } finally {
            [$this->company, $this->member] = [$previous, $previousMember];
        }
    }
}
