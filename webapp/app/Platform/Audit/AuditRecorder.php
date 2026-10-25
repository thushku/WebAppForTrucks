<?php

namespace App\Platform\Audit;

use App\Platform\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Writes the audit trail (ported from Clinic Ledger).
 *
 * Two ways in:
 *  · AUTOMATICALLY, from the `Audited` trait, so a new screen cannot ship
 *    without an audit trail simply because someone forgot to add one;
 *  · EXPLICITLY, via `record()`, for acts that are not one model write —
 *    a sign-in, an unlock, an export, a trip closed.
 */
class AuditRecorder
{
    /** Never written to the log, at any depth; matched by field NAME. */
    public const REDACTED = [
        'password', 'password_hash', 'pin_hash', 'remember_token', 'code_hash',
        'token_hash', 'token', 'secret', 'api_key', 'private_key',
        'account_number', 'iban', 'upi_id', 'snapshot', 'public_token',
    ];

    /** Columns that move by themselves, never because somebody decided something. */
    public const NOT_AN_ADMINISTRATIVE_ACT = [
        'updated_at', 'last_login_at', 'last_login_ip', 'failed_attempts', 'locked_until',
        'remember_token', 'last_used_at', 'last_seen_at',
    ];

    /** Acts worth a second look in the log. */
    public const SENSITIVE_ACTIONS = [
        'role.granted', 'role.revoked', 'security.configured', 'security.unlocked',
        'member.removed', 'data.exported', 'company.deletion_requested', 'device.revoked',
        'bank_account.created', 'bank_account.updated', 'bank_account.deleted',
        'party_bank_account.created', 'party_bank_account.updated', 'party_bank_account.deleted',
    ];

    public function __construct(private CompanyContext $context) {}

