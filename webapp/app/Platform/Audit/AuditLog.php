<?php

namespace App\Platform\Audit;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of the audit trail, read on the company's Activity screen.
 *
 * Read-only by construction: saving or deleting an existing row throws,
 * so no screen, job or console command can rewrite history. New rows are
 * written only by AuditRecorder (with a plain insert).
 */
class AuditLog extends Model
{
    use BelongsToCompany;

    protected $table = 'audit_log';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'change_set'  => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('The audit log is append-only.'));
        static::deleting(fn () => throw new \LogicException('The audit log is append-only.'));
    }
}
