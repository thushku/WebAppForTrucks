<?php

namespace App\Modules\Settings\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * One of our bank accounts, printed on bills (with the UPI QR in India).
 * The account number, IBAN and UPI id are encrypted at rest; screens show
 * the last four digits.
 */
class CompanyBankAccount extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $hidden = ['account_number', 'iban', 'upi_id'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted', 'iban' => 'encrypted', 'upi_id' => 'encrypted', 'is_default' => 'boolean', 'show_on_invoice' => 'boolean', 'active' => 'boolean'];
    }

    public function masked(): string
    {
        return '•••• ' . $this->account_last4;
    }
}