    /**
     * Record an event. Swallows its own failures: an audit write that threw
     * would roll back the very change it describes, turning a logging
     * problem into an outage. The failure goes to the application log.
     */
    public function record(
        string $action,
        string $summary,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $entityLabel = null,
        array $changes = [],
        ?string $reason = null,
        ?string $severity = null,
        ?int $companyId = null,
    ): void {
        try {
            [$actorType, $actorId, $actorLabel] = $this->actor();

            DB::table('audit_log')->insert([
                'company_id'   => $companyId ?? ($this->context->has() ? $this->context->id() : null),
                'actor_type'   => $actorType,
                'actor_id'     => $actorId,
                'actor_label'  => $actorLabel ? mb_substr($actorLabel, 0, 150) : null,
                'action'       => mb_substr($action, 0, 80),
                'entity_type'  => $entityType,
                'entity_id'    => $entityId,
                'entity_label' => $entityLabel ? mb_substr($entityLabel, 0, 200) : null,
                'change_set'   => $changes ? $this->encode($changes) : null,
                'summary'      => mb_substr($summary, 0, 500),
                'reason'       => $reason ? mb_substr($reason, 0, 500) : null,
                'severity'     => $severity ?? $this->severityFor($action),
                'ip'           => app()->runningInConsole() ? null : Request::ip(),
                'user_agent'   => app()->runningInConsole() ? null : mb_substr((string) Request::userAgent(), 0, 255),
                'occurred_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Record a model write, with only the fields that actually moved. */
    public function recordModelChange(Model $model, string $event): void
    {
        $type    = Str::snake(class_basename($model));
        $changes = match ($event) {
            'created' => $this->redact($model->getAttributes()),
            'updated' => $this->diff($model),
            default   => [],
        };

        // An "update" that moved nothing is noise, and noise is what makes
        // people stop reading a log.
        if ($event === 'updated' && $changes === []) {
            return;
        }

        $this->record(
            action: "{$type}.{$event}",
            summary: $this->describe($model, $type, $event, $changes),
            entityType: $type,
            entityId: (string) $model->getKey(),
            entityLabel: $this->labelFor($model),
            changes: $changes,
            companyId: $model->getAttribute('company_id') ?: null,
        );
    }

    // ---- Shaping ---------------------------------------------------------

    /** @return array<string, array{from: mixed, to: mixed}> */
    protected function diff(Model $model): array
    {
        $changes = [];

        foreach ($model->getDirty() as $field => $new) {
            if (in_array($field, self::NOT_AN_ADMINISTRATIVE_ACT, true)) {
                continue;
            }

            $changes[$field] = $this->isRedacted($field)
                ? ['from' => '[redacted]', 'to' => '[redacted]']
                : ['from' => $this->scalarise($model->getOriginal($field)), 'to' => $this->scalarise($new)];
        }

        return $changes;
    }

    protected function redact(array $attributes): array
    {
        $out = [];

        foreach ($attributes as $field => $value) {
            if (in_array($field, ['created_at', 'updated_at'], true)) {
                continue;
            }

            $out[$field] = $this->isRedacted($field) ? '[redacted]' : $this->scalarise($value);
        }

        return $out;
    }

    public function isRedacted(string $field): bool
    {
        if (in_array($field, self::REDACTED, true)) {
            return true;
        }

        foreach (['password', 'secret', 'token', '_hash', 'private', 'account_number', 'iban'] as $needle) {
            if (str_contains($field, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Strings are checked first: raw bytes handed to json_encode lose the whole change set. */
    protected function scalarise(mixed $value): mixed
    {
        return match (true) {
            $value === null                        => null,
            is_string($value)                      => mb_check_encoding($value, 'UTF-8') ? $value : '0x' . bin2hex($value),
            is_scalar($value)                      => $value,
            $value instanceof \DateTimeInterface   => $value->format('Y-m-d H:i:s'),
            $value instanceof \BackedEnum          => $value->value,
            default                                => json_encode($value),
        };
    }

    protected function encode(array $changes): ?string
    {
        $json = json_encode($changes);

        return $json !== false ? $json : (json_encode($changes, JSON_INVALID_UTF8_SUBSTITUTE) ?: null);
    }

    protected function describe(Model $model, string $type, string $event, array $changes): string
    {
        $label = $this->labelFor($model);
        $what  = str_replace('_', ' ', $type) . ($label ? " \"{$label}\"" : '');

        if ($event !== 'updated') {
            return ucfirst($event) . " {$what}";
        }

        $fields = array_keys($changes);
        $more   = count($fields) > 3 ? ' and ' . (count($fields) - 3) . ' more' : '';

        return 'Changed ' . implode(', ', array_slice($fields, 0, 3)) . "{$more} on {$what}";
    }

    /** The name a person would use for this record. */
    protected function labelFor(Model $model): ?string
    {
        foreach (['label', 'name', 'number', 'reg_no', 'email', 'code', 'title'] as $field) {
            // Only fields the model has: asking a loaded model for a column it
            // does not have throws in strict mode, and would stop the save.
            if (! array_key_exists($field, $model->getAttributes()) && ! $model->hasGetMutator($field) && ! $model->hasAttributeGetMutator($field)) {
                continue;
            }
            $value = $model->getAttribute($field);
            if (! empty($value) && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    protected function severityFor(string $action): string
    {
        if (in_array($action, self::SENSITIVE_ACTIONS, true)) {
            return 'sensitive';
        }

        return str_ends_with($action, '.deleted') || str_ends_with($action, '.created') ? 'notable' : 'info';
    }

    /** @return array{0: string, 1: ?int, 2: ?string} */
    protected function actor(): array
    {
        if ($admin = Auth::guard('platform')->user()) {
            return ['admin', $admin->id, $admin->name . ' (console)'];
        }

        // The request's own guard first (the phone API signs in with a token),
        // then the web session.
        $account = Auth::user() ?? Auth::guard('web')->user();

        if ($account instanceof \App\Models\Account) {
            return ['account', $account->id, $account->name ?: $account->email];
        }

        return ['system', null, app()->runningInConsole() ? 'System (console)' : 'System'];
    }
}
