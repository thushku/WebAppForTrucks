<?php

namespace App\Modules\Road\Services;

use App\Modules\Road\Models\EventPhoto;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Trips\Models\Consignment;
use App\Platform\Tenancy\CompanyContext;
use App\Support\Images;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Photos of road events (blueprint 01 §5, 03 §5.2a). The entry is saved
 * first, without photos, so a driver with weak signal never loses it; the
 * photos follow one by one and may arrive hours later.
 *
 *  · Each photo is made small (≤ 1600 px, ≤ 500 KB) and stripped of the
 *    camera's hidden data.
 *  · Two fingerprints catch a photo used before: the exact bytes of what the
 *    phone sent (the same file again), and the look-alike hash (the same
 *    meter or slip photographed again, or the old photo re-saved). Either
 *    flags the entry "same_photo" for the manager; neither refuses it.
 *  · Sending the same photo for the same slot again (a retry) is harmless.
 */
class PhotoService
{
    /** Look-alike when at most this many of the 64 hash bits differ. */
    public const LOOK_ALIKE_BITS = 4;

    /** How far back, and how many photos, the look-alike check compares. */
    private const LOOK_BACK_DAYS = 60;

    private const LOOK_BACK_PHOTOS = 400;

    public function __construct(private CompanyContext $context) {}

    /** @return array{result: string, photo: EventPhoto} result: saved | duplicate | replaced */
    public function store(RoadEvent $event, string $slot, UploadedFile $upload, ?string $takenAt = null): array
    {
        if (! in_array($slot, EventPhoto::SLOTS, true)) {
            throw ValidationException::withMessages(['slot' => __('Unknown photo slot.')]);
        }

        $original = (string) file_get_contents((string) $upload->getRealPath());
        $sha      = hash('sha256', $original);
        $existing = EventPhoto::where('road_event_id', $event->id)->where('slot', $slot)->first();

        if ($existing && $existing->sha256 === $sha) {
            return ['result' => 'duplicate', 'photo' => $existing];
        }
        if ($existing && ! in_array($event->status, ['info', 'waiting_2nd', 'waiting_check', 'queried', 'disputed'], true)) {
            throw ValidationException::withMessages(['photo' => __('This entry has been checked; its photos can no longer change.')]);
        }

        $bytes = Images::shrink((string) $upload->getRealPath());
        $ahash = Images::averageHash($bytes);
        $twin  = $this->twin($event, $sha, $ahash);

        $company = $this->context->company();
        $path    = "companies/{$company->uuid}/road/" . now()->format('Y/m') . "/{$event->uuid}-{$slot}.jpg";

        $photo = DB::transaction(function () use ($event, $slot, $existing, $path, $bytes, $sha, $ahash, $twin, $takenAt) {
            Storage::disk('local')->put($path, $bytes);

            $fields = [
                'path' => $path, 'bytes' => strlen($bytes), 'sha256' => $sha, 'ahash' => $ahash,
                'duplicate_of' => $twin?->id,
                'taken_at' => $takenAt ? CarbonImmutable::parse($takenAt)->utc() : null,
            ];
            $photo = $existing
                ? tap($existing)->forceFill($fields)->save()
                : EventPhoto::create($fields + ['road_event_id' => $event->id, 'slot' => $slot]);

            $flags = array_values(array_diff($event->flags ?? [], ['same_photo']));
            if ($twin || EventPhoto::where('road_event_id', $event->id)->whereNotNull('duplicate_of')->exists()) {
                $flags[] = 'same_photo';
            }
            $event->forceFill([
                'photos_received' => EventPhoto::where('road_event_id', $event->id)->count(),
                'flags' => $flags ?: null,
            ])->save();

            // The POD photo of a delivery: the office now holds the proof of delivery.
            if ($slot === 'pod' && $event->type === 'delivered' && ! empty($event->data['consignment_id'])) {
                Consignment::whereKey($event->data['consignment_id'])->where('trip_id', $event->trip_id)->where('pod_status', 'none')
                    ->update(['pod_status' => 'received', 'pod_received_at' => now(), 'updated_at' => now()]);
            }

            return $photo;
        });

        return ['result' => $existing ? 'replaced' : 'saved', 'photo' => $photo];
    }

    /**
     * The earlier photo (on another entry) this one copies: the same bytes,
     * the same look-alike hash, or one within a few bits of it among the
     * company's recent photos of the same kind.
     */
    private function twin(RoadEvent $event, string $sha, string $ahash): ?EventPhoto
    {
        $others = EventPhoto::where('road_event_id', '!=', $event->id);

        $exact = (clone $others)->where(fn ($q) => $q->where('sha256', $sha)->orWhere('ahash', $ahash))->orderBy('id')->first();
        if ($exact) {
            return $exact;
        }
        // A blank or one-colour picture hashes to all zeros or ones: no use for comparing.
        if (in_array($ahash, ['0000000000000000', 'ffffffffffffffff'], true)) {
            return null;
        }

        return (clone $others)->where('created_at', '>=', now()->subDays(self::LOOK_BACK_DAYS))
            ->orderByDesc('id')->limit(self::LOOK_BACK_PHOTOS)->get(['id', 'ahash'])
            ->first(fn (EventPhoto $p) => Images::distance($p->ahash, $ahash) <= self::LOOK_ALIKE_BITS);
    }
}
