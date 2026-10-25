{{-- The office's own income and expenses on the trip, also after it is closed (D8). Every change is audited. --}}
@php
  use App\Modules\Trips\Models\TripOfficeEntry;
  $canOffice = auth()->user()->can('trip.office_entries');
@endphp
<section class="card overflow-hidden" id="office">
  <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-4">
    <div>
      <h3 class="font-semibold">{{ __('Office income and expenses') }}</h3>
      <p class="text-xs text-ink-500">{{ __('Later bills, commission, office charges, claims — also after the trip is closed.') }}</p>
    </div>
  </div>
  @if ($office->isNotEmpty())
    <div class="overflow-x-auto">
      <table class="table">
        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('What') }}</th><th>{{ __('Party') }}</th><th class="text-end">{{ __('Amount') }}</th><th></th></tr></thead>
        <tbody>
          @foreach ($office as $o)
            <tr class="{{ $o->cancelled_at ? 'opacity-50' : '' }}">
              <td class="whitespace-nowrap text-sm">{{ $o->entry_date->format('d M Y') }}</td>
              <td class="text-sm">
                <span class="pill {{ $o->kind === 'income' ? 'bg-emerald-50 text-paid' : 'bg-ink-100 text-ink-700' }}">{{ __(TripOfficeEntry::KINDS[$o->kind]) }}</span>
                {{ $o->kind === 'income' ? __(TripOfficeEntry::INCOME[$o->category] ?? $o->category) : ($expenseCategories[$o->category] ?? $o->category) }}
                <div class="text-xs text-ink-500">{{ collect([$o->reference, $o->note, $o->cancelled_at ? __('Cancelled: :reason', ['reason' => $o->cancel_reason]) : null])->filter()->join(' · ') }}</div>
              </td>
              <td class="text-sm">{{ $o->party?->name ?? '—' }}</td>
              <td class="money text-end">{{ $o->kind === 'expense' ? '− ' : '' }}{{ \App\Support\Money::company($o->amount) }}</td>
              <td class="text-end">
                @if ($canOffice && ! $o->cancelled_at)
                  <details class="inline-block text-start">
                    <summary class="btn-ghost cursor-pointer list-none px-2 py-1 text-xs">{{ __('Cancel') }}</summary>
                    <form method="POST" action="{{ route('trips.office.cancel', [$t, $o]) }}" class="mt-2 grid w-56 gap-2">@csrf
                      <input name="reason" class="input py-1.5 text-sm" maxlength="255" required placeholder="{{ __('Why') }}" aria-label="{{ __('Why') }}">
                      <button class="btn-danger px-2.5 py-1.5 text-xs">{{ __('Cancel entry') }}</button>
                    </form>
                  </details>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
  @if ($canOffice)
    <form method="POST" action="{{ route('trips.office.store', $t) }}" class="grid gap-3 border-t border-ink-100 p-6 sm:grid-cols-2 xl:grid-cols-6" novalidate data-office-form>
      @csrf
      <div>
        <label class="label" for="o_kind">{{ __('Kind') }}</label>
        <select id="o_kind" name="kind" class="input" data-office-kind>@foreach (TripOfficeEntry::KINDS as $k => $label)<option value="{{ $k }}" @selected(old('kind', 'expense') === $k)>{{ __($label) }}</option>@endforeach</select>
      </div>
      <div class="xl:col-span-2">
        <label class="label" for="o_category">{{ __('For') }}</label>
        <select id="o_category" name="category" class="input">
          <optgroup label="{{ __('Expense') }}" data-office-group="expense">@foreach ($expenseCategories as $code => $name)<option value="{{ $code }}" @selected(old('category') === $code)>{{ $name }}</option>@endforeach</optgroup>
          <optgroup label="{{ __('Income') }}" data-office-group="income">@foreach (TripOfficeEntry::INCOME as $code => $name)<option value="{{ $code }}" @selected(old('category') === $code)>{{ __($name) }}</option>@endforeach</optgroup>
        </select>
      </div>
      <div><label class="label" for="o_amount">{{ __('Amount') }}</label><input id="o_amount" name="amount" type="number" min="0.01" step="0.01" class="input" value="{{ old('amount') }}"></div>
      <div><label class="label" for="o_date">{{ __('Date') }}</label><input id="o_date" name="entry_date" type="date" class="input" value="{{ old('entry_date', now()->toDateString()) }}"></div>
      <div><label class="label" for="o_ref">{{ __('Bill / ref.') }}</label><input id="o_ref" name="reference" class="input" maxlength="60" value="{{ old('reference') }}"></div>
      <div class="sm:col-span-2 xl:col-span-5"><label class="label" for="o_note">{{ __('Note') }}</label><input id="o_note" name="note" class="input" maxlength="255" value="{{ old('note') }}"></div>
      <div class="flex items-end"><button class="btn-secondary w-full"><x-icon name="plus" /> {{ __('Add') }}</button></div>
      @foreach (['kind', 'category', 'amount', 'entry_date', 'reference', 'note', 'office'] as $f)@error($f)<p class="error sm:col-span-2 xl:col-span-6">{{ $message }}</p>@enderror @endforeach
    </form>
  @endif
</section>
