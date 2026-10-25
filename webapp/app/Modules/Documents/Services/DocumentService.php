<?php

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVersion;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Parties\Models\Party;
use App\Modules\People\Models\Person;
use App\Platform\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adding, correcting and renewing documents, each in one transaction.
 *
 *  · Correcting changes the current version (a typo in the number).
 *  · Renewing archives the current version, read-only, with its files, and
 *    starts the next version (new number, dates, cost, files).
 *  · A driver's licence document keeps the person's licence number and expiry
 *    in step, so the people list, the crew app and the dispatch check agree.
 */
class DocumentService
{
    public function __construct(private DocumentFiles $files, private AuditRecorder $audit) {}

    /** The owner row, checked to be in this company (the scope does that), or a 404. */
    public function owner(string $type, int $id): ?Model
    {
        return match ($type) {
            'truck'   => Truck::findOrFail($id),
            'person'  => Person::findOrFail($id),
            'party'   => Party::findOrFail($id),
            'company' => null,
            default   => abort(404),
        };
    }

    /**
     * @param array<string, mixed> $data
     * @param UploadedFile[]       $uploads
     */
    public function create(string $ownerType, int $ownerId, array $data, array $uploads = []): Document
    {
        $this->owner($ownerType, $ownerId);

        return DB::transaction(function () use ($ownerType, $ownerId, $data, $uploads) {
            $document = Document::create($data + [
                'owner_type' => $ownerType,
                'owner_id'   => $ownerType === 'company' ? 0 : $ownerId,
                'version'    => 1,
                'created_by' => auth()->id(),
            ]);
            $this->attach($document, $uploads);
            $this->syncPerson($document);

            return $document;
        });
    }

    /** Correct the current version. */
    public function update(Document $document, array $data, array $uploads = []): void
    {
        DB::transaction(function () use ($document, $data, $uploads) {
            $document->fill($data);
            if ($document->isDirty('expires_on')) {
                // A new date starts the reminders again.
                $document->forceFill(['last_reminder_days' => null, 'reminded_at' => null, 'snoozed_until' => null, 'snooze_reason' => null]);
            }
            $document->save();
            $this->attach($document, $uploads);
            $this->syncPerson($document);
        });
    }

    /** Renew: the current version goes to history, the document takes the new details. */
    public function renew(Document $document, array $data, array $uploads = []): void
    {
        if (! empty($data['expires_on']) && $document->expires_on && $data['expires_on'] <= $document->expires_on->toDateString()) {
            throw ValidationException::withMessages(['expires_on' => __('A renewal must end after the current one (:date).', ['date' => $document->expires_on->format('d M Y')])]);
        }

        DB::transaction(function () use ($document, $data, $uploads) {
            DocumentVersion::create([
                'document_id' => $document->id,
                'version'     => $document->version,
                'number'      => $document->number,
                'authority'   => $document->authority,
                'issued_on'   => $document->issued_on,
                'valid_from'  => $document->valid_from,
                'expires_on'  => $document->expires_on,
                'amount'      => $document->amount,
                'vendor_party_id' => $document->vendor_party_id,
                'notes'       => $document->notes,
                'renewed_by'  => auth()->id(),
                'archived_at' => now(),
            ]);

            $document->fill($data)->forceFill([
                'version'            => $document->version + 1,
                'last_reminder_days' => null,
                'reminded_at'        => null,
                'snoozed_until'      => null,
                'snooze_reason'      => null,
            ])->save();

            $this->attach($document, $uploads);
            $this->syncPerson($document);
        });

        $this->audit->record('document.renewed', "Renewed {$document->document_type} {$document->number} (version {$document->version})", 'document', (string) $document->id, $document->number, severity: 'notable');
    }

    public function snooze(Document $document, string $until, string $reason): void
    {
        $document->forceFill(['snoozed_until' => $until, 'snooze_reason' => $reason])->save();
    }

    /** @param UploadedFile[] $uploads */
    private function attach(Document $document, array $uploads): void
    {
        foreach (array_values($uploads) as $i => $upload) {
            $this->files->store($document, $upload, count($uploads) > 1 ? __('Page :n', ['n' => $i + 1]) : null);
        }
    }

    /** The licence document is the source for the person's licence fields. */
    private function syncPerson(Document $document): void
    {
        if ($document->owner_type !== 'person' || $document->document_type !== 'licence') {
            return;
        }
        $person = Person::find($document->owner_id);
        $person?->forceFill([
            'licence_no'     => $document->number ? strtoupper(preg_replace('/\s+/', '', $document->number)) : $person->licence_no,
            'licence_expiry' => $document->expires_on ?? $person->licence_expiry,
        ])->save();
    }
}
