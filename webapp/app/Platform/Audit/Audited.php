<?php

namespace App\Platform\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Put this on anything a person creates or configures, and it records itself.
 *
 * Money and trip records use it too: India's audit-trail rule asks for an
 * edit log of every change to the books that cannot be switched off. There
 * is deliberately no flag to turn it off.
 *
 * NOT for very high-volume rows (GPS points, login attempts): those are
 * already immutable evidence on their own.
 */
trait Audited
{
    public static function bootAudited(): void
    {
        static::created(fn (Model $model) => app(AuditRecorder::class)->recordModelChange($model, 'created'));
        static::updated(fn (Model $model) => app(AuditRecorder::class)->recordModelChange($model, 'updated'));
        static::deleted(fn (Model $model) => app(AuditRecorder::class)->recordModelChange($model, 'deleted'));
    }
}
