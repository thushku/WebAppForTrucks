{{-- One copy of the copy set (the template editor). --}}
<tr>
  <td class="py-1 pe-2"><input name="design[copies][{{ $i }}][label]" value="{{ $copy['label'] }}" maxlength="{{ \App\Modules\Invoicing\Design\DesignSchema::TEXT_MAX }}" class="input py-1.5" aria-label="{{ __('Copy label') }}"></td>
  <td class="py-1 text-center"><input type="color" name="design[copies][{{ $i }}][tint]" value="{{ $copy['tint'] }}" class="h-8 w-10 cursor-pointer rounded border border-ink-100" aria-label="{{ __('Copy tint') }}"></td>
  @if ($family === 'lr')<td class="py-1 text-center"><input type="checkbox" name="design[copies][{{ $i }}][hide_freight]" value="1" class="size-4 rounded border-ink-300" @checked($copy['hide_freight']) aria-label="{{ __('Hide the freight on this copy') }}"></td>@endif
  <td class="py-1 text-end"><button type="button" class="btn-ghost px-2 text-xs" data-remove-row aria-label="{{ __('Remove this copy') }}"><x-icon name="x" class="size-4" /></button></td>
</tr>
