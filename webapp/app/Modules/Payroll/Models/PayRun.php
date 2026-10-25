<?php

namespace App\Modules\Payroll\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One pay run for any date range (13-parity-additions #24): draft while the
 * office checks it, approved (each payslip's pay posted to the person's
 * earnings), paid once every payslip is paid, or cancelled — its postings
 * reversed, never edited.
 */
class PayRun extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = ['draft' => 'Draft', 'approved' => 'Approved, to pay', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];

    public const SCOPES = ['crew' => 'Drivers and helpers', 'everyone' => 'Everyone with a pay set'];

    protected $guarded = ['id', 'company_id', 'uuid'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'approved_at' => 'datetime',
            'gross_total' => 'decimal:2', 'deductions_total' => 'decimal:2', 'net_total' => 'decimal:2', 'paid_total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(fn (PayRun $run) => $run->uuid ??= (string) Str::uuid7());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class)->orderBy('id');
    }

    /** "1–31 Oct 2026", "16 Oct – 15 Nov 2026". */
    public function periodLabel(): string
    {
        $from = $this->period_from;
        $to = $this->period_to;
        if ($from->isSameMonth($to)) {
            return $from->format('j') . '–' . $to->translatedFormat('j M Y');
        }

        return $from->translatedFormat($from->isSameYear($to) ? 'j M' : 'j M Y') . ' – ' . $to->translatedFormat('j M Y');
    }

    /** For the audit log and the books. */
    public function getLabelAttribute(): string
    {
        return __('Pay run :period', ['period' => $this->periodLabel()]);
    }
}
