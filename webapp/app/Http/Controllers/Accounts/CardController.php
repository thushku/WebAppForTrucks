<?php

namespace App\Http\Controllers\Accounts;

use App\Modules\Accounts\Models\CardStatement;
use App\Modules\Accounts\Models\CardStatementLine;
use App\Modules\Accounts\Services\CardStatements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Accounts → Card statements (M44): bring a FASTag or fuel-card statement
 * in, and go through its charges — matched to the crew's entries, booked
 * when nobody recorded them, or set aside — and the entries that claim the
 * card but have no charge.
 */
class CardController extends AccountsController
{
    public function index(): View
    {
        return view('accounts.cards', [
            'statements' => CardStatement::with('vendor:id,name')->withCount(['rows as open' => fn ($q) => $q->where('status', 'open')])->orderByDesc('id')->limit(50)->get(),
            'pumps' => \App\Modules\Parties\Models\Party::active()->withRole('vendor')->orderBy('name')->get(['id', 'name', 'vendor_category'])
                ->sortBy(fn ($p) => $p->vendor_category === 'fuel_pump' ? 0 : 1)->pluck('name', 'id'),
            'open' => CardStatementLine::where('status', 'open')->selectRaw('kind, count(*) as n, sum(amount) as total')->groupBy('kind')->get()->keyBy('kind'),
        ]);
    }

    public function show(Request $request, CardStatement $statement, CardStatements $cards): View
    {
        $show = $request->validate(['show' => ['nullable', Rule::in(['open', 'matched', 'booked', 'ignored', 'all'])]])['show'] ?? 'all';
        $lines = CardStatementLine::with(['truck:id,reg_no', 'trip:id,trip_number', 'event.person:id,name', 'voucher:id,number'])->where('card_statement_id', $statement->id)
            ->when($show !== 'all', fn ($q) => $q->where('status', $show))->orderBy('txn_at')->orderBy('id')->get();

        return view('accounts.card', [
            'statement' => $statement, 'lines' => $lines, 'show' => $show,
            'counts' => CardStatementLine::where('card_statement_id', $statement->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'candidates' => $lines->where('status', 'open')->mapWithKeys(fn ($l) => [$l->id => $cards->candidates($l)->take(5)]),
            'unmatched' => $cards->withoutCharge($statement),
        ]);
    }

    public function import(Request $request, CardStatements $cards): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', Rule::in(array_keys(CardStatement::KINDS))], 'file' => ['required', 'file', 'max:4096', 'mimes:csv,txt'],
            'vendor_party_id' => ['nullable', 'required_if:kind,pump', 'integer', Rule::exists('party_roles', 'party_id')->where('company_id', $this->context->id())->where('role', 'vendor')]],
            ['vendor_party_id.required_if' => __('Choose the pump whose statement this is.')]);
        $pump = ! empty($data['vendor_party_id']) ? \App\Modules\Parties\Models\Party::find($data['vendor_party_id']) : null;
        $statement = $cards->import($data['kind'], $request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName(), $request->user(), $pump);
        $open = CardStatementLine::where('card_statement_id', $statement->id)->where('status', 'open')->count();

        return redirect()->route('accounts.cards.show', $statement)->with('status', __(':n charges brought in (:d seen before, left out); :m matched to the crew’s entries, :o to look at.', [
            'n' => $statement->lines, 'd' => $statement->skipped, 'm' => $statement->lines - $open, 'o' => $open]));
    }

    public function line(Request $request, CardStatementLine $line, CardStatements $cards): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['match', 'book', 'ignore', 'undo'])], 'event_id' => ['nullable', 'required_if:action,match', 'integer'],
            'note' => ['nullable', 'required_if:action,ignore', 'string', 'max:255']]);
        match ($data['action']) {
            'match' => $cards->match($line, (int) $data['event_id'], $request->user()),
            'book' => $cards->book($line, $request->user()),
            'ignore' => $cards->ignore($line, (string) $data['note'], $request->user()),
            'undo' => $cards->unmatch($line),
        };

        return back()->with('status', match ($data['action']) {
            'match' => __('Matched.'), 'book' => __('Booked as the truck’s cost, from the card.'), 'ignore' => __('Set aside, with your note.'), 'undo' => __('Open again.'),
        });
    }
}
