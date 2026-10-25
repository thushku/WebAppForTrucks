<?php

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentShare;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Share a pack of documents by an expiring link (blueprint 09 §6.2 "Share"):
 * "all papers of KL-07-AB-1234" for a client's gate or a police check. The
 * page shows each document's details and its images with "Copy shared on
 * <date> for <purpose>" burnt in. Only the link's hash is stored; opening is
 * counted; the link can be ended early.
 */
class DocumentShares
{
    public const MAX_DAYS = 30;

    public function __construct(private CompanyContext $context, private AuditRecorder $audit) {}

    /**
     * @param int[] $documentIds
     * @return string the link token (shown once)
     */
    public function create(array $documentIds, string $purpose, int $days): string
    {
        $ids   = Document::whereIn('id', $documentIds)->pluck('id')->all();
        $token = Str::random(48);
        $date  = now($this->context->company()->timezone)->format('d M Y');

        DocumentShare::create([
            'token_hash'   => hash('sha256', $token),
            'purpose'      => $purpose,
            'watermark'    => __('Copy shared on :date for :purpose', ['date' => $date, 'purpose' => $purpose]),
            'document_ids' => $ids,
            'expires_at'   => now()->addDays(max(1, min(self::MAX_DAYS, $days))),
            'created_by'   => auth()->id(),
        ]);

        return $token;
    }

    /** The live share for a token (any company — the link carries none), counting the visit. */
    public function open(string $token, bool $count = true): ?DocumentShare
    {
        if (! preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return null;
        }

        return DB::transaction(function () use ($token, $count) {
            $share = DocumentShare::withoutGlobalScopes()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $share || ! $share->isLive()) {
                return null;
            }
            if ($count) {
                $share->forceFill(['opened_count' => $share->opened_count + 1, 'last_opened_at' => now()])->saveQuietly();
            }

            return $share;
        });
    }
}
