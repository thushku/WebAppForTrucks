<?php

namespace App\Modules\Parties\Services;

use App\Modules\Invoicing\Models\Invoice;
use App\Modules\Invoicing\Services\DocumentLinks;
use App\Modules\Parties\Models\Party;
use App\Modules\Trips\Models\Consignment;
use App\Modules\Trips\Services\Tracking;
use App\Platform\Tenancy\Company;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A client's own page (M47, blueprint 08 phase 4 "client portal"): one
 * secure link per client, no sign-in, never indexed. It lists their loads of
 * the last 60 days — each with its tracking link (M45) — and their open
 * bills with what is due and overdue, each opening its own secure bill page.
 * Never another client's anything, nor freight on loads not yet billed. The
 * office can stop the link at any time and make a new one.
 */
class ClientPage
{
    public const DAYS = 60;

    public function __construct(private DocumentLinks $links, private CompanyContext $context, private Tracking $tracking) {}

    /** The client's live link, if there is one. */
    public function current(Party $party): ?string
    {
        $token = DB::table('document_links')->where('company_id', $this->context->id())->where('document_type', 'client')->where('document_id', $party->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->orderByDesc('id')->value('token');

        return $token ? route('client.page', $token) : null;
    }

    public function link(Party $party): string
    {
        return $this->current($party) ?? route('client.page', $this->links->create('client', $party->id));
    }

    /** Stop the client's link: it no longer opens. */
    public function stop(Party $party): void
    {
        DB::table('document_links')->where('company_id', $this->context->id())->where('document_type', 'client')->where('document_id', $party->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->update(['expires_at' => now()]);
    }

    public function message(Party $party, string $url): string
    {
        return __('Your loads and bills with :company: :url', ['company' => $this->context->company()->name, 'url' => $url]);
    }

    /** A load's tracking link still opens: its trip is not cancelled and it was not delivered too long ago. */
    private function trackable(Consignment $load): bool
    {
        return $load->trip && $load->trip->status !== 'cancelled'
            && ! ($load->delivered_at && $load->delivered_at->copy()->addDays(Tracking::DAYS_AFTER_DELIVERY)->isPast());
    }

    /** @return ?array<string, mixed> null when the link is unknown or stopped */
    public function view(string $token): ?array
    {
        $link = $this->links->find($token);
        if (! $link || $link->document_type !== 'client') {
            return null;
        }
        $company = Company::find($link->company_id);
        if (! $company?->isUsable()) {
            return null;
        }

        return $this->context->runAs($company, function () use ($company, $link) {
            $party = Party::find($link->document_id);
            if (! $party) {
                return null;
            }
            $today = CarbonImmutable::now($company->timezone ?: 'UTC')->toDateString();
            $loads = Consignment::with(['pickupStop.place:id,name', 'dropStop.place:id,name', 'trip:id,status'])->where('status', '!=', 'cancelled')
                ->where(fn ($q) => $q->where('billing_party_id', $party->id)->orWhere('consignor_party_id', $party->id)->orWhere('consignee_party_id', $party->id))
                ->whereNotNull('lr_number')->where('created_at', '>=', now()->subDays(self::DAYS))->orderByDesc('lr_date')->orderByDesc('id')->limit(100)->get()
                ->map(fn (Consignment $c) => (object) ['load' => $c, 'route' => collect([$c->pickupStop?->place?->name, $c->dropStop?->place?->name])->filter()->join(' → '),
                    'track' => $this->trackable($c) ? $this->tracking->link($c) : null]);
            $bills = Invoice::where('party_id', $party->id)->whereNotNull('finalized_at')->whereIn('status', Invoice::OPEN_STATUSES)->where('doc_type', '!=', 'credit_note')
                ->where('balance_due', '>', 0)->orderBy('due_date')->get(['id', 'number', 'doc_type', 'issue_date', 'due_date', 'total', 'balance_due', 'public_token', 'currency']);

            return [
                'company' => $company, 'party' => $party, 'loads' => $loads, 'bills' => $bills, 'today' => $today,
                'due' => round((float) $bills->sum('balance_due'), 2),
                'overdue' => round((float) $bills->filter(fn ($b) => $b->due_date && $b->due_date->toDateString() < $today)->sum('balance_due'), 2),
            ];
        });
    }
}
