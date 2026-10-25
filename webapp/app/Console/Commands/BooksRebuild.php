<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Models\VoucherLine;
use App\Modules\Accounts\Services\Books;
use App\Modules\Accounts\Services\ChartOfAccounts;
use App\Modules\Accounts\Services\Posting;
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
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Post to the book whatever is not in it yet: everything recorded before the
 * books existed (M11), or after a fix to a posting rule. Safe to run again —
 * a document posts once — and it ends with a check that the book balances.
 * Cancelled and voided documents post and reverse, so the history is whole.
 */
class BooksRebuild extends Command
{
    protected $signature = 'fleet:books {--company= : One company id only}';

    protected $description = 'Post every money record that is not in the books yet, then check the books balance';

    public function handle(CompanyContext $context): int
    {
        $failed = false;
        foreach (Company::query()->when($this->option('company'), fn ($q, $id) => $q->whereKey($id))->get() as $company) {
            $failed = ! $context->runAs($company, fn () => $this->company($company)) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function company(Company $company): bool
    {
        $posting = app(Posting::class);
        $books = app(Books::class);
        app(ChartOfAccounts::class)->ensure();
        $count = 0;
        $post = function ($voucher) use (&$count) {
            $count += $voucher && $voucher->wasRecentlyCreated ? 1 : 0;
        };

        // Only what never posted: a document posted and reversed since is complete, not missing.
        $new = fn (string $type) => fn ($model) => ! $books->seen($type, $model->id);

        DB::transaction(function () use ($posting, $books, $post, $new) {
            foreach (Invoice::whereNotNull('finalized_at')->orderBy('issue_date')->orderBy('id')->get()->filter($new('invoice')) as $invoice) {
                $post($posting->invoice($invoice));
                if ($invoice->status === 'void') {
                    $post($books->reverseSource('invoice', $invoice->id, $invoice->void_reason ?: 'Voided'));
                }
            }
            foreach (Receipt::orderBy('receipt_date')->orderBy('id')->get()->filter($new('receipt')) as $receipt) {
                $post($posting->receipt($receipt));
                if ($receipt->status === 'void') {
                    $post($books->reverseSource('receipt', $receipt->id, $receipt->void_reason ?: 'Voided'));
                }
            }
            TripAdvance::orderBy('given_on')->orderBy('id')->get()->filter($new('trip_advance'))->each(fn ($a) => $post($posting->advance($a)));
            RoadEvent::where('status', 'approved')->whereNotNull('amount')->orderBy('event_at')->get()->filter($new('road_event'))->each(fn ($e) => $post($posting->roadEvent($e)));
            foreach (TripSettlement::orderBy('settled_at')->orderBy('id')->get()->filter($new('settlement')) as $settlement) {
                $post($posting->settlement($settlement));
                if ($settlement->voided_at) {
                    $post($books->reverseSource('settlement', $settlement->id, $settlement->void_reason ?: 'Settlement undone'));
                }
            }
            foreach (TripOfficeEntry::orderBy('entry_date')->orderBy('id')->get()->filter($new('office_entry')) as $entry) {
                $post($posting->officeEntry($entry));
                if ($entry->cancelled_at) {
                    $post($books->reverseSource('office_entry', $entry->id, $entry->cancel_reason ?: 'Cancelled'));
                }
            }
            HireChallan::whereIn('status', ['issued', 'settled'])->orderBy('challan_date')->get()->filter($new('hire_challan'))->each(fn ($c) => $post($posting->hireChallan($c)));
            HireChallanLine::whereHas('challan', fn ($q) => $q->whereIn('status', ['issued', 'settled']))->orderBy('line_date')->orderBy('id')->get()->filter($new('hire_line'))->each(fn ($l) => $post($posting->hireLine($l)));
            BrokerCommission::whereNull('hire_challan_id')->orderBy('id')->get()->filter($new('broker_commission'))->each(fn ($c) => $post($posting->brokerCommission($c)));
            ServiceJob::where('status', 'done')->orderBy('job_date')->get()->filter($new('service_job'))->each(fn ($j) => $post($posting->serviceJob($j)));
        });

        $totals = VoucherLine::selectRaw('coalesce(sum(debit), 0) as d, coalesce(sum(credit), 0) as c')->first();
        $balanced = abs((float) $totals->d - (float) $totals->c) < 0.0005;
        $this->line(sprintf('%s: %d new voucher(s); debits %s, credits %s — %s', $company->name, $count,
            number_format((float) $totals->d, 2), number_format((float) $totals->c, 2), $balanced ? 'balanced' : 'NOT BALANCED'));

        return $balanced;
    }
}
