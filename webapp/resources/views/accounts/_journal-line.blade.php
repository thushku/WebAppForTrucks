{{-- One line of a journal voucher. --}}
<tr>
  <td class="min-w-64"><select name="lines[{{ $i }}][ledger_account_id]" class="input" aria-label="{{ __('Ledger') }}">
    <option value="">{{ __('Choose a ledger') }}</option>
    @foreach ($ledgers->groupBy(fn ($l) => $l->group->name) as $group => $items)
      <optgroup label="{{ $group }}">@foreach ($items as $l)<option value="{{ $l->id }}" @selected(($row['ledger_account_id'] ?? null) == $l->id)>{{ $l->name }}</option>@endforeach</optgroup>
    @endforeach
  </select></td>
  <td><input name="lines[{{ $i }}][debit]" type="number" step="0.01" min="0" value="{{ $row['debit'] ?? '' }}" class="input w-32 text-end" aria-label="{{ __('Debit') }}"></td>
  <td><input name="lines[{{ $i }}][credit]" type="number" step="0.01" min="0" value="{{ $row['credit'] ?? '' }}" class="input w-32 text-end" aria-label="{{ __('Credit') }}"></td>
  <td><select name="lines[{{ $i }}][truck_id]" class="input w-36" aria-label="{{ __('Truck') }}"><option value="">—</option>@foreach ($trucks as $id => $reg)<option value="{{ $id }}" @selected(($row['truck_id'] ?? null) == $id)>{{ $reg }}</option>@endforeach</select></td>
  <td><input name="lines[{{ $i }}][note]" value="{{ $row['note'] ?? '' }}" maxlength="255" class="input" aria-label="{{ __('Note') }}"></td>
  <td class="text-end"><button type="button" class="btn-ghost px-2 text-xs" data-remove-row aria-label="{{ __('Remove this line') }}"><x-icon name="x" class="size-4" /></button></td>
</tr>
