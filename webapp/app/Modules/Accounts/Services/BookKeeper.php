<?php

namespace App\Modules\Accounts\Services;

use App\Modules\Fleet\Models\ServiceJob;
use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Models\Receipt;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Trips\Models\BrokerCommission;
use App\Modules\Trips\Models\HireChallan;
use App\Modules\Trips\Models\HireChallanLine;
use App\Modules\Trips\Models\TripAdvance;
use App\Modules\Trips\Models\TripOfficeEntry;
use App\Modules\Trips\Models\TripSettlement;

/**
 * Keeps the book in step with the operations: whenever a money record is
 * made, issued, approved, cancelled or voided — by a screen, the phone API,
 * the demo or a command — its voucher is posted or reversed in the same
 * database transaction. If the posting fails, the operation fails with it,
 * so the book and the trips never disagree.
 */
final class BookKeeper
{
    public static function register(): void
    {
        $posting = fn () => app(Posting::class);
        $books = fn () => app(Books::class);

        Invoice::updated(function (Invoice $invoice) use ($posting, $books) {
            if ($invoice->wasChanged('finalized_at') && $invoice->finalized_at) {
                $posting()->invoice($invoice);
            }
            if ($invoice->wasChanged('status') && $invoice->status === 'void') {
                $books()->reverseSource('invoice', $invoice->id, $invoice->void_reason ?: __('Voided'));
            }
        });

        // A new receipt's status may still be the column default (not on the model yet): anything but void posts.
        Receipt::created(fn (Receipt $receipt) => $receipt->status !== 'void' ? $posting()->receipt($receipt) : null);
        Receipt::updated(function (Receipt $receipt) use ($books) {
            if ($receipt->wasChanged('status') && $receipt->status === 'void') {
                $books()->reverseSource('receipt', $receipt->id, $receipt->void_reason ?: __('Voided'));
            }
        });

        TripAdvance::created(fn (TripAdvance $advance) => $posting()->advance($advance));
        TripAdvance::deleted(fn (TripAdvance $advance) => $books()->reverseSource('trip_advance', $advance->id, __('Advance removed')));

        RoadEvent::created(fn (RoadEvent $event) => $event->status === 'approved' ? $posting()->roadEvent($event) : null);
        RoadEvent::updated(function (RoadEvent $event) use ($posting, $books) {
            if (! $event->wasChanged('status')) {
                return;
            }
            if ($event->status === 'approved') {
                $posting()->roadEvent($event);
            } elseif ($event->getOriginal('status') === 'approved') {
                $books()->reverseSource('road_event', $event->id, $event->cancel_reason ?: $event->decision_note ?: __('No longer approved'));
            }
        });

        TripSettlement::created(function (TripSettlement $settlement) use ($posting) {
            if ($voucher = $posting()->settlement($settlement)) {
                // The link only: written without model events, the settlement itself is not changed.
                TripSettlement::whereKey($settlement->id)->toBase()->update(['voucher_id' => $voucher->id]);
            }
        });
        TripSettlement::updated(function (TripSettlement $settlement) use ($books) {
            if ($settlement->wasChanged('voided_at') && $settlement->voided_at) {
                $books()->reverseSource('settlement', $settlement->id, $settlement->void_reason ?: __('Settlement undone'));
            }
        });

        TripOfficeEntry::created(fn (TripOfficeEntry $entry) => $entry->cancelled_at ? null : $posting()->officeEntry($entry));
        TripOfficeEntry::updated(function (TripOfficeEntry $entry) use ($books) {
            if ($entry->wasChanged('cancelled_at') && $entry->cancelled_at) {
                $books()->reverseSource('office_entry', $entry->id, $entry->cancel_reason ?: __('Cancelled'));
            }
        });

        HireChallan::updated(function (HireChallan $challan) use ($posting, $books) {
            if (! $challan->wasChanged('status')) {
                return;
            }
            if ($challan->status === 'issued') {
                $posting()->hireChallan($challan);
            } elseif ($challan->status === 'cancelled') {
                $books()->reverseSource('hire_challan', $challan->id, __('Hire challan cancelled'));
                foreach (HireChallanLine::where('hire_challan_id', $challan->id)->pluck('id') as $lineId) {
                    $books()->reverseSource('hire_line', $lineId, __('Hire challan cancelled'));
                }
            }
        });
        HireChallanLine::created(fn (HireChallanLine $line) => $posting()->hireLine($line));

        BrokerCommission::created(fn (BrokerCommission $commission) => $posting()->brokerCommission($commission));
        BrokerCommission::deleted(fn (BrokerCommission $commission) => $books()->reverseSource('broker_commission', $commission->id, __('Commission removed')));

        ServiceJob::saved(function (ServiceJob $job) use ($posting, $books) {
            if (! $job->wasRecentlyCreated && ! $job->wasChanged('status')) {
                return;
            }
            if ($job->status === 'done') {
                $posting()->serviceJob($job);
            } elseif ($job->getOriginal('status') === 'done') {
                $books()->reverseSource('service_job', $job->id, __('Service job :status', ['status' => $job->status]));
            }
        });
    }
}
