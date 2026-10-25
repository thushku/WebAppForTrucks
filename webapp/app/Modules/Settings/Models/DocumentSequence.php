<?php

namespace App\Modules\Settings\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How one document type is numbered (ported from Clinic Ledger): prefix,
 * format, the number each period starts at, and when to restart. The number
 * itself is taken by DocumentNumbering under a row lock when a document is
 * issued; the running counters live in document_counters, one per period.
 */
class DocumentSequence extends Model
{
    use Audited, BelongsToCompany;

    /** Every numbered document in the product: label, starting prefix, format, restart. */
    public const DOC_TYPES = [
        'trip'            => ['Trip', '', '{YY}{MM}{DD}-{SHP}-{FRM}-{TO}-{01}', 'daily'],
        'lr'              => ['LR / consignment note', 'LR/', '{PREFIX}{FY2}/{00001}', 'fiscal_year'],
        'invoice'         => ['Tax invoice / bill', 'INV/', '{PREFIX}{FY2}/{00001}', 'fiscal_year'],
        'credit_note'     => ['Credit note', 'CN/', '{PREFIX}{FY2}/{0001}', 'fiscal_year'],
        'debit_note'      => ['Debit note', 'DN/', '{PREFIX}{FY2}/{0001}', 'fiscal_year'],
        'receipt'         => ['Money receipt', 'RCT/', '{PREFIX}{FY2}/{00001}', 'fiscal_year'],
        'payment_voucher' => ['Payment voucher', 'PV/', '{PREFIX}{FY2}/{00001}', 'fiscal_year'],
        'hire_challan'    => ['Lorry hire challan', 'HC/', '{PREFIX}{FY2}/{00001}', 'fiscal_year'],
        'quotation'       => ['Quotation', 'QT/', '{PREFIX}{FY2}/{0001}', 'fiscal_year'],
        'fuel_slip'       => ['Fuel slip', 'FS/', '{PREFIX}{FY2}/{00001}', 'fiscal_year'],
        'journal'         => ['Journal voucher', 'JV/', '{PREFIX}{FY2}/{00001}', 'fiscal_year'],
        'voucher'         => ['Accounting voucher (made by the system)', 'V/', '{PREFIX}{FY2}/{000001}', 'fiscal_year'],
        'complaint'       => ['Truck complaint', 'C-', '{PREFIX}{1}', 'never'],
        'service_job'     => ['Service job', 'SJ/', '{PREFIX}{FY2}/{0001}', 'fiscal_year'],
    ];

    public const RESET_POLICIES = [
        'fiscal_year' => 'Every financial year',
        'monthly'     => 'Every month',
        'daily'       => 'Every day',
        'never'       => 'Never (one running series)',
    ];

    /** Documents that are tax invoices in India: number at most 16 characters, unique per year (GST Rule 46). */
    public const TAX_INVOICE_TYPES = ['invoice', 'credit_note', 'debit_note'];

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['scope_type' => 'company', 'scope_id' => 0, 'prefix' => '', 'start_number' => 1, 'reset_policy' => 'fiscal_year'];

    protected function casts(): array
    {
        return ['start_number' => 'integer', 'max_length' => 'integer', 'scope_id' => 'integer'];
    }

    public function counters(): HasMany
    {
        return $this->hasMany(DocumentCounter::class);
    }

    /** "Tax invoice / bill" (not `label()`: the audit log reads a `label` attribute). */
    public function displayName(): string
    {
        return __(self::DOC_TYPES[$this->doc_type][0] ?? ucfirst(str_replace('_', ' ', $this->doc_type)));
    }
}
