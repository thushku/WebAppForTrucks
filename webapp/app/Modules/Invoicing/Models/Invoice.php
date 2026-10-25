<?php

namespace App\Modules\Invoicing\Models;

use App\Modules\Parties\Models\Party;
use App\Modules\Tax\Models\TaxRegistration;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A freight bill, ported from Clinic Ledger's Invoice (blueprint 10 §2). A
 * draft is a working document; once finalised it is evidence: number,
 * lines, tax and the printed snapshot never change again — enforced here, so
 * no screen, job or console command can rewrite it. Fixes are a void (the
 * number is kept) or a credit / debit note.
 *
 * One table for the family (doc_type): invoice, supplementary invoice
 * (detention, rate difference — against an invoice), credit note (takes
 * money off its invoice; what it could not take is the client's credit),
 * debit note (a further charge, paid like an invoice).
 */
class Invoice extends Model
{
    use Audited, BelongsToCompany, SoftDeletes;

    public const DOC_TYPES = [
        'invoice'       => 'Invoice',
        'supplementary' => 'Supplementary invoice',
        'credit_note'   => 'Credit note',
        'debit_note'    => 'Debit note',
    ];

    /** Which number series a document takes. A supplementary invoice is an invoice for numbering (GST Rule 46). */
    public const SERIES = ['invoice' => 'invoice', 'supplementary' => 'invoice', 'credit_note' => 'credit_note', 'debit_note' => 'debit_note'];

    public const OPEN_STATUSES = ['finalised', 'sent', 'viewed', 'partial'];

    /** Columns that may still change once finalised. */
    public const MUTABLE_AFTER_FINAL = [
        'status', 'amount_paid', 'amount_credited', 'credit_applied', 'balance_due', 'sent_at', 'viewed_at', 'paid_at',
        'voided_at', 'void_reason', 'public_token', 'submitted_via', 'submitted_on', 'submission_ref', 'due_date_from_submission',
        'updated_at', 'deleted_at',
    ];

    protected $guarded = ['id', 'company_id', 'uuid'];

    protected $attributes = ['status' => 'draft', 'doc_type' => 'invoice', 'discount_type' => 'none', 'inclusive' => false];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            $invoice->uuid ??= (string) Str::uuid7();
        });

        static::updating(function (Invoice $invoice) {
            if ($invoice->getOriginal('finalized_at') === null) {
                return;
            }
            $changed = array_diff(array_keys($invoice->getDirty()), self::MUTABLE_AFTER_FINAL);
            if ($changed !== []) {
                throw new RuntimeException('A finalised invoice is never edited (' . implode(', ', $changed) . '). Void it, or issue a credit or debit note.');
            }
        });

        static::deleting(function (Invoice $invoice) {
            if ($invoice->finalized_at !== null) {
                throw new RuntimeException('A finalised invoice is never deleted. Void it instead.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'issue_date' => 'date', 'due_date' => 'date', 'period_from' => 'date', 'period_to' => 'date', 'submitted_on' => 'date',
            'inclusive' => 'boolean', 'reverse_charge' => 'boolean', 'pending_evidence' => 'boolean',
            'discount_value' => 'decimal:3', 'subtotal' => 'decimal:3', 'discount_amount' => 'decimal:3', 'taxable_total' => 'decimal:3',
            'tax_total' => 'decimal:3', 'tax_notional' => 'decimal:3', 'round_off' => 'decimal:3', 'total' => 'decimal:3',
            'advance_less' => 'decimal:3', 'amount_paid' => 'decimal:3', 'amount_credited' => 'decimal:3', 'credit_applied' => 'decimal:3',
            'tds_expected' => 'decimal:3', 'balance_due' => 'decimal:3',
            'evidence' => 'array', 'snapshot' => 'encrypted:array', 'tax_trace' => 'array', 'withholding' => 'array', 'design_snapshot' => 'array',
            'finalized_at' => 'datetime', 'sent_at' => 'datetime', 'viewed_at' => 'datetime', 'paid_at' => 'datetime', 'voided_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceTax::class)->orderBy('sort_order');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TaxRegistration::class, 'tax_registration_id');
    }

    public function related(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_invoice_id');
    }

    public function notesAgainst(): HasMany
    {
        return $this->hasMany(self::class, 'related_invoice_id')->orderBy('issue_date')->orderBy('id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class)->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinal(): bool
    {
        return $this->finalized_at !== null && $this->status !== 'void';
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    public function isCreditNote(): bool
    {
        return $this->doc_type === 'credit_note';
    }

    public function isNote(): bool
    {
        return in_array($this->doc_type, ['credit_note', 'debit_note'], true);
    }

    /** Waits for money: an issued invoice, supplementary bill or debit note with something left. */
    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true) && ! $this->isCreditNote();
    }

    public function isOverdue(?CarbonImmutable $today = null): bool
    {
        return $this->isOpen() && (float) $this->balance_due > 0 && $this->due_date->lt($today ?? CarbonImmutable::today());
    }

    public function kindLabel(): string
    {
        return __(self::DOC_TYPES[$this->doc_type] ?? 'Invoice');
    }

    /** What is left to credit: the total less credit notes already issued against it. */
    public function creditable(?int $exceptNoteId = null): float
    {
        $credited = self::where('related_invoice_id', $this->id)->where('doc_type', 'credit_note')->whereNotNull('finalized_at')->where('status', '!=', 'void')
            ->when($exceptNoteId, fn ($q) => $q->whereKeyNot($exceptNoteId))->sum('total');

        return round(max(0, (float) $this->total - (float) $credited), 2);
    }

    /**
     * Balance after advance, payments and credit notes, and the status that
     * follows. Not saved: the caller saves in its transaction.
     */
    public function settle(?\Carbon\CarbonInterface $paidOn = null): void
    {
        $balance = round((float) $this->total - (float) $this->advance_less - (float) $this->amount_paid - (float) $this->amount_credited, 3);
        $status  = $balance <= 0.005
            ? 'paid'
            : ((float) $this->amount_paid > 0 ? 'partial' : ($this->viewed_at ? 'viewed' : ($this->sent_at ? 'sent' : 'finalised')));

        $this->forceFill(['balance_due' => max(0, $balance), 'status' => $status, 'paid_at' => $status === 'paid' ? ($paidOn ?? $this->paid_at ?? now()) : null]);
    }

    public function statusLabel(): string
    {
        if ($this->isOverdue()) {
            return __('Overdue');
        }

        return match ($this->status) {
            'draft'     => __('Draft'),
            'finalised' => __('Issued'),
            'issued'    => __('Issued'),
            'sent'      => __('Sent'),
            'viewed'    => __('Viewed'),
            'partial'   => __('Partly paid'),
            'paid'      => $this->isCreditNote() ? __('Issued') : __('Paid'),
            'void'      => __('Void'),
            default     => ucfirst($this->status),
        };
    }

    public function statusTone(): string
    {
        if ($this->isOverdue()) {
            return 'bg-red-50 text-overdue';
        }

        return match ($this->status) {
            'draft'          => 'bg-ink-100 text-ink-700',
            'sent', 'viewed' => 'bg-sky-50 text-sent',
            'partial'        => 'bg-amber-50 text-partial',
            'paid'           => 'bg-emerald-50 text-paid',
            'void'           => 'bg-ink-100 text-ink-400 line-through',
            default          => 'bg-brand-50 text-brand-700',
        };
    }

    public function numberOrDraft(): string
    {
        return $this->number ?: __('Draft');
    }

    /** "Invoice-INV-26-27-00042" for file names (13 §A #26). */
    public function fileName(): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $this->doc_type))) . '-' . str_replace(['/', '\\', ' '], '-', $this->number ?: 'Draft-' . $this->id);
    }

    public function getLabelAttribute(): string
    {
        return $this->number ?? ('Draft ' . $this->kindLabel() . ' #' . $this->id);
    }
}
