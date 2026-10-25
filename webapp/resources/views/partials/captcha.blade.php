{{-- The built-in picture challenge (no outside service; Clinic Ledger's Captcha). --}}
<div>
  <label class="label" for="captcha">{{ __('Type the characters in the picture') }}</label>
  <div class="flex items-center gap-3">
    <img src="{{ route('captcha') }}" data-captcha-image data-src="{{ route('captcha') }}" alt="{{ __('Picture with characters to type') }}" width="190" height="64" class="rounded-xl border border-ink-100">
    <button type="button" class="btn-ghost px-2" data-captcha-refresh aria-label="{{ __('Show a new picture') }}"><x-icon name="rotate" /></button>
  </div>
  <input id="captcha" name="captcha" class="input mt-2 uppercase tracking-[.3em]" autocomplete="off" autocapitalize="characters" maxlength="12" required>
  @error('captcha')<span class="error">{{ $message }}</span>@enderror
</div>
