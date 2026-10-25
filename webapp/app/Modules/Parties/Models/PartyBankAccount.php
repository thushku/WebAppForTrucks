<?php

namespace App\Modules\Parties\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Where we pay a supplier or vendor. The account number, IBAN and UPI id are
 * encrypted at rest and never written to the audit log (AuditRecorder
 * redacts them by name); screens show the last four digits only.
 *
 * Changing a supplier's bank account is how payment fraud usually starts, so
 * it needs `settings.bank` and is marked sensitive in the log.
 */
class PartyBankAccount extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $hidden = ['account_number', 'iban', 'upi_id'];

    protected $attributes = ['is_default' => false];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'iban'           => 'encrypted',
            'upi_id'         => 'encrypted',
            'is_default'     => 'boolean',
            'verified_at'    => 'datetime',
        ];
    }

    /** "HDFC Bank ···· 4321" */
    public function getLabelAttribute(): string
    {
        return trim(($this->bank_name ?: __('Bank account')) . ($this->account_last4 ? ' ···· ' . $this->account_last4 : ''));
    }

    /** "r***@okhdfc" */
    public function maskedUpi(): ?string
    {
        $upi = $this->upi_id;
        if (! $upi || ! str_contains($upi, '@')) {
            return $upi ? '***' : null;
        }
        [$name, $handle] = explode('@', $upi, 2);

        return mb_substr($name, 0, 1) . '***@' . $handle;
    }
}
