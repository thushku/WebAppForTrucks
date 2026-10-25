{{-- One extra printed line (the template editor). --}}
<tr>
  <td class="py-1 pe-2"><input name="design[custom][{{ $i }}][label]" value="{{ $line['label'] }}" maxlength="40" class="input py-1.5" aria-label="{{ __('Label') }}" placeholder="{{ __('Udyam') }}"></td>
  <td class="py-1 pe-2"><input name="design[custom][{{ $i }}][value]" value="{{ $line['value'] }}" maxlength="80" class="input py-1.5" aria-label="{{ __('Value') }}"></td>
  <td class="py-1 text-end"><button type="button" class="btn-ghost px-2 text-xs" data-remove-row aria-label="{{ __('Remove this line') }}"><x-icon name="x" class="size-4" /></button></td>
</tr>
