<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Fine;
use App\Modules\Fleet\Models\Truck;
use App\Modules\Fleet\Services\Fines;
use App\Modules\People\Models\Person;
use App\Modules\Settings\Models\CompanyBankAccount;
use App\Platform\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trucks → Fines (M48): every traffic fine and e-challan — open (unpaid or
 * disputed), paid, cancelled — with the truck, the driver of that moment and
 * who bears it; the drivers' black points of 12 months; a fine's own page to
 * correct, pay, dispute or cancel it. Seen with truck.view, recorded with
 * truck.manage, paid with accounts.post (it is a payment).
 */
class FineController extends Controller
{
    public const TABS = ['' => 'Open', 'paid' => 'Paid', 'cancelled' => 'Cancelled', 'all' => 'All'];

    public function __construct(private CompanyContext $context, private Fines $fines) {}

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : '';
        $q = trim((string) $request->query('q'));
        $truckId = $request->integer('truck') ?: null;
        $personId = $request->integer('person') ?: null;
        $filtered = fn () => Fine::visible()->when($truckId, fn ($w) => $w->where('truck_id', $truckId))->when($personId, fn ($w) => $w->where('person_id', $personId))
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('challan_no', 'like', "%{$q}%")->orWhere('offence', 'like', "%{$q}%")->orWhere('place', 'like', "%{$q}%")));
        $list = $filtered()->with(['truck:id,reg_no', 'person:id,name', 'payslip.run'])
            ->when($tab === '', fn ($w) => $w->whereIn('status', ['unpaid', 'disputed']))->when(in_array($tab, ['paid', 'cancelled'], true), fn ($w) => $w->where('status', $tab))
            ->orderByDesc('issued_at')->paginate(50)->withQueryString();
        $counts = $filtered()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $points = $this->fines->points();

        return view('trucks.fines', [
            'tab' => $tab, 'q' => $q, 'list' => $list, 'service' => $this->fines,
            'counts' => ['' => ($counts['unpaid'] ?? 0) + ($counts['disputed'] ?? 0), 'paid' => $counts['paid'] ?? 0, 'cancelled' => $counts['cancelled'] ?? 0, 'all' => $counts->sum()],
            'open' => $this->fines->open(),
            'points' => $points, 'pointNames' => Person::whereIn('id', $points->pluck('person_id'))->pluck('name', 'id'),
            'truck' => $truckId ? Truck::find($truckId, ['id', 'reg_no']) : null, 'person' => $personId ? Person::find($personId, ['id', 'name']) : null,
            'trucks' => Truck::inBranch()->orderBy('reg_no')->pluck('reg_no', 'id'),
            'drivers' => Person::inBranch()->where('status', '!=', 'left')->orderBy('name')->pluck('name', 'id'),
            'now' => CarbonImmutable::now($this->context->company()->timezone ?: 'UTC'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $fine = $this->fines->record($request->validate($this->fines->rules()), $request->user());

        return redirect()->route('trucks.fines.show', $fine)->with('status', $fine->person_id
            ? __('Fine recorded for :name.', ['name' => Person::whereKey($fine->person_id)->value('name')]) : __('Fine recorded.'));
    }

    public function show(Fine $fine): View
    {
        abort_unless(Fine::visible()->whereKey($fine->id)->exists(), 404);
        $fine->load(['truck:id,reg_no', 'person:id,name', 'trip:id,trip_number,uuid', 'payslip.run', 'voucher:id,number']);

        return view('trucks.fine', [
            'fine' => $fine, 'service' => $this->fines,
            'trucks' => Truck::inBranch()->orderBy('reg_no')->pluck('reg_no', 'id'),
            'drivers' => Person::inBranch()->orderBy('name')->pluck('name', 'id'),
            'banks' => CompanyBankAccount::where('active', true)->orderByDesc('is_default')->get(),
            'points' => $fine->person_id ? (int) ($this->fines->points($fine->person_id)->first()?->points ?? 0) : 0,
            'tz' => $this->context->company()->timezone ?: 'UTC',
            'today' => CarbonImmutable::now($this->context->company()->timezone ?: 'UTC')->startOfDay(),
        ]);
    }

    public function update(Request $request, Fine $fine): RedirectResponse
    {
        abort_unless(Fine::visible()->whereKey($fine->id)->exists(), 404);
        $this->fines->update($fine, $request->validate($this->fines->rules($fine)));

        return redirect()->route('trucks.fines.show', $fine)->with('status', __('Saved.'));
    }

    public function pay(Request $request, Fine $fine): RedirectResponse
    {
        abort_unless(Fine::visible()->whereKey($fine->id)->exists(), 404);
        $this->fines->pay($fine, $request->validate($this->fines->payRules()), $request->user());

        return redirect()->route('trucks.fines.show', $fine)->with('status', __('Fine marked paid.'));
    }

    /** Dispute, reopen after a lost dispute, or cancel. */
    public function status(Request $request, Fine $fine): RedirectResponse
    {
        abort_unless(Fine::visible()->whereKey($fine->id)->exists(), 404);
        $data = $request->validate(['action' => ['required', 'in:dispute,reopen,cancel'], 'note' => ['nullable', 'string', 'max:255']]);
        match ($data['action']) {
            'dispute' => $this->fines->dispute($fine, (string) ($data['note'] ?? '')),
            'reopen' => $this->fines->reopen($fine),
            'cancel' => $this->fines->cancel($fine, (string) ($data['note'] ?? '')),
        };

        return redirect()->route('trucks.fines.show', $fine)->with('status', match ($data['action']) {
            'dispute' => __('Marked disputed.'), 'reopen' => __('Back to unpaid.'), default => __('Fine cancelled.'),
        });
    }
}
